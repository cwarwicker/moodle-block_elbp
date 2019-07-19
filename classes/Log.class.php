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


global $DB;

// Define constants

// Modules
define("LOG_MODULE_ELBP", "ELBP");
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

/**
 *
 */
class Log {

    public static function getRecentLogs($limit = 20){

      global $DB;

      $recent = $DB->get_records_sql("SELECT * FROM {lbp_logs} WHERE module = 'ELBP' AND element != 'SETTINGS' ORDER BY time DESC", null, 0, $limit);
      return $recent;

    }

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