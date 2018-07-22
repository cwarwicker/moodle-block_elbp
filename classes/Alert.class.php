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
 * Class for different types of alerts which can be sent.
 * At the moment just Email Alerts, but could be SMS alerts as well in the future
 */
abstract class Alert {
    
    protected $alertedUsers = array();
    public static $history_time = 604800; # 1 week
    
    /**
     * Clear the array of users to be alerted
     * @return \ELBP\Alert
     */
    public function reset(){
        $this->alertedUsers = array();
        return $this;
    }
    
    /**
     * Get all users who want alerts for a given event, for a given student
     * @param int $eventID
     * @param int $studentID
     */
    protected function getUsersWhoWantStudentEvent($eventID, $studentID){
        
        global $DB;
        
        $users = $DB->get_records_sql("SELECT u.*
                                        FROM {lbp_alerts} a
                                        INNER JOIN {user} u ON u.id = a.userid 
                                        WHERE a.eventid = ? AND a.studentid = ? AND a.courseid IS NULL AND a.mass IS NULL AND value = 1", array($eventID, $studentID));

        return $users;
        
        
        
    }
    
    /**
     * Get all users who want alerts for a given event, for a given course
     * @param int $eventID
     * @param int $courseID
     */
    protected function getUsersWhoWantCourseEvent($eventID, $courseID){
        
        global $DB;
        
        $users = $DB->get_records_sql("SELECT u.*
                                        FROM {lbp_alerts} a
                                        INNER JOIN {user} u ON u.id = a.userid 
                                        WHERE a.eventid = ? AND a.courseid = ? AND a.studentid IS NULL AND a.mass IS NULL AND value = 1", array($eventID, $courseID));
        
        return $users;
        
        
        
    }
    
    /**
     * Check if a given user has a given mass event setting
     * @global \ELBP\type $DB
     * @param type $userID
     * @param type $eventID
     * @param type $type
     * @return type
     */
    protected function hasUserGotMassEvent($userID, $eventID, $type){
        
        global $DB;
        
        $record = $DB->get_records_sql("SELECT * 
                                        FROM {lbp_alerts}
                                        WHERE userid = ? AND eventid = ? AND courseid IS NULL AND studentid IS NULL AND mass = ? AND value = 1", array($userID, $eventID, $type));
        
        return ($record);
        
    }
    
    
    /**
     * Queue a message to be dispatched in the cron job
     * @param string $type 'email', 'sms', etc...
     * @param object $user
     * @param string $subject
     * @param string $content
     * @param string $htmlContent
     * @param int $historyID (default:null)
     */
    public function queue($type, $user, $subject, $content, $htmlContent, $historyID = null)
    {
        
        global $DB;
        
        $data = new \stdClass();
        $data->type = $type;
        $data->userid = $user->id;
        $data->subject = $subject;
        $data->content = $content;
        $data->htmlcontent = $htmlContent;
        $data->timequeued = time();
        $data->historyid = $historyID;
        
        return $DB->insert_record("lbp_alert_queue", $data);
        
    }
    
    /**
     * Process messages from the queue
     * @param int $limit The amount to limit it to
     */
    public static function processQueue($limit)
    {
        
        global $DB;
        
        $cnt = 0;
        
        // Find the first $limit records that were queued
        $records = $DB->get_records("lbp_alert_queue", null, "timequeued ASC", "*", 0, $limit);
        if ($records)
        {
            
            $EmailAlert = new \ELBP\EmailAlert();
            
            foreach($records as $record)
            {
                
                $user = $DB->get_record("user", array("id" => $record->userid));
                
                // If the user exists, send the message
                if ($user)
                {
                
                    switch($record->type)
                    {
                        case 'email':
                            if ($EmailAlert->send($user, $record->subject, $record->content, $record->htmlcontent, $record->historyid))
                            {
                                $cnt++;
                            }
                        break;
                    }
                
                }
                
                // Whether the user exists or not, now delete the record
                $DB->delete_records("lbp_alert_queue", array("id" => $record->id));
                
            }
            
        }
        
        return $cnt;
        
    }
    
    /**
     * Process automated events, such as target deadline checking, attendance checking, etc...
     */
    public static function processAuto()
    {
        
        global $DB;
        
        // Find all automated events in the system
        $cnt = 0;
        
        $events = $DB->get_records("lbp_alert_events", array("auto" => 1, "enabled" => 1));
        if ($events)
        {
         
            $ELBP = \ELBP\ELBP::instantiate();
            
            foreach($events as $event)
            {
                
                $plugin = $ELBP->getPluginByID($event->pluginid);

                if ($plugin)
                {

                    $method = "AutomatedEvent_".strtolower( str_replace(" ", "_", $event->name) );

                    // See if the plugin class has a method for this event and if so, call it
                    if (method_exists($plugin, $method))
                    {
                        $cnt += $plugin->$method($event);
                    }

                }
                
            }
           
            
        }
        
        return $cnt;
        
    }
    
    /**
     * Check the alert history table to see if we have a record for this recently, to avoid
     * sending the same alerts over and over again
     * @global type $DB
     * @param array $params
     */
    public static function checkHistory( array $params )
    {
        
        global $DB;
        
        $requiredParams = array("userID", "studentID", "eventID", "attributes");
        
        foreach($requiredParams as $req)
        {
            if (!array_key_exists($req, $params)){
                return false;
            }
        }
        
        $time = time() - self::$history_time;
                
        $cntAttributes = count($params['attributes']);
        $cntMatched = 0;
        
        $records = $DB->get_records("lbp_alert_history", array("userid" => $params['userID'], "studentid" => $params['studentID'], "eventid" => $params['eventID']));
        
        if ($records)
        {
            foreach($records as $record)
            {
                
                // If more than 1 week old, skip it
                if ($record->timesent <= $time){
                    continue;
                }
                
                // Check attributes to see if they match with the ones provided
                $cntMatched = 0;
                
                $attributes = $DB->get_records("lbp_alert_history_attributes", array("historyid" => $record->id));
                
                if ($attributes)
                {
                    foreach($attributes as $attribute)
                    {
                        if (isset($params['attributes'][$attribute->field]) && $params['attributes'][$attribute->field] == $attribute->value)
                        {
                            $cntMatched++;
                        }
                    }
                }
                
                // If all match, return true
                if ($cntMatched == $cntAttributes){
                    return true;
                }
                
            }
        }
        
        return false;
        
    }
    
    
    
    /**
     * Log record into the alert history table so we can check against it and make sure we're not sending the same email each night
     * @param type $params
     */
    public static function logHistory(array $params)
    {
        
        global $DB;
        
        $requiredParams = array("userID", "studentID", "eventID", "attributes");
        
        foreach($requiredParams as $req)
        {
            if (!array_key_exists($req, $params)) return false;
        }
        
        $data = new \stdClass();
        $data->userid = $params['userID'];
        $data->studentid = $params['studentID'];
        $data->eventid = $params['eventID'];
        $data->timesent = 0;
        $id = $DB->insert_record("lbp_alert_history", $data);
        if ($id !== false)
        {
        
            // Insert attributes
            if (is_array($params['attributes']))
            {
                foreach($params['attributes'] as $attribute => $value)
                {
                    $data = new \stdClass();
                    $data->historyid = $id;
                    $data->field = $attribute;
                    $data->value = $value;
                    $DB->insert_record("lbp_alert_history_attributes", $data);
                }
            }
        
        }
        
        return $id;
        
    }
    
    /**
     * Garbage collection
     * Remove any alerts from more than 2 days ago - Incase cron hasn't been running and then tries to send shit loads
     */
    public static function gc()
    {
        
        global $DB;
        
        $ago = strtotime("-2 days 00:00:00");
        
        $cnt = $DB->count_records_select("lbp_alert_queue", "timequeued < ?", array($ago));
        
        $DB->delete_records_select("lbp_alert_queue", "timequeued < ?", array($ago));
        
        // Also clear out old history logs
        $ago = time() - self::$history_time - 3600; # Extra hour just incase (no idea in case of what..but in case you know.. apocolypse maybe or rampant space catapillars come and fuck up the server)
        $logs = $DB->get_records_select("lbp_alert_history", "timesent < ? AND timesent > 0", array($ago));
        if ($logs)
        {
            foreach($logs as $log)
            {
                // Delete it
                $DB->delete_records("lbp_alert_history", array("id" => $log->id));
                // Delete attributes
                $DB->delete_records("lbp_alert_history_attributes", array("historyid" => $log->id));
            }
        }
        
        
        // Delete any alert_attributes that have an invalid useralertid
        $invalid = $DB->get_records_sql("SELECT aa.id
                                        FROM {lbp_alert_attributes} aa
                                        LEFT JOIN {lbp_alerts} a ON a.id = aa.useralertid
                                        WHERE a.id IS NULL");
        if ($invalid)
        {
            foreach($invalid as $inv)
            {
                $DB->delete_records("lbp_alert_attributes", array("id" => $inv->id));
            }
        }
        
        
        return $cnt;
        
    }


    abstract function run($event, $pluginID, $studentID, $content, $htmlContent);
    abstract protected function send($userID, $subject, $content, $htmlContent);
    
    /**
     * Check if a given user wants a given alert for a given type (e.g. course, student, mass
     * @global \ELBP\type $DB
     * @param type $userID
     * @param type $eventID
     * @param type $type
     * @param type $id
     * @return type
     */
    public static function userWantsEventAlerts($userID, $eventID, $type, $id)
    {
        
        global $DB;
        
        $params = array(
            'eventid' => $eventID,
            'userid' => $userID,
            'courseid' => null,
            'studentid' => null,
            'mass' => null
        );
        
        if ($type == 'course'){
            $params['courseid'] = $id;
        } elseif ($type == 'student'){
            $params['studentid'] = $id;
        } elseif ($type == 'mentees' || $type == 'addsup'){
            $params['mass'] = $type;
        }
        
        $record = $DB->get_record("lbp_alerts", $params, "*", IGNORE_MULTIPLE);
                
        return ($record && $record->value == 1) ? true : false;
        
    }
    
    /**
     * Update what alerts users want
     * @global \ELBP\type $DB
     * @param type $userID
     * @param type $eventID
     * @param type $courseID
     * @param type $groupID
     * @param type $studentID
     * @param type $val
     * @param type $attributes
     * @return type
     */
    public static function updateUserAlert($userID, $eventID, $type, $id, $val, $attributes)
    {
        
        global $DB;
                
        
        $obj = new \stdClass();
        $obj->userid = $userID;
        $obj->eventid = $eventID;
        $obj->courseid = null;
        $obj->studentid = null;
        $obj->mass = null;
        $obj->value = $val;
        
        if ($type == 'course'){
            $obj->courseid = $id;
        } elseif ($type == 'student'){
            $obj->studentid = $id;
        } elseif ($type == 'mentees' || $type == 'addsup'){
            $obj->mass = $type;
        }
        
        $id = $DB->insert_record("lbp_alerts", $obj);
                        
        
        // Add them again
        if (!is_null($attributes))
        {
            
            // Attributes should always be an array
            if ($attributes)
            {
                
                $n = count($attributes);
                
                foreach($attributes as $attribute => $value)
                {
                    
                    // This might be an array if there are multiple rows, e.g. att & pucn you can define various
                    // different attributes for one event.
                    if (is_array($value))
                    {
                        
                        $n--;

                        foreach($value as $key => $v)
                        {
                            
                            $ins = new \stdClass();
                            $ins->useralertid = $id;
                            $ins->field = $key;
                            $ins->value = $v;
                            $DB->insert_record("lbp_alert_attributes", $ins);                            
                            
                        }
                        
                        
                        // Create new record to use for next one
                        if ($n > 0)
                        {
                            $id = $DB->insert_record("lbp_alerts", $obj);
                        }
                        
                        
                    }
                    
                    // Else it's probably just a value
                    else
                    {
                        
                        $ins = new \stdClass();
                        $ins->useralertid = $id;
                        $ins->field = $attribute;
                        $ins->value = $value;
                        $DB->insert_record("lbp_alert_attributes", $ins);
                        
                    }
                    
                }
            }
            
        }
                
        return $id;
        
    }
    
    /**
     * Get a user's events
     * @global \ELBP\type $DB
     * @global type $USER
     * @param type $eventID
     * @param type $courseID
     * @param type $groupID
     * @param type $studentID
     * @return type
     */
    public static function getUserAlerts($eventID, $type, $id)
    {
        
        global $DB, $USER;
        
        $params = array(
            'eventid' => $eventID,
            'userid' => $USER->id,
            'courseid' => null,
            'studentid' => null,
            'mass' => null
        );
        
        if ($type == 'course'){
            $params['courseid'] = $id;
        } elseif ($type == 'student'){
            $params['studentid'] = $id;
        } elseif ($type == 'mentees' || $type == 'addsup'){
            $params['mass'] = $type;
        }
        
        return $DB->get_records("lbp_alerts", $params);
        
    }
    
    /**
     * Delete all of a user's alerts for a given thing (course, student, mass)
     * @global \ELBP\type $DB
     * @param type $userID
     * @param type $type
     * @param type $id
     * @return type
     */    
    public static function deleteUserAlerts($userID, $type, $id){
        
        global $DB;
        
        $params = array(
            'userid' => $userID,
            'courseid' => null,
            'studentid' => null,
            'mass' => null
        );
        
        if ($type == 'course'){
            $params['courseid'] = $id;
        } elseif ($type == 'student'){
            $params['studentid'] = $id;
        } elseif ($type == 'mentees' || $type == 'addsup'){
            $params['mass'] = $type;
        }
        
        return $DB->delete_records("lbp_alerts", $params);
        
    }
    
    
}