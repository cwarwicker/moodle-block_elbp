<?php
/**
 * My Settings page
 * 
 * @copyright 2017 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 */

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

$MSGS = array('errors' => '', 'success' => '');
$ELBP = ELBP\ELBP::instantiate();
$TPL = new \ELBP\Template();


// Need to be logged in to view this page
require_login();

// Frontpage context
$frontPageContext = context_course::instance(SITEID);

// Set up PAGE
$PAGE->set_context( $frontPageContext );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/settings.php');
$PAGE->set_title( $ELBP->getELBPFullName() . ' ::: ' . get_string('mysettings', 'block_elbp') );
$PAGE->set_cacheable(true);

$ELBP->loadJavascript();

// If course is set, put that into breadcrumb
$PAGE->navbar->add( $ELBP->getELBPFullName() , null, navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( get_string('mysettings', 'block_elbp') , null, navigation_node::TYPE_CUSTOM);

if (isset($_POST['save_settings'])){
    $ELBP->saveUserSettings();
} 

echo $OUTPUT->header();

$TPL->set("ELBP", $ELBP);
$TPL->set("MSGS", $MSGS);

$DBC = new \ELBP\DB();

// Get courses and students for the drop-down menus
$TPL->set("userCourses", $DBC->getTeachersCourses($USER->id));
$TPL->set("allStudents", $DBC->getAllTutorsStudents($USER->id));

$TPL->load( $CFG->dirroot . '/blocks/elbp/tpl/settings.html' );
$TPL->display();

echo $OUTPUT->footer();