<?php

/**
 * This script fetches new Rx notifications, gets the corresponsing images from Braincode, and inserts into Loris
 *
 * PHP Version 5
 *
 * @category   Main
 * @package    Loris
 * @subpackage Tools
 * @author     Various <example@example.com>
 * @license    Loris license
 * @link       https://www.github.com/aces/Loris-Trunk/
 */

//set_include_path(get_include_path().":../project/libraries:../php/libraries:");
require_once __DIR__ . "/../vendor/autoload.php";
require_once "generic_includes.php";
require_once "Utility.class.inc";
require_once "Candidate.class.inc";
require_once "TimePoint.class.inc";

/**
 * Informs BrainCode of a new MRI upload
 *
 * This tool is to notify BrainCode of an Upload
 *
 * @category   Main
 * @package    Loris
 * @subpackage Tools
 * @author     Various <example@example.com>
 * @license    Loris license
 * @link       https://www.github.com/aces/Loris-Trunk/
 */

if (!empty($argv[1]) && !empty($argv[2])) {

    $username = $argv[1];
    $password = $argv[2];
} else {
    print "Please enter the username and password \n";
    exit;
}

// for now use a dummy test data; but eventually this has to be fetched from somewhere
$data      = array(
                  'ProjectID'       => 'BYC01_QC',
                  'CandIDBC'        => 'PHA_LEG0002',
                  'Session'         => 'spred_E00290',
                  'Event'           => 'Archived',
                  'EventData'       => "{'PHANTOM' : Lego}",
                  );
$IsPhant = $data['EventData'];
$IsPhant = explode(':', $IsPhant)[1];
$IsPhant = str_replace(" ", "", $IsPhant);
$IsPhant = str_replace("}", "", $IsPhant);

$userID = 'mouna.safiharab@gmail.com';
$fixCenterID = 64; // BAY
$fixVisitLabel = 'Initial_MRI';
$fixSubID = 1; // SCI
$projectID = $data['ProjectID'];
$CandIDBC = $data['CandIDBC'];;
$SessionID   = $data['Session'];


function curlit($url, $username, $password)
{
    $R      = [];

    $ch = curl_init();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password" );
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    $data = curl_exec($ch);

    if (curl_errno($ch))
    {
        echo 'error: '.curl_error($ch).'<br><br>';
    }
    else
    {
        $R = json_decode($data,true);
        $count = count($R["ResultSet"]["Result"]); //$R["ResultSet"]["totalRecords"];
        echo 'Number of Records: '.$count.'<br>';   
        $resultArray = $R["ResultSet"]["Result"];
//      echo "<pre>";
//      var_dump($R);
//      echo "</pre>";

        if ($count != '0')
        {
            foreach($resultArray as $r)
            {
                echo "<pre>";
                var_dump($r);
                echo "</pre>";
                echo " REST URI = ".$r["URI"]."<br>\n";
            }

        }
        else
        {
            echo "No Data for the above ^ URI!<br><br>";
        }
    }
    curl_close($ch);    
    return $R;
}

# An HTTP GET request example

echo "<h1>LORIS ==> XNAT PHP ACCESS TEST</h1><br><br>";

$xnatURL    = 'https://spred.braincode.ca/spred';

// Retrieve Subject List
echo "<br><h1>Data for the Subject and Session</h1><br>";
$url = $xnatURL.'/data/archive/projects/'.$projectID.'/subjects/'.$CandIDBC.'/experiments/?format=json';
echo "URL = ".$url."<br><br>";
$E = curlit($url, $username, $password);
$expArray = $E["ResultSet"]["Result"];

