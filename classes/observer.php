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