<?php
/**
 * Configure the Targets plugin
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