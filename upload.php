<?php
/**
 * File Upload for file attribute in custom forms
 * 
 * This file handles the file upload when you are using a file attribute element
 * jQuery can't send form data and file data in the same request, so as soon as the file element
 * changes it will fire off this upload, which will upload the file to a temp location, and create a download
 * code, which will be returned so we can put it into the form for when it is submitted.
 * 
 * When it's submitted it will move the file to the directory for that target/tutorial/whatever 
 * 
 * So if you don;'t submit, the file will stay in the temp folder until it's deleted by a gc
 * 
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


require_once '../../config.php';
require_once 'lib.php';


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

// Upload the file, if it's been sent
if (isset($_FILES['qqfile'])){
    
    // This will be stored in the temp directory for the time being, until it is saved somewhere else
    if (!\elbp_create_data_directory("tmp") || !\elbp_create_data_directory("tmp/" . $USER->id) ){
        $result = array('success' => false,'error' => get_string('uploads:dirnoexist', 'block_elbp'));
        echo json_encode($result);
        exit;
    }
    
    $Upload = new \ELBP\Upload();
    
    // If there was a problem uploading the temporary file - stop
    if ($_FILES['qqfile']['error'] > 0){
        $Upload->setFile( $_FILES['qqfile'] );    
        $result = array('success' => false, 'error' => $Upload->getUploadErrorCodeMessage());
        echo json_encode($result);
        exit;
    }
    
    $Upload->setUploadDir( $CFG->dataroot . '/ELBP/tmp/' . $USER->id . '/' );
    $Upload->setFile( $_FILES['qqfile'] );   
    $Upload->doNotChangeFileName = true;
    
    $result = $Upload->doUpload();
    $result['uploadName'] = '/tmp/' . $USER->id . '/' . $Upload->filename;   
        
    echo json_encode($result);
    exit;
   
}