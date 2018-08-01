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