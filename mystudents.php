<?php
/**
 * List of students' ELBPs.
 * Can be by course (students on any course teacher is assigned to), or by mentees (any students personal tutor is assigned to)
 * 
 * We recommend using the bc_dashboard block which should have come packaged with the ELBP, in which case you will never use this file
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

$courseID = optional_param('courseid', 1, PARAM_INT);
$groupID = optional_param('groupid', false, PARAM_INT);
$view = optional_param('view', null, PARAM_ALPHA);

// If dashboard is installed use that instead
$check_block = $DB->get_record("block", array("name" => "bc_dashboard"));
if ($check_block){
    header('Location:' . $CFG->wwwroot . '/blocks/bc_dashboard/'.$courseID.'/elbp/mystudents');
    exit;
}


// Need to be logged in to view this page
require_login();

$ELBP = ELBP\ELBP::instantiate();
$DBC = new ELBP\DB();

// If the user has no courses they are a teacher on and no mentees linked to them, redirect them to their own ELBP
if (!$DBC->hasTeacherCourses($USER->id) && !$DBC->hasTutorMentees($USER->id) && !is_siteadmin())
{
    redirect( $CFG->wwwroot . '/blocks/elbp/view.php?id=' . $USER->id );
}



// Page in query string
$page = optional_param('page', 1, PARAM_INT);

// Display pages
$perpage = ELBP\Setting::getSetting('list_stud_per_page', $USER->id);
if (!$perpage) $perpage = 15;

// URL of current page
$pageURL = $_SERVER['REQUEST_URI'];

$records = array();

// Check course context is valid
$courseContext = context_course::instance($courseID);
if (!$courseContext){
    print_error( get_string('invalidcourse', 'block_elbp') );
}

// If we're not looking at the front page course (as this would bring back every student probably), then create course object
$course = false;

if ($courseID > 1){
    // THis shouldn't be able to fail in theory, if courseContext hasn't failed
    $course = $DBC->getCourse(array("type" => "id", "val" => $courseID));
}

// Set up PAGE
$PAGE->set_context( $courseContext );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/mystudents.php?id='.$courseID);
$PAGE->set_title( $ELBP->getELBPFullName() . ' ::: ' . get_string('mystudents', 'block_elbp') );
$PAGE->set_heading( get_string('mystudents', 'block_elbp') );
$PAGE->set_cacheable(true);

// If course is set, put that into breadcrumb
if ($course) $PAGE->navbar->add( $course->shortname , $CFG->wwwroot . "/course/view.php?id={$course->id}", navigation_node::TYPE_COURSE);
$PAGE->navbar->add( $ELBP->getELBPFullName() , null, navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( get_string('mystudents', 'block_elbp') , null, navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

// Define variables to be used in heredocs
$vars = array();
$vars['string_courses'] = get_string('courses', 'block_elbp');
$vars['string_mentees'] = get_string('mentees', 'block_elbp');
$vars['link_class']['courses'] = '';
$vars['link_class']['mentees'] = '';

// If view is "course" and courseID is defined, then we are looking at a course
if ($view == 'course')
{
    
    // Check access permissions on the specified course ID
    $access = $ELBP->getCoursePermissions($courseID);
    
    // Need to be a teacher on the course (or admin) if we can view the students on the course
    if ( !isset($access['teacher']) && !isset($access['god']) ){
        print_error( get_string('nopermissionscourse', 'block_elbp') );
    }
    
    // If $course is not false, then we are looking at a valid course that is NOT the front page
    if ($course)
    {
        
        // Before we build the list of students, work out the LIMIT based on our limit setting perpage and the page number
                        
        // Work out the LIMIT based on these things
        $limitMin = ($page - 1) * $perpage;
        $limitMax = $perpage;
                
        // Get all the students on this course
        $records = $DBC->getStudentsOnCourse($course->id, null, false, array($limitMin, $limitMax));
        
    }
        
    
    // At this point we must have the correct permissions then
    $vars['link_class']['courses'] = 'selected';
    
}
elseif ($view == 'mentees')
{
    
    // Work out the LIMIT based on these things
    $limitMin = ($page - 1) * $perpage;
    $limitMax = $perpage;
    
    // Get all mentees associated with tutor
    
    $records = $DBC->getMenteesOnTutor($USER->id, null, false, array($limitMin, $limitMax));
    
    
    $vars['link_class']['mentees'] = 'selected';
    
}


// Navigation tabs - Courses, Mentees
$html = <<<HTML

   <ul class="elbp_tabrow">
        <li class="{$vars['link_class']['courses']}"><a href="mystudents.php?view=course">{$vars['string_courses']}</a></li>
        <li class="{$vars['link_class']['mentees']}"><a href="mystudents.php?view=mentees">{$vars['string_mentees']}</a></li>
    </ul>

HTML;

// Heading
if ($view == 'course'){
    $html .= "<h2 class='elbp_h2 elbp_centre'>".get_string('yourcourses', 'block_elbp')."</h2>";
    if ($course){
        $html .= "<h3 class='elbp_h3 elbp_centre'>({$course->shortname})</h3>";
    }
}
elseif ($view == 'mentees'){
    $html .= "<h2 class='elbp_h2 elbp_centre'>".get_string('yourmentees', 'block_elbp')."</h2>";
}
        
        
// If not a valid view        
if ($view != 'course' && $view != 'mentees')
{
    $html .= "<p class='elbp_centre'>" . get_string('choosevalid', 'block_elbp') . "</p>";
}
        
        
// If we're on view=course but no courseid is set, we need to choose frmo a list of course
elseif ($view == 'course' && !$course)
{
    
    // Display list of courses
    $teachersCourses = $DBC->getTeachersCourses($USER->id);
    
    $html .= "<div class='elbp_centre'>";
    
    if ($teachersCourses)
    {
        foreach($teachersCourses as $teachersCourse)
        {
            $html .= "<a href='mystudents.php?view=course&courseid={$teachersCourse->id}'>{$teachersCourse->fullname}</a><br>";
        }
    }
    else
    {
        $html .= "<p>".get_string('nocourses', 'block_elbp')."</p>";
    }
    
    $html .= "</div>";
    
}
        

// Otherwise:
else
{
        
    // Filter block
    $html .= $ELBP->buildStudentListFilter();

    // There should be a $records variable now if they are looking @ a valid course or are looking at mentees, if not can't display a table
            
    if ($view == 'mentees')
    {

        // Refine mentee search by course & then possibly further by group
        // Get all courses tutor is assigned to (this will be with courseid = (int) and studentid null
        $tutorsCourses = $DBC->getTutorsAssignedCourses($USER->id);

        // If they have any courses, show a select menu
        if ($tutorsCourses)
        {
            $url = strip_from_query_string("courseid", $pageURL);
            $url = strip_from_query_string("groupid", $url);
            $url = strip_from_query_string("page", $url);
            $html .= "<div class='elbp_centre'>";

            $html .= "<select onchange='window.location=\"".  append_query_string($url, 'courseid=')."\"+this.value;'>";
                $html .= "<option value='1'>".get_string('filterbycourse', 'block_elbp')."</option>";
                foreach($tutorsCourses as $tutorsCourse)
                {
                    $selected = ($course && $course->id == $tutorsCourse->id) ? "selected" : "";
                    $html .= "<option value='{$tutorsCourse->id}' {$selected}>{$tutorsCourse->fullname}</option>";
                }
            $html .= "</select> ";

            // If a course has been selected, display any groups that course has (which the tutor is linked to)
            if ($course)
            {

                $courseGroups = $DBC->getTutorsAssignedGroups($USER->id, $course->id);
                $url = strip_from_query_string("groupid", $pageURL);

                 $html .= "<select onchange='window.location=\"".  append_query_string($url, 'groupid=')."\"+this.value;'>";
                     $html .= "<option value='0'>".get_string('filterbygroup', 'block_elbp')."</option>";
                     foreach($courseGroups as $group)
                     {
                         $selected = ($groupID && $groupID == $group->id) ? "selected" : "";
                         $html .= "<option value='{$group->id}' {$selected}>{$group->name}</option>";
                     }
                 $html .= "</select> ";

            }

            $html .= "</div>";
            $html .= "<br>";
        }


    }
    
    if ($records)
    {

        // Results
        $params = array();

        if ($view == 'course' && $course){
            $params['course'] = true;
            $params['courseID'] = $course->id;
        }
        if ($view == 'mentees'){
            $params['mentees'] = true;

        }

        $html .= $ELBP->buildListOfStudents($records, $params);
    
    }
    else
    {
        $html .= "<p class='elbp_centre'>".get_string('nostudents', 'block_elbp')."</p>";
    }


}

$html .= "<br><br>";
echo $html;

echo $OUTPUT->footer();