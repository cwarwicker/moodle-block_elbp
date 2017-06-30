<?php
/**
 * Script to be called by AJAX when uploading file
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 */
header('Content-Type: text/html');

require_once '../../../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

require_login();

// If POST and FILES empty most likely we've exceeded post limit, so it will return the studentID not set error which is wrong
// So let's display the file size one instead
if (empty($_POST) && empty($_FILES)){
    $result = array('success' => false,'error' => get_string('uploads:postexceeded', 'block_elbp'));
    echo json_encode($result);
    exit;
}

// Check uploading from Moodle - This is only going to work if the referer is actually sent, so it's not exactly great, but worth having anyway
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $CFG->wwwroot) !== 0){
    exit;
}

$ELBP = ELBP\ELBP::instantiate();
$ATT = $ELBP->getPlugin("Attachments");

// Student ID?
if (!isset($_POST['sid']) || !ctype_digit($_POST['sid'])){
    $result = array('success' => false, 'error' => get_string('uploads:sidnotset', 'block_elbp'));
    echo json_encode($result);
    exit;
}

// FIle title?
if (!isset($_POST['title']) || empty($_POST['title'])){
    $result = array('success' => false, 'error' => get_string('uploads:titlenotset', 'block_elbp'));
    echo json_encode($result);
    exit;
}

$studentID = (int)$_REQUEST['sid'];
$ATT->loadStudent($studentID);

// We have the permission to do this?
$access = $ELBP->getUserPermissions($studentID);
if (!$ELBP->anyPermissionsTrue($access) || !elbp_has_capability('block/elbp:add_attachment', $access)){
    $result = array('success' => false, 'error' => get_string('invalidaccess', 'block_elbp'));
    echo json_encode($result);
    exit;
}

// Upload the file, if it's been sent
if (isset($_FILES['qqfile'])){
    
    // Before we do this, let's make sure we have created the directory we want to upload to
    $ATT->createDataDirectory( $studentID );
    
    $Upload = new \ELBP\Upload();
    
    // If there was a problem uploading the temporary file - stop
    if ($_FILES['qqfile']['error'] > 0){
        $Upload->setFile( $_FILES['qqfile'] );    
        $result = array('success' => false, 'error' => $Upload->getUploadErrorCodeMessage());
        echo json_encode($result);
        exit;
    }
    
    $Upload->setMimeTypes( $ATT->getAllowedMimeTypes() );
    $Upload->setUploadDir( $CFG->dataroot . '/ELBP/Attachments/'.$studentID.'/' );
    $Upload->setMaxSize( $ATT->getMaxFileSize() );
    $Upload->setFile( $_FILES['qqfile'] );    
    
    $result = $Upload->doUpload();
    $result['uploadName'] = $Upload->filename;
    
    // If OK, store in DB
    if ($Upload->getResult()){
        
        if (!$ATT->insertAttachment($_POST['title'], $Upload->filename)){
            $result = array('success' => false, 'error' => get_string('errors:couldnotinsertrecord', 'block_elbp'));
        }
        
    }
    
    echo json_encode($result);
    exit;
        
}