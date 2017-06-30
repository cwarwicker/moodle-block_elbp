<?php

/**
 * 
 * Age, Sex, Location Class
 * 
 * Core class to define all Additional Support Lecturer related methods, such as checking if a user is an
 * ASL of a given student, adding students/courses/groups to an ASL, etc...
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

namespace ELBP;

/**
 * 
 */
class ASL {
    
    private $tutorID = false;
    private $outputMsg;
    private $dbc;
    
    private $assignby = 'id';
    
    const DEFAULT_ROLE = 'elbp_asl'; # Shortname of the default role to use
    
    /**
     * 
     * @global type $DBC
     */
    public function __construct() {
        global $DBC;
        $this->outputMsg = '';
        $this->dbc = (!is_null($DBC)) ? $DBC : new \ELBP\DB();
    }
    
    /**
     * Set the method we are assigning by
     * @param type $by
     */
    public function setAssignBy($by){
        $this->assignby = $by;
    }
    
    /**
     * Add a msg to the output string to be displayed at the end of assigning
     * @param type $msg
     * @param type $class
     */
    public function addToOutput($msg, $class='')
    {
        $this->outputMsg .= "<span class='{$class}'>{$msg}<br></span>";
    }
    
    /**
     * Return the output msg
     * @return type
     */
    public function getOutputMsg()
    {
        return $this->outputMsg;
    }
    
    /**
     * Clear the output msg
     */
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
        return false;        
    }
    
    /**
     * Assign the tutor to a whole group on a course
     * @param type $groupID
     * @param type $courseID
     * @return boolean
     */
    public function assignWholeGroup($groupID, $courseID)
    {
        return false;
    }
    
    /**
     * Assign all the students on a course to the personal tutor
     * Also store a record in tutor_assignments of this course, and any groups on the course
     * @param type $courseID
     */
    public function assignWholeCourse($courseID)
    {
       return false;
    }
    
    /**
     * Assign individual mentees to the ASL
     * @param array|int $mentees Can be an array of student IDs or just a student ID
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
                       get_string('asadditionalsupportstud', 'block_elbp');
                $this->addToOutput( $msg, "elbp_success" );
            }
            else
            {
                $this->addToOutput( get_string('errorassigningrole', 'block_elbp'), "elbp_error" );
            }
            
                        
        }
            
        
    }
    
    /**
     * Check if a given student ID is assigned to the currently loaded ASL
     * @global type $DB
     * @param type $studentID
     * @return boolean
     */
    public function isStudentAssigned($studentID){
        
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
        
        // If we've defined a different role to use, use that, otherwise use the default
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = self::DEFAULT_ROLE;
        
        $aslRole = $this->dbc->getRole($role);
        if (!$aslRole){
            $this->addToOutput( get_string('erroraslrole', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        
        // Now, check if tutor is assigned to this student
        return $DB->get_record("role_assignments", array("roleid" => $aslRole, "userid" => $this->tutorID, "contextid" => $context->id));
        
    }
    
    /**
     * Specifically assign 1 student to the current asl
     * @param int $studentID
     * @return boolean
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
            $this->addToOutput( get_string('invalidstudent', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        $context = \context_user::instance($student->id);
        
        if (!$context){
            $this->addToOutput( get_string('errorcreatingcontext', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = self::DEFAULT_ROLE;
        
        $aslRole = $this->dbc->getRole($role);
        if (!$aslRole){
            $this->addToOutput( get_string('erroraslrole', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        if ( role_assign($aslRole, $this->tutorID, $context) ){
            return true;
        }
        else
        {
            return false;
        }
        
    }
    
    
    /**
     * Specifically remove 1 student from the current asl
     * @param type $studentID
     * @return boolean
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
        
        // Get defined role or default role
        $role = \ELBP\Setting::getSetting('elbp_asl');
        if (!$role) $role = self::DEFAULT_ROLE;
        
        $aslRole = $this->dbc->getRole($role);
        if (!$aslRole){
            $this->addToOutput( get_string('errorptrole', 'block_elbp'), "elbp_error" );
            return false;
        }
        
        role_unassign($aslRole, $this->tutorID, $context->id);
        
        // role_unassign returns fuck all, so have to return true and assume. Thanks Moodle.
        return true;
        
        
    }
    
    
    
}