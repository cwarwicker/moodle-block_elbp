<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Electronic Learning Blue Print
 *
 * ELBP is a moodle block plugin, which provides one singular place for all of a student's key academic information to be stored and viewed, such as attendance, targets, tutorials,
 * reports, qualification progress, etc... as well as unlimited custom sections.
 *
 * @package     block_elbp
 * @copyright   2017-onwards Conn Warwicker
 * @author      Conn Warwicker <conn@cmrwarwicker.com>
 * @link        https://github.com/cwarwicker/moodle-block_elbp
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Originally developed at Bedford College, now maintained by Conn Warwicker
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
if (isset($_FILES['file'])){

    // This will be stored in the temp directory for the time being, until it is saved somewhere else
    if (!\elbp_create_data_directory("tmp") || !\elbp_create_data_directory("tmp/" . $USER->id) ){
        $result = array('success' => false,'error' => get_string('uploads:dirnoexist', 'block_elbp'));
        echo json_encode($result);
        exit;
    }

    $Upload = new \ELBP\Upload();

    // If there was a problem uploading the temporary file - stop
    if ($_FILES['file']['error'] > 0){
        $Upload->setFile( $_FILES['file'] );
        $result = array('success' => false, 'error' => $Upload->getUploadErrorCodeMessage());
        echo json_encode($result);
        exit;
    }

    $Upload->setUploadDir( $CFG->dataroot . '/ELBP/tmp/' . $USER->id . '/' );
    $Upload->setFile( $_FILES['file'] );
    $Upload->doNotChangeFileName = true;

    $result = $Upload->doUpload();
    $result['tmp'] = 'tmp:' . $USER->id . '/' . $Upload->filename;
    $result['title'] = $Upload->filename;

    echo json_encode($result);
    exit;

}