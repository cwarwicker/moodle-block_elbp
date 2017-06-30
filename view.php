<?php
/**
 * Viewing a student's ELBP
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

require_login();

$ELBP = ELBP\ELBP::instantiate( array("load_plugins" => true) ); # False because profile is done manually and others are done by ajax
$DBC = new ELBP\DB();

$userID = optional_param('id', false, PARAM_INT);
$courseID = optional_param('courseid', SITEID, PARAM_INT);

$string = $ELBP->getString();

$course = false;

if ($courseID > 1){
    // THis shouldn't be able to fail in theory, if courseContext hasn't failed
    $course = $DBC->getCourse(array("type" => "id", "val" => $courseID));
}

// If no userid is sent, load up the logged in user's elbp
if (!$userID) $userID = $USER->id;

$user = $DBC->getUser(array("type" => "id", "val" => $userID));
if (!$user){
    print_error( get_string('invaliduser', 'block_elbp') );
}


// Check permissions - do we have the access rights to view this user's ELBP?
$access = $ELBP->getUserPermissions($user->id);
if (!$ELBP->anyPermissionsTrue($access)){
    print_error( get_string('nopermissionsuser', 'block_elbp') );
}

$ELBP->loadStudent($userID);
$ELBP->loadCourse($courseID);


// Set up PAGE
$PAGE->set_context( context_course::instance($courseID) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/view.php?id='.$userID);
$PAGE->set_title( $ELBP->getELBPShortName() . " :: " . fullname($user) . " ({$user->username})" );
$PAGE->set_heading( $ELBP->getELBPMyName() );
$PAGE->set_cacheable(true);
$PAGE->set_pagelayout( $ELBP->getThemeLayout() );
$ELBP->loadCSS();
$ELBP->loadJavascript();

// Student breadcrumb
if ($access['user'])
{
    $PAGE->navbar->add( $ELBP->getELBPMyName() , null, navigation_node::TYPE_CUSTOM);
}

// Staff breadcrumb
else
{
    // If course is set, put that into breadcrumb
    if ($course){
        $PAGE->navbar->add( $course->shortname , $CFG->wwwroot . "/course/view.php?id={$course->id}", navigation_node::TYPE_COURSE);
        $PAGE->navbar->add( $ELBP->getELBPShortName() , $CFG->wwwroot . "/blocks/elbp/mystudents.php?view=course&courseid={$course->id}", navigation_node::TYPE_CUSTOM);
    }
    else
    {
        $PAGE->navbar->add( $ELBP->getELBPShortName() , $CFG->wwwroot . "/blocks/elbp/mystudents.php", navigation_node::TYPE_CUSTOM);
    }
    $PAGE->navbar->add( fullname($user) . " ({$user->username})" , null, navigation_node::TYPE_CUSTOM);
}

echo $OUTPUT->header();

$html = "";
$html .= "<div id='elbp_wrapper'>";


// TOp bar
if ( $access['user'] == 0 || ($access['user'] == 1 && elbp_has_capability('block/elbp:change_my_settings', $access)) || (elbp_has_capability('block/elbp:change_others_settings', $access)) )
{
    $html .= "<div id='elbp_option_bar'>";
    
        // CHange user bar
        // If they are not this user then they must have permissions from being a course teacher or personal tutor, etc...
        if ($access['user'] == 0){
            $html .= elbp_switch_user_bar();
        } 

        // If we are the user and we have the change_my_sewttings capability, let them change their colours
        if ($access['user'] == 1 && elbp_has_capability('block/elbp:change_my_settings', $access)){
            // Settings - e.g. change colours of boxes
            $html .= "<div class='elbp_float_right'><a href='#' onclick='ELBP.my_settings();return false;'><img id='mysettingsimg' src='{$CFG->wwwroot}/blocks/elbp/pix/icons/cog.png' style='width:16px;' title='".get_string('settings')."' alt='".get_string('settings')."' /></a></div>";
        } elseif (elbp_has_capability('block/elbp:change_others_settings', $access)){
            // Otherwise if we can change the settings of others, let us do that
            $html .= "<div class='elbp_float_right'><a href='#' onclick='ELBP.my_settings(\"{$userID}\");return false;'><img id='mysettingsimg' src='{$CFG->wwwroot}/blocks/elbp/pix/icons/cog.png' style='width:16px;' title='".get_string('settings')."' alt='".get_string('settings')."' /></a></div>";
        }
    
    $html .= "<br class='elbp_cl'>";
    $html .= "</div>";
}


// If not plugins are installed, display that message and don't auto install the student profile one
if (!\ELBP\Plugins\Plugin::anyPluginsAvailable()){
    echo "<p class='elbp_centre'>".get_string('noplugins', 'block_elbp')."</p>";
    echo $OUTPUT->footer();
    exit;
}

// Overall Student Progress Bar
$html .= $ELBP->getStudentProgressBar();

// Student Profile - This is always at the top, not in any group so we can do this one manually
try {
    $plugin = ELBP\Plugins\Plugin::instaniate("StudentProfile");
    
    if ($plugin->isEnabled())
    {
    
        if ($plugin->loadStudent($userID))
        {
            $html .= $plugin->display();
        }
        else
        {
            $html .= "<div class='c'><br>".get_string('studprofnotavailable', 'block_elbp')."</div>";
        }
    
    }
    
} catch (ELBPException $e) {
    echo $e->getException();
}


$TPL = new ELBP\Template();

// Get plugin groups
$layout = \ELBP\PluginLayout::getUsersLayout($userID, $courseID);
$groups = array();
if ($layout){
    $groups = $DBC->getPluginGroups($layout->getID());
    $group = reset($groups);
}

$TPL->set("layout", $layout);
$TPL->set("groups", $groups);

try {
    $html .= $TPL->load($CFG->dirroot . '/blocks/elbp/tpl/view.html');
} catch (\ELBP\ELBPException $e){
    $html .= $e->getException();
}

$html .= "</div>";

echo $html;


// I'm not sure how to inject HTML after all the Moodle code but before the </body> so this is the best I can do atm
$TPL = new ELBP\Template();
try {
    $TPL->set("access", $ELBP->getAccess());
    $TPL->set("ELBP", $ELBP);
    $TPL->load($CFG->dirroot . '/blocks/elbp/tpl/footer.html');
    $TPL->display();
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}

// If no groups, display a message
if (!$layout){
    echo "<p class='elbp_centre'>".get_string('nopluginlayout', 'block_elbp')."</p>";
} elseif (!$groups){
    echo "<p class='elbp_centre'>".get_string('noplugingroups', 'block_elbp')."</p>";
}

echo $OUTPUT->footer();

if (isset($group) && $group){
    echo "<script>$(document).ready( function(){ELBP.load('group', {$group->id});} );</script>";
}
