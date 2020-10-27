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
 * @copyright   2011-2017 Bedford College, 2017 onwards Conn Warwicker
 * @author      Conn Warwicker <conn@cmrwarwicker.com>
 * @link        https://github.com/cwarwicker/moodle-block_elbp
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Originally developed at Bedford College, now maintained by Conn Warwicker
 * 
 */

require_once '../../../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

$ELBP = block_elbp\ELBP::instantiate();
$DBC = new block_elbp\DB();

$view = optional_param('view', 'main', PARAM_ALPHA);

$access = $ELBP->getCoursePermissions(SITEID);
if (!$access['god']){
    print_error( get_string('invalidaccess', 'block_elbp') );
}

// Need to be logged in to view this page
require_login();

try {
    $OBJ = \block_elbp\Plugins\Plugin::instaniate("Register");
} catch (\block_elbp\ELBPException $e){
    echo $e->getException();
    exit;
}

$TPL = new \block_elbp\Template();
$MSGS['errors'] = '';
$MSGS['success'] = '';

// Submitted
if (!empty($_POST))
{
    $OBJ->saveConfig($_POST);
    $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
    $TPL->set("MSGS", $MSGS);
}


// Set up PAGE
$PAGE->set_context( context_course::instance(1) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/plugins/'.$OBJ->getName().'/config.php');
$PAGE->set_title( get_string('registerconfig', 'block_elbp') );
$PAGE->set_heading( get_string('registerconfig', 'block_elbp') );
$PAGE->set_cacheable(true);
$PAGE->set_pagelayout( $ELBP->getThemeLayout() );
$ELBP->loadJavascript();
$ELBP->loadCSS();

// If course is set, put that into breadcrumb
$PAGE->navbar->add( $ELBP->getELBPFullName(), null);
$PAGE->navbar->add( get_string('config', 'block_elbp'), $CFG->wwwroot . '/blocks/elbp/config.php?view=plugins', navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( $OBJ->getTitle(), $CFG->wwwroot . '/blocks/elbp/plugins/'.$OBJ->getName().'/config.php', navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();


$TPL->set("OBJ", $OBJ);
$TPL->set("view", $view);
$TPL->set("MSGS", $MSGS);
$TPL->set("OUTPUT", $OUTPUT);


switch($view)
{
    
    case 'settings':
        
        $settings = array();
        $settings['display_name'] = $OBJ->getSetting('display_name');
        
        $TPL->set("allValues", $OBJ->getAllValueInfo());
        $TPL->set("settings", $settings);
        
    break;

    case 'mis':
        
        $core = $OBJ->getMainMIS();
        if ($core){
            $conn = new \block_elbp\MISConnection($core->id);
            $TPL->set("conn", $conn);
        }
        
    break;
    
    case 'data':
        
        $reload = (bool)optional_param('reload', 0, PARAM_INT);
        
        // Create directory for template csvs
        $OBJ->createDataDirectory('templates');
        
        // If template csv doesn't exist, create it, otherwise get the file path
        $importFile = $OBJ->createTemplateImportCsv($reload);
        $TPL->set("importFile", $importFile);
        
        // If example csv doesn't exist, create it, otherwise get the file path
        $exampleFile = $OBJ->createExampleImportCsv($reload);
        $TPL->set("exampleFile", $exampleFile);
        
    break;
    
}

try {
    $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/'.$OBJ->getName().'/tpl/config.html' );
    $TPL->display();
} catch (\block_elbp\ELBPException $e){
    echo $e->getException();
}

echo $OUTPUT->footer();