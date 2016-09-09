<?php
/**
 * The forms for the new profile menu
 * @package main
 */
class NDB_Form_new_phantom_profile extends NDB_Form
{
    function _hasAccess()
    {
        // create user object
        $user =& User::singleton();

        $site =& Site::singleton($user->getData('CenterID'));
        
        if ($site->isStudySite() or ($site->getCenterName() == "DCC")) {
            return $user->hasPermission('data_entry');
        }

        return false;
    }

    function new_phantom_profile()
    {
        $user =& User::singleton();
        $config =& NDB_Config::singleton();
	$db =& Database::singleton();

        // Request the phantom type
        $phantListTypes = array('' => '', 'Human' => 'Human (type 1)', 'Lego' => 'Lego (type 2)', 'Test' => 'Test (type 3)');
        $this->addSelect('PhantomTypes', 'Phantom Type', $phantListTypes);

        // Request the phantom name
        $phantName = $db->pselect("SELECT ID, Name FROM phantom_identification", array());
        $phantListNames = array('' => '');
        $phantListIDs = array('' => '');
        foreach($phantName as $row) {
            $phantListNames[$row['ID']] = $row['Name'] . ' (ID ' . sprintf("%02d", $row['ID']) . ')';
        }
        $this->addSelect('PhantomNames', 'Phantom Name', $phantListNames);

        // Request the PSCID if it is set to 'user' in the config file
        $PSCIDsettings = $config->getSetting('PSCIDPhantoms');
        if($PSCIDsettings['generation'] == 'user') {
            $this->addBasicText('PSCID', 'PSCID');
        }

        $this->form->addFormRule(array(&$this, '_validate'));
    }


    function _validate($values)
    {
        $errors = array();

        $user =& User::singleton();
        $config =& NDB_Config::singleton();
	$db =& Database::singleton();

        $PSCIDsettings = $config->getSetting('PSCIDPhantoms');
        if($PSCIDsettings['generation'] == 'user') {
            $db =& Database::singleton();
            $user =& User::singleton();
            $centerID = $user->getData('CenterID');
            $site =& Site::singleton($centerID);

            $phantInfo = $db->pselect("SELECT ID, Type FROM phantom_identification WHERE ID=:phantName", array('phantName' => $values['PhantomNames']));

	    foreach($phantInfo as $row) {
                $expectedphantID = $row['ID'];
                $expectedtype = $row['Type'];
	    }
	    $expectedphantID = sprintf("%02d", $expectedphantID);
	    // Convert expectedtype to expectedtypeID
	    if($expectedtype == 'Human') {
		$expectedtypeID = 1;
	    } elseif ($expectedtype == 'Lego') {
                $expectedtypeID = 2;
            } elseif ($expectedtype == 'Test') {
                $expectedtypeID = 3;
            } else {
                $errors['PhantomTypes'] = 'Phantom Type ID can not be extracted from the Phantom Type';
	    }

$expected_pscid = 'X' . $site->getSiteAlias() . $expectedtypeID . $expectedphantID;

            if(($values['PhantomTypes']) != ($expectedtype)) {
                $errors['PhantomTypes'] = 'Phantom Type must be concordant with the type in the phantom table for that Phantom Name';
            } 

            if(empty($values['PSCID'])) {
                $errors['PSCID'] = 'PSCID must be specified';
            } elseif(!Candidate::validatePSCIDPhant($values['PSCID'], $site->getSiteAlias())) {
                $errors['PSCID'] = 'PSCID does not contain the alias for the site you belong to';
            } elseif($expected_pscid != $values['PSCID']) {
                $errors['PSCID'] = 'PSCID does not follow the structure of X, followed by Site Alias, followed 
				    by the ID of the Phantom Type, followed by the ID of the Phantom Name';
            } elseif($db->pselectOne("SELECT count(PSCID) FROM candidate WHERE PSCID=:V_PSCID", 
                array('V_PSCID' => $values[PSCID])) > 0
            ) {
                    $errors['PSCID'] = 'PSCID has already been registered';
            }
        }

        return $errors;
    }

    function _process($values)
    {
        // set up the arguments to Candidate::createNew
        $user =& User::singleton();
        $config =& NDB_Config::singleton();
	$db =& Database::singleton();
        $centerID = $user->getData('CenterID');

        // create the candidate in candidate table 
	$phantID = Candidate::_generateCandID();
        // Get DoB and Gender from the phantom_identification table
	$phantInfo = $db->pselect("SELECT DoB, Gender FROM phantom_identification WHERE ID=:phantName", array('phantName' => $values['PhantomNames']));
        foreach($phantInfo as $row) {
            $dob = $row['DoB'];
            $gender = $row['Gender'];
        }
       	$setArray = array(
                         'CandID'          => $phantID,
                         'PSCID'           => $values['PSCID'],
                         'CenterID'        => $centerID,
                         'DoB'        	   => $dob,
                         'Gender'          => $gender,
                         'Entity_type'     => 'Scanner',
                         'RegisteredBy'    => $user->getUsername(),
                         'UserID'          => $user->getUsername(),
                        );

        $db->insert('candidate', $setArray);
        

        // get the candidate
        $candidate =& Candidate::singleton($phantID);

        //------------------------------------------------------------

        $this->tpl_data['success'] = true;
        $this->tpl_data['candID'] = $phantID;
        $this->tpl_data['PSCID'] = $candidate->getPSCID();

        // freeze it, just in case
        $this->form->freeze();
    }


}
?>
