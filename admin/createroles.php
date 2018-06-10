<?php

/**
 * Create Roles script
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

require_once '../../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

// Need to be logged in to view this page
require_login();

$ELBP = ELBP\ELBP::instantiate();

$access = $ELBP->getCoursePermissions(SITEID);
if (!$access['god'] && $view != 'course'){
    print_error( get_string('invalidaccess', 'block_elbp') );
}

// Required data
$systemContext = \context_system::instance();


$results = array('errors' => array(), 'success' => array());

// Front page teacher
if (!$ELBP->getSetting('elbp_frontpageteacher')){
    
    $name = get_string('frontpageteacherrolename', 'block_elbp');
    $shortname = 'elbp_frontpageteacher';
    $desc = get_string('frontpageteacher:desc', 'block_elbp');
    $id = create_role($name, $shortname, $desc, 'frontpage');
    
    // If it created successfully
    if ($id){
        
        // Set the role contexts
        set_role_contextlevels($id, array(CONTEXT_COURSE));
        
        // Update the block/elbp:view_elbp capability and block/bc_dashboard:view_bc_dashboard
        assign_capability('block/elbp:view_elbp', CAP_ALLOW, $id, $systemContext);
        assign_capability('block/bc_dashboard:view_bc_dashboard', CAP_ALLOW, $id, $systemContext);
        
        // Update elbp setting
        $ELBP->updateSetting('elbp_frontpageteacher', $shortname);
        
        // Print success message
        $results['success'][] = sprintf(get_string('creatednewrole', 'block_elbp'), $id, $name, $shortname, $desc);
        
    } else {
        // Otherwise
        $results['errors'][] = sprintf(get_string('errors:createnewrole', 'block_elbp'), $shortname);
    }
    
}



// Personal Tutor
if (!$ELBP->getSetting('elbp_personaltutor')){
    
    $name = get_string('personaltutorrolename', 'block_elbp');
    $shortname = 'elbp_personaltutor';
    $desc = get_string('personaltutor:desc', 'block_elbp');
    $id = create_role($name, $shortname, $desc, 'teacher');
    
    // If it created successfully
    if ($id){
        
        // Set the role contexts
        set_role_contextlevels($id, array(CONTEXT_USER));
        
        // Update the block/elbp:view_elbp capability
        assign_capability('block/elbp:view_elbp', CAP_ALLOW, $id, $systemContext);
        
        // Update the elbp setting
        $ELBP->updateSetting('elbp_personaltutor', $shortname);
        
        // Print success message
        $results['success'][] = sprintf(get_string('creatednewrole', 'block_elbp'), $id, $name, $shortname, $desc);
        
    } else {
        // Otherwise
        $results['errors'][] = sprintf(get_string('errors:createnewrole', 'block_elbp'), $shortname);
    }
    
}


// Additional Support Tutor
if (!$ELBP->getSetting('elbp_asl')){
    
    $name = get_string('addsuptutorrolename', 'block_elbp');
    $shortname = 'elbp_addsuptutor';
    $desc = get_string('addsuptutor:desc', 'block_elbp');
    $id = create_role($name, $shortname, $desc, 'teacher');
    
    // If it created successfully
    if ($id){
        
        // Set the role contexts
        set_role_contextlevels($id, array(CONTEXT_USER));
        
        // Update the block/elbp:view_elbp capability
        assign_capability('block/elbp:view_elbp', CAP_ALLOW, $id, $systemContext);
        
        // Update the elbp setting
        $ELBP->updateSetting('elbp_asl', $shortname);
        
        // Print success message
        $results['success'][] = sprintf(get_string('creatednewrole', 'block_elbp'), $id, $name, $shortname, $desc);
        
    } else {
        // Otherwise
        $results['errors'][] = sprintf(get_string('errors:createnewrole', 'block_elbp'), $shortname);
    }
}



// ELBP Manager/Admin
if (!$ELBP->getSetting('elbp_admin')){
    
    $name = get_string('elbpadminrolename', 'block_elbp');
    $shortname = 'elbp_manager';
    $desc = get_string('elbpadmin:desc', 'block_elbp');
    $id = create_role($name, $shortname, $desc, 'frontpage');
    
    // If it created successfully
    if ($id){
        
        // Set the role contexts
        set_role_contextlevels($id, array(CONTEXT_COURSE));
        
        // Update the block/elbp:view_elbp capability & view_bc_dashboard
        assign_capability('block/elbp:elbp_admin', CAP_ALLOW, $id, $systemContext);
        assign_capability('block/bc_dashboard:view_bc_dashboard', CAP_ALLOW, $id, $systemContext);

        // Update the elbp setting
        $ELBP->updateSetting('elbp_admin', $shortname);
        
        // Print success message
        $results['success'][] = sprintf(get_string('creatednewrole', 'block_elbp'), $id, $name, $shortname, $desc);
        
    } else {
        // Otherwise
        $results['errors'][] = sprintf(get_string('errors:createnewrole', 'block_elbp'), $shortname);
    }
}





// Set up PAGE
$PAGE->set_context( context_course::instance(SITEID) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/admin/createroles.php');
$PAGE->set_title( $ELBP->getELBPFullName() . ' ::: ' . get_string('adminscripts', 'block_elbp') );
$PAGE->set_heading( get_string('config', 'block_elbp') );
$PAGE->set_pagelayout( $ELBP->getThemeLayout() );

// If course is set, put that into breadcrumb
$PAGE->navbar->add( $ELBP->getELBPFullName(), null);
$PAGE->navbar->add( get_string('adminscripts', 'block_elbp') );
$PAGE->navbar->add( get_string('createroles', 'block_elbp'), $CFG->wwwroot . '/blocks/elbp/createroles.php', navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

if ($results['success']){
    echo "<div class='process-output-success'>";
        foreach($results['success'] as $msg)
        {
            echo $msg . "<br><br>";
        }
    echo "</div>";
}

echo "<br>";

if ($results['errors']){
    echo "<div class='process-output-error'>";
        foreach($results['errors'] as $msg)
        {
            echo $msg . "<br><br>";
        }
    echo "</div>";
}

echo $OUTPUT->footer();