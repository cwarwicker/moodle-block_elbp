<?php

/**
 * EMail Alerts
 * 
 * This class extends the Alert class, and deals with the sending of EMail alerts to users
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
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

namespace ELBP;

/**
 * 
 */
class EmailAlert extends Alert {
    
    /**
     * This is the same as the run method, except this is used to send the alert only to the student themselves, no-one else
     * @param type $eventName
     * @param type $pluginID
     * @param type $studentID
     * @param type $content
     * @param type $htmlContent
     */
    function runStudent($eventName, $pluginID, $studentID, $content, $htmlContent)
    {
        
        global $DB;
                        
        if (\ELBP\Setting::getSetting('enable_email_alerts') != 1) return false;
                
        // Check plugin exists
        $plugin = $DB->get_record("lbp_plugins", array("id" => $pluginID, "enabled" => 1), "id, title");
        if (!$plugin) return false;
        
        // Check event exists
        $event = $DB->get_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => $eventName, "enabled" => 1));
        if (!$event) return false;
        
        // Check user exists
        $student = $DB->get_record("user", array("id" => $studentID, "deleted" => 0), "id, firstname, lastname, username");
        if (!$student) return false;
        
        $subject = $plugin->title . " :: " . $eventName;
        
        // Queue the alert
        $this->queue("email", $student, $subject, $content, $htmlContent);

        return true;
        
    }
    
    
    /**
     * 
     * @global type $DB
     * @param type $eventName
     * @param type $pluginID
     * @param type $studentID
     * @param type $content
     * @param type $htmlContent
     * @return boolean
     */
    function run($eventName, $pluginID, $studentID, $content, $htmlContent, $confidentialityLevel = null)
    {
        
        global $DB;
        
        $ELBP = new \ELBP\ELBP();
        
        if (\ELBP\Setting::getSetting('enable_email_alerts') != 1) return false;
        
        // Check plugin exists
        $plugin = $DB->get_record("lbp_plugins", array("id" => $pluginID, "enabled" => 1), "id, title");
        if (!$plugin) return false;
        
        // Check event exists
        $event = $DB->get_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => $eventName, "enabled" => 1));
        if (!$event) return false;
        
        // Check user exists
        $student = $DB->get_record("user", array("id" => $studentID, "deleted" => 0));
        if (!$student) return false;
        
        $conf = new \ELBP\Confidentiality();
        
        $subject = $plugin->title . " :: " . $eventName;
                
        // First find all the users who want email alerts for this event, specific to this student
        $users = $this->getUsersWhoWantStudentEvent($event->id, $student->id);
        if ($users)
        {
            
            $studContent = $content;
            $studHtml = $htmlContent;
            
            $studContent .= "\n\n" . str_replace("%event%", $eventName, get_string('alerts:receieving:student', 'block_elbp')) . ": " . fullname($student) . " ({$student->username})";
            $studHtml .= "<br><br><small>" . str_replace("%event%", $eventName, get_string('alerts:receieving:student', 'block_elbp')) . ": " . fullname($student) . " ({$student->username})</small>";

            // Alert them all
            foreach($users as $user)
            {
                
                // if confidentiality level specified, check they can see this stuff
                if (!is_null($confidentialityLevel))
                {
                    
                    $access = $ELBP->getUserPermissions($studentID, $user->id);
                    if (!$conf->meetsConfidentialityRequirement($access, $confidentialityLevel)){
                        continue;
                    }
                    
                }
                
                
                // Append this user, so they don't get the alert twice
                $this->alertedUsers[$user->id] = true;
                
                // Queue the alert
                $this->queue("email", $user, $subject, $studContent, $studHtml);
                
            }
        }
        
        
        
        // Now find all the users who want email alerts for anyone on a course, that this student is on
        $ELBPDB = new \ELBP\DB();
        $courses = $ELBPDB->getStudentsCourses($studentID);
        if ($courses)
        {
            foreach($courses as $course)
            {
                
                // FInd users who want alerts for this course
                $users = $this->getUsersWhoWantCourseEvent($event->id, $course->id);
                if ($users)
                {
                    
                    $courseContent = $content;
                    $courseHtml = $htmlContent;

                    $courseContent .= "\n\n" . str_replace("%event%", $eventName, get_string('alerts:receieving:course', 'block_elbp')) . ": {$course->fullname}";
                    $courseHtml .= "<br><br><small>" . str_replace("%event%", $eventName, get_string('alerts:receieving:course', 'block_elbp')) . ": {$course->fullname}</small>";

                    
                    foreach($users as $user)
                    {
                        
                        // If this user has already been alerted, don't do it again
                        if (isset($this->alertedUsers[$user->id]) && $this->alertedUsers[$user->id] == true) continue;
                        
                        // Append
                        $this->alertedUsers[$user->id] = true;
                        
                        // Queue the alert
                        $this->queue("email", $user, $subject, $courseContent, $courseHtml);
                        
                    }
                }
                
            }
        }
        
        // Now find all users who have this student as a mentee and want email alerts for this event for all of their mentees
        $users = $ELBPDB->getTutorsOnStudent($student->id);
        if ($users)
        {
            foreach($users as $user)
            {
                
                // Does this user have a setting for this alert event for the mass group "mentees"?
                if (!isset($this->alertedUsers[$user->id]) && $this->hasUserGotMassEvent($user->id, $event->id, 'mentees'))
                {

                    // Build the content
                    $courseContent = $content;
                    $courseHtml = $htmlContent;

                    $courseContent .= "\n\n" . str_replace("%event%", $eventName, get_string('alerts:receieving:mentees', 'block_elbp'));
                    $courseHtml .= "<br><br><small>" . str_replace("%event%", $eventName, get_string('alerts:receieving:mentees', 'block_elbp'))."</small>";

                    // Append
                    $this->alertedUsers[$user->id] = true;

                    // Queue the alert
                    $this->queue("email", $user, $subject, $courseContent, $courseHtml);
                    
                }
                
            }
        }
        
        
        
        // Now find all users who have this student as an additional support student and want email alerts for this event for all of their additional support students
        $users = $ELBPDB->getAslOnStudent($student->id);
        if ($users)
        {
            foreach($users as $user)
            {
                
                // Does this user have a setting for this alert event for the mass group "mentees"?
                if (!isset($this->alertedUsers[$user->id]) && $this->hasUserGotMassEvent($user->id, $event->id, 'addsup'))
                {

                    // Build the content
                    $courseContent = $content;
                    $courseHtml = $htmlContent;

                    $courseContent .= "\n\n" . str_replace("%event%", $eventName, get_string('alerts:receieving:addsup', 'block_elbp'));
                    $courseHtml .= "<br><br><small>" . str_replace("%event%", $eventName, get_string('alerts:receieving:addsup', 'block_elbp'))."</small>";

                    // Append
                    $this->alertedUsers[$user->id] = true;

                    // Queue the alert
                    $this->queue("email", $user, $subject, $courseContent, $courseHtml);
                    
                }
                
            }
        }
        
        return true;
        
    }
        
    /**
     * Send the alert
     * @param type $user
     * @param type $subject
     * @param type $content
     */
    public function send($user, $subject, $content, $htmlContent, $historyID = null, $sentBy = null)
    {        
        
        global $DB;
                
        if (is_null($sentBy)){
            $sentBy = $user;
        }
                        
        // If we have a historyid to check, update the record in history with new time
        if (!is_null($historyID))
        {
            $data = new \stdClass();
            $data->id = $historyID;
            $data->timesent = time();
            $DB->update_record("lbp_alert_history", $data);
        }
        
        return email_to_user($user, $sentBy, $subject, $content, $htmlContent);
                
        
    }
    
    
}