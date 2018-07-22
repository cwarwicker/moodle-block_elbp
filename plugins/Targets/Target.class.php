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

namespace ELBP\Plugins\Targets;


/**
 * 
 */
class Target extends \ELBP\BasePluginObject {
    
    private $id = false;
    private $name;
    private $progress;
    private $statusID;
    private $courseID;
    private $studentID;
    private $staffID;
    private $deadline;
    private $setTime;
    private $updatedTime;
    private $del;
    
    protected $attributes = array();
    private $staff;
    private $student;
    private $comments;
    private $errors = array();
    private $Targets = false;
    private $noAlert = false;
    
    /**
     * Construct Target object based on ID
     * @param mixed $id
     */
    public function __construct($param, $TargetsObj = false) {
        
        global $DB;
                        
        if ($TargetsObj !== false) $this->setTargetsObject($TargetsObj);
        
        if (is_numeric($param))
        {
                            
            $record = $DB->get_record("lbp_targets", array("id"=>$param));

            if ($record)
            {

                $this->id = $record->id;
                $this->name = $record->name;
                $this->progress = $record->progress;
                $this->statusID = $record->status;
                $this->courseID = $record->courseid;
                $this->studentID = $record->studentid;
                $this->staffID = $record->setbyuserid;
                $this->deadline = $record->deadline;
                $this->setTime = $record->settime;
                $this->updatedTime = $record->updatedtime;
                $this->del = $record->del;

                $this->staff = $DB->get_record("user", array("id"=>$this->staffID));
                $this->student = $DB->get_record("user", array("id" => $this->studentID));
                $this->comments = $this->loadComments();
                
                $this->loadAttributes();

            }

        }
        elseif (is_array($param))
        {
            $this->loadData($param);
        }
                
    }
    
    /**
     * Check if the Target is a valid one from the DB
     * @return type
     */
    public function isValid(){
        return ($this->id) ? true : false;
    }
    
    /**
     * Is the target late?
     * @return type
     */
    public function isLate(){
        return ($this->deadline < time()) ? true : false;
    }
    
    /**
     * Is the target achieved?
     * @global \ELBP\Plugins\Targets\type $DB
     * @return boolean
     */
    public function isAchieved(){
        
        global $DB;
        
        $status = $DB->get_record("lbp_target_status", array("id" => $this->statusID));
        if ($status && $status->achieved == 1) return true;
        else return false;
        
    }
    
    /**
     * Is the target ignored?
     * @global \ELBP\Plugins\Targets\type $DB
     * @return boolean
     */
    public function isIgnored(){
        
        global $DB;
        
        $status = $DB->get_record("lbp_target_status", array("id" => $this->statusID));
        if ($status && $status->ignored == 1) return true;
        else return false;
        
    }
    
    /**
     * Is the target deleted?
     * @return type
     */
    public function isDeleted(){
        return ($this->del == 1) ? true : false;
    }
    
    /**
     * Is the target linked to a tutorial?
     * @global \ELBP\Plugins\Targets\type $DB
     * @return type
     */
    public function isLinkedToTutorial(){
        
        global $DB;
        
        $check = $DB->get_record_select("lbp_tutorial_attributes", "field = ? AND value = ?", array("Targets", $this->id), "id", IGNORE_MULTIPLE);
        return ($check) ? true : false;
        
    }
    
    /**
     * Is the target linked to a tutorial?
     * @global \ELBP\Plugins\Targets\type $DB
     * @return type
     */
    public function isLinkedToAdditionalSupport(){
        
        global $DB;
        
        $check = $DB->get_record_select("lbp_add_sup_attributes", "field = ? AND value = ?", array("Targets", $this->id), "id", IGNORE_MULTIPLE);
        return ($check) ? true : false;
        
    }
    
    /**
     * Get the target id
     * @return type
     */
    public function getID(){
        return $this->id;
    }
    
    /**
     * Get the target name
     * @return type
     */
    public function getName(){
        return (!empty($this->name)) ? $this->name : '#'.get_string('unnamed', 'block_elbp').'#';
    }
    
    /**
     * Get the progress % of the target
     * @return type
     */
    public function getProgress(){
        return $this->progress;
    }
    
    /**
     * Set the progress %
     * @param type $val
     */
    public function setProgress($val){
        $this->progress = $val;
    }
    
    /**
     * Get the status id of the target
     * @return type
     */
    public function getStatus(){
        return $this->statusID;
    }
    
    /**
     * Get the name of the target's status
     * @global \ELBP\Plugins\Targets\type $DB
     * @return boolean
     */
    public function getStatusName(){
        
        if (isset($this->statusName)){
            return $this->statusName;
        }
        
        global $DB;
        $record = $DB->get_record("lbp_target_status", array("id" => $this->statusID));
        if ($record){
            $this->statusName = $record->status;
            return $this->statusName;
        }
        
        return false;
        
    }
    
    /**
     * Get the image icon of the target's status
     * @global \ELBP\Plugins\Targets\type $DB
     * @return boolean
     */
    public function getStatusImage(){
        
        global $DB;
        
        $record = $DB->get_record("lbp_target_status", array("id" => $this->statusID), "img");
        if ($record)
        {
            return $record->img;
        }
        
        return false;
        
    }
    
    /**
     * Get the int deleted value
     * @return int 1 or 0
     */
    public function getDeleted(){
        return $this->del;
    }
        
    /**
     * Get the unix timestamp of the deadline
     * @return type
     */
    public function getDeadline(){
        return $this->deadline;
    }
    
    /**
     * Get the student id
     * @return type
     */
    public function getStudentID(){
        return $this->studentID;
    }
    
    /**
     * Get the student record
     * @global \ELBP\Plugins\Targets\type $DB
     * @return type
     */
    public function getStudent(){
        global $DB;
        if (is_null($this->student)){
            $this->student = $DB->get_record("user", array("id" => $this->studentID));
        }
        return $this->student;
    }
    
