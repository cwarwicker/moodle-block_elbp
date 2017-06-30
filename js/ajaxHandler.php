<?php

/**
 * This script handles all AJAX requests
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

require '../../../config.php';
require '../lib.php';

set_time_limit(0); # On localhost some things can take a while to run, so add this here

// Require login unless we are logged in from Parent Portal
if (!isset($_SESSION['pp_user'])){
    require_login();
}

$PAGE->set_url( $CFG->wwwroot . '/blocks/elbp/js/ajaxHandler.php' );
$PAGE->set_context( context_course::instance(1) ); # Don't see why Moodle insists on this

$ELBP = ELBP\ELBP::instantiate();

if (isset($_POST['plugin']) && $_POST['plugin']){
    $ELBP->handleAjaxRequest($_POST['plugin'], $_POST['action'], $_POST['params']);
    exit;
}

$params = $_POST['params'];

// Not a plugin specific, so let's check what it is
switch($_POST['action'])
{
    
    // This is for loading an HTML template into some content, e.g. loading the list of plugins in a group
    case 'load_template':
        
        if (!isset($params['type']) || !isset($params['id'])) exit;
        
        // Load up a list of plugins into a group
        if ($params['type'] == 'group')
        {
            $groupID = $params['id'];
            // Get all plugins in this group
            $dbPlugins = $ELBP->getPlugins($groupID);
                                    
            $plugins = array();
            
            if ($dbPlugins)
            {
                foreach($dbPlugins as $dbPlugin)
                {
                    
                    if (isset($dbPlugin->custom))
                    {
                        
                        $plugin = new ELBP\Plugins\CustomPlugin($dbPlugin->getID());
                        if (isset($params['student']) && $params['student'] > 0){
                            $access = $ELBP->getUserPermissions($params['student']);
                            if ($access){
                                $plugin->loadStudent($params['student']);
                                $plugin->loadCourse($params['course']);
                                $plugin->setAccess($access);
                            }
                        }

                        // We are checking here as well, because certain plugins may not want to be viewed 100% of the time
                        // E.g. Only show Additional Support plugin if student is linked to an ASL
                        if ($plugin->isEnabled()){
                            $plugins[] = $plugin;
                        }
                        
                    }
                    else
                    {
                        
                        try {
                            $plugin = ELBP\Plugins\Plugin::instaniate($dbPlugin->getName(), $dbPlugin->getPath());
                            if (isset($params['student']) && $params['student'] > 0){
                                $access = $ELBP->getUserPermissions($params['student']);
                                if ($access){
                                    $plugin->loadStudent($params['student']);
                                    $plugin->loadCourse($params['course']);
                                    $plugin->setAccess($access);
                                }
                            }

                            // We are checking here as well, because certain plugins may not want to be viewed 100% of the time
                            // E.g. Only show Additional Support plugin if student is linked to an ASL
                            if ($plugin->isEnabled()){
                                $plugins[] = $plugin;
                            }

                        }
                        catch (\ELBPException $e){
                            echo $e->getException();
                        }
                        
                    }                    
                    
                }
            }
            
            
            
            $TPL = new ELBP\Template();
            $TPL->set("plugins", $plugins)
                ->set("groupID", $groupID)
                ->set("string", $ELBP->getString());
            $TPL->load($CFG->dirroot . '/blocks/elbp/tpl/group.html');
            $TPL->display();
            exit;
        }
        
                
    break;
    
    case 'load_expanded':
                
        if (!isset($params['pluginname']) || !isset($params['student'])) exit;
        
        // Try to call plugin
        try {
            $plugin = ELBP\Plugins\Plugin::instaniate($params['pluginname']);
            $access = $ELBP->getUserPermissions($params['student']);
            if ($access) $plugin->loadStudent($params['student']);
            $plugin->loadCourse( $params['course'] );
            $plugin->setAccess($access);
            $plugin->display($params);
            exit;
        } catch (\ELBP\ELBPException $e){
            
            // Check if custom
            $check = $DB->get_record("lbp_custom_plugins", array("name" => $params['pluginname']));
            if ($check)
            {
                $plugin = new \ELBP\Plugins\CustomPlugin($check->id);
                $plugin->loadStudent($params['student']);
                if ($access) $plugin->loadStudent($params['student']);
                $plugin->loadCourse( $params['course'] );
                $plugin->setAccess($access);
                $plugin->display($params);
                exit;
            }
            
            echo $e->getException();
            exit;
            
        }
                
        
        
    break;
    
    case 'test_mis_connection':
        try {
            $MIS = \ELBP\MIS\Manager::instantiate( $params );
            $MIS->show_conn_err = false;
            $try = $MIS->connect( array("host"=>$params['host'], "user"=>$params['user'], "pass"=>$params['pass'], "db"=>$params['db']) );
            if (!$try){
                echo "<img src='{$CFG->wwwroot}/blocks/elbp/pix/error.png' /><br><small>{$MIS->getError()}</small>";
            }
            else echo "<img src='{$CFG->wwwroot}/blocks/elbp/pix/success.png' />";
        } catch (\ELBP\ELBPException $e){
            echo "<img src='{$CFG->wwwroot}/blocks/elbp/pix/error.png' /> <small>".$e->getMessage()."<br>".$e->getExpected()."</small></span>";
        }
    break;
    
    case 'switch_user':
        
        $DBC = new ELBP\DB();
        
        switch($params['action'])
        {
        
            case 'load_users':
                
                $param = $params['param'];
                
                if ($param == 'other')
                {
                    
                    echo "$('#switch_user_select').after(' <span id=\"find_other_user\"><input type=\"text\" id=\"search_other_student\" value=\"\" placeholder=\"".get_string('username')."\" /> <input type=\"button\" name=\"load_other_student\" value=\"".get_string('go', 'block_elbp')."\" class=\"elbp_small\" onclick=\"ELBP.switch_search_user( $(\'#search_other_student\').val() );return false;\" /></span>');";
                    
                } 
                else
                {
                    
                    // If a digit it's a course ID
                    if (ctype_digit($param)) $students = $DBC->getStudentsOnCourse($param);
                    // Else it's mentees
                    else $students = $DBC->getMenteesOnTutor($USER->id);

                    if ($students)
                    {

                        foreach($students as $student)
                        {
                            echo "$('#switch_user_users').append('<option value=\"{$student->id}\">{$student->username} ::: ".elbp_html(fullname($student))."</option>');";
                        }

                    }
                    
                }
                
                
                
                
            break;
        
        
        }
        
        
    break;
    
    case 'load_my_settings':
                
        $userID = (isset($params['userID'])) ? $params['userID'] : false;
        
        // If we are trying to change the settings of another user, make sure we can
        if ($userID){
            
            $access = $ELBP->getUserPermissions($userID);            
            if (!elbp_has_capability('block/elbp:change_others_settings', $access)){
                exit;
            }
            
            // Is ok
            $params['student'] = $userID;
            
        }
        
        $user = $DB->get_record("user", array("id" => $params['student']));
        if (!$user) exit;
        
        $TPL = new ELBP\Template();
        $TPL->set("plugins", $ELBP->getPlugins())
            ->set("string", $ELBP->getString())
            ->set("studentID", $params['student'])
            ->set("userFullName", fullname($user) . " ({$user->username})")
            ->set("layouts", \ELBP\PluginLayout::getAllPluginLayouts(true));
        $TPL->load($CFG->dirroot . '/blocks/elbp/tpl/my_settings.html');
        $TPL->display();
        exit;
        
    break;

    case 'save_my_settings':
        
        $data = $params['data'];
        $userID = (isset($params['userID'])) ? $params['userID'] : false;
        $access = $ELBP->getUserPermissions($userID);
        
        if ($userID){
            
            if (!elbp_has_capability('block/elbp:change_others_settings', $access)){
                exit;
            }
                        
        } else {
            $userID = $USER->id;
        }
        
        $user = $DB->get_record("user", array("id" => $userID));
        if (!$user) exit;
        
        $plugins = $ELBP->getPlugins();
        
        // Loop through plugins and see if we have a value in the array for the bg & font colours
        // If we do, add/update them as user setting for this user
                
        if ($plugins)
        {
            
            foreach($plugins as $plugin)
            {
                
                if ($plugin->isCustom()){
                    $bg = $plugin->getName() . "_bg_custom";
                    $font = $plugin->getName() . "_font_custom";
                } else {
                    $bg = $plugin->getName() . "_bg";
                    $font = $plugin->getName() . "_font";
                }
                
                if (isset($data[$bg])){
                    $plugin->updateSetting("header_bg_col", $data[$bg], $userID);
                }
                
                if (isset($data[$font])){
                    $plugin->updateSetting("header_font_col", $data[$font], $userID);
                }
                
            }
            
        }
        
        
        // Layout setting
        if (elbp_has_capability('block/elbp:change_users_plugins_layout', $access)){
            $ELBP->updateSetting('plugins_layout', $data['plugins_layout'], $userID);            
        }
        
        
    break;
    
    case 'course_picker':
        
        switch($params['action'])
        {   
            case 'choose_category':
                
                if (!ctype_digit($params['catID'])) exit;
                
                // Get courses in that cat
                $courses = get_courses($params['catID'], "c.shortname ASC, c.fullname ASC");
                if (!$courses) exit;
                
                foreach($courses as $course)
                {
                    if (isset($params['use']) && !empty($params['use'])){
                        echo "<option value='".elbp_html($course->$params['use'])."'>{$course->shortname}: {$course->fullname}</option>";
                    } else {
                        echo "<option value='".elbp_html($course->shortname)."'>{$course->shortname}: {$course->fullname}</option>";
                    }
                }
                
                exit;
                
            break;
            
            case 'search_courses':
                
                $DBC = new ELBP\DB();
                                
                $search = trim($params['search']);
                
                // If search is empty, get all courses for this cat again, else filter by search
                if (empty($search)){
                    if (!ctype_digit($params['catID'])) $params['catID'] = 'all';
                    $courses = get_courses($params['catID'], "c.shortname ASC, c.fullname ASC"); 
                } else {
                    if (!ctype_digit($params['catID'])) $params['catID'] = null;
                    $courses = $DBC->searchCourse($search, $params['catID']);  
                }
                
                if (!$courses) exit;
                
                foreach($courses as $course)
                {
                    if (isset($params['use']) && !empty($params['use'])){
                        echo "<option value='".elbp_html($course->$params['use'])."'>{$course->shortname}: {$course->fullname}</option>";
                    } else {
                        echo "<option value='".elbp_html($course->shortname)."'>{$course->shortname}: {$course->fullname}</option>";
                    }
                }
                
                exit;
                
            break;
        }
        
    break;
    
    case 'user_picker':
        
        switch($params['action'])
        { 
            case 'search_users':
                
                $DBC = new ELBP\DB();
                                
                $search = trim($params['search']);
                $limit = 100;
                                
                // If search is empty, get all courses for this cat again, else filter by search
                if (empty($search)){
                    $users = $DBC->getUsers($limit);
                } else {
                    $users = $DBC->searchUser($search, null, $limit);  
                }
                                
                if (!$users) exit;
                
                foreach($users as $user)
                {
                    echo "<option value='".elbp_html($user->username)."'>".fullname($user)." ({$user->username})</option>";
                }
                
                if (count($users) == $limit)
                {
                    echo "<option value=''>---- ".get_string('moreresults', 'block_elbp')." ----</option>";
                }
                
                exit;
                
            break;
        }
        
    break;
    
    case 'execute':
        
        // Must have CLI capability - This is an Admin only thing, so doesn't use the elbp_has_capability
        if (!has_capability('block/elbp:use_quick_tool', context_system::instance())){
            exit;
        }
        
        echo "[{$USER->username}@".gethostname()."] (".date('d-m-Y H:i:s').") ";
        echo nl2br($ELBP->executeAjaxCommand($params['action']));
        exit;
        
    break;

    case 'search_load_student':

        $search = $params['search'];
        
        $user = $DB->get_record("user", array("username" => $search, "deleted" => 0));
        if ($user)
        {
            echo "$('#switch_users_loading').html('".get_string('loading', 'block_elbp')." ".fullname($user)." ({$user->username}) ...');";
            echo "window.location.href = '{$CFG->wwwroot}/blocks/elbp/view.php?id={$user->id}';";
        }
        else
        {
            echo "alert('".get_string('nosuchuser', 'block_elbp')."');";
        }
        
        exit;
        
    break;
    
    case 'edit_plugin_attribute':

        if (isset($_POST['num'])){
            $params['num'] = $_POST['num'];
        }
        
        $element = \ELBP\ELBPFormElement::create($params);
        $form = \elbp_get_attribute_edit_form($element);
        echo $form;
        
    break;
    
    case 'set_student_manual_progress':

        // Must have capability
        $access = $ELBP->getUserPermissions($params['studentID']);
        if (!\elbp_has_capability('block/elbp:update_student_manual_progress', $access)){
            exit;
        }
        
        if ($ELBP->updateSetting('student_progress_rank', $params['rank'], $params['studentID'])){
            
            echo "$('.elbp_progress_traffic_light').addClass('elbp_progress_traffic_light_trans');";
            echo "$('#elbp_progress_traffic_light_{$params['rank']}').removeClass('elbp_progress_traffic_light_trans');";
            
        }
        
        exit;
                
    break;

    
    
}