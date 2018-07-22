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