    /**
     * Get the course record
     * @global \ELBP\Plugins\Targets\type $DB
     * @return type
     */
    public function getCourse(){
        global $DB;
        if (is_null($this->course)){
            $this->course = $DB->get_record("course", array("id" => $this->courseID));
        }
        return $this->course;
    }
    
    /**
     * Get the id of the user who set the target
     * @return type
     */
    public function getStaffID(){
        return $this->staffID;
    }
    
    /**
     * Get the unix timestamp of when the target was set
     * @return type
     */
    public function getSetTime(){
        return $this->setTime;
    }
    
    /**
     * Set the unix timestamp of when the target was last updated
     * @return type
     */
    public function getUpdatedTime(){
        return $this->updatedTime;
    }
    
    /**
     * Get any errors
     * @return type
     */
    public function getErrors(){
        return $this->errors;
    }
    
    /**
     * Set a property to say we don't want any alerts to run
     * @param type $bool
     */
    public function setNoAlert($bool){
        $this->noAlert = $bool;
    }
    
    /**
     * Set the target's status id
     * @param type $id
     */
    public function setStatusID($id){
        $this->statusID = $id;
    }
    
    
    /**
     * Get the record of the achieved status
     * @global \ELBP\Plugins\Targets\type $DB
     * @return type
     */
    public function findAchievedStatus(){
        global $DB;
        return $DB->get_record("lbp_target_status", array("achieved"=>1));
    }
    
    /**
     * Get a string to show either when the target is due or when it was compelted
     * @return type
     */
    public function getDueOrCompleted()
    {
        
        if (!$this->isAchieved())
        {
            return get_string('due', 'block_elbp') . ": " . $this->getDueDate();
        }
        else
        {
            return get_string('completed', 'block_elbp') . ": " . $this->getUpdatedDate();
        }       
        
    }
    
    /**
     * Get the string to display for whether or not a target is late or still has time left to be met
     * @return type
     */
    public function getLateOrRemaining(){
        
        // If achieved - nothing
        if ($this->isAchieved() || $this->isIgnored()) return;
        
        // Past the dseadline
        if ($this->deadline <= time()){

            $difference = time() - $this->deadline;

            $period = get_string('minutes', 'block_elbp');
            $difference /= 60;

            if ($difference >= 60){

                $period = get_string('hours', 'block_elbp');
                $difference /= 60;

                if ($difference >= 24){            
                    $difference /= 24;
                    $period = get_string('days', 'block_elbp');
                }

            }

            $difference = round($difference);
            return "<span class='elbp_bad'>({$difference} {$period} ". strtolower( get_string('late', 'block_elbp') ).")</span>";
            
        }
        
        // Deadline not reached yet
        else
        {
            
            $difference = $this->deadline - time();
            
            $period = get_string('minutes', 'block_elbp');
            $difference /= 60;
            
            if ($difference >= 60){
            
                $period = get_string('hours', 'block_elbp');
                $difference /= 60;

                if ($difference >= 24){            
                    $difference /= 24;
                    $period = get_string('days', 'block_elbp');
                }
            
            }
            
            $difference = round($difference);
            return "({$difference} {$period} ". strtolower( get_string('remaining', 'block_elbp') ).")";
            
        }
        
    }
    
    /**
     * Get the colour of the progress bar dependant on the % completed
     * @return string
     */
    public function getProgressColour(){
        
        if ($this->progress < 33) return "red";
        elseif ($this->progress < 66) return "orange";
        elseif ($this->progress < 100) return "blue";
        else return "green";
        
    }
    
    /**
     * Get the due date (deadline) of the target in a given format
     * @param string $format
     * @return type
     */
    public function getDueDate($format = 'M jS Y'){
        return date($format, $this->deadline);
    }
    
    /**
     * Get the date the target was set in format: M js Y
     * @return type
     */
    public function getSetDate($format = 'M jS Y'){
        return date($format, $this->setTime);
    }
    
    /**
     * Get the due date (deadline) of the target in a given format
     * @param string $format
     * @return type
     */
    public function getUpdatedDate($format = 'M jS Y'){
        return date($format, $this->updatedTime);
    }
    
    /**
     * Get the name of the member of staff who set the Target
     * @return type
     */
    public function getStaffName(){
        return fullname($this->staff);
    }
    
    /**
     * Set Targets object for use
     * @param type $obj
     */
    public function setTargetsObject($obj){
        $this->Targets = $obj;
    }
    
    /**
     * Get a particular comment
     * @param int $id
     */
    public function getComment($id){
        
        // Comment might be parent level or child level, so have to loop through and look for it
        
        if ($this->comments)
        {
            foreach($this->comments as $comment)
            {
                
                if ($comment->id == $id) return $comment;      
                
                if ($comment->childComments)
                {
                    $check = $this->getRecursiveComment($comment, $id);
                    if ($check) return $check;
                }
                
            }
        }
        
        return false;        
        
    }
    
    /**
     * Get recursive comments
     * @param type $comment
     * @param type $id
     * @return boolean
     */
    private function getRecursiveComment($comment, $id)
    {
                
        foreach($comment->childComments as $child)
        {
            
            if ($child->id == $id) return $child;
            
            if ($child->childComments)
            {
                $check = $this->getRecursiveComment($child, $id);
                if ($check) return $check;
            }
            
        }
        
        return false;
        
    }
    
