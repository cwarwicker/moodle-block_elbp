<?php

/**
 * Handle Moodle events, such as course enrolments/unenrolments and what that means for the elbp data
 * 
 * @copyright 2015 Bedford College
 * @package Bedford College Grade Tracker
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 * 
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

class block_elbp_observer {
    
    /**
}
     * @param \core\event\base $data
     */
    public static function eblp_user_enrolment(\core\event\base $data) {
        
        if ($data->contextlevel == CONTEXT_COURSE){
            global $DB;
    
            $ELBPDB = new \ELBP\DB();

            // Get context & role assignment
            $context = $DB->get_record("context", array("contextlevel" => CONTEXT_COURSE, "instanceid" => $data->courseid));
            if (!$context) return true;

            $role = $DB->get_record("role_assignments", array("userid" => $data->relateduserid, "contextid" => $context->id));
            if (!$role) return true;

            // Must be student
            if ($role->roleid <> $ELBPDB->getRole("student")) return true;

            // Find any PTs assigned to this course and add this user to them
            $assigned = $DB->get_records("lbp_tutor_assignments", array("courseid" => $data->courseid));
            if ($assigned)
            {
                foreach($assigned as $record)
                {

                    $PT = new \ELBP\PersonalTutor();
                    $PT->loadTutorID($record->tutorid);
                    $PT->assignMentee($data->relateduserid);

                }
            }
        }
        

    }
    
    
    /**
     * @param \core\event\base $data
     */
    public static function eblp_user_unenrolment(\core\event\base $data) {
        
        
    }
    
    
    /**
     * @param \core\event\base $data
     */
    public static function elbp_group_member_added(\core\event\base $data) {
                
        if ($data->contextlevel == CONTEXT_COURSE){
            global $DB;

            // Find any PTs assigned to this group
            $assigned = $DB->get_records("lbp_tutor_assignments", array("groupid" => $data->objectid));
            if ($assigned)
            {
                foreach($assigned as $record)
                {

                    $PT = new \ELBP\PersonalTutor();
                    $PT->loadTutorID($record->tutorid);
                    $PT->assignMentee($data->userid);

                }
            }
        }
    }
    
    
    /**
     * @param \core\event\base $data
     */
    public static function elbp_group_member_removed(\core\event\base $data) {
        
        if ($data->contextlevel == CONTEXT_COURSE){
            global $DB;

            // Find any PTs assigned to this group
            $assigned = $DB->get_records("lbp_tutor_assignments", array("groupid" => $data->objectid));
            if ($assigned)
            {
                foreach($assigned as $record)
                {

                    $PT = new \ELBP\PersonalTutor();
                    $PT->loadTutorID($record->tutorid);
                    $PT->removeMentee($data->userid);

                }
            }
        }
    }
   
}