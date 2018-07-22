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

namespace ELBP\Plugins\Attachments;

/**
 * 
 */
class Attachment {
    
    private $id = false;
    private $title;
    private $studentid;
    private $filename;
    private $dateuploaded;
    private $uploadedby;
    private $del;
    
    private $comments;
    
    public function __construct($id){
        
        global $DB;
        
        // Get the attachment record from the DB
        $check = $DB->get_record("lbp_attachments", array("id" => $id));
        
        if ($check)
        {
            foreach($check as $field => $value)
            {
                $this->$field = $value;
            }
            $this->loadComments();
        }
        
    }
    
    /**
     * Is the attachment valid?
     * @return type
     */
    public function isValid(){
        return ($this->id) ? true: false;
    }
    
    /**
     * Does the actual file exist?
     * @global type $CFG
     * @return type
     */
    public function exists(){
        global $CFG;
        $file = "{$CFG->dataroot}/ELBP/Attachments/{$this->studentid}/{$this->filename}"; 
        return (file_exists($file));
    }
    
    /**
     * Get the attachment id
     * @return type
     */
    public function getID(){
        return $this->id;
    }
    
    /**
     * Get the attachment title
     * @return type
     */
    public function getTitle(){
        return $this->title;
    }
    
    /**
     * Get the student id loaded into the plugin
     * @return type
     */
    public function getStudentID(){
        return $this->studentid;
    }
    
    /**
     * Get the file name of the attachment
     * @return type
     */
    public function getFileName(){
        return $this->filename;
    }
    
    /**
     * Get the date of the upload in the specified format
     * @param type $format
     * @return type
     */
    public function getDate($format = 'M jS Y'){
        return date($format, $this->dateuploaded);
    }
    
    /**
     * Get the timestamp of the time the attachment was uploaded
     * @return type
     */
    public function getUploadedUnix(){
        return $this->dateuploaded;
    }
    
    /**
     * Get the id of the user who uploaded the attachment
     * @return type
     */
    public function getUploadedByID(){
        return $this->uploadedby;
    }
    
    /**
     * Get the user who uploaded the attachment
     * @global \ELBP\Plugins\Attachments\type $DB
     * @return type
     */
    public function getUploadedBy(){
        global $DB;
        return $DB->get_record("user", array("id" => $this->uploadedby));
    }
    
    /**
     * Is it deleted?
     * @return type
     */
    public function isDeleted(){
        return ($this->del == 1) ? true : false;
    }
    
    /**
     * Count the attachments on this attachment
     * @global \ELBP\Plugins\Attachments\type $DB
     * @return type
     */
    public function countComments(){
        global $DB;
        return $DB->count_records("lbp_attachment_comments", array("attachmentid" => $this->id, "del" => 0));
    }
    
    /**
     * Delete the attachment from the DB and delete the actual file
     * @global \ELBP\Plugins\Attachments\type $CFG
     * @global \ELBP\Plugins\Attachments\type $DB
     * @return boolean
     */
    public function delete(){
        
        global $CFG, $DB;
        
        if ($this->isDeleted()) return true;
        
        // We won't be able to recover the attachment after its deleted, since the actual file will be deleted, but
        // just for reference so we can see what files were attached if we ever need to know, we'll keep the db record
        $obj = new \stdClass();
        $obj->id = $this->id;
        $obj->del = 1;
                
        if ($DB->update_record("lbp_attachments", $obj))
        {
            
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ATTACHMENT, LOG_ACTION_ELBP_ATTACHMENT_DELETED_ATTACHMENT, $this->getStudentID(), array(
                "attachmentID" => $this->id
            ));
            
            // Delete actual file
            if ($this->exists()){
                $file = "{$CFG->dataroot}/ELBP/Attachments/{$this->studentid}/{$this->filename}"; 
                return unlink($file);
            }
            
            return true;
            
        }
        