    /**
     * Load comments into object property
     * This gets all the parent comments, then recursively loads all their children.
     */
    private function loadComments(){
        
        global $DB;
        
        $results = $DB->get_records_select("lbp_target_comments", "targetid = ? AND parent IS NULL AND del = 0", array($this->id), "time ASC");
        
        $return = array();
        
        // If there are some results, see if there are any child comments
        if ($results)
        {
            
            foreach($results as $result)
            {
                
                $user = $DB->get_record("user", array("id" => $result->userid));
                $result->firstName = ($user) ? $user->firstname : "?";
                $result->lastName = ($user) ? $user->lastname : "?";
                $result->width = 80;
                $result->css = elbp_get_comment_css($result->width);
                $result->childComments = $this->loadRecursiveComments($result->id);
                $return[$result->id] = $result;
                
            }
            
        }
        
        return $return;
        
        
    }
    
    /**
     * Recursively load all child comments of a given comment
     * @param int $parentID
     */
    private function loadRecursiveComments($parentID, $width = 80){
        
        global $DB;
        
        $results = $DB->get_records_select("lbp_target_comments", "targetid = ? AND parent = ? AND del = 0", array($this->id, $parentID), "time ASC");
        
        $return = array();
        
        if ($results)
        {
            
            $width--;
            if ($width < 50) $width = 50;
            
            foreach($results as $result)
            {
                
                $user = $DB->get_record("user", array("id" => $result->userid));
                $result->firstName = ($user) ? $user->firstname : "?";
                $result->lastName = ($user) ? $user->lastname : "?";
                $result->width = $width;
                $result->css = elbp_get_comment_css($width);
                $result->childComments = $this->loadRecursiveComments($result->id, $width);
                $return[] = $result;
                
            }
            
        }
        
        return $return;
        
        
    }
    
    /**
     * Count the number of comments on this Target
     * @return int
     */
    public function countComments(){
        return count($this->comments);
    }
    
    /**
     * Return a list of comments
     */
    public function getComments(){
        return $this->comments;       
        
    }
    
