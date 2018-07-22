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
global $DB, $CFG;

$ELBP = ELBP\ELBP::instantiate();
$DBC = new ELBP\DB();

$view = optional_param('view', 'main', PARAM_ALPHA);
$edittarget = optional_param('edittarget', false, PARAM_INT);
$deletetarget = optional_param('deletetarget', false, PARAM_INT);

$access = $ELBP->getCoursePermissions(1);
if (!$access['god']){
    print_error( get_string('invalidaccess', 'block_elbp') );
}

// Need to be logged in to view this page
require_login();

try {
    $TAR = \ELBP\Plugins\Plugin::instaniate("Targets");
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
    $TAR->saveConfig($_POST);
    $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
    $TPL->set("MSGS", $MSGS);
}

// Reload attributes
$TAR->loadDefaultAttributes();

// Set up PAGE
$PAGE->set_context( context_course::instance(1) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/plugins/Targets/config.php');
$PAGE->set_title( get_string('targetsconfig', 'block_elbp') );
$PAGE->set_heading( get_string('targetsconfig', 'block_elbp') );
$PAGE->set_cacheable(true);
$PAGE->set_pagelayout( $ELBP->getThemeLayout() );
$ELBP->loadJavascript();
$ELBP->loadCSS();

// If course is set, put that into breadcrumb
$PAGE->navbar->add( $ELBP->getELBPFullName(), null);
$PAGE->navbar->add( get_string('config', 'block_elbp'), $CFG->wwwroot . '/blocks/elbp/config.php?view=plugins', navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( $TAR->getTitle(), $CFG->wwwroot . '/blocks/elbp/plugins/'.$TAR->getName().'/config.php', navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

$FORM = new ELBP\ELBPForm();
$FORM->load($TAR->getDefaultAttributes());

$targets = $DB->get_records('lbp_target_sets', array('deleted' => 0));

if ($edittarget)
{
    $chosentarget = $DB->get_record('lbp_target_sets', array('id' => $edittarget));
    $attribs = $DB->get_records('lbp_target_set_attributes', array('targetsetid' => $chosentarget->id));
    
    $TPL->set("chosentarget", $chosentarget);
    $TPL->set("attribs", $attribs);
}

$TPL->set("TAR", $TAR);
$TPL->set("view", $view);
$TPL->set("MSGS", $MSGS);
$TPL->set("OUTPUT", $OUTPUT);
$TPL->set("FORM", $FORM);
$TPL->set("data", \ELBP\Plugins\Targets\Target::getDataForNewTargetForm($edittarget, false, true));
$TPL->set("targets", $targets);
$TPL->set("edittarget", $edittarget);
$TPL->set("deletetarget", $deletetarget);



try {
    $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/Targets/tpl/config.html' );
    $TPL->display();
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}

echo $OUTPUT->footer();