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


$type = optional_param('type', false, PARAM_ALPHA);


$ELBP = ELBP\ELBP::instantiate();
$DBC = new ELBP\DB();

$courseID = SITEID; # Front Page

// Check course context is valid
$courseContext = context_course::instance($courseID);
if (!$courseContext){
    print_error( get_string('invalidcourse', 'block_elbp') );
}

$access = $ELBP->getCoursePermissions($courseID);
    
// Need to be a teacher on the course (or admin) if we can view the students on the course
if ( !isset($access['teacher']) && !isset($access['god']) ){
    print_error( get_string('nopermissionscourse', 'block_elbp') );
}

// URL of current page
$pageURL = $_SERVER['REQUEST_URI'];

// Need to be logged in to view this page
require_login();




// Set up PAGE
$PAGE->set_context( $courseContext );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/assign_tutors.php?id='.$courseID);
$PAGE->set_title( $ELBP->getELBPFullName() . ' ::: ' . get_string('assignpt', 'block_elbp') );
$PAGE->set_heading('heading');
$PAGE->set_cacheable(true);

// If course is set, put that into breadcrumb
$PAGE->navbar->add( $ELBP->getELBPFullName() , null, navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( get_string('assignpt', 'block_elbp') , null, navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

$html = "";

// Define variables to be used in heredocs
$vars = array();
$vars['string_courses'] = get_string('assignbycourses', 'block_elbp');
$vars['string_course'] = get_string('assignbycourse', 'block_elbp');
$vars['string_students'] = get_string('assignbystudent', 'block_elbp');
$vars['string_bulk'] = get_string('bulkupload', 'block_elbp');
$vars['link_class']['course'] = '';
$vars['link_class']['courses'] = '';
$vars['link_class']['students'] = '';
$vars['link_class']['bulk'] = '';
$vars['string_search'] = get_string('search', 'block_elbp');
$vars['string_search_course'] = get_string('searchcourse', 'block_elbp');
$vars['string_search_tutor'] = get_string('searchtutor', 'block_elbp');
$vars['string_search_student'] = get_string('searchstudent', 'block_elbp');
$vars['string_assign'] = get_string('assign', 'block_elbp');
$vars['string_results'] = get_string('results', 'block_elbp');
$vars['assign_button'] = "<input type='submit' class='elbp_big_button' name='submit_assign' value='{$vars['string_assign']}' />";


$html .= "<h2 class='elbp_h2 elbp_centre'>".get_string('assignpt', 'block_elbp')."</h2>";

// Tab styles
if ($type == 'student') $vars['link_class']['students'] = 'selected';
elseif ($type == 'course') $vars['link_class']['course'] = 'selected';
elseif ($type == 'courses') $vars['link_class']['courses'] = 'selected';
elseif ($type == 'bulk') $vars['link_class']['bulk'] = 'selected';

// Navigation tabs - Courses, Mentees
$html .= <<<HTML

   <ul class="elbp_tabrow">
        <li class="{$vars['link_class']['courses']}"><a href="assign_tutors.php?type=courses">{$vars['string_courses']}</a></li>
        <li class="{$vars['link_class']['course']}"><a href="assign_tutors.php?type=course">{$vars['string_course']}</a></li>
        <li class="{$vars['link_class']['students']}"><a href="assign_tutors.php?type=student">{$vars['string_students']}</a></li>
        <li class="{$vars['link_class']['bulk']}"><a href="assign_tutors.php?type=bulk">{$vars['string_bulk']}</a></li>
    </ul>

HTML;
        

// Assign by an individual student
if ($type == 'student')
{
        
    $results = array();
    $results['tutor'] = '';
    $results['student'] = '';
    $results['assign'] = '';
    
    $searchTutor = optional_param('search_tutor', false, PARAM_TEXT);
    $searchStudent = optional_param('search_student', false, PARAM_TEXT);
    $selectedTutors = optional_param_array('select_tutors', false, PARAM_TEXT);
    $selectedStudents = optional_param_array('select_students', false, PARAM_TEXT);
    
    
    // Assign those selected
    
    if (isset($_POST['submit_assign']))
    {
        
        $error = false;
        
        if (!$selectedTutors || !$selectedStudents)
        {
            $results['assign'] = "<span class='elbp_err'>".get_string('plsselvalidtutorstudent', 'block_elbp')."<br></span>";
            $error = true;
        }
        
        // Check submitted tutors are valid
        if ($selectedTutors)
        {
            foreach ($selectedTutors as $tutor)
            {
                if (!$DBC->isValidUser($tutor)){
                    $error = true;
                    
                    // If they exist in the system, display their name in the error msg, else just do a general msg
                    $tutorName = $DBC->getUserName($tutor);
                    if ($tutorName) $results['assign'] .= "<span class='elbp_err'>{$tutorName} ".get_string('isnotvalidtutor', 'block_elbp')."<br></span>";
                    else $results['assign'] .= "<span class='elbp_err'>ID #{$tutor} ".get_string('isnotvalidtutor', 'block_elbp')."<br></span>";
                }
            }
        }
        
        // Check submitted students are valid
        if ($selectedStudents)
        {
            foreach ($selectedStudents as $student)
            {
                if (!$DBC->isValidUser($student)){
                    $error = true;
                    // If they exist in the system, display their name in the error msg, else just do a general msg
                    $results['assign'] .= "<span class='elbp_err'>ID #{$student} ".get_string('isnotvalidstudent', 'block_elbp')."<br></span>";
                }
            }
        }
        
        
        
        
        // If no errors, do it
        if (!$error)
        {
            
            $PT = new \ELBP\PersonalTutor();
            
            foreach($selectedTutors as $tutor)
            {
                $PT->loadTutorID($tutor);
                $PT->assignIndividualMentees($selectedStudents);
                $PT->addToOutput("");
            }
            
            $results['assign'] .= $PT->getOutputMsg();
                        
        }
        
        
        
        
    }
    
    
    
    
    
                
    // Search for a tutor with this name
    if ($searchTutor)
    {

        // if we have an additional where clause to define staff, use that
        $tutorResults = $DBC->searchUser($searchTutor);

        if ($tutorResults)
        {

            $results['tutor'] .= "<select multiple='multiple' name='select_tutors[]' class='elbp_select_fill'>";
                foreach($tutorResults as $result)
                {
                    $results['tutor'] .= "<option value='{$result->id}' title='".fullname($result).", {$result->email}'>".fullname($result).", {$result->email}</option>";
                }
            $results['tutor'] .= "</select>";

        }

    }

    if ($searchStudent)
    {
        $studentResults = $DBC->searchUser($searchStudent);

        if ($studentResults)
        {

            $results['student'] .= "<select multiple='multiple' name='select_students[]' class='elbp_select_fill'>";
                foreach($studentResults as $result)
                {
                    $results['student'] .= "<option value='{$result->id}' title='".fullname($result).", {$result->email}'>".fullname($result).", {$result->email}</option>";
                }
            $results['student'] .= "</select>";

        }

    }


    $searchTutor = elbp_html($searchTutor);
    $searchStudent = elbp_html($searchStudent);
        
            
    
    // Build form - Search for tutor & search for student
    $html .= <<<HTML
        <form action='' method='post'>
        <table style='width:100%;' class='elbp_centre'>
            <tr><th style='width:35%;'>{$vars['string_search_tutor']}</th><th style='width:30%;'></th><th style='width:35%;'>{$vars['string_search_student']}</th></tr>
            <tr>
                <td><input type='text' class='elbp_max' name='search_tutor' value='{$searchTutor}' /></td>
                <td><input type='submit' class='elbp_big_button' name='submit_search_by_student' value='{$vars['string_search']}' /></td>
                <td><input type='text' class='elbp_max' name='search_student' value='{$searchStudent}' /></td>
            </tr>
        </table>
        </form>
HTML;
            
    if (!empty($results['tutor']) || !empty($results['student']))
    {
        
        // If one of them has no results, don't display the assign button
        if (empty($results['tutor']) || empty($results['student'])){
            $vars['assign_button'] = '';
        }
        
        $html .= "<br><div class='elbp_centre'>{$results['assign']}</div>";
        
        $html .= <<<HTML
            <form action='' method='post'>
            <table style='width:100%;' class='elbp_centre'>
                <tr><th style='width:40%;'><span class='elbp_small elbp_bold'>{$vars['string_results']}</span></th><th style='width:20%;'></th><th style='width:40%;'><span class='elbp_small elbp_bold'>{$vars['string_results']}</span></th></tr>
                <tr>
                    <td>{$results['tutor']}</td>
                    <td style='vertical-align:middle;'>{$vars['assign_button']}</td>
                    <td>{$results['student']}</td>
                </tr>
            </table>
            <input type='hidden' name='search_tutor' value='{$searchTutor}' />
            <input type='hidden' name='search_student' value='{$searchStudent}' />
            </form>
    
HTML;
                
    }
        
}



// Individual course in more detail - groups and students
elseif ($type == 'course')
{
    
    $results = array();
    $results['tutor'] = '';
    $results['course'] = '';
    $results['assign'] = '';
    
    $searchCourses = false;
    $course = false;
    
    $searchCourse = optional_param('search_course', false, PARAM_TEXT);
    $searchTutor = optional_param('search_tutor', false, PARAM_TEXT);
    $selectedTutors = optional_param_array('select_tutors', false, PARAM_TEXT);
    $selectedStudents = optional_param_array('select_students', false, PARAM_TEXT);
    
    
    $courseid = optional_param('courseid', false, PARAM_INT);
    $groupid = optional_param('groupid', false, PARAM_INT);

    if ($courseid)
    {
        $course = $DBC->getCourse(array("type"=>"id", "val"=>$courseid));
    }
    
    if ($groupid)
    {
        $group = groups_get_group($groupid);
    }
    
    
    
    // Search for a tutor with this name
    if ($searchTutor)
    {

        // if we have an additional where clause to define staff, use that
        $tutorResults = $DBC->searchUser($searchTutor);

        if ($tutorResults)
        {

            $results['tutor'] .= "<select multiple='multiple' name='select_tutors[]' class='elbp_select_fill'>";
                foreach($tutorResults as $result)
                {
                    $results['tutor'] .= "<option value='{$result->id}' title='".fullname($result).", {$result->email}'>".fullname($result).", {$result->email}</option>";
                }
            $results['tutor'] .= "</select>";

        }

    }
    
    
    if ($searchCourse)
    {
        
        // if we have an additional where clause to define staff, use that
        $courseResults = $DBC->searchCourse($searchCourse);

        if ($courseResults)
        {

            $results['course'] .= "<select name='courseid' onchange='this.form.submit();'  style='max-width:90%;'>";
            $results['course'] .= "<option value=''>".get_string('selectcourse', 'block_elbp')."</option>";
                foreach($courseResults as $result)
                {
                    $sel = ($course && $course->id == $result->id) ? "selected" : "";
                    $results['course'] .= "<option value='{$result->id}' title='[{$result->shortname}] {$result->fullname}' {$sel}>{$result->fullname}</option>";
                }
            $results['course'] .= "</select><br><br>";

        }
        
    }
    
    
    // If valid course selected from drop down, show link to assign to whole course
    if ($course)
    {
        
        
        // Form submissions
        if (isset($_POST['submit_whole_course']))
        {
            
            
            $error = false;
        
            if (!$selectedTutors)
            {
                $results['assign'] = "<span class='elbp_err'>".get_string('plsselvalidtutor', 'block_elbp')."<br></span>";
                $error = true;
            }
            
            
            if (!$error)
            {
            

                $PT = new ELBP\PersonalTutor();

                foreach($selectedTutors as $tutor)
                {
                    $PT->loadTutorID($tutor);
                    $PT->assignWholeCourse($course->id);
                    $PT->addToOutput("");
                }

                $results['assign'] .= $PT->getOutputMsg();
            
            }
            
            
        }
        
        
        elseif (isset($_POST['submit_whole_group']) && $group)
        {
            
            $error = false;
        
            if (!$selectedTutors)
            {
                $results['assign'] = "<span class='elbp_err'>".get_string('plsselvalidtutor', 'block_elbp')."<br></span>";
                $error = true;
            }
            
            
            if (!$error)
            {
            

                $PT = new ELBP\PersonalTutor();

                foreach($selectedTutors as $tutor)
                {
                    $PT->loadTutorID($tutor);
                    $PT->assignWholeGroup($group->id, $course->id);
                    $PT->addToOutput("");
                }

                $results['assign'] .= $PT->getOutputMsg();
            
            }
            
        }
        
        elseif(isset($_POST['submit_students_from_course']))
        {
            
            $error = false;
        
            if (!$selectedTutors)
            {
                $results['assign'] = "<span class='elbp_err'>".get_string('plsselvalidtutor', 'block_elbp')."<br></span>";
                $error = true;
            }
                       
            
             // Check submitted tutors are valid
            if ($selectedTutors)
            {
                foreach ($selectedTutors as $tutor)
                {
                    if (!$DBC->isValidUser($tutor)){
                        $error = true;

                        // If they exist in the system, display their name in the error msg, else just do a general msg
                        $tutorName = $DBC->getUserName($tutor);
                        if ($tutorName) $results['assign'] .= "<span class='elbp_err'>{$tutorName} ".get_string('isnotvalidtutor', 'block_elbp')."<br></span>";
                        else $results['assign'] .= "<span class='elbp_err'>ID #{$tutor} ".get_string('isnotvalidtutor', 'block_elbp')."<br></span>";
                    }
                }
            }

            // Check submitted students are valid
            if ($selectedStudents)
            {
                foreach ($selectedStudents as $student)
                {
                    if (!$DBC->isValidUser($student)){
                        $error = true;
                        // If they exist in the system, display their name in the error msg, else just do a general msg
                        $results['assign'] .= "<span class='elbp_err'>ID #{$student} ".get_string('isnotvalidstudent', 'block_elbp')."<br></span>";
                    }
                }
            }




            // If no errors, do it
            if (!$error)
            {

                $PT = new \ELBP\PersonalTutor();

                foreach($selectedTutors as $tutor)
                {
                    $PT->loadTutorID($tutor);
                    $PT->assignIndividualMentees($selectedStudents);
                    $PT->addToOutput("");
                }

                $results['assign'] .= $PT->getOutputMsg();

            }
                            
        }
        
        
        
        
        
        
        
        $results['course'] .= "<input type='submit' class='elbp_button_4px elbp_up' name='submit_whole_course' value='".get_string('assigntowholecourse', 'block_elbp')."' /><br><br>";
        
        // Any groups?
        $groups = groups_get_all_groups($course->id);
        if ($groups)
        {

            $results['course'] .= "<select name='groupid' onchange='this.form.submit();' style='max-width:90%;'>";
            $results['course'] .= "<option value=''>".get_string('selectgroup', 'block_elbp')."</option>";

            foreach($groups as $courseGroup)
            {
                $sel = ($groupid && $groupid == $courseGroup->id) ? "selected" : "";
                $results['course'] .= "<option value='{$courseGroup->id}' {$sel}>{$courseGroup->name}</option>";
            }

            $results['course'] .= "</select><br><br>";
            
            // Valid group selected?
            
            if (isset($group) && $group)
            {

                $results['course']  .= "<input type='submit' class='elbp_button_4px elbp_up' name='submit_whole_group' value='".get_string('assigntowholegroup', 'block_elbp')."' /><br>";

                // Display all students on this group
                $results['course']  .= "<br><h3 class='elbp_h3'>{$course->fullname} - ".get_string('group')." {$group->name}</h3>";
                $students = groups_get_members($group->id);

            }
            else
            {
                $results['course']  .= "<h3 class='elbp_h3'>{$course->fullname}</h3>";
                $students = $DBC->getStudentsOnCourse($course->id);
            }
            
            
        }
        else
        {
            $results['course']  .= "<h3 class='elbp_h3'>{$course->fullname}</h3>";
            $students = $DBC->getStudentsOnCourse($course->id);
        }
        
        if ($students)
        {
            $results['course'] .= "<select name='select_students[]' multiple='multiple' style='max-width:90%;height:200px;'>";
                foreach($students as $student)
                {
                    $results['course'] .= "<option value='{$student->id}' title='".fullname($student)." ({$student->username})'>".fullname($student)." ({$student->username})</option>";
                }
            $results['course'] .= "</select><br><br>";
            $results['course'] .= "<input type='submit' name='submit_students_from_course' class='elbp_button_4px elbp_up' value='".get_string('assigntoselectedstudents', 'block_elbp')."' /><br>";
        }
        else
        {
            $results['course'] .= "<br><p>".get_string('nostudents', 'block_elbp')."</p>";
        }
        
        
    }
    

              
    $searchCourse = elbp_html($searchCourse);
    

    
    
    
    
    // Build form - Search for tutor & search for course
    $html .= <<<HTML
        <form action='' method='post'>
        <table style='width:100%;' class='elbp_centre'>
            <tr><th style='width:45%;'>{$vars['string_search_tutor']}</th><th style='width:10%;'></th><th style='width:45%;'>{$vars['string_search_course']}</th></tr>
            <tr>
                <td><input type='text' class='elbp_max' name='search_tutor' value='{$searchTutor}' /></td>
                <td><input type='submit' class='elbp_big_button' name='submit_search' value='{$vars['string_search']}' /></td>
                <td><input type='text' class='elbp_max' name='search_course' value='{$searchCourse}' /></td>
            </tr>
        </table>
        </form>
HTML;
    
                
    // If there are results for the tutor or course, display extra stuff
    if (!empty($results['tutor']) || !empty($results['course']))
    {
        
        
        $html .= "<br><div class='elbp_centre'>{$results['assign']}</div>";
        
        $html .= <<<HTML
            <form action='' method='post'>
            <table style='width:100%;table-layout:fixed;' class='elbp_centre'>
                <tr><th style='width:30%;'><span class='elbp_small elbp_bold'>{$vars['string_results']}</span></th><th style='width:10%;'></th><th style='width:60%;'><span class='elbp_small elbp_bold'>{$vars['string_results']}</span></th></tr>
                <tr>
                    <td style='vertical-align:top;'>{$results['tutor']}</td>
                    <td style='vertical-align:top;'></td>
                    <td style='vertical-align:top;'>{$results['course']}</td>
                </tr>
            </table>
            <input type='hidden' name='search_tutor' value='{$searchTutor}' />
            <input type='hidden' name='search_course' value='{$searchCourse}' />
            </form>
    
HTML;
                            
    }
    
    
}




// Assign multiple courses to a tutor
elseif ($type == 'courses')
{
    
    
    $results = array();
    $results['tutor'] = '';
    $results['course'] = '';
    $results['assign'] = '';
    
    $searchTutor = optional_param('search_tutor', false, PARAM_TEXT);
    $searchCourse = optional_param('search_course', false, PARAM_TEXT);
    $selectedTutors = optional_param_array('select_tutors', false, PARAM_TEXT);
    $selectedCourses = optional_param_array('select_courses', false, PARAM_TEXT);
    
    
    // Assign those selected
    if (isset($_POST['submit_assign']))
    {
        
        $error = false;
        
        if (!$selectedTutors || !$selectedCourses)
        {
            $results['assign'] = "<span class='elbp_err'>".get_string('plsselvalidtutorcourse', 'block_elbp')."<br></span>";
            $error = true;
        }
        
        // Check submitted tutors are valid
        if ($selectedTutors)
        {
            foreach ($selectedTutors as $tutor)
            {
                if (!$DBC->isValidUser($tutor)){
                    $error = true;
                    
                    // If they exist in the system, display their name in the error msg, else just do a general msg
                    $tutorName = $DBC->getUserName($tutor);
                    if ($tutorName) $results['assign'] .= "<span class='elbp_err'>{$tutorName} ".get_string('isnotvalidtutor', 'block_elbp')."<br></span>";
                    else $results['assign'] .= "<span class='elbp_err'>ID #{$tutor} ".get_string('isnotvalidtutor', 'block_elbp')."<br></span>";
                }
            }
        }
        
        // Check submitted students are valid
        if ($selectedCourses)
        {
            foreach ($selectedCourses as $selectedCourse)
            {
                if (!$DBC->getCourse(array("type"=>"id", "val"=>$selectedCourse))){
                    $error = true;
                    // If they exist in the system, display their name in the error msg, else just do a general msg
                    $results['assign'] .= "<span class='elbp_err'>ID #{$selectedCourse} ".get_string('isnotvalidcourse', 'block_elbp')."<br></span>";
                }
            }
        }
        
        
        
        
        // If no errors, do it
        if (!$error)
        {
            
            $PT = new ELBP\PersonalTutor();
            
            foreach($selectedTutors as $tutor)
            {
                $PT->loadTutorID($tutor);
                $PT->assignCourses($selectedCourses);
                $PT->addToOutput("");
            }
            
            $results['assign'] .= $PT->getOutputMsg();
                        
        }
        
        
        
        
    }
    
    
    
    
    
    // Search for a tutor with this name
    if ($searchTutor)
    {

        // if we have an additional where clause to define staff, use that        
        $tutorResults = $DBC->searchUser($searchTutor);

        if ($tutorResults)
        {

            $results['tutor'] .= "<select multiple='multiple' name='select_tutors[]' class='elbp_select_fill'>";
                foreach($tutorResults as $result)
                {
                    $results['tutor'] .= "<option value='{$result->id}' title='".fullname($result).", {$result->email}'>".fullname($result).", {$result->email}</option>";
                }
            $results['tutor'] .= "</select>";

        }

    }
    
    
    if ($searchCourse)
    {
        
        // if we have an additional where clause to define staff, use that
        $courseResults = $DBC->searchCourse($searchCourse);

        if ($courseResults)
        {

            $results['course'] .= "<select multiple='multiple' name='select_courses[]' class='elbp_select_fill'>";
                foreach($courseResults as $result)
                {
                    $results['course'] .= "<option value='{$result->id}' title='[{$result->shortname}] {$result->fullname}'>[{$result->shortname}] {$result->fullname}</option>";
                }
            $results['course'] .= "</select>";

        }
        
    }
    
    
    
    
    // Build form - Search for tutor & search for student
    $html .= <<<HTML
        <form action='' method='post'>
        <table style='width:100%;' class='elbp_centre'>
            <tr><th style='width:35%;'>{$vars['string_search_tutor']}</th><th style='width:30%;'></th><th style='width:35%;'>{$vars['string_search_course']}</th></tr>
            <tr>
                <td><input type='text' class='elbp_max' name='search_tutor' value='{$searchTutor}' /></td>
                <td><input type='submit' class='elbp_big_button' name='submit_search_by_student' value='{$vars['string_search']}' /></td>
                <td><input type='text' class='elbp_max' name='search_course' value='{$searchCourse}' /></td>
            </tr>
        </table>
        </form>
HTML;
            
    if (!empty($results['tutor']) || !empty($results['course']))
    {
        
        // If one of them has no results, don't display the assign button
        if (empty($results['tutor']) || empty($results['course'])){
            $vars['assign_button'] = '';
        }
        
        $html .= "<br><div class='elbp_centre'>{$results['assign']}</div>";
        
        $html .= <<<HTML
            <form action='' method='post'>
            <table style='width:100%;' class='elbp_centre'>
                <tr><th style='width:40%;'><span class='elbp_small elbp_bold'>{$vars['string_results']}</span></th><th style='width:20%;'></th><th style='width:40%;'><span class='elbp_small elbp_bold'>{$vars['string_results']}</span></th></tr>
                <tr>
                    <td>{$results['tutor']}</td>
                    <td style='vertical-align:middle;'>{$vars['assign_button']}</td>
                    <td>{$results['course']}</td>
                </tr>
            </table>
            <input type='hidden' name='search_tutor' value='{$searchTutor}' />
            <input type='hidden' name='search_course' value='{$searchCourse}' />
            </form>
    
HTML;
                
    }
    
    
}

elseif ($type == 'bulk')
{
    
    $PT = new ELBP\PersonalTutor();
    
    if (isset($_POST['upload'])){

        $error = false;
        $fields = array('username', 'idnumber');
        
        $tutorField = $_POST['tutor_field'];
        $studentField = $_POST['student_field'];
        
        $headers = array('tutor', 'student');
        
        // CHeck fields are valid
        if (!in_array($tutorField, $fields) || !in_array($studentField, $fields))
        {
            $error = true;
            $html .= \elbp_error_msg( get_string('fieldsnotfilledin', 'block_elbp') );
        }
        
        // Check file is uploaded
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] > 0)
        {
            $error = true;
            $html .= \elbp_error_msg( get_string('uploads:filenotset', 'block_elbp') );            
        }
        
        if (isset($_FILES['import_file']) && $_FILES['import_file']['size'] > 0)
        {
            
            // Check mimetype of file
            $fInfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($fInfo, $_FILES['import_file']['tmp_name']);
            finfo_close($fInfo);

            // Mime types not set
            if ($mime != 'text/csv' && $mime != 'text/plain'){
                $error = true;
                $html .= \elbp_error_msg( get_string('uploads:invalidmimetype', 'block_elbp') . " ( {$mime} )" ); 
            }
            
            // Open file
            $fh = fopen($_FILES['import_file']['tmp_name'], 'r');
            if (!$fh){
                $error = true;
                $html .= \elbp_error_msg( get_string('uploads:cannotopenfile', 'block_elbp') ); 
            }
            
            // CHeck headers
            if ($fh)
            {
                
                $headerRow = fgetcsv($fh);
                if ($headerRow !== $headers)
                {
                    $error = true;
                    $e = get_string('import:headersdontmatch', 'block_elbp');
                    $e = str_replace("%exp%", implode(",", $headers), $e);
                    $e = str_replace("%fnd%", implode(",", $headerRow), $e);
                    $html .= \elbp_error_msg( $e ); 
                }
                
            }
            
            
            // OK
            if (!$error)
            {
                
                $PT->setAssignBy($studentField);
                $ELBPDB = new \ELBP\DB();
                
                while( ($row = fgetcsv($fh)) !== false )
                {

                    $row = array_map('trim', $row);

                    $tutorVal = $row[0];
                    $studentVal = $row[1];
                    
                    // Get tutor
                    $tutor = $ELBPDB->getUser( array('type' => $tutorField, 'val' => $tutorVal) );
                    
                    if (!$tutor){
                        $PT->addToOutput( get_string('invaliduser', 'block_elbp') . " ({$tutorVal})" );
                        continue;
                    }
                    
                    $PT->loadTutorID($tutor->id);
                    $PT->assignIndividualMentees($studentVal);

                }
                
                $html .= \elbp_success_msg( $PT->getOutputMsg() );
                                
            }  
            
        }
        
    }
    
    
    
    
    
    // Create template file
    $importFile = $PT->createTemplateBulkPTFile();
    if ($importFile)
    {
        $html .= "<p class='elbp_centre'><a href='{$CFG->wwwroot}/blocks/elbp/download.php?f={$importFile}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/file_icons/page_white_excel.png' /> ".get_string('csvtemplate', 'block_elbp')."</a></p>";
    }
    
    $html .= "<form action='' method='post' enctype='multipart/form-data'>";
    
        $html .= "<div class='elbp_centre'>";
        
            $html .= "<table style='margin:auto;'>";
            
                $html .= "<tr><th>".get_string('uploadtutorbyfield', 'block_elbp')."</th><th>".get_string('uploadstudentbyfield', 'block_elbp')."</th></tr>";
                
                $html .= "<tr>";
                
                    $html .= "<td><select name='tutor_field'><option value=''></option><option value='username'>username</option><option value='idnumber'>idnumber</option></select></td>";
                    
                    $html .= "<td><select name='student_field'><option value=''></option><option value='username'>username</option><option value='idnumber'>idnumber</option></select></td>";
                    
                $html .= "</tr>";
            
            $html .= "</table>";
            
            $html .= "<br><br>";
            
            $html .= "<input type='file' name='import_file' />";
            
            $html .= "<br><br>";
            
            $html .= "<input type='submit' name='upload' value='".get_string('upload')."' />";
        
        $html .= "</div>";
    
    $html .= "</form>";
    
}




$html .= "<br><br>";

echo $html;
echo $OUTPUT->footer();