<?php

/**
 * This script fetches new mri study from notification_psool table and notify BrainCode
 *
 * PHP Version 5
 *
 * @category Main
 * @package  Loris
 * @author   Various <example@example.com>
 * @license  Loris license
 * @link     https://www.github.com/aces/Loris-Trunk/
 */
require_once __DIR__ . "/../vendor/autoload.php";
require_once "generic_includes.php";
require_once "Utility.class.inc";

/**
 * Informs BrainCode of a new MRI upload
 *
 * @category Main
 * @package  Loris
 * @author   Various <example@example.com>
 * @license  Loris license
 * @link     https://www.github.com/aces/Loris-Trunk/
 */

if(!empty($argv[1]) && !empty($argv[2])){

    $username = $argv[1];
    $password = $argv[2];
}

else {
    print "Please enter the username and password \n";
    exit;
}


$url = 'https://xtxgate.braincode.ca/api-token-auth/'; 


// Get authentication token first
$data = array('username'=>$username, 'password'=>$password);
$data_json = json_encode($data);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS,$data_json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response  = curl_exec($ch); 

$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
print "HTTP code: $httpcode \n";

if ($httpcode == 200) {
      $atoken_tmp = json_decode($response);
      $atoken = $atoken_tmp->token;

      $filename = 'token_response_' . $atoken . '.txt';
      file_put_contents($filename, $response);
}
elseif ($httpcode === 400 || $httpcode === 401) {
        print "Could not get authentication token; HTTP code: $httpcode\n";
}
curl_close($ch); 

// Now use the token to send a notification to BrainCode
$url = 'https://xtxgate.braincode.ca/notify/'; 
$Project               = 'CCNA';

$query = "SELECT ProcessID FROM notification_spool WHERE NotificationTypeID ='1' " .
         "AND ProcessID NOT IN (SELECT ProcessID from notification_spool WHERE NotificationTypeID ='16')";
print "query is $query \n";
$result=$DB->pselect($query,array());

if ($result) {
    // Cycle through results
    foreach ($result as $row) {
        $upload_id=$row['ProcessID'];

        $query2 = "SELECT SessionID, number_of_mincInserted, number_of_mincCreated FROM mri_upload " .
                 "WHERE UploadID= " . $upload_id;
        print "query2 is $query2 \n";
        $result2 = $DB->pselectRow($query2,array());
        $SessionID         = $result2['SessionID'];
        $nb_minc_created   = $result2['number_of_mincCreated'];
        $nb_minc_inserted  = $result2['number_of_mincInserted'];
        // Get PatientName from tarchive table because mri_upload has this field blank for phantoms
        $query3 = "SELECT t.PatientName from tarchive t JOIN mri_upload m " .
                 "ON t.TarchiveID=m.TarchiveID AND m.UploadID = " . $upload_id;
        print "query3 is $query3 \n";
        $result3           = $DB->pselectRow($query3,array());
        $PatientName       = $result3['PatientName'];
        $StrLenPName       = strlen($PatientName);

        // Strlen can not exceed 25 so for phantoms this might be a problem; so take last 25 chars
        if ($StrLenPName > 25) {
            $PatientName = substr($PatientName, -25);
        }

        /*
        * Notify BrainCode that CCNA just uploaded and processed a scan
        * The data sent will reflect how many files ($count) have been 
        * uploaded for a candidate and session 
        */
 
       $data = array('project'=>$Project, 'subject'=>$PatientName, 'session'=>$SessionID, 'event'=>'archived', 'count_created'=>$nb_minc_created, 'count_inserted'=>$nb_minc_inserted);
       $data_json = json_encode($data);

       $ch = curl_init();

       curl_setopt($ch, CURLOPT_URL, $url);
       curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json), 'Authorization: Token '.$atoken));
       curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
       curl_setopt($ch, CURLOPT_POSTFIELDS,$data_json);
       curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

       curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       $response  = curl_exec($ch); 

       $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
       print "HTTP code: $httpcode \n";

       if ($httpcode === 201) {

              $filename = 'braincode_notification_' . $PatientName . '_' . $SessionID . '_' . $nb_minc_created . '_' . $nb_minc_inserted . '.txt';
              file_put_contents($filename, $response);

              $message = "The URL " . $url . " was notified with this message: Patient " . $PatientName . " with session ID " . $SessionID . 
                         " had " . $nb_minc_created . " and " . $nb_minc_inserted . " mincs created and inserted, respectively \n"; 

              $query4 = "INSERT INTO notification_spool (NotificationTypeID,ProcessID,Message) VALUES (16,$upload_id,$message)";
              print "query4 is $query4 \n";

              $DB->insert('notification_spool', array('NotificationTypeID' => '16', 'ProcessID' => $upload_id, 'Message' => $message)
              ); 
       }
       elseif ($httpcode === 400 || $httpcode === 401) {

              $message = "The URL " . $url . " could NOT be notified with this message: Patient " . $PatientName . " with session ID " . $SessionID . 
                         " had " . $nb_minc_created . " and " . $nb_minc_inserted . " mincs created and inserted, respectively;" .
			 " due to an HTTP code: $httpcode \n"; 

              $query4 = "INSERT INTO notification_spool (NotificationTypeID,ProcessID,Message) VALUES (16,$upload_id,$message)";
              print "query4 is $query4 \n";

              $DB->insert('notification_spool', array('NotificationTypeID' => '16', 'ProcessID' => $upload_id, 'Message' => $message)
              ); 
       }
    } // end of each upload_id
} // end of if $result

// no $result, no new notification needs to be sent
else {
    echo "No new notifications to be sent\n ";
    die();
}

curl_close($ch); 


?>
