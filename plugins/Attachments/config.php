<?php
/**
 * Configure the Attachments plugin
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

$ELBP = ELBP\ELBP::instantiate();
$DBC = new ELBP\DB();

$view = optional_param('view', 'main', PARAM_ALPHA);

$access = $ELBP->getCoursePermissions(1);
if (!$access['god']){
    print_error( get_string('invalidaccess', 'block_elbp') );
}

// Need to be logged in to view this page
require_login();

try {
    $ATT = \ELBP\Plugins\Plugin::instaniate("Attachments");
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
    exit;
}

$TPL = new \ELBP\Template();
$MSGS['errors'] = '';
$MSGS['success'] = '';

// Submitted
if (!empty($_POST))
{
    $ATT->saveConfig($_POST);
    $TPL->set("saved", true);
}


// Set up PAGE
$PAGE->set_context( context_course::instance(1) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/plugins/Attachments/config.php');
$PAGE->set_title( get_string('attachmentsconfig', 'block_elbp') );
$PAGE->set_heading( get_string('attachmentsconfig', 'block_elbp') );
$PAGE->set_cacheable(true);
$PAGE->set_pagelayout( $ELBP->getThemeLayout() );
$ELBP->loadJavascript();
$ELBP->loadCSS();

// If course is set, put that into breadcrumb
$PAGE->navbar->add( $ELBP->getELBPFullName(), null);
$PAGE->navbar->add( get_string('config', 'block_elbp'), $CFG->wwwroot . '/blocks/elbp/config.php?view=plugins', navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( $ATT->getTitle(), $CFG->wwwroot . '/blocks/elbp/plugins/Attachments/config.php', navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

$mimes = array(
    "OFFICE" => get_common_mime_types("OFFICE"),
    "IMG" => get_common_mime_types("IMG"),
    "TXT" => get_common_mime_types("TXT"),
    "AUDIO" => get_common_mime_types("AUDIO"),
    "VIDEO" => get_common_mime_types("VIDEO"),
    "OTHER" => get_common_mime_types("OTHER"),
);

$TPL->set("ATT", $ATT);
$TPL->set("allowedMimes", $ATT->getAllowedMimeTypes());
$TPL->set("mimes", $mimes);
$TPL->set("definedMimes", $ATT->getIndividuallyDefinedMimeTypes());
$TPL->set("view", $view);
$TPL->set("MSGS", $MSGS);
$TPL->set("OUTPUT", $OUTPUT);

try {
    $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/Attachments/tpl/config.html' );
    $TPL->display();
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}

echo $OUTPUT->footer();