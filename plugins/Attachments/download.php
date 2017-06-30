<?php
/**
 * Download the attachment / view it in browser - whatever is relevant for that type of file
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

require_once '../../../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';
require_once $CFG->dirroot . '/lib/filelib.php';

require_login();

$id = required_param('id', PARAM_INT);

$ELBP = ELBP\ELBP::instantiate();

$attachment = new \ELBP\Plugins\Attachments\Attachment($id);
if (!$attachment->isValid() || $attachment->isDeleted()){
    print_error( get_string('invalidrecord', 'block_elbp') );
}

// Permissions to view this?
$access = $ELBP->getUserPermissions($attachment->getStudentID());
if (!$ELBP->anyPermissionsTrue($access)) return false;

$filename = $CFG->dataroot . "/ELBP/Attachments/" . $attachment->getStudentID() . "/" . $attachment->getFileName();
if (!file_exists($filename)){
    print_error( get_string('filenotfound', 'block_elbp') );
    exit;
}

send_file($filename, $attachment->getFileName());
exit;