foreach($expArray as $e) {
    if ($e["ID"] == $SessionID) {
        $dateScan = str_replace("-", "", $e["insert_date"]);
        $dateScan = str_replace(" ", "", $dateScan);
        $dateScan = str_replace(":", "", $dateScan);
        $dateScan = str_replace(".", "", $dateScan);
        echo "Date of scan = " . $dateScan . "<br><br>\n";

        // The first link includes additional directories, not just DICOM ones
        // $url = $xnatURL.'/data/archive/projects/'.$s["project"].'/subjects/'.$s["ID"].'/experiments/'.
        //         $e["ID"].'/scans/ALL/files?format=zip';

        // This will only get DICOMs
        $url = $xnatURL . '/data/archive/projects/' . $projectID . '/subjects/'. $CandIDBC . '/experiments/' .
            $e["ID"] . '/scans/ALL/resources/DICOM/files?format=zip';

        echo "URL = " . $url . "<br><br>\n";
        $F = curlit($url, $username, $password);

        echo "File Download URL = " . $url . "<br><br>\n";

        set_time_limit(0);          // May want to place a limit

        //File to save. Add logic to set file path and name as needed
        //$localFileName = dirname(__FILE__) . "testimgfile.tmp";
        $localFileName = "/home/lorisadmin/BrainCode/tmp/tmpfile_" . $dateScan . ".zip";

        $fp = fopen($localFileName, 'w+');
        if ($fp === false) {
            echo "ERROR opening local File: " . $localFileName . "<br><br>\n";

        } else {
            echo "Saving File Here: " . $localFileName . "<br><br>\n";
        }

        //file we are downloading, replace spaces with %20
        $ch = curl_init(str_replace(" ", "%20", $url));

        // Need to authenticate
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        curl_setopt($ch, CURLOPT_TIMEOUT, 150);
        //give curl the file pointer so that it can write to it
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $data = curl_exec($ch);         //get curl response

        //done
        curl_close($ch);

        // Set a user for the creation of the new candidate and for the history table
        if (!isset($_SESSION['State'])) {
            $_SESSION['State'] =& State::singleton();
        }
        $_SESSION['State']->setUsername($userID);

        // Create a Loris Candidate if the External ID has not been created
        $count_extID = $DB->pselectOne(
            " SELECT COUNT(*) FROM candidate c 
            WHERE c.ExternalID=:extID",
            array(
                'extID' => $CandIDBC
            )
        );
        if ($count_extID == 0) {
            // Create a Loris Candidate for this uploaded zip
            $CandID = Candidate::createNew($fixCenterID, NULL, NULL, NULL, NULL);
            $candidate =& Candidate::singleton($CandID);
            $PSCID = $candidate->getPSCID();
            print "Candidate and PSCID are: " . $CandID . " and " . $PSCID . "\n";
            $DB->update(
                'candidate',
                array(
                    'ExternalID' => $CandIDBC
                ),
                array(
                    'CandID' => $CandID
                )
            );
            if ($IsPhant == "Lego" || $IsPhant == "Human") {
                // update the entity type to Scanner; used for lego and human phantoms
                $DB->update(
                    'candidate',
                    array(
                        'Entity_type' => 'Scanner'
                    ),
                    array(
                        'CandID' => $CandID
                    )
                );
            }
        }
        else {
            $CandID = $DB->pselectOne(
                " SELECT CandID FROM candidate c 
                WHERE c.ExternalID=:extID",
                array(
                    'extID' => $CandIDBC
                )
            );
            $PSCID = $DB->pselectOne(
                " SELECT PSCID FROM candidate c 
                WHERE c.ExternalID=:extID",
                array(
                    'extID' => $CandIDBC
                )
            );
            print "CandID and PSCID are already in the database, as: " . $CandID . " and " . $PSCID . "\n";
        }
        // Create a session for this uploaded zip if not phantom
        // Loris-MRI takes care of phantom session creation
        if ($IsPhant == "False") {
            $TimePntID = Timepoint::createNew($CandID, $fixSubID, $fixVisitLabel);
            $DB->update(
                'session',
                array(
                    'CenterID' => $fixCenterID
                ),
                array(
                    'CandID' => $CandID
                )
            );
        }

        // cp images to destination directiory: /data/incoming
        if ($IsPhant == "Lego") {
            $PN = 'lego_phantom_L1_BYC_' . $dateScan;
        } elseif ($IsPhant == "Human") {
            $PN = 'human_phantom_L1_BYC_' . $dateScan;
        } elseif ($IsPhant == "False") {
            $PN = $PSCID . '_' . $CandID . '_' . $fixVisitLabel;
        } else {
            print "Eventdata type can either be phantom (human or lego) or real candidate. \n";
        }
        $destFileName = $PN . ".zip";
        $dest_dir = "/data/incoming";
        shell_exec('cp ' . $localFileName . " " . $dest_dir . "/" . $destFileName);
        shell_exec('cd ' . $dest_dir);
        $unzip_dest_dir = $dest_dir . "/" . $PN . "/";
        shell_exec('mkdir ' . $unzip_dest_dir);
        shell_exec('unzip ' . $dest_dir . "/" . $destFileName . ' -d ' . $unzip_dest_dir);

        // anonymize DICOM
        $find_var = "(find " . $unzip_dest_dir . " -type f)";
        $cmd1 = 'for i in $' . $find_var . '; do dcmodify -ma PatientName="' . $PN . '" $i; done';
        $cmd2 = 'for i in $' . $find_var . '; do rm -f $i.bak ; done';
        print "cmd 1 is: " . $cmd1 . "\n";
        print "cmd 2 is: " . $cmd2 . "\n";
        shell_exec($cmd1);
        shell_exec($cmd2);
        shell_exec('zip -r ' . $dest_dir . "/" . $PN . ".zip " . $dest_dir . "/" . $PN . "/");

        // create the .txt file as the input for running batch_imaging_upload.pl
        // create/append one record entry per .zip file or scan
        $MRICodePath = "/data/loris-MRI/bin/mri";
        $file = $MRICodePath . "/" . 'imageuploader_list.txt';
        $current = file_get_contents($file);
        print "IsPhant is: " . $IsPhant . "\n";
        if ($IsPhant == "Lego" || $IsPhant == "Human") {
            $current .= $dest_dir . "/" . $destFileName . " Y \n";
        } else {
            $current .= $dest_dir . "/" . $destFileName . " " . $PN . "\n";
        }
        // Write the contents back to the file
        file_put_contents($file, $current);
    } // end of if session=$SessionID
} // end of foreach session


?>
