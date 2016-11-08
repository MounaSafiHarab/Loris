<?php
/**
 * Handles a request to the Event Notification portion of the Loris API
 * POST will record a notification event , GET will retreive all notifications
 * or a specific record i.e. /api/v0.0.2/notify or /api/v0.0.2/notify/##
 *
 * Notification Events POSTED are saved to the database Notify Table
 * Example data POST format is as follows:
 * {'ProjectID':'CCNA01','CandID':'101010','PSCID':'55555','Event':'Archived'} 
 * --
 * -- Table structure for table `Notify`
 * --
 * 
 * CREATE TABLE IF NOT EXISTS `Notify` (
 *   `ID` int(11) NOT NULL AUTO_INCREMENT,
 *   `owner` varchar(255) NOT NULL,
 *   `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   `ProjectID` varchar(16) NOT NULL,
 *   `CandID` varchar(25) NOT NULL,
 *   `PSCID` varchar(256) NOT NULL,
 *   `Event` varchar(25) NOT NULL,
 *   `SourceIP` varchar(12) NOT NULL,
 *   `Status` varchar(8) NOT NULL
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 *
 * In processing events SourceIP and owner must be validated to ensure the remote event
 * is fron a known source.
 *
 * Status represents the current processing state of the notification:
 * 'P' 	Pending - processing required
 * 'I'  In Process - processing has comenced on this notification
 * 'C'  Complete - processing has completed successfully
 * 'E'  Error - processing failed, review logs for detail 
 *  
 * The following additions also need to be made to the .htaccess file
 *	# Notify API rewrite rules (added Mark Prati)
 *
 *	RewriteRule ^notify$ Notify.php?PrintNotifies=true [L]
 *	RewriteRule ^notify(/)$ Notify.php?PrintNotifies=true [L]
 *	RewriteRule ^notify/([0-9]+)$ Notify.php?ID=$1&PrintNotifies=true [L]
  
 * PHP Version 5.5+
 *
 * @category Main
 * @package  API
 * @author   Mark Prati <mprati@research.baycrest.org>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://www.github.com/aces/Loris/
 */
namespace Loris\API;
set_include_path(get_include_path() . ":" . __DIR__);
require_once 'APIBase.php';

/**
 * Class to handle a request to the Event Notification portion of the Loris API
 *
 * @category Main
 * @package  API
 * @author   Mark Prati <mprati@research.baycrest.org>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://www.github.com/aces/Loris/
 */
class Notify extends APIBase
{
    var $RequestData;

    /**
     * Create a Notification request handler
     *
     * @param string $method The HTTP request method of the request
     * @param array  $data   The data that was POSTed to the request
     */
    public function __construct($method, $data=null)
    {
        $this->AllowedMethods = [
                                 'GET',
                                 'POST',
                                ];
        $this->RequestData    = $data;

        parent::__construct($method);
    }

    /**
     * Calculate an ETag by taking a hash of the number of candidates in the
     * database and the time of the most recently changed one.
     *
     * @return string An ETag for ths candidates object
     */
    function calculateETag()
    {
        $ETagCriteria = $this->DB->pselectRow(
            "SELECT MAX(TestDate) as Time,
                    COUNT(DISTINCT CandID) as NumCandidates
             FROM candidate WHERE Active='Y'",
            array()
        );
        return md5(
            'Candidates:'
            . $ETagCriteria['Time']
            . ':' . $ETagCriteria['NumCandidates']
        );
    }

    /**
     * Handles a notify GET request
     *
     * @return none, but populates $this->JSON
     */

    public function handleGET()
    {
        $selectStr = "SELECT CandID, ProjectID, PSCID, Event, SourceIP, Status FROM NotifyAPI";
	
	// only numbers allowed (no nasty stuff)
        if (isset($_GET['ID']) && is_numeric($_GET['ID']))
	    $selectStr = $selectStr." WHERE ID = ".$_GET['ID'];

        $notifyEvents = $this->DB->pselect($selectStr, [] );
        $this->JSON = ["NotifyEvent" => $notifyEvents];
    }

