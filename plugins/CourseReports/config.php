<?php

/**
 * Configure the Course Reports plugin
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
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
    $OBJ = \ELBP\Plugins\Plugin::instaniate("CourseReports");
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
    $OBJ->saveConfig($_POST);
    $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
    $TPL->set("MSGS", $MSGS);
}

// Reload atrributes
$OBJ->loadDefaultAttributes();


// Set up PAGE
$PAGE->set_context( context_course::instance(1) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/plugins/'.$OBJ->getName().'/config.php');
$PAGE->set_title( get_string('coursereportsconfig', 'block_elbp') );
$PAGE->set_heading( get_string('coursereportsconfig', 'block_elbp') );
$PAGE->set_cacheable(true);
$PAGE->set_pagelayout( $ELBP->getThemeLayout() );
$ELBP->loadJavascript();
$ELBP->loadCSS();

$PAGE->navbar->add( $ELBP->getELBPFullName(), null);
$PAGE->navbar->add( get_string('config', 'block_elbp'), $CFG->wwwroot . '/blocks/elbp/config.php?view=plugins', navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( $OBJ->getTitle(), $CFG->wwwroot . '/blocks/elbp/plugins/'.$OBJ->getName().'/config.php', navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();


$TPL->set("OBJ", $OBJ);
$TPL->set("FORM", new \ELBP\ELBPForm());
$TPL->set("view", $view);
$TPL->set("MSGS", $MSGS);
$TPL->set("OUTPUT", $OUTPUT);

// Settings for display
$settings = array();
$settings['course_types'] = \ELBP\Setting::getSetting('course_types', null, $OBJ->getID());
$settings['course_name'] = \ELBP\Setting::getSetting('course_name', null, $OBJ->getID());

$TPL->set("settings", $settings);

try {
    $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/'.$OBJ->getName().'/tpl/config.html' );
    $TPL->display();
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}

echo $OUTPUT->footer();