    /**
     * Add a comment to a target
     * @global type $DB
     * @global type $USER
     * @param string $comment
     * @param mixed $parentID If specified this is the parent id of the comment, otherwise null
     * @return bool
     */
    public function addComment($comment, $parentID = null){
        
        global $DB, $USER;
               
        $obj = new \stdClass();
        $obj->targetid = $this->id;
        $obj->userid = $USER->id;
        $obj->parent = $parentID;
        $obj->comments = $comment;
        $obj->time = time();
        $obj->del = 0;
        if($id = $DB->insert_record("lbp_target_comments", $obj)){
            
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TARGET, LOG_ACTION_ELBP_TARGET_ADDED_COMMENT, $this->getStudentID(), array(
                "commentID" => $id
            ));
            
            // Trigger alerts
            $alertContent = get_string('alerts:targetcommentadded', 'block_elbp') . "\n" . 
                                get_string('targetname', 'block_elbp') . ": " . $this->name . "\n" . 
                                get_string('user') . ": " . fullname($USER) . "\n" . 
                                get_string('comment', 'block_elbp') . ": " . $comment . "\n";
            
            // Student Comments - always
            elbp_event_trigger_student("Target Comment Added", $this->Targets->getID(), $this->studentID, $alertContent, nl2br($alertContent));
            
            // Staff comments - if we want them
            if (!$this->noAlert){
                
                elbp_event_trigger("Target Comment Added", $this->Targets->getID(), $this->studentID, $alertContent, nl2br($alertContent));
            
            }
            
            $user = $DB->get_record("user", array("id" => $obj->userid));
            $obj->id = $id;
            $obj->firstName = $user->firstname;
            $obj->lastName = $user->lastname;
            $obj->width = 80;
            
            // Count levels of threading
            $cntThreading = $this->countCommentThreading($id);
            $obj->width -= ( $cntThreading * 2 );
                        
            $obj->css = elbp_get_comment_css($obj->width);
            return $obj;
            
        } else {
            return false;
        }
        
    }
    
    /**
     * Delete a comment and all its child comments
     * @param type $commentID
     */
    public function deleteComment($commentID){
        
        global $DB;
        
        $comment = $this->getComment($commentID);
        if (!$comment) return false;
                
        $obj = new \stdClass();
        $obj->id = $commentID;
        $obj->del = 1;
        
        if ($DB->update_record("lbp_target_comments", $obj)){
            
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TARGET, LOG_ACTION_ELBP_TARGET_DELETED_COMMENT, $this->getStudentID(), array(
                "commentID" => $commentID
            ));
            
            // Recursive
            if (isset($comment->childComments) && $comment->childComments)
            {
                foreach($comment->childComments as $child)
                {
                    $this->deleteComment($child->id);
                }
            }
            
            return true;
            
        }
        
        return false;
                
    }
    
    /**
     * Count the levels of comment threading of a given comment so we can work out the width % to apply
     * @param type $commentID
     */
    private function countCommentThreading($commentID){
        
        global $DB;
        
        $cnt = 0;
        
        $check = $DB->get_record("lbp_target_comments", array("id" => $commentID));
        if (!is_null($check->parent)){
            
            $cnt++;
            $cnt += $this->countCommentThreading($check->parent);
            
        }
        
        return $cnt;
        
    }
    
    /**
     * Display comments for PDF printing
     * @param type $childComments
     */
    public function displayPdfComments($childComments = false, $childLevel = 0){
        
        global $ELBP, $USER;
        
        $output = "";
        
        $comments = (!$childComments) ? $this->getComments() : $childComments;
        
        if ($comments)
        {
            foreach($comments as $comment)
            {

                if ($childLevel > 0) $output .= str_repeat("&nbsp;&nbsp;&nbsp;", $childLevel);
                $output .= "<small><b>{$comment->firstName} {$comment->lastName}</b> - ".date('D jS M Y H:i', $comment->time)."</small><br>";
                if ($childLevel > 0) $output .= str_repeat("&nbsp;&nbsp;&nbsp;", $childLevel);
                $output .= "<small>".elbp_html($comment->comments, true) . "</small><br>";
                if ($comment->childComments)
                {
                    $output .= $this->displayPdfComments($comment->childComments, ++$childLevel);
                }

            }
        }
        else
        {
            $output .= "<br><small>".get_string('nocomments', 'block_elbp')."</small>";
        }
        
        return $output;
        
    }
    
    /**
     * Display the comments, recursively going through all levels of child comments
     * @param mixed $childComments False for the first level, then an object of child comments
     */
    public function displayComments($childComments = false){
        
        global $ELBP, $USER;
        
        $output = "";
        
        $comments = (!$childComments) ? $this->getComments() : $childComments;
        $access = $ELBP->getUserPermissions($this->getStudentID());
        
        foreach($comments as $comment)
        {
            
            $output .= "<div id='comment_{$comment->id}' class='elbp_comment_box' style='width:90%;" . ((isset($comment->css->bdr)) ? "border: 1px solid {$comment->css->bdr};" : "") . ((isset($comment->css->bg)) ? "background-color:{$comment->css->bg};" : "") . "'>";
            $output .= "<p id='elbp_comment_add_output_comment_{$comment->id}'></p>";
            $output .= elbp_html($comment->comments, true);
            $output .= "<br><br>";
            $output .= "<small><b>{$comment->firstName} {$comment->lastName}</b></small><br>";
            $output .= "<small>".date('D jS M Y H:i', $comment->time)."</small><br>";
            
            if (elbp_has_capability('block/elbp:add_target_comment', $access)){
                $output .= "<small><a href='#' onclick='$(\"#comment_reply_{$comment->id}\").slideToggle();return false;'>".get_string('reply', 'block_elbp')."</a></small><br>";
                $output .= "<div id='comment_reply_{$comment->id}' class='elbp_comment_textarea' style='display:none;'><textarea id='add_reply_{$comment->id}'></textarea><br><br><input class='elbp_big_button' type='button' value='".get_string('submit', 'block_elbp')."' onclick='ELBP.Targets.add_comment({$this->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;' /><br><br></div>";
            }
            
            // We either need the delete_any_target_comment capability if the comment is not ours, or if it is ours, we need the delete_my_target_comment
            if ( ($comment->userid <> $USER->id && elbp_has_capability('block/elbp:delete_any_target_comment', $access) ) || ( $comment->userid == $USER->id && elbp_has_capability('block/elbp:delete_my_target_comment', $access) ) ){
                $output .= "<small><a href='#' onclick='ELBP.Targets.delete_comment({$this->id}, {$comment->id});return false;'>".get_string('delete', 'block_elbp')."</a></small><br><br>";
            }
            
            if ($comment->childComments)
            {
                $output .= $this->displayComments($comment->childComments);
            }
            
            $output .= "</div>";
            
        }
        
        return $output;
        
    }
    
    
    
    /**
     * Given an array of data, build up the Target object based on that instead of a DB record
     * This is used for creating a new target or editing an existing one
     * @param type $data
     */
    public function loadData($data){
                
        $this->id = (isset($data['target_id'])) ? $data['target_id'] : -1; # Set to -1 if not set, as probably new target
        if (isset($data['target_name'])) $this->name = $data['target_name'];
        if (isset($data['target_deadline']) && !elbp_is_empty($data['target_deadline'])){
            $deadline =  \DateTime::createFromFormat('d-m-Y H:i:s', $data['target_deadline'] . ' 00:00:00');
            $this->deadline = $deadline->format("U"); # Unix
        }
        if (isset($data['target_progress'])) $this->progress = $data['target_progress'];
        if (isset($data['target_status'])) $this->statusID = $data['target_status'];
        if (isset($data['studentID'])) $this->studentID = $data['studentID'];
        if (isset($data['courseID'])) $this->courseID = $data['courseID'];
        if (isset($data['setTime'])) $this->setTime = $data['setTime'];
        if (isset($data['updatedTime'])) $this->updatedTime = $data['updatedTime'];
        if (isset($data['staffID'])) $this->staffID = $data['staffID'];
        
        // If progress set to 100% set it to status achieved       
        if ($this->progress == 100 && ($ach = $this->findAchievedStatus()) && $this->Targets && $this->Targets->getSetting('target_set_achieved_when_100_progress') == 1){
            $this->statusID = $ach->id;
        }
        
        // Other way round - if status set to achieved, progress set to 100%
        if (($ach = $this->findAchievedStatus()) && $this->statusID == $ach->id && $this->Targets && $this->Targets->getSetting('target_set_100_progress_when_achieved') == 1){
            $this->progress = 100;
        }        
        
        unset($data['target_id']);
        unset($data['target_name']);
        unset($data['target_deadline']);
        unset($data['target_progress']);
        unset($data['target_status']);
        unset($data['studentID']);
        unset($data['courseID']);
        unset($data['setTime']);
        unset($data['updatedTime']);
        unset($data['staffID']);
        
        // Attributes
        $TAR = new \ELBP\Plugins\Targets();
        $this->setSubmittedAttributes($data, $TAR);
                        
    }
    
    /**
     * Get the content to display the target
     * @global type $ELBP
     * @return type
     */
    public function display(){
        
        global $ELBP;
        
        $targets = $ELBP->getPlugin("Targets");
        
        $attributes = $targets->getAttributesForDisplay($this);
                
        if (!$attributes) return get_string('noattributesdefinedtarget', 'block_elbp');
                        
        $output = "";
        
        $output .= "<div class='elbp_target_main_elements'>";
        $mainAttributes = $targets->getAttributesForDisplayDisplayType("main", $attributes);
        if ($mainAttributes)
        {
            foreach($mainAttributes as $attribute)
            {
                $output .= "<h2>{$attribute->name}</h2>";
                $output .= "<div class='elbp_target_attribute_content'>";
                    $output .= $attribute->displayValue();
                $output .= "</div>";

            }
        }
        $output .= "</div>";
        
        
        $output .= "<div class='elbp_target_side_elements'><br>";
        $sideAttributes = $targets->getAttributesForDisplayDisplayType("side", $attributes);
        if ($sideAttributes)
        {
            foreach($sideAttributes as $attribute)
            {
                $output .= "<p>{$attribute->name}: ".$attribute->displayValue(). "</p>";
            }
        }
        $output .= "</div>";
        
        $output .= "<br style='clear:both;' />";
        
        
                
        echo $output;
        
    }
    
    
    
    /**
     * Generate simple HTML output to be printed
     */
    public function printOut()
    {
        
        global $CFG;
                
        ob_clean();
        
        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('target', 'block_elbp') . ' - ' . $this->getName();
        $logo = \ELBP\ELBP::getPrintLogo();
        $title = get_string('target', 'block_elbp');
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';

        $attributes = $this->Targets->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);
        
        $txt = "";
        $txt .= "<div class='c'><h3>{$this->getName()}</h3></div>";
        
        $txt .= "<table class='info'>";
            $txt .= "<tr><td>".get_string('dateset', 'block_elbp').": {$this->getSetDate()}</td><td>".get_string('setby', 'block_elbp').": {$this->getStaffName()}</td><td>".get_string('deadline', 'block_elbp').": {$this->getDueDate()}</td></tr>";
            $txt .= "<tr><td>".get_string('targetstatus', 'block_elbp').": {$this->getStatusName()}</td><td></td><td>".get_string('targetprogress', 'block_elbp').": {$this->getProgress()}%</td></tr>";
            
            // Side attributes
            $sideAttributes = $this->Targets->getAttributesForDisplayDisplayType("side", $attributes);
            if ($sideAttributes)
            {
                $n = 0;
                $num = 0;
                $cnt = count($sideAttributes);
                
                foreach($sideAttributes as $attribute)
                {
                    
                    $n++;
                    $num++;
                                        
                    if ($n == 1){
                        $txt .= "<tr num='{$n}'>";
                    }

                    if ($attribute->display == 'side')
                    {
                        $txt .= "<td>{$attribute->name}: ".$attribute->displayValue(true). "</td>";
                    }
                                        
                    if ($n == 2 || $num == $cnt){
                        $txt .= "</tr>";
                        $n = 0;
                    }

                }
            }
            
        $txt .= "</table>";
        
        $txt .= "<hr>";
        
        // Main attributes
        $mainAttributes = $this->Targets->getAttributesForDisplayDisplayType("main", $attributes);
        if ($mainAttributes)
        {

            foreach($mainAttributes as $attribute)
            {

                if ($attribute->display == 'main')
                {
                    $txt .= "<div class='attribute-main'><p class='b'>{$attribute->name}</p><p>".$attribute->displayValue(true)."</p></div>";
                }

            }
        }
        
        $txt .= "<hr>";
        
        // Comments
        $txt .= "<b>".get_string('targetcomments', 'block_elbp')."</b><br><br>";
        $txt .= $this->displayPdfComments();
        
        
        $TPL = new \ELBP\Template();
        $TPL->set("logo", $logo);
        $TPL->set("pageTitle", $pageTitle);
        $TPL->set("title", $title);
        $TPL->set("heading", $heading);
        $TPL->set("content", $txt);
        
        
        $TPL->load( $CFG->dirroot . '/blocks/elbp/tpl/print.html' );
        $TPL->display();
        exit;
        
        
        
    }
    
    
    
    /**
     * Set the value of a Target's attribute
     * @param type $field
     * @param type $val
     */
    public function setAttributeContent($field, $val)
    {
        global $DB;
        $record = $DB->get_record("lbp_target_attributes", array("targetid"=>$this->id, "field"=>$field));
        if ($record){
            $record->value = $val;
            $DB->update_record("lbp_target_attributes", $record);
        } else {
            $data = new \stdClass();
            $data->targetid = $this->id;
            $data->field = $field;
            $data->value = $val;
            $DB->insert_record("lbp_target_attributes", $data);
        }
    }
    
    /**
     * Load attributes on this target
     * @global \ELBP\Plugins\Targets\type $DB
     * @return type
     */
    public function loadAttributes(){
        
        global $DB;
                
        $check = $DB->get_records("lbp_target_attributes", array("targetid" => $this->id));
        
        $this->attributes = parent::_loadAttributes($check);
                
        return $this->attributes;
        
    }
    
        
    
    /**
     * Delete Target
     * @return boolean
     */
    public function delete()
    {
        
        global $DB;
                
        $data = new \stdClass();
        $data->id = $this->id;
        $data->del = 1;
                
        if (!$DB->update_record("lbp_targets", $data)){
            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }
        
        // Log Action
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TARGET, LOG_ACTION_ELBP_TARGET_DELETED_TARGET, $this->studentID, array(
            "targetID" => $this->id
        ));
        
        return true;
    }
    
    /**
     * Save the Target back into the database (could be new or could be edited)
     * @return boolean
     */
    public function save()
    {
        
        global $CFG, $USER, $DB;
        
        if (!$this->id) return false;
                
        if (elbp_is_empty($this->name)) $this->errors[] = get_string('targeterrors:name', 'block_elbp');
        if (!ctype_digit($this->deadline)) $this->errors[] = get_string('targeterrors:deadline', 'block_elbp');
        if (!ctype_digit($this->studentID)) $this->errors[] = get_string('targeterrors:studentid', 'block_elbp');
        if (!ctype_digit($this->statusID)) $this->errors[] = get_string('targeterrors:statusid', 'block_elbp');
        
        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->Targets->getElementsFromAttributeString( $this );
                        
        if ($allAttributes)
        {
                        
            foreach($allAttributes as $definedAttribute)
            {
                
                $value = (isset($this->attributes[$definedAttribute->name])) ? $this->attributes[$definedAttribute->name] : '';
                                
                if (!empty($definedAttribute->validation))
                {
                    foreach($definedAttribute->validation as $validation)
                    {
                        if (!$definedAttribute->validateResponse($value, $validation))
                        {
                            $langStr = str_replace("_", "", strtolower($validation));
                            $this->errors[] = get_string('validation:'.$langStr, 'block_elbp') . ": " . $definedAttribute->name;
                        }
                    }
                }
                
            }
        }
                
        if (!empty($this->errors)) return false;
        
        // Create a tmp object of the object's current DB state before we change anything
        $tmpTarget = new Target($this->id);
        
                        
        // Save it
        
        // New, so insert it
        if ($this->id == -1)
        {
                        
            $data = new \stdClass();
            $data->studentid = $this->studentID;
            $data->courseid = $this->courseID;
            $data->name = $this->name;
            $data->status = $this->statusID;
            $data->deadline = $this->deadline;
            $data->progress = $this->progress;
            $data->setbyuserid = $USER->id;
            $data->settime = time();
            $data->updatedtime = time();
            
            
            // Insert the target 
            if (!$id = $DB->insert_record("lbp_targets", $data)){
                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TARGET, LOG_ACTION_ELBP_TARGET_CREATED_TARGET, $this->studentID, array(
                "targetID" => $id,
                "name" => $this->name,
                "statusID" => $this->statusID,
                "deadline" => $this->deadline,
                "progress" => $this->progress,
                "attributes" => http_build_query($this->attributes)
            ));
            
            
            
            // Set the properties that won't have been sent in params, like ID and time set
            $this->id = $id;
            $this->setTime = $data->settime;
            $this->staffID = $data->setbyuserid;
            
            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->Targets)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            // Add to calendar
            if ($this->Targets->getSetting('integrate_calendar') == 1){
                
                require_once $CFG->dirroot.'/calendar/lib.php';
                $access = $this->Targets->getAccess();
                $context = reset($access['context']);
                                
                $eventData = new \stdClass();
                $eventData->action = 'new';
                $eventData->course = 0;
                $eventData->courseid = 0;
                $eventData->timeduration = 0;
                $eventData->eventrepeats = 0;
                $eventData->eventtype = 'elbp';
                $eventData->userid = $this->studentID;
                $eventData->name = $this->name;
                if ($this->Targets->getSetting('external_target_name_hover_attribute')){
                    $eventData->description = $this->getAttribute($this->Targets->getSetting('external_target_name_hover_attribute'));
                }
                $eventData->timestart = $this->deadline;
                $eventData->context = $context;
                                
                if (calendar_add_event_allowed($eventData)){
                    $event = new \calendar_event($eventData);
                    $event->update($eventData);
                    // Append to attributes
                    $this->attributes['calendar_event_id'] = $event->properties()->id;
                }
                               
                                
            }
            
            
            
            // Now using that target ID, insert it's attributes
            if ($this->attributes)
            {
                
                foreach($this->attributes as $field => $value)
                {
                    
                    // If array, do each of them
                    if (is_array($value))
                    {
                        
                        foreach($value as $val)
                        {
                                                        
                            if ($val == '') $val = null;
                            
                            $ins = new \stdClass();
                            $ins->targetid = $id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_target_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                            
                        }
                        
                    }
                    else
                    {
                        
                        if ($value == '') $value = null;
                        
                        // Insert
                        $ins = new \stdClass();
                        $ins->targetid = $id;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_target_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                        
                    }
                                        
                }
                
            }
            
            // Alerts
            $alertContent = get_string('alerts:targetadded', 'block_elbp') . 
                                $this->getInfoForEventTrigger(false);
                        
            // Trigger student alert - always
            elbp_event_trigger_student("Target Added", $this->Targets->getID(), $this->studentID, $alertContent, nl2br($alertContent));
           
            // Trigger staff alerts - if we want them
            if (!$this->noAlert){
                elbp_event_trigger("Target Added", $this->Targets->getID(), $this->studentID, $alertContent, nl2br($alertContent));
            }
                        
            
        }
        else
        {
                        
            // Update target
            $data = new \stdClass();
            $data->id = $this->id;
            $data->name = $this->name;
            $data->deadline = $this->deadline;
            $data->progress = $this->progress;
            $data->status = $this->statusID;
            $data->updatedtime = time();
            
            
            if (!$DB->update_record("lbp_targets", $data)){
                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TARGET, LOG_ACTION_ELBP_TARGET_UPDATED_TARGET, $this->studentID, array(
                "targetID" => $this->id,
                "name" => $this->name,
                "statusID" => $this->statusID,
                "deadline" => $this->deadline,
                "progress" => $this->progress,
                "attributes" => http_build_query($this->attributes)
            ));
            
            
            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->Targets)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            
            // Calendar
            if ($this->Targets->getSetting('integrate_calendar') == 1){
                
                require_once $CFG->dirroot.'/calendar/lib.php';
                
                $check = $DB->get_record("lbp_target_attributes", array("targetid" => $this->id, "field" => "calendar_event_id"));
                if ($check)
                {

                    $access = $this->Targets->getAccess();
                    $context = reset($access['context']);
                    
                    $event = new \stdClass();
                    $event->id = $check->value;
                    
                    $event = new \calendar_event($event);
                    $event->name = $this->name;
                    if ($this->Targets->getSetting('external_target_name_hover_attribute')){
                        $event->description = $this->getAttribute($this->Targets->getSetting('external_target_name_hover_attribute'));
                    }
                    $event->userid = $this->studentID;
                    $event->eventtype = 'elbp';
                    $event->timestart = $this->deadline;
                    $event->context = $context;
                    if (calendar_add_event_allowed($event)){
                        $event->update($event);
                    }
                    
                }
                else
                {

                    $access = $this->Targets->getAccess();
                    $context = reset($access['context']);
                    
                    $eventData = new \stdClass();
                    $eventData->action = 'new';
                    $eventData->course = 0;
                    $eventData->courseid = 0;
                    $eventData->timeduration = 0;
                    $eventData->eventrepeats = 0;
                    $eventData->eventtype = 'elbp';
                    $eventData->userid = $this->studentID;
                    $eventData->name = $this->name;
                    if ($this->Targets->getSetting('external_target_name_hover_attribute')){
                        $eventData->description = $this->getAttribute($this->Targets->getSetting('external_target_name_hover_attribute'));
                    }
                    $eventData->timestart = $this->deadline;
                    $eventData->context = $context;

                    if (calendar_add_event_allowed($eventData)){
                        $event = new \calendar_event($eventData);
                        $event->update($event);
                        // Append to attributes
                        $this->attributes['calendar_event_id'] = $event->id;

                    }

                }

            }
            
            
            
            
            // Update attributes for target
            if ($this->attributes)
            {
                                
                foreach($this->attributes as $field => $value)
                {
                    
                    
                    // If array, do each of them
                    if (is_array($value))
                    {
                        
                        // If it's an array then we're going to have to delete all records of this att first
                        // Otherwise, say we saved 4 values: one, two, three, four oringally, then we update to: one, four
                        // The two & thre would still be in there
                        $DB->delete_records("lbp_target_attributes", array("targetid" => $this->id, "field" => $field));
                        
                        foreach($value as $val)
                        {
                         
                            if ($val == '') $val = null;
                            
                            $ins = new \stdClass();
                            $ins->targetid = $this->id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_target_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                                                        
                        }
                                                
                    }
                    else
                    {
                        
                        if ($value == '') $value = null;
                        
                        // Get att from DB
                        $attribute = $DB->get_record_select("lbp_target_attributes", "targetid = ? AND field = ?", array($this->id, $field));
                        
                        // if it exists, update it
                        if ($attribute)
                        {
                            $ins = new \stdClass();
                            $ins->id = $attribute->id;
                            $ins->value = $value;
                            if (!$DB->update_record("lbp_target_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                        }
                        
                        // Else, insert it
                        else
                        {
                            $ins = new \stdClass();
                            $ins->targetid = $this->id;
                            $ins->field = $field;
                            $ins->value = $value;
                            if (!$DB->insert_record("lbp_target_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                        }
                        
                    }
                    
                }
                
                // Now loop through the defined attributes in the config settings
                // If any of those cannot be found in the attributes supplied, e.g. may be a checkbox with nothing selected
                // now, having had something selected before (it won't send a value), and delete them
                if ($allAttributes)
                {

                    foreach($allAttributes as $allAttribute)
                    {

                        if (!isset($this->attributes[$allAttribute->name]))
                        {
                            $DB->delete_records("lbp_target_attributes", array("targetid" => $this->id, "field" => $allAttribute->name));
                        }

                    }
                }
                
                
            }
            
            
            // Trigger alerts
                $alertContent = get_string('alerts:targetupdated', 'block_elbp') . 
                                $this->getInfoForEventTrigger(false);
                $htmlContent = get_string('alerts:targetupdated', 'block_elbp') . 
                               $this->getInfoForEventTrigger(true, $tmpTarget);
            
            // Student alerts - always
            elbp_event_trigger_student("Target Updated", $this->Targets->getID(), $this->studentID, $alertContent, $htmlContent);
                
                
            // Staff alerts - if we want them
            if (!$this->noAlert){
                
                elbp_event_trigger("Target Updated", $this->Targets->getID(), $this->studentID, $alertContent, $htmlContent);
            }
                        
        }
        
        return true;
        
        
    }
    
    /**
     * Get content for event triggered alert emails
     * @global type $CFG
     * @global \ELBP\Plugins\Targets\type $USER
     * @param type $useHtml
     * @param type $tmpTarget
     * @return string
     */
    public function getInfoForEventTrigger($useHtml = false, $tmpTarget = false)
    {
        global $CFG, $USER;
            
        $output = "";
        
        // If using HTML
        if ($useHtml)
        {
            $output .= "<br>----------<br>";
            $output .= get_string('student', 'block_elbp') . ": " . fullname($this->getStudent()) . " ({$this->getStudent()->username})<br>";
            if ($this->courseID > 1){
                $output .= get_string('course') . ": " . $this->getCourse()->fullname . "<br>";
            }
            
            // Old target name
            $output .= "<del style='color:red;'>" . get_string('targetname', 'block_elbp') . ": " . $tmpTarget->getName() . "</del><br>";
            
            // New target name
            $output .= "<ins style='color:blue;'>" . get_string('targetname', 'block_elbp') . ": " . $this->getName() . "</ins><br>";
            
            // Old status
            $output .= "<del style='color:red;'>" . get_string('targetstatus', 'block_elbp') . ": " . $tmpTarget->getStatusName() . "</del><br>";
            
            // New status
            $output .= "<ins style='color:blue;'>" . get_string('targetstatus', 'block_elbp') . ": " . $this->getStatusName() . "</ins><br>";
            
            // Old deadline
            $output .= "<del style='color:red;'>" . get_string('deadline', 'block_elbp') . ": " . $tmpTarget->getDueDate() . "</del><br>";
            
            // New deadline
            $output .= "<ins style='color:blue;'>" . get_string('deadline', 'block_elbp') . ": " . $this->getDueDate() . "</ins><br>";

            // Old progress
            $output .= "<del style='color:red;'>" .  get_string('targetprogress', 'block_elbp') . ": " . $tmpTarget->getProgress() . "</del><br>";
            
            // New progress
            $output .= "<ins style='color:blue;'>" . get_string('targetprogress', 'block_elbp') . ": " . $this->getProgress() . "</ins><br>";
            

            // Attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {
                    if (is_array($value)) $value = implode(",", $value);
                    $value = preg_replace("/\n/", " ", $value);
                    
                    // Old attribute value
                    $output .= "<del style='color:red;'>{$field}: " . $tmpTarget->getAttribute($field) . "</del><br>";
                    
                    // New attrribute value
                    $output .= "<ins style='color:blue;'>{$field}: " . $value . "</ins><br>";
                    
                }

            }

            $output .= "----------<br>";
            $output .= get_string('updatedby', 'block_elbp') . ": " . fullname($USER) . "<br>";
            $output .= get_string('link', 'block_elbp') . ": " . "<a href='{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->studentID}'>{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->studentID}</a><br>";

        }
        
        // Otherwise
        else
        {
            $output .= "\n----------\n";
            $output .= get_string('student', 'block_elbp') . ": " . fullname($this->getStudent()) . " ({$this->getStudent()->username})\n";
            if ($this->courseID > 1){
                $output .= get_string('course') . ": " . $this->getCourse()->fullname . "\n";
            }
            $output .= get_string('targetname', 'block_elbp') . ": " . $this->name . "\n";
            $output .= get_string('targetstatus', 'block_elbp') . ": " . $this->getStatusName() . "\n";
            $output .= get_string('deadline', 'block_elbp') . ": " . $this->getDueDate() . "\n";
            $output .= get_string('targetprogress', 'block_elbp') . ": " . $this->getProgress() . "\n";

            // Attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {
                    if (is_array($value)) $value = implode(",", $value);
                    $value = preg_replace("/\n/", " ", $value);
                    $output .= $field . ": " . $value . "\n";
                }

            }

            $output .= "----------\n";
            $output .= get_string('updatedby', 'block_elbp') . ": " . fullname($USER) . "\n";
            $output .= get_string('link', 'block_elbp') . ": " . "{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->studentID}\n";

        }
                
        
        return $output;
        
    }
    
    /**
     * Get either current data if editing a target, or blank required data for new target
     * @global \ELBP\Plugins\Targets\type $ELBP
     * @param type $id
     * @return string|boolean
     */
    public static function getDataForNewTargetForm($id = false, $targets = false, $targetsets = false)
    {
        
        global $ELBP;
        
        if (!$targets){
            $targets = $ELBP->getPlugin("Targets");
        }
        
        $attributes = $targets->getAttributesForDisplay();
                
        $data = array();
        
        // Build data to put into form (will be blank if new form instead of editing existing one)
        if ($id)
        {
            if($targetsets)
            {
                $target = new TargetSets($id);
            }
            else
            {
                $target = new Target($id);
            }
            
            if (!$target->isValid()) return false;
            
            $data['id'] = $target->getID();
            $data['name'] = $target->getName();
            $data['deadline'] = $target->getDueDate('d-m-Y');
            $data['progress'] = $target->getProgress();
            $data['status'] = $target->getStatus();
            $data['del'] = $target->getDeleted();
            $data['setTime'] = $target->getSetTime();
            $data['setByID'] = $target->getStaffID();
                        
            // Since it's a real Session, get all the actual attributes stored for it, not just the ones we think it should have from the config
            $definedAttributes = $target->getAttributes();
                        
            $processedAttributes = array();
                        
            // Loop through all possible attributes defined in the system
            $data['atts'] = array();
                        
            // Loop through default attributes
            if ($attributes)
            {
                foreach($attributes as $attribute)
                {
                    
                    $attribute->loadObject($targets);
                    
                    // If the attribute name exists in the defined attributes (ones linked to this target)
                    // Simply add it to the data array
                    if (array_key_exists($attribute->name, $definedAttributes))
                    {
                        $attribute->setValue($definedAttributes[$attribute->name]);
                        $data['atts'][] = $attribute;
                        $processedAttributes[] = $attribute->name;
                    }
                    else
                    {
                        
                        // Otherwise
                        // Loop through defined attributes (linked to target) and see if there are any LIKE
                        // this attribute, e.g. for Matrices they will be Name_Row => Col rather than Name => Col
                        $valueArray = array();
                        $like = false;
                        
                        if ($definedAttributes)
                        {
                            foreach($definedAttributes as $key => $d)
                            {
                                $explode = explode($attribute->name . "_", $key);
                                if ($explode && count($explode) > 1)
                                {
                                    $valueArray[$explode[1]] = $d;
                                    $like = true;
                                }
                            }

                            if (count($valueArray) == 1){
                                $valueArray = reset($valueArray);
                            }
                        }
                        
                        // If we found some, add them
                        if ($like)
                        {
                            $attribute->setValue($valueArray);
                            $data['atts'][] = $attribute;
                            $processedAttributes[] = $attribute->name;
                        }
                        else
                        {
                            // Otherwise add them without a value
                            $data['atts'][] = $attribute;
                            $processedAttributes[] = $attribute->name;
                        }
                        
                    }
                                        
                }
            }
                        
            // Now loop through the actual attributes in the DB and get any that aren't defined in config attributes
            // These will be hooked attributes
            if ($definedAttributes)
            {
                foreach($definedAttributes as $definedAttribute => $value)
                {
                    if (!in_array($definedAttribute, $processedAttributes))
                    {
                        $data['hookAtts'][$definedAttribute] = $value;
                    }
                }
            }
                        
        }
        else
        {
            
            $data['id'] = -1;
            $data['name'] = '';
            $data['deadline'] = '';
            $data['progress'] = 0;
            $data['status'] = 0;
            $data['del'] = 0;
            $data['setTime'] = 0;
            $data['setByID'] = 0;
            
            if ($attributes){
                foreach($attributes as $attribute){
                    $attribute->loadObject($targets);
                }
            }
            
            $data['atts'] = $attributes;
            
        }

        return $data;
        
    }
    
    
}