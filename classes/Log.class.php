<?php

/**
 * Class for the logging of actions and the retrieval of logs
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


global $DB;

// Define constants

// Modules
define("LOG_MODULE_ELBP", "ELBP");
//define("LOG_MODULE_GRADETRACKER", "GRADETRACKER");
define("LOG_MODULE_PARENT_PORTAL", "PARENT_PORTAL");

// Elements
    // ELBP ELements
    define("LOG_ELEMENT_ELBP_PROFILE", "PROFILE");
    define("LOG_ELEMENT_ELBP_ATT_PUNC", "ATTENDANCE_PUNCTUALITY");
    define("LOG_ELEMENT_ELBP_TUTORIAL", "TUTORIAL");
    define("LOG_ELEMENT_ELBP_TARGET", "TARGET");
    define("LOG_ELEMENT_ELBP_NOTE", "NOTE");
    define("LOG_ELEMENT_ELBP_ATTACHMENT", "ATTACHMENT");
    define("LOG_ELEMENT_ELBP_PRIOR_LEARNING", "PRIOR_LEARNING");
    define("LOG_ELEMENT_ELBP_COURSE_REPORT", "COURSE_REPORT");
    define("LOG_ELEMENT_ELBP_COMMENT", "COMMENT");
    define("LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT", "ADDITIONAL_SUPPORT");
    define("LOG_ELEMENT_ELBP_CHALLENGES", "CHALLENGES");
    define("LOG_ELEMENT_ELBP_CUSTOM", "CUSTOM");
    
    define("LOG_ELEMENT_ELBP_SETTINGS", "SETTINGS");

    // Grade Tracker Elements
//    define("LOG_ELEMENT_GRADETRACKER_QUALIFICATION", "QUALIFICATION");
//    define("LOG_ELEMENT_GRADETRACKER_UNIT", "UNIT"); 
//    define("LOG_ELEMENT_GRADETRACKER_CRITERIA", "CRITERIA");
//    define("LOG_ELEMENT_GRADETRACKER_SETTINGS", "SETTINGS"); # This is for admin stuff, like setting new values, lvls, etc..
//    define("LOG_ELEMENT_GRADETRACKER_TASK", "TASK");
//    define("LOG_ELEMENT_GRADETRACKER_RANGE", "RANGE");
    
    
    
 // Actions
    
    // ELBP Actions
    
        // Profile related
        define("LOG_ACTION_ELBP_PROFILE_UPDATED_PROFILE_FIELD", "updated student's profile field");
    
        // Att & Punc related
    
        // Tutorial related
        define("LOG_ACTION_ELBP_TUTORIAL_CREATED_TUTORIAL", "created a new tutorial");
        define("LOG_ACTION_ELBP_TUTORIAL_UPDATED_TUTORIAL", "updated tutorial");
        define("LOG_ACTION_ELBP_TUTORIAL_DELETED_TUTORIAL", "deleted tutorial");
        define("LOG_ACTION_ELBP_TUTORIAL_DELETED_ATTRIBUTE", "deleted tutorial's attribute");
        
        // Target related
        define("LOG_ACTION_ELBP_TARGET_CREATED_TARGET", "created a new target");
        define("LOG_ACTION_ELBP_TARGET_UPDATED_TARGET", "updated target");
        define("LOG_ACTION_ELBP_TARGET_DELETED_TARGET", "deleted target");
        define("LOG_ACTION_ELBP_TARGET_ADDED_COMMENT", "added comment to target");
        define("LOG_ACTION_ELBP_TARGET_DELETED_COMMENT", "deleted coment from target");
        
        // Notes & Concerns related
    
        // Attachments related
        define("LOG_ACTION_ELBP_ATTACHMENT_ADDED_ATTACHMENT", "added a new attachment");
        define("LOG_ACTION_ELBP_ATTACHMENT_DELETED_ATTACHMENT", "deleted attachment");
        define("LOG_ACTION_ELBP_ATTACHMENT_ADDED_COMMENT", "added comment to attachment");
        define("LOG_ACTION_ELBP_ATTACHMENT_DELETED_COMMENT", "deleted comment from attachment");
        
        // Prior Learning related
    
        // Course Report related
        define("LOG_ACTION_ELBP_COURSE_REPORT_ADDED_REPORT", "added a new course report");
        define("LOG_ACTION_ELBP_COURSE_REPORT_UPDATED_REPORT", "updated course report");
        define("LOG_ACTION_ELBP_COURSE_REPORT_DELETED_REPORT", "deleted course report");
        define("LOG_ACTION_ELBP_COURSE_REPORT_ADDED_TERMLY_REPORT", "added a new periodical course report");
        define("LOG_ACTION_ELBP_COURSE_REPORT_UPDATED_TERMLY_REPORT", "updated a periodical course report");

        // Incident related
        define("LOG_ACTION_ELBP_COMMENTS_ADDED_COMMENT", "added a new comment");
        define("LOG_ACTION_ELBP_COMMENTS_UPDATED_COMMENT", "updated comment");
        define("LOG_ACTION_ELBP_COMMENTS_DELETED_COMMENT", "deleted comment");
        define("LOG_ACTION_ELBP_COMMENTS_RESOLVED_COMMENT", "resolved comment");
        
        // Additional Support related
        define("LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_ADDED_SESSION", "added a new session");
        define("LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_UPDATED_SESSION", "updated session");
        define("LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_DELETED_SESSION", "deleted session");
        define("LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_DELETED_ATTRIBUTE", "deleted session's atrribute");
        define("LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_ADDED_COMMENT", "added comment to session");
        define("LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_DELETED_COMMENT", "deleted comment from session");
        
        // Challenges related
        define("LOG_ACTION_ELBP_CHALLENGES_UPDATED_CHALLENGES", "updated student's challenges");
        define("LOG_ACTION_ELBP_CHALLENGES_UPDATED_CONFIDENTIALITY", "updated student's confidentiality level");
        
        // Custom plugin related
        define("LOG_ACTION_ELBP_CUSTOM_ADDED_ITEM", "added a new item");
        define("LOG_ACTION_ELBP_CUSTOM_UPDATED_ITEM", "updated item");
        define("LOG_ACTION_ELBP_CUSTOM_DELETED_ITEM", "deleted item");
        
        // Settings related
        define("LOG_ACTION_ELBP_SETTINGS_UPDATED_SETTING", "updated setting");
        define("LOG_ACTION_ELBP_SETTINGS_DELETED_SETTING", "deleted setting");
        
        define("LOG_ACTION_ELBP_SETTINGS_SET_USER_CAPABILITY", "set user capability");
    
    // Grade Tracker Actions

        // Qual related
//        define("LOG_ACTION_GRADETRACKER_INSERTED_QUAL", "inserted qualification");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_QUAL", "updated qualification");
//        define("LOG_ACTION_GRADETRACKER_DELETED_QUAL", "deleted qualification");
//        define("LOG_ACTION_GRADETRACKER_ADDED_UNIT_TO_QUAL", "added unit onto qualification");
//        define("LOG_ACTION_GRADETRACKER_REMOVED_UNIT_FROM_QUAL", "removed unit from qualification");
//        define("LOG_ACTION_GRADETRACKER_ADDED_QUAL_TO_COURSE", "added qualification to course");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_QUAL_COMMENTS", "updated student's qualification comments");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_QUAL_AWARD", "updated student's qualification award");
//        define("LOG_ACTION_GRADETRACKER_DELETED_QUAL_AWARD", "deleted student's qualification award");
//        define("LOG_ACTION_GRADETRACKER_SAVED_GRID", "saved student's grid");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_QUAL_ATTRIBUTE", "updated student's qualification attribute");
//
//        // Criteria related
//        define("LOG_ACTION_GRADETRACKER_INSERTED_CRIT", "inserted criteria");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRIT", "updated criteria");
//        define("LOG_ACTION_GRADETRACKER_DELETED_CRIT", "deleted criteria");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRIT_AWARD", "updated student's criteria award");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRIT_AWARD_AUTO", "automatically updated student's criteria award");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRIT_COMMENT", "updated student's criteria comment");
//        define("LOG_ACTION_GRADETRACKER_DELETED_CRIT_COMMENT", "deleted student's criteria comment");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_USER_DEFINED_VALUE", "updated student's user defined value");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRIT_USER_TARGET_DATE", "updated student's target date");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRIT_USER_AWARD_DATE", "updated student's award date");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_OUTCOME_OBSERVATION", "updated student's outcome observation");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_SIGNOFF_RANGE_OBSERVATION", "updated student's signoff range observation");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRIT_ATTRIBUTE", "updated student's criteria attribute");
//
//        // Unit related
//        define("LOG_ACTION_GRADETRACKER_INSERTED_UNIT", "inserted unit");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_UNIT", "updated unit");
//        define("LOG_ACTION_GRADETRACKER_DELETED_UNIT", "deleted unit");
//        define("LOG_ACTION_GRADETRACKER_ADDED_STUDENT_TO_UNIT", "added student to unit");
//        define("LOG_ACTION_GRADETRACKER_DELETED_STUDENT_FROM_UNIT", "deleted student from unit");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_STUDENT_TO_UNIT", "updated student on unit");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_UNIT_AWARD", "updated student's unit award");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_UNIT_COMMENT", "updated student's unit comment");
//        define("LOG_ACTION_GRADETRACKER_DELETED_UNIT_COMMENT", "deleted student's unit comment");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_UNIT_ATTRIBUTE", "updated student's unit attribute");
//
//        // Task related
//        define("LOG_ACTION_GRADETRACKER_INSERTED_TASK", "inserted task");
//        define("LOG_ACTION_GRADETRACKER_INSERTED_TASK_AWARD", "inserted student's criteria task award");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_TASK_AWARD", "updated student's criteria task award");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_TASK_COMMENT", "updated student's criteria task comment");
//        define("LOG_ACTION_GRADETRACKER_DELETED_TASK_COMMENT", "deleted student's criteria task comment");
//
//        // Range related
//        define("LOG_ACTION_GRADETRACKER_UPDATED_CRITERIA_RANGE_VALUE", "updated student's criteria/range value");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_RANGE_AWARD_DATE", "updated student's range award date");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_RANGE_AWARD", "updated student's range award");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_RANGE_AWARD_AUTO", "automatically updated student's range award");
//        define("LOG_ACTION_GRADETRACKER_UPDATED_RANGE_TARGET_DATE", "updated student's range target date");
//        define("LOG_ACTION_GRADETRACKER_DELETED_SIGNOFF_SHEET", "deleted signoff sheet"); # Not technically range but who cares
//        define("LOG_ACTION_GRADETRACKER_DELETED_SIGNOFF_SHEET_RANGE", "deleted signoff sheet range");
        
        
        
/**
 * 
 */
