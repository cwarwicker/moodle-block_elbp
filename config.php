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