        return false;
        
    }
    
    /**
     * Get any comments on this attachment
     * @return type
     */
    public function getComments(){
        return $this->comments;
    }
    
    /**
     * Load any comments on this attachment
     * @global \ELBP\Plugins\Attachments\type $DB
     * @return type
     */
    public function loadComments(){
        
        global $DB;
        
        $results = $DB->get_records("lbp_attachment_comments", array("attachmentid" => $this->id, "del" => 0, "parent" => null), "id DESC");
        
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
        
        $this->comments = $return;
        return $this->comments;
        
    }
    
    
    /**
     * Recursively load all child comments of a given comment
     * @param int $parentID
     */
    private function loadRecursiveComments($parentID, $width = 80){
        
        global $DB;
        
        $results = $DB->get_records("lbp_attachment_comments", array("attachmentid" => $this->id, "parent" => $parentID, "del" => 0), "id DESC");
        
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
            
            if (elbp_has_capability('block/elbp:add_attachment_comment', $access)){
                $output .= "<small><a href='#' onclick='$(\"#comment_reply_{$comment->id}\").slideToggle();return false;'>".get_string('reply', 'block_elbp')."</a></small><br>";
                $output .= "<div id='comment_reply_{$comment->id}' class='elbp_comment_textarea' style='display:none;'><textarea id='add_reply_{$comment->id}'></textarea><br><br><input class='elbp_big_button' type='button' value='".get_string('submit', 'block_elbp')."' onclick='ELBP.Attachments.add_comment({$this->id}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;' /><br><br></div>";
            }
            
            // We either need the delete_any_target_comment capability if the comment is not ours, or if it is ours, we need the delete_my_target_comment
            if ( ($comment->userid <> $USER->id && elbp_has_capability('block/elbp:delete_any_attachment_comment', $access) ) || ( $comment->userid == $USER->id && elbp_has_capability('block/elbp:delete_my_attachment_comment', $access) ) ){
                $output .= "<small><a href='#' onclick='ELBP.Attachments.delete_comment({$comment->id});return false;'>".get_string('delete', 'block_elbp')."</a></small><br><br>";
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
     * Add a comment to an attachment
     * @global type $DB
     * @global type $USER
     * @param string $comment
     * @param mixed $parentID If specified this is the parent id of the comment, otherwise null
     * @return bool
     */
    public function addComment($comment, $parentID = null){
        
        global $DB, $USER;
        
        $obj = new \stdClass();
        $obj->attachmentid = $this->id;
        $obj->userid = $USER->id;
        $obj->parent = $parentID;
        $obj->comments = $comment;
        $obj->time = time();
        $obj->del = 0;
        if($id = $DB->insert_record("lbp_attachment_comments", $obj)){
            
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ATTACHMENT, LOG_ACTION_ELBP_ATTACHMENT_ADDED_COMMENT, $this->getStudentID(), array(
                "commentID" => $id
            ));
            
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
     * Count the levels of comment threading of a given comment so we can work out the width % to apply
     * @param type $commentID
     */
    private function countCommentThreading($commentID){
        
        global $DB;
        
        $cnt = 0;
        
        $check = $DB->get_record("lbp_attachment_comments", array("id" => $commentID));
        if (!is_null($check->parent)){
            
            $cnt++;
            $cnt += $this->countCommentThreading($check->parent);
            
        }
        
        return $cnt;
        
    }
    
    /**
     * Delete a given comment from the attachment
     * @global \ELBP\Plugins\Attachments\type $DB
     * @param type $commentID
     * @return int|boolean
     */
    public function deleteComment($commentID){
        
        global $DB;
        
        $comment = $this->getComment($commentID);
        if (!$comment) return false;
                
        $numDel = 0;
        
        $obj = new \stdClass();
        $obj->id = $commentID;
        $obj->del = 1;
        if ($DB->update_record("lbp_attachment_comments", $obj)){
            
            $numDel++;
            
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ATTACHMENT, LOG_ACTION_ELBP_ATTACHMENT_DELETED_COMMENT, $this->getStudentID(), array(
                "commentID" => $commentID
            ));
                        
            // Recursive
            if (isset($comment->childComments) && $comment->childComments)
            {
                foreach($comment->childComments as $child)
                {
                    $this->deleteComment($child->id);
                    $numDel++;
                }
            }
            
            return $numDel;
            
        }
        
        return false;
        
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
     * Get a comment from any level
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
    
}