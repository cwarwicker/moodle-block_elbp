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

namespace ELBP;

/**
 * 
 */
class PersonalTutor {
    
    private $tutorID = false;
    private $outputMsg;
    private $dbc;
    
    private $assignby = 'id';
    
    const DEFAULT_ROLE = 'elbp_personaltutor';
    
    public function __construct() {
        global $DBC;
        $this->tutorID = false;
        $this->outputMsg = '';
        $this->dbc = (!is_null($DBC)) ? $DBC : new \ELBP\DB();
    }
    
    /**
     * The field in the mdl_user table we are using to assign students by, e.g. "username", "id", etc...
     * @param type $by
     */
    public function setAssignBy($by){
        $this->assignby = $by;
    }
    
    public function addToOutput($msg, $class='')
    {
        $this->outputMsg .= "<span class='{$class}'>{$msg}<br></span>";
    }
    
    public function getOutputMsg()
    {
        return $this->outputMsg;
    }
    
    public function clearOutputMsg()
    {
        $this->outputMsg = '';
    }
    
    /**
     * Set the current tutorID to given value
     * @param type $userID
     */
    public function loadTutorID($userID)
    {
        $this->tutorID = $userID;
    }
    
    /**
     * Take an array of courseIDs and assign the current tutor to them all
     * @param type $courses
     */
    public function assignCourses($courses)
    {
        if (!$this->tutorID){
            $this->addToOutput( get_string('tutoridnotset', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        // If it's an array, then do multiple ones
        if (is_array($courses))
        {
            
            foreach($courses as $course)
            {
                $this->assignCourses($course);
            }
            
            return;
            
        }
        
        // If it's a digit, it's just the one to do
        elseif (ctype_digit($courses))
        {
            $this->assignWholeCourse($courses);
        }
        
    }
    
    /**
     * Assign the tutor to a whole group on a course
     * @param type $groupID
     * @param type $courseID
     * @return boolean
     */
    public function assignWholeGroup($groupID, $courseID)
    {
        
        
        if (!$this->tutorID){
            $this->addToOutput( get_string('tutoridnotset', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        if (!$this->isLinkedToGroup($courseID, $groupID))
        {
            $this->linkToGroup($courseID, $groupID);
        }
        
        // Students
        $students = groups_get_members($groupID);
        if ($students)
        {
            foreach($students as $student)
            {
                if ($this->assignMentee($student->id)){
                    $msg = get_string('assigned', 'block_elbp') . " " . $this->dbc->getFullName($student->id) . " " . 
                           get_string('to', 'block_elbp') . " " . $this->dbc->getFullName($this->tutorID) . " " . 
                           get_string('asamentee', 'block_elbp');
                    $this->addToOutput( $msg, "elbp_success" );
                }
                else
                {
                    $this->addToOutput( get_string('errorassigningrole', 'block_elbp'), "elbp_error" );
                }
            }
        }
        
    }
    
    /**
     * Assign all the students on a course to the personal tutor
     * Also store a record in tutor_assignments of this course, and any groups on the course
     * @param type $courseID
     */
    public function assignWholeCourse($courseID)
    {
        
        global $DB;
        
        if (!$this->tutorID){
            $this->addToOutput( get_string('tutoridnotset', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        // First let's store the records in lbp_tutor_assignments
        
        // Course record
        if (!$this->isLinkedToCourse($courseID))
        {
            $this->linkToCourse($courseID);
        }
                
        $course = $DB->get_record("course", array("id" => $courseID));
        
        $msg = "<br><b>" . get_string('assigned', 'block_elbp') . " " . $course->fullname . " ({$course->shortname}) " . 
                           get_string('to', 'block_elbp') . " " . $this->dbc->getFullName($this->tutorID) . "</b>";
                    $this->addToOutput( $msg, "elbp_success" );
        
        // Now do all the role assignments
        $students = $this->dbc->getStudentsOnCourse($courseID);
        if ($students)
        {
            foreach($students as $student)
            {
                if ($this->assignMentee($student->id)){
                    $msg = get_string('assigned', 'block_elbp') . " " . $this->dbc->getFullName($student->id) . " " . 
                           get_string('to', 'block_elbp') . " " . $this->dbc->getFullName($this->tutorID) . " " . 
                           get_string('asamentee', 'block_elbp');
                    $this->addToOutput( $msg, "elbp_success" );
                }
                else
                {
                    $this->addToOutput( get_string('errorassigningrole', 'block_elbp'), "elbp_error" );
                }
            }
                        
        }
        
        
    }
    
    /**
     * Create a record in tutor_assignments for a given courseid
     * @param type $courseID
     */
    private function linkToCourse($courseID)
    {
        global $DB;
        
        $obj = new \stdClass();
        $obj->tutorid = $this->tutorID;
        $obj->courseid = $courseID;
        $obj->lastupdated = time();
        return $DB->insert_record("lbp_tutor_assignments", $obj);
    }
    
    /**
     * Create a record in tutor_assignments for a given courseid & groupid
     * @param type $courseID
     */
    private function linkToGroup($courseID, $groupID)
    {
        global $DB;
        
        $obj = new \stdClass();
        $obj->tutorid = $this->tutorID;
        $obj->groupid = $groupID;
        $obj->lastupdated = time();
        return $DB->insert_record("lbp_tutor_assignments", $obj);
    }
    
    /**
     * Check if there is a record in lbp_tutor_assignments for a particular course
     * @param type $courseID
     */
    private function isLinkedToCourse($courseID)
    {
        global $DB;
        return $DB->get_record_select("lbp_tutor_assignments", "tutorid = ? AND courseid = ?", array($this->tutorID, $courseID), "id", IGNORE_MULTIPLE);
    }
    
    /**
     * Check if there is a record in lbp_tutor_assignments for a particular course & group
     * @global type $DB
     * @param type $courseID
     * @param type $groupID
     * @return type
     */
    private function isLinkedToGroup($courseID, $groupID)
    {
        global $DB;
        return $DB->get_record_select("lbp_tutor_assignments", "tutorid = ? AND courseid = ? AND groupid = ?", array($this->tutorID, $courseID, $groupID), "id", IGNORE_MULTIPLE);
    }
    
    /**
     * Assign mentees to a PT
     * If $mentees is an array loop through them all
     * Otherwise just assign that single user
     * @param type $mentees
     * @return boolean
     */
    public function assignIndividualMentees($mentees)
    {
        
        if (!$this->tutorID){
            $this->addToOutput( get_string('tutoridnotset', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        
        // If it's an array, then do multiple ones
        if (is_array($mentees))
        {
            
            foreach($mentees as $mentee)
            {
                $this->assignIndividualMentees($mentee);
            }
            
            return;
            
        }
        
        // If it's a digit, it's just the one to do
        else
        {
                        
            if ($this->assignMentee($mentees)){
                $msg = get_string('assigned', 'block_elbp') . " " . $this->dbc->getFullName($mentees, $this->assignby) . " " . 
                       get_string('to', 'block_elbp') . " " . $this->dbc->getFullName($this->tutorID) . " " . 
                       get_string('asamentee', 'block_elbp');
                $this->addToOutput( $msg, "elbp_success" );
            }
            else
            {
                $this->addToOutput( get_string('errorassigningrole', 'block_elbp'), "elbp_error" );
            }
            
                        
        }
            
        
    }
    
    /**
     * Remove a mentee from a PT
     * If $mentees is an array, loop through it and remove all of them
     * Otherwise just remove that single user
     * @param mixed $mentees
     * @return boolean
     */
    public function removeIndividualMentees($mentees)
    {
        
        if (!$this->tutorID){
            $this->addToOutput( get_string('tutoridnotset', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        
        // If it's an array, then do multiple ones
        if (is_array($mentees))
        {
            
            foreach($mentees as $mentee)
            {
                $this->removeIndividualMentees($mentee);
            }
            
            return;
            
        }
        
        // If it's a digit, it's just the one to do
        else
        {
                        
            if (is_object($mentees)) $mentees = $mentees->id;
            
            // This always returns true, as role_unassign doesn't return anything useful
            if ($this->removeMentee($mentees)){
                $msg = get_string('removed', 'block_elbp') . " " . $this->dbc->getFullName($mentees, $this->assignby) . " " . 
                       get_string('from', 'block_elbp') . " " . $this->dbc->getFullName($this->tutorID) . " " . 
                       get_string('asamentee', 'block_elbp');
                $this->addToOutput( $msg, "elbp_success" );
            }            
                        
        }
            
        
    }
    
    
    /**
     * Specifically assign 1 mentee to the current tutor
     * @param type $studentID
     */
    public function assignMentee($studentID)
    {
        
        global $DB;
        
        if (!$this->tutorID){
            $this->addToOutput( get_string('tutoridnotset', 'block_elbp'), "elbp_error" );
            return false;
        }
                        
        // First thing we need is a context for the type CONTEXT_USER with the given studentID, so that we can assign a role with it
        $student = $DB->get_record("user", array($this->assignby => $studentID));
        if (!$student){
            $this->addToOutput( get_string('invalidstudent', 'block_elbp') . " ({$studentID})", "elbp_error" );
            return false;
        }
        
        // Cannot assign to self
        if ($this->tutorID == $student->id){
            $this->addToOutput( get_string('cannotassigntoself', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        $context = \context_user::instance($student->id);
        
        if (!$context){
            $this->addToOutput( get_string('errorcreatingcontext', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        $ptRole = \ELBP\Setting::getSetting('elbp_personaltutor');
        if (!$ptRole) $ptRole = self::DEFAULT_ROLE;
        
        $personalTutorRole = $this->dbc->getRole($ptRole);
        if (!$personalTutorRole){
            $this->addToOutput( get_string('errorptrole', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        if ( role_assign($personalTutorRole, $this->tutorID, $context) ){
            return true;
        }
        else
        {
            return false;
        }
        
    }
    
    
    
    
    
    
    /**
     * Specifically remove 1 mentee from the current tutor
     * @param type $studentID
     */
    public function removeMentee($studentID)
    {
        
        global $DB;
        
        if (!$this->tutorID){
            $this->addToOutput( get_string('tutoridnotset', 'block_elbp'), "elbp_error" );
            return false;
        }
                
        // First thing we need is a context for the type CONTEXT_USER with the given studentID, so that we can assign a role with it
        $student = $DB->get_record("user", array($this->assignby => $studentID));
        if (!$student){
            $this->addToOutput( get_string('invalidstudent', 'block_elbp'), "elbp_error" );
            return false;
        }
                
        $context = \context_user::instance($student->id);
        
        if (!$context){
            $this->addToOutput( get_string('errorcreatingcontext', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        $ptRole = \ELBP\Setting::getSetting('elbp_personaltutor');
        if (!$ptRole) $ptRole = self::DEFAULT_ROLE;
        
        $personalTutorRole = $this->dbc->getRole($ptRole);
        if (!$personalTutorRole){
            $this->addToOutput( get_string('errorptrole', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        role_unassign($personalTutorRole, $this->tutorID, $context->id);
        
        // role_unassign returns fuck all, so have to return true and assume
        return true;
        
        
    }
    
    
    /**
     * Create the import csv for bulk personal tutor/mentee uploads
     * @global type $CFG
     * @return string|boolean
     */
    public function createTemplateBulkPTFile(){
        
        global $CFG;
        
        $file = $CFG->dataroot . '/ELBP/pt_template.csv';
        $code = \elbp_create_data_path_code($file);
        
        // If it already exists and we don't want to reload it, just return
        if (file_exists($file)){
            return $code;
        }
                
        // Now lets create the new one - The headers are going to be in English so we can easily compare headers
        $headers = array(
            'tutor',
            'student'
        );
        
        // Using "w" we truncate the file if it already exists
        $fh = fopen($file, 'w');
        if ($fh === false){
            return false;
        }
        
        $fp = fputcsv($fh, $headers);
        
        if ($fp === false){
            return false;
        }
        
        fclose($fh);        
        return $code;       
        
    }
    
    
    
    
    
    /**
     * Get the personal tutor role name, as defined in ELBP settings
     * @return type
     */
    static function getPersonalTutorRole(){
        
        $ptRole = \ELBP\Setting::getSetting('elbp_personaltutor');
        if (!$ptRole) $ptRole = self::DEFAULT_ROLE;
        return $ptRole;
        
    }
    
    
}