    /**
     * Handles a notify event POST request,
     * validate event data and if everything
     * is valid, create add an event record to the Notify table
     *
     * Example data POST format is as follows:
     * {'ProjectID':'CCNA','CandID':'101010','PSCID':'55555','Event':'Archived'} 
     *
     * @return none, but populates $this->JSON and writes to DB
     */
    public function handlePOST()
    {
        $factory = \NDB_Factory::singleton();
        $config  = $factory->config();

        if (isset($this->RequestData['Event'])) {
            $data = $this->RequestData;
error_log ("In POST: " . $data . "\n");
            if ($data === null) {
error_log ("In NULL data loop \n");
                $this->header("HTTP/1.1 400 Bad Request");
                $this->safeExit(0);
            }

            //validate client IP address 
	    // a list of vailid SourceIP's should be read from a config table not hard coded
            $ip = $this->get_ip_address();
error_log("IP is " . $ip . "\n");
            $accepted_ip = $config->getSetting(
                'AcceptedExternalIP'
            );
//            if (!in_array($ip, ['127.0.0.1', '172.24.15.89', '123.321.123.321'])) 
//            if (!in_array($ip, ['132.206.37.36'])) 
            if (!in_array($ip, $accepted_ip)) 
            {  
error_log("In the wrong IP loop");
                $this->header("HTTP/1.1 400 Bad Request");
                $this->safeExit(0);
            }

	    //check a valid  'Event' field is Present
	    // a list of vailid events should be read from a config table not hard coded
            $this->verifyField($data, 'Event', ['Archived','Instrument']);

            //Notify::createNew
            try {
                $this->createNew(
                    $data['ProjectID'],
                    $data['CandID'],
                    $data['PSCID'],
                    $data['Event']
                );
                $this->header("HTTP/1.1 201 Created");
                $this->JSON = [
                               'Meta' => ["Event CandID" => $data['CandID']],
                              ];
            } catch(\LorisException $e) {
                $this->header("HTTP/1.1 400 Bad Request");
                $this->safeExit(0);
            }
        } else {
            $this->header("HTTP/1.1 400 Bad Request");
            $this->safeExit(0);
        }
    }

    /**
     * Verifies that the field POSTed to the URL is valid.
     *
     * @param array  $data   The data that was posted
     * @param string $field  The field to be validated in $data
     * @param mixed  $values Can either be an array of valid values for
     *                       the field, or a string representing the format
     *                       expected of the data.
     *
     * @return none, but will generate an error and exit if the value is invalid.
     */
    protected function verifyField($data, $field, $values)
    {
        if (!isset($data[$field])) {
            $this->header("HTTP/1.1 400 Bad Request");
            throw new \Exception("AAAAH $field");
            $this->safeExit(0);
        }
        if (is_array($values) && !in_array($data[$field], $values)) {
            $this->header("HTTP/1.1 400 Bad Request");
            $this->safeExit(0);
        }
        if ($values === 'YYYY-MM-DD'
            && !preg_match("/\d\d\d\d\-\d\d\-\d\d/", $data[$field])
        ) {
            $this->header("HTTP/1.1 400 Bad Request");
            $this->safeExit(0);
        }
    }

    /**
     * Creates a new Notify record with "status" Pending
     *
     * @param string $ProjectID   
     * @param string $CandID CandID related to event
     * @param string $PSCID  PSCID related to event
     * @param string $Event  event type
     */
    public function createNew($ProjectID, $CandID, $PSCID, $Event) {

	$user = \User::singleton();
	$owner = $user->getUsername();
	$SourceIP = $this->get_ip_address();

        // insert the new Notification into the database
        $setArray = array(  'ProjectID' => $ProjectID,
                            'CandID'    => $CandID,
                            'PSCID'     => $PSCID,
                            'Event'     => $Event,
                            'SourceIP'  => $SourceIP,
                            'owner'     => $owner,
                            'status'	=> 'P',
                         );

        $this->DB->insert('NotifyAPI', $setArray);

        // return the candid
        return $candID;
    }


    public function get_ip_address() {

	$ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
	foreach ($ip_keys as $key) 
        {
	    if (array_key_exists($key, $_SERVER) === true) 
            {
		foreach (explode(',', $_SERVER[$key]) as $ip) 
                {		            
		     $ip = trim($ip);                // trim for safety measures		            
		     if ($this->validate_ip($ip))    // attempt to validate IP
		         return $ip;
		}
	    }
        }
	return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
    }
	/**
	 * Ensures an ip address is both a valid IP and does not fall within a private network range.
	 */
    public function validate_ip($ip)
    {
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
	    return false;
	return true;
    }
}

if (isset($_REQUEST['PrintNotifies'])) 
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $obj = new Notify($_SERVER['REQUEST_METHOD'], $_POST);
    } else {
        $obj = new Notify($_SERVER['REQUEST_METHOD']);
    }
    print $obj->toJSONString();
}
?>
