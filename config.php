<?php

/**
 * Configuration file for the ELBP & its various plugins and whatnot
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
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

// Need to be logged in to view this page
require_login();

$ELBP = ELBP\ELBP::instantiate();
$ELBP->loadStudent(null);
$ELBP->loadCourse(null);

$DBC = new ELBP\DB();

$view = optional_param('view', 'main', PARAM_ALPHA);
$id = optional_param('id', false, PARAM_INT);
$course = false;

$access = $ELBP->getCoursePermissions(SITEID);
if (!$access['god'] && $view != 'course'){
    print_error( get_string('invalidaccess', 'block_elbp') );
}

// If they are looking at the course settings, need to have that permission
if ($view == 'course' && $id && !\has_capability('block/elbp:edit_course_settings', \context_course::instance($id))){
    print_error( get_string('invalidaccess', 'block_elbp') );
} elseif ($view == 'course' && !$id){
    print_error( get_string('invalidcourse', 'block_elbp') );
}


$TPL = new \ELBP\Template();
$MSGS['errors'] = '';
$MSGS['success'] = '';
$FORMVALS = array();

// Submitted
$ELBP->saveConfig($view);

// Reload some stuff that would have been setup on load
$ELBP->reloadPlugins();

// Set up PAGE
$PAGE->set_context( context_course::instance(SITEID) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/config.php');
$PAGE->set_title( $ELBP->getELBPFullName() . ' ::: ' . get_string('config', 'block_elbp') );
$PAGE->set_heading( get_string('config', 'block_elbp') );
$PAGE->set_cacheable(true);
$PAGE->set_pagelayout( $ELBP->getThemeLayout() );
$ELBP->loadJavascript();
$ELBP->loadCSS();

// If course is set, put that into breadcrumb
$PAGE->navbar->add( $ELBP->getELBPFullName(), null);
$PAGE->navbar->add( get_string('config', 'block_elbp'), $CFG->wwwroot . '/blocks/elbp/config.php', navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

$TPL->set("ELBP", $ELBP);
$TPL->set("view", $view);
$TPL->set("access", $access);

$TPL->load( $CFG->dirroot . '/blocks/elbp/tpl/config.html' );
$TPL->display();

echo $OUTPUT->footer();