class Log {
    
    /**
     * Give an list of logs from the db, return an array of readable information about them
     * @param type $logs
     * @return array $return
     */
    public static function parseListOfLogs($logs){
        
        global $CFG, $DB;
        
        $return = array();
        
        if ($logs)
        {
            
            foreach($logs as $log)
            {
                
                $info = "";
                
                $user = $DB->get_record("user", array("id" => $log->userid));
                if ($user) $info .= fullname($user) . " ";
                
                $info .= $log->action . " ";
                
                // Get more detail if we can
//                switch($log->action)
//                {
//
//                    case LOG_ACTION_ELBP_TARGET_CREATED_TARGET:
//                    case LOG_ACTION_ELBP_TARGET_UPDATED_TARGET:
//
//                        $att = $DB->get_record("lbp_log_attributes", array("logid" => $log->id, "field" => "name"));
//                        if ($att){
//                            $info .= "\"{$att->value}\" ";
//                        }
//
//                    break;
//
//                    case LOG_ACTION_ELBP_TARGET_ADDED_COMMENT:
//                        
//                        $att = $DB->get_record("lbp_log_attributes", array("logid" => $log->id, "field" => "commentID"));
//                        if ($att){
//                            $comment = $DB->get_record("lbp_target_comments", array("id" => $att->value));
//                            if ($comment){
//                                $target = new \ELBP\Plugins\Targets\Target($comment->targetid);
//                                if ($target){
//                                    $info .= "\"{$target->getName()}\": <em><small>'".substr($comment->comments, 0, 25)."..'</small></em> ";
//                                }
//                            }
//                        }
//                        
//                    break;
//
//                }
                
                $info .= strtolower( get_string('for', 'block_elbp') ) . " ";
                
                $student = $DB->get_record("user", array("id" => $log->studentid));
                if ($user) $info .= "<a href='{$CFG->wwwroot}/blocks/elbp/view.php?id={$log->studentid}' target='_blank'>".fullname($student) . " ({$student->username}) </a>";
                
                
                $array = array(
                    "log" => $log,
                    "info" => $info,
                    "user" => $DB->get_record("user", array("id" => $log->userid)),
                    "student" => $student
                );
                
                $return[] = $array;
                
            }
            
        }
        
        return $return;
        
    }
    
}