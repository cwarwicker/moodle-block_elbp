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

namespace ELBP\Plugins;

require_once 'Attachment.class.php';

/**
 *
 */
class Attachments extends \ELBP\Plugins\Plugin {

    protected $tables = array(
        'lbp_attachments',
        'lbp_attachment_comments'
    );

    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {

        $this->requiredExtensions = array(
            'core' => array('fileinfo'),
            'optional' => array()
        );

        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Attachments",
                "path" => null,
                "version" => \ELBP\ELBP::getBlockVersionStatic()
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
        }

    }

    /**
     * Get the max file size allowed by server
     */
    public function getMaxFileSize(){
        return ini_get('upload_max_filesize');
    }

    /**
     * Get the user's attachments
     * @global type $DB
     * @param int $limit Limit to this number
     * @return \ELBP\Plugins\Attachments\Attachment
     */
    public function getUserAttachments($limit = 0){

        global $DB;

        $return = array();

        // Academic Year
        $academicYearUnix = $this->getAcademicYearUnix();

        $results = $DB->get_records_sql("SELECT *
                                         FROM {lbp_attachments}
                                         WHERE del = 0 AND studentid = ?
                                         ORDER BY id DESC", array($this->student->id), 0, $limit);

        if ($results)
        {
            foreach($results as $result)
            {
                $att = new \ELBP\Plugins\Attachments\Attachment($result->id);

                // Academic year
                if ($academicYearUnix && $att->getUploadedUnix() < $academicYearUnix){
                    continue;
                }

                if ($att->isValid()){
                    $return[] = $att;
                }
            }
        }

        return $return;

    }

    /**
     * Get an array of mime types that we are allowed to upload
     * @return type
     */
    public function getAllowedMimeTypes(){

        return explode(";", $this->getSetting('allowed_mime_types'));

    }

    /**
     * Get the mime types which have been typed in, not in the pre-populated list
     */
    public function getIndividuallyDefinedMimeTypes(){

        $mimes = $this->getAllowedMimeTypes();
        $default = get_common_mime_types();

        $defaultMimes = array();

        // Turn default multidimensional array into just one single array
        foreach($default as $def)
        {
            foreach($def as $ext => $mime)
            {
                if (is_array($mime)){
                    foreach($mime as $m)
                    {
                        $defaultMimes[] = $m;
                    }
                } else {
                    $defaultMimes[] = $mime;
                }
            }
        }

        $return = array();

        if ($mimes)
        {
            foreach($mimes as $mime)
            {
                // Check if this mime type is in our prepopulated array. If not, it must be individually defined
                if (!in_array($mime, $defaultMimes))
                {
                    $return[] = $mime;
                }
            }
        }

        return $return;

    }

    /**
     * Insert attachment to the DB
     * @global \ELBP\Plugins\type $DB
     * @global type $USER
     * @param type $title
     * @param type $filename
     * @return boolean
     */
    public function insertAttachment($title, $filename){

        global $DB, $USER;

        if (!$this->student) return false;

        $obj = new \stdClass();
        $obj->studentid = $this->student->id;
        $obj->title = $title;
        $obj->filename = $filename;
        $obj->dateuploaded = time();
        $obj->uploadedby = $USER->id;
        $obj->del = 0;

        if( ($id = $DB->insert_record("lbp_attachments", $obj)) !== false ){

            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ATTACHMENT, LOG_ACTION_ELBP_ATTACHMENT_ADDED_ATTACHMENT, $this->student->id, array(
                "attachmentID" => $id
            ));

            return $id;

        }

        return false;

    }

    /**
     * Save the settings just sent in the plugin configuration form
     * @param type $settings
     */
    public function saveConfig($settings)
    {

        // Since we only have one row in lbp_settings for each setting, we'll have to implode the mime types and explode them again in php later on
        $mimes = implode(";", array_filter($settings['allowed_mime_types']));
        $settings['allowed_mime_types'] = $mimes;

        parent::saveConfig($settings);
        return true;

    }

     /**
     * Install the plugin
     */
    public function install()
    {

        global $DB;

        $return = true;
        $pluginID = $this->createPlugin();
        $return = $return && $pluginID;

        // This is a core ELBP plugin, so the extra tables it requires are handled by the core ELBP install.xml

        // Default settings
        $settings = array();
        $settings['allowed_mime_types'] = 'application/msword;application/excel;application/vnd.ms-excel;application/mspowerpoint;application/powerpoint;application/vnd.ms-powerpoint;application/x-mspowerpoint;application/vnd.openxmlformats-officedocument.wordprocessingml.document;application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;application/vnd.openxmlformats-officedocument.presentationml.presentation;text/plain;text/richtext;application/rtf;application/x-rtf;application/pdf;text/rtf';

        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }

        return $return;

    }

    /**
     * Uninstall the plugin and truncate data from its tables
     * @global \ELBP\Plugins\type $DB
     */
    public function uninstall() {

        global $DB;

        if ($this->tables){
            foreach($this->tables as $table){
                $DB->execute("TRUNCATE {{$table}}");
            }
        }

        parent::uninstall();

    }

    /**
     * Upgrade the plugin from an older version to newer
     */
    public function upgrade(){

    }


    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){

        $TPL = new \ELBP\Template();

        $TPL->set("obj", $this);
        $TPL->set("attachments", $this->getUserAttachments(5));

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Handle AJAX requests sent to the plugin
     * @global \ELBP\Plugins\type $DB
     * @global \ELBP\Plugins\type $USER
     * @param type $action
     * @param null $params
     * @param type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){

        global $DB, $USER;

        switch($action)
        {

            case 'load_display_type':

                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                // Attachments
                $attachments = $this->getUserAttachments();

                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("access", $access)
                    ->set("attachments", $attachments);

                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/Attachments/tpl/'.$params['type'].'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;

            break;

            case 'delete':

                // Correct params are set?
                if (!$params || !isset($params['id']) || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                // Attachment is valid?
                $attachment = new \ELBP\Plugins\Attachments\Attachment($params['id']);
                if (!$attachment->isValid()) return false;

                // Does the file actually exist on the server?


                // Attachment belongs to this student?
                if ($attachment->getStudentID() <> $params['studentID']) return false;

                // Permissions?
                if ($attachment->getUploadedByID() == $USER->id && !elbp_has_capability('block/elbp:delete_my_attachment', $access)) return false;
                elseif ($attachment->getUploadedByID() <> $USER->id && !elbp_has_capability('block/elbp:delete_any_attachment', $access)) return false;

                // Delete
                if (!$attachment->delete()){
                    echo "$('#attachments_output').html('<div class=\"elbp_err_box\" id=\"add_errors\"></div>');";
                    echo "$('#add_errors').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                // Success
                echo "$('#attachments_output').html('<div class=\"elbp_success_box\" id=\"add_success\"></div>');";
                echo "$('#add_success').append('<span>".get_string('attachmentdeleted', 'block_elbp')."</span><br>');";
                echo "$('#attachment_{$attachment->getID()}').remove();";

            break;


            case 'add_comment':

                if (!$params || !isset($params['id']) || !isset($params['comment'])) return false;

                if (elbp_is_empty($params['comment'])) return false;

                $attachment = new \ELBP\Plugins\Attachments\Attachment($params['id']);
                if (!$attachment->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($attachment->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_attachment_comment', $access)) return false;

                // If parent ID is set, make sure that comment is on this target
                if (isset($params['parentID'])){
                    $checkParent = $DB->get_record("lbp_attachment_comments", array("id" => $params['parentID'], "attachmentid" => $attachment->getID()));
                    if (!$checkParent) return false;
                } else {
                    $params['parentID'] = null;
                }

                // If problem, error message
                if (!$comment = $attachment->addComment($params['comment'], $params['parentID'])){
                    echo "$('#elbp_comment_add_output_{$attachment->getID()}').html('<div class=\"elbp_err_box\" id=\"generic_err_box_{$attachment->getID()}\"></div>');";
                    echo "$('#generic_err_box_{$attachment->getID()}').append('<span>".get_string('errors:couldnotinsertrecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                // Was OK
                $commentText = substr($comment->comments, 0, 30) . '...';

                // Append new comment box
                if (isset($params['parentID'])){
                    echo "$('#elbp_comment_add_output_comment_{$params['parentID']}').html('<div class=\"elbp_success_box\" id=\"generic_success_box_comment_{$comment->id}\"></div>');";
                    echo "$('#generic_success_box_comment_{$comment->id}').append('<span>".get_string('commentadded', 'block_elbp').": ".elbp_html($commentText, true)."</span><br>');";
                    echo "$('#add_reply_{$params['parentID']}').val('');";
                    echo "$('#comment_{$params['parentID']}').append('<div id=\'comment_{$comment->id}\' class=\'elbp_comment_box\' style=\'width:90%;background-color:{$comment->css->bg};border: 1px solid {$comment->css->bdr};\'><p id=\'elbp_comment_add_output_comment_{$comment->id}\'></p>".elbp_html($comment->comments, true)."<br><br><small><b>{$comment->firstName} {$comment->lastName}</b></small><br><small>".date('D jS M Y H:i', $comment->time)."</small><br><small><a href=\'#\' onclick=\'$(\"#comment_reply_{$comment->id}\").slideToggle();return false;\'>".get_string('reply', 'block_elbp')."</a></small><br><small><a href=\'#\' onclick=\'ELBP.Attachments.delete_comment({$comment->id});return false;\'>".get_string('delete', 'block_elbp')."</a></small><br><div id=\'comment_reply_{$comment->id}\' class=\'elbp_comment_textarea\' style=\'display:none;\'><textarea id=\'add_reply_{$comment->id}\'></textarea><br><br><input class=\'elbp_big_button\' type=\'button\' value=\'".get_string('submit', 'block_elbp')."\' onclick=\'ELBP.Attachments.add_comment({$attachment->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;\' /><br><br></div></div>');";
                } else {
                    echo "$('#elbp_comment_add_output_{$attachment->getID()}').html('<div class=\"elbp_success_box\" id=\"generic_success_box_{$attachment->getID()}\"></div>');";
                    echo "$('#generic_success_box_{$attachment->getID()}').append('<span>".get_string('commentadded', 'block_elbp').": ".elbp_html($commentText, true)."</span><br>');";
                    echo "$('#add_comment_{$attachment->getID()}').val('');";
                    echo "$('#elbp_comments_content_{$attachment->getID()}').append('<div id=\'comment_{$comment->id}\' class=\'elbp_comment_box\' style=\'width:90%;background-color:{$comment->css->bg};border: 1px solid {$comment->css->bdr};\'><p id=\'elbp_comment_add_output_comment_{$comment->id}\'></p>".elbp_html($comment->comments, true)."<br><br><small><b>{$comment->firstName} {$comment->lastName}</b></small><br><small>".date('D jS M Y H:i', $comment->time)."</small><br><small><a href=\'#\' onclick=\'$(\"#comment_reply_{$comment->id}\").slideToggle();return false;\'>".get_string('reply', 'block_elbp')."</a></small><br><small><a href=\'#\' onclick=\'ELBP.Attachments.delete_comment({$comment->id});return false;\'>".get_string('delete', 'block_elbp')."</a></small><br><div id=\'comment_reply_{$comment->id}\' class=\'elbp_comment_textarea\' style=\'display:none;\'><textarea id=\'add_reply_{$comment->id}\'></textarea><br><br><input class=\'elbp_big_button\' type=\'button\' value=\'".get_string('submit', 'block_elbp')."\' onclick=\'ELBP.Attachments.add_comment({$attachment->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;\' /><br><br></div></div>');";
                }

                echo "$('#attachment_{$attachment->getID()} td:nth-child(5) a').text( parseInt($('#attachment_{$attachment->getID()} td:nth-child(5) a').text()) + 1 );";


            break;

            case 'delete_comment':

                if (!$params || !isset($params['id'])) return false;

                $commentID = $params['id'];

                $comment = $DB->get_record("lbp_attachment_comments", array("id" => $commentID));
                if (!$comment) return false;

                $attachment = new \ELBP\Plugins\Attachments\Attachment($comment->attachmentid);
                if (!$attachment->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($attachment->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                // Your comment
                if ($comment->userid == $USER->id && !elbp_has_capability('block/elbp:delete_my_attachment_comment', $access)) return false;
                elseif ($comment->userid <> $USER->id && !elbp_has_capability('block/elbp:delete_any_attachment_comment', $access)) return false;

                // Delete comment

                // Delete
                if (!$numDeleted = $attachment->deleteComment($comment->id)){
                    echo "$('#attachments_output').html('<div class=\"elbp_err_box\" id=\"add_errors\"></div>');";
                    echo "$('#add_errors').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                // Success
                echo "$('#attachments_output').html('<div class=\"elbp_success_box\" id=\"add_success\"></div>');";
                echo "$('#add_success').append('<span>".get_string('commentdeleted', 'block_elbp')."</span><br>');";
                echo "$('#comment_{$comment->id}').remove();";
                echo "$('#attachment_{$attachment->getID()} td:nth-child(5) a').text( parseInt($('#attachment_{$attachment->getID()} td:nth-child(5) a').text()) - {$numDeleted} );";
                exit;


            break;


        }

    }


}