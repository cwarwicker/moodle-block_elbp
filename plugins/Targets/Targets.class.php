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

require_once $CFG->dirroot . '/blocks/elbp/plugins/Targets/Target.class.php';
require_once $CFG->dirroot . '/blocks/elbp/plugins/Targets/TargetSets.class.php';


/**
 *
 */
class Targets extends Plugin {

    protected $tables = array(
        'lbp_targets',
        'lbp_target_attributes',
        'lbp_target_comments',
        'lbp_target_status'
    );

    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {

        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Targets",
                "path" => null,
                "version" => \ELBP\ELBP::getBlockVersionStatic()
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
            $this->loadDefaultAttributes();
        }

    }

    /**
     * Yes it is. It's the only plugin which actually makes any sense using progress bars on the block
     * @return boolean
     */
    public function isUsingBlockProgress(){
        return true;
    }

    /**
     * Install the plugin
     */
    public function install()
    {

        global $CFG, $DB;

        $pluginID = $this->createPlugin();
        $return = true && $pluginID;

        // This is a core ELBP plugin, so the extra tables it requires are handled by the core ELBP install.xml


        // Default settings
        $settings = array();
        $settings['block_progress_bars_enabled'] = 1;
        $settings['external_target_name_hover_attribute'] = 'Target';
        $settings['attributes'] = 'elbpform:[{"id":"qqWp2nmH8d","name":"Target Type","type":"Select","display":"side","default":"","instructions":"","options":["TAP","SAP","AddSupp"],"other":[],"studentID":false,"usersValue":false,"obj":null},{"id":"P923NN4Ayp","name":"Target","type":"Moodle Text Editor","display":"main","default":"","instructions":"","options":false,"validation":["REQUIRED"],"other":[],"studentID":false,"usersValue":false,"obj":null}]';
        $settings['target_set_100_progress_when_achieved'] = 1;
        $settings['target_set_achieved_when_100_progress'] = 1;
        $settings['new_target_instructions'] = 'Please ensure targets are SMART: Specific, Measurable, Achieveable, Realistic, Time-bound';


        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }

        // Hooks that other plugins can use
        $DB->insert_record("lbp_hooks", array("pluginid" => $pluginID, "name" => "Targets"));

        // Statuses
        $DB->insert_record("lbp_target_status", array("status" => "To Be Achieved", "img" => "{$CFG->wwwroot}/blocks/elbp/plugins/Targets/pix/tobeachieved.png", "achieved" => 0, "ordernum" => 1, "listinsummary" => 0, "ignored" => 0));
        $DB->insert_record("lbp_target_status", array("status" => "Partially Achieved", "img" => "{$CFG->wwwroot}/blocks/elbp/plugins/Targets/pix/partiallyachieved.png", "achieved" => 0, "ordernum" => 2, "listinsummary" => 0, "ignored" => 0));
        $DB->insert_record("lbp_target_status", array("status" => "Achieved", "img" => "{$CFG->wwwroot}/blocks/elbp/plugins/Targets/pix/achieved.png", "achieved" => 1, "ordernum" => 3, "listinsummary" => 0, "ignored" => 0));
        $DB->insert_record("lbp_target_status", array("status" => "Withdrawn", "img" => "{$CFG->wwwroot}/blocks/elbp/plugins/Targets/pix/withdrawn.png", "achieved" => 0, "ordernum" => 4, "listinsummary" => 0, "ignored" => 1));

        // Alert events
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Target Added", "description" => "A new target is added into the system", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Target Updated", "description" => "A target is updated (status, progress, etc...)", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Target Comment Added", "description" => "A comment is added onto a target", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Target Deadline Passes", "description" => "A target passes its deadline without being achieved", "auto" => 1, "enabled" => 1));

        return $return;

    }

    /**
     * Truncate related tables and uninstall plugin
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

        global $DB;

        // [Upgrades here]
        if ($this->version < 2017052500)
        {
            $record = $DB->get_record("lbp_alert_events", array("pluginid" => $this->id, "name" => "Target Deadline Passes"));
            if ($record)
            {
                $record->auto = 1;
                $DB->update_record("lbp_alert_events", $record);
            }
        }



    }




    /**
     * Get the expanded view
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()){

        $output = "";

        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);

        try {
            $output .= $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/Targets/tpl/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;

    }


    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){

        $TPL = new \ELBP\Template();



       # $TPL->set("courses", $courses);
        $TPL->set("obj", $this);
        #$TPL->set("types", $this->getTypes());

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/Targets/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Handle ajax requests sent to the plugin
     * @global type $CFG
     * @global \ELBP\Plugins\type $DB
     * @global type $USER
     * @param type $action
     * @param null $params
     * @param \ELBP\Plugins\type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){

        global $CFG, $DB, $USER;

        switch($action)
        {

            case 'load_display_type':

                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                // Get the type from the ID
                if (!isset($params['type'])) return false;

                $statusInfo = false;
                $targets = false;
                $filtering = false;

                if (ctype_digit($params['type'])){
                    $targets = $this->getUserTargets($params['type']);
                    $statusInfo = $this->getStatus($params['type']);
                    $filtering = $this->getTargetFiltering();
                }

                $FORM = new \ELBP\ELBPForm();
                $FORM->loadStudentID($this->student->id);

                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this);
                $TPL->set("targets", $targets);
                $TPL->set("FORM", $FORM);
                $TPL->set("access", $this->access);
                $TPL->set("filtering", $filtering);

                if ($statusInfo)
                {
                    $TPL->set("status", $statusInfo->status);
                    $TPL->set("count", $this->countUserTargetsByStatus($statusInfo->id));
                }

                // If it's a digit we are loading up the targets of a given defined status, otherwise a specific tpl page
                if (ctype_digit($params['type'])) $page = 'type';
                else $page = $params['type'];

                $TPL->set("page", $page);

                $targetID = false;

                if ($page == 'edit'){
                    $targetID = $params['targetID'];
                    $page = 'new'; # Use the same form, just check for different capabilities
                }

                // if new or edit target need the data
                if ($page == 'new'){

                    $targetsetattributes = false;
                    $targetsetsdropdown = $DB->get_records('lbp_target_sets', array('deleted' => 0));
                    $targets = array();

                    foreach ($targetsetsdropdown as $tsd)
                    {
                        $targetobject = new \stdClass();
                        $targetobject->id = $tsd->id;
                        $targetobject->name = $tsd->name;
                        $targetobject->deleted = $tsd->deleted;
                        $targetobject->attributes = array();

                        $targetsetattributes = $DB->get_records('lbp_target_set_attributes', array('targetsetid' => $tsd->id));

                        foreach ($targetsetattributes as $ts)
                        {
                            $attribute = array();
                            $attribute['id'] = $ts->id;
                            $attribute['targetsetid'] = $ts->targetsetid;
                            $attribute['field'] = $ts->field;
                            $attribute['value'] = $ts->value;
                            $targetobject->attributes[] = $attribute;
                        }

                        $targets[$targetobject->id] = $targetobject;
                    }

                    $TPL->set("targetsetattributes", $targetsetattributes);
                    $TPL->set("targetsetsdropdown", $targetsetsdropdown);
//                    $TPL->set("targetobject", $targetobject);
                    $TPL->set("targets", $targets);

                    $TPL->set("data", \ELBP\Plugins\Targets\Target::getDataForNewTargetForm($targetID, $this));

                    if (isset($params['loadedFrom'])) $TPL->set("loadedFrom", $params['loadedFrom']);
                    if (isset($params['putInto'])) $TPL->set("putInto", $params['putInto']);
                }

                $TPL->set("targetID", $targetID);

                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/Targets/tpl/'.$page.'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;

            break;

            case 'save_target':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_target', $access)) return false;

                $target = new \ELBP\Plugins\Targets\Target($params, $this);

                // If the target exists, check to make sure the student ID on it is the same as the one we specified
                if ($target->getID() > 0 && $target->getStudentID() <> $params['studentID']) return false;

                // If it was created through something else, e.g. tutorial, don't send alert
                if (isset($params['loadedFrom']) && $params['loadedFrom'] != ''){
                    $target->setNoAlert(true);
                }

                if (!$target->save()){

                    echo "$('#new_target_output').html('<div class=\"elbp_err_box\" id=\"add_target_errors\"></div>');";

                    foreach($target->getErrors() as $error){

                        echo "$('#add_target_errors').append('<span>{$error}</span><br>');";

                    }

                    exit;

                }

                // If loaded from somewhere else, e.g. adding a target to a tutorial, we want to go back there
                if (isset($params['loadedFrom']) && $params['loadedFrom'] != ''){

                    // Restore the state from that Plugin
                    echo "ELBP.restore_state('{$params['loadedFrom']}');";
                    echo "ELBP.Targets.loaded_from = false;";

                    // if we defined somewhere to put the info back into, do that
                    if (isset($params['putInto']) && $params['putInto'] != ''){

                        $loadedFrom = $ELBP->getPlugin($params['loadedFrom']);
                        $loadedFromTitle = $loadedFrom->getTitle();
                        $call = '';

                        // Add to whatever element we specified
//                        if ($params['target_id'] > 0){
//                            // Remove old table row so we can add new one with updated info
//                            echo "$('#new_added_target_id_{$target->getID()}').remove();";
//                        }

                        if ($params['loadedFrom'] == 'AdditionalSupport'){
                            $info = "<tr class=\'added_target_row\' id=\'new_added_target_id_{$target->getID()}\'>";
                            $info .= "<td><a href=\'#\' onclick=\'ELBP.{$params['loadedFrom']}.edit_target({$target->getID()}, \"{$loadedFromTitle}\");return false;\'>".elbp_html($target->getName())."</a></td>";

                            if ($ELBP->getPlugin("AdditionalSupport")->getSetting('confidence_enabled') == 1)
                            {
                                $info .= "<td>".get_string('atthestart', 'block_elbp').": <select name=\'Targets Confidence Start {$target->getID()}\'><option value=\'\'></option>";
                                    for ($i = 1; $i <= $ELBP->getPlugin("AdditionalSupport")->getConfidenceLimit(); $i++)
                                    {
                                        $att = "Targets Confidence Start {$target->getID()}";
                                        $chk = ($target->getAttribute($att) == $i) ? 'selected' : '';
                                        $info .= "<option value=\'{$i}\' {$chk} >{$i}</option>";
                                    }
                                $info .= "</select> &nbsp;&nbsp; ".get_string('now', 'block_elbp').": <select name=\'Targets Confidence End {$target->getID()}\'><option value=\'\'></option>";
                                    for ($i = 1; $i <= $ELBP->getPlugin("AdditionalSupport")->getConfidenceLimit(); $i++)
                                    {
                                        $att = "Targets Confidence End {$target->getID()}";
                                        $chk = ($target->getAttribute($att) == $i) ? 'selected' : '';
                                        $info .= "<option value=\'{$i}\' {$chk} >{$i}</option>";
                                    }
                                $info .= "</select>";
                                $info .= "</td>";
                            }
                            $info .= "<td><a href=\'#\' onclick=\'ELBP.{$params['loadedFrom']}.remove_target({$target->getID()});return false;\' title=\'".get_string('remove', 'block_elbp')."\'><img src=\'".$CFG->wwwroot."/blocks/elbp/pix/remove.png\' alt=\'".get_string('remove', 'block_elbp')."\' /></a><input type=\'hidden\' name=\'Targets\' value=\'{$target->getID()}\' /></td>";
                            $info .= "</tr>";
                            echo "ELBP.Targets.tmp_deadline = '".$target->getDueDate('d-m-Y')."';";
                            echo "if( $('#new_added_target_id_{$target->getID()}').length == 0 ){  $('#{$params['putInto']}').append('{$info}');  }";

                            $autoSave = \ELBP\Setting::getSetting('addsup_autosave', $USER->id);
                            if ($autoSave == 1){
                                echo "if (ELBP.{$params['loadedFrom']}.auto_save !== undefined) { ELBP.{$params['loadedFrom']}.auto_save(); }";
                            }

                        }
                        elseif ($params['loadedFrom'] == 'Tutorials')
                        {
                            $info = "<tr class=\'added_target_row\' id=\'new_added_target_id_{$target->getID()}\'><td>{$target->getSetDate()}</td><td><a href=\'#\' onclick=\'ELBP.{$params['loadedFrom']}.edit_target({$target->getID()}, \"{$loadedFromTitle}\");return false;\'>".elbp_html($target->getName())."</a></td><td>{$target->getStatusName()}</td><td>{$target->getDueDate()}</td><td><a href=\'#\' onclick=\'ELBP.{$params['loadedFrom']}.remove_target({$target->getID()});return false;\' title=\'".get_string('remove', 'block_elbp')."\'><img src=\'".$CFG->wwwroot."/blocks/elbp/pix/remove.png\' alt=\'".get_string('remove', 'block_elbp')."\' /></a><input type=\'hidden\' name=\'Targets\' value=\'{$target->getID()}\' /></td></tr>";
                            echo "$('#{$params['putInto']}').append('{$info}');";
                            $autoSave = \ELBP\Setting::getSetting('tutorial_autosave', $USER->id);
                            if ($autoSave == 1){
                                echo "if (ELBP.{$params['loadedFrom']}.auto_save !== undefined) { ELBP.{$params['loadedFrom']}.auto_save(); }";
                            }
                        }



                    }

                    exit;

                }


                // SUccess message at top
                echo "$('#new_target_output').html('<div class=\"elbp_success_box\" id=\"add_target_success\"></div>');";
                echo "$('#add_target_success').append('<span>".get_string('targetupdated', 'block_elbp')."</span><br>');";

                if ($params['target_id'] <= 0){
                    echo "$('#new_target_form')[0].reset();";
                }

                exit;

            break;

            case 'delete_target':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['targetID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:delete_target', $access)) return false;

                $target = new \ELBP\Plugins\Targets\Target($params['targetID'], $this);
                if (!$target->isValid()) return false;

                // check to make sure the student ID on it is the same as the one we specified
                if ($target->getStudentID() <> $params['studentID']) return false;


                if (!$target->delete()){
                    echo "$('#generic_output').html('<div class=\"elbp_err_box\" id=\"generic_err_box\"></div>');";
                    echo "$('#generic_err_box').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                echo "$('#generic_output').html('<div class=\"elbp_success_box\" id=\"generic_success_box\"></div>');";
                echo "$('#generic_success_box').append('<span>".get_string('targetdeleted', 'block_elbp')."</span><br>');";
                echo "$('#elbp_target_id_{$target->getID()}').remove();";

                exit;

            break;

            case 'add_comment':

                if (!$params || !isset($params['targetID']) || !isset($params['comment'])) return false;

                if (elbp_is_empty($params['comment'])) return false;

                $target = new \ELBP\Plugins\Targets\Target($params['targetID'], $this);
                if (!$target->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($target->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_target_comment', $access)) return false;

                // If parent ID is set, make sure that comment is on this target
                if (isset($params['parentID'])){
                    $checkParent = $DB->get_record("lbp_target_comments", array("id" => $params['parentID'], "targetid" => $target->getID()));
                    if (!$checkParent) return false;
                } else {
                    $params['parentID'] = null;
                }

                // If problem, error message
                if (!$comment = $target->addComment($params['comment'], $params['parentID'])){
                    echo "$('#elbp_comment_add_output_{$target->getID()}').html('<div class=\"elbp_err_box\" id=\"generic_err_box_{$target->getID()}\"></div>');";
                    echo "$('#generic_err_box_{$target->getID()}').append('<span>".get_string('errors:couldnotinsertrecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                // Was OK
                $commentText = substr($comment->comments, 0, 30) . '...';

                // Append new comment box
                if (isset($params['parentID'])){
                    echo "$('#elbp_comment_add_output_comment_{$params['parentID']}').html('<div class=\"elbp_success_box\" id=\"generic_success_box_comment_{$comment->id}\"></div>');";
                    echo "$('#generic_success_box_comment_{$comment->id}').append('<span>".get_string('commentadded', 'block_elbp').": ".elbp_html($commentText, true)."</span><br>');";
                    echo "$('#add_reply_{$params['parentID']}').val('');";
                    echo "$('#comment_{$params['parentID']}').append('<div id=\'comment_{$comment->id}\' class=\'elbp_comment_box\' style=\'width:90%;background-color:{$comment->css->bg};border: 1px solid {$comment->css->bdr};\'><p id=\'elbp_comment_add_output_comment_{$comment->id}\'></p>".elbp_html($comment->comments, true)."<br><br><small><b>{$comment->firstName} {$comment->lastName}</b></small><br><small>".date('D jS M Y H:i', $comment->time)."</small><br><small><a href=\'#\' onclick=\'$(\"#comment_reply_{$comment->id}\").slideToggle();return false;\'>".get_string('reply', 'block_elbp')."</a></small><br><div id=\'comment_reply_{$comment->id}\' class=\'elbp_comment_textarea\' style=\'display:none;\'><textarea id=\'add_reply_{$comment->id}\'></textarea><br><br><input class=\'elbp_big_button\' type=\'button\' value=\'".get_string('submit', 'block_elbp')."\' onclick=\'ELBP.Targets.add_comment({$target->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;\' /><br><br></div></div>');";
                } else {
                    echo "$('#elbp_comment_add_output_{$target->getID()}').html('<div class=\"elbp_success_box\" id=\"generic_success_box_{$target->getID()}\"></div>');";
                    echo "$('#generic_success_box_{$target->getID()}').append('<span>".get_string('commentadded', 'block_elbp').": ".elbp_html($commentText, true)."</span><br>');";
                    echo "$('#add_comment_{$target->getID()}').val('');";
                    echo "$('#elbp_comments_content_{$target->getID()}').append('<div id=\'comment_{$comment->id}\' class=\'elbp_comment_box\' style=\'width:90%;background-color:{$comment->css->bg};border: 1px solid {$comment->css->bdr};\'><p id=\'elbp_comment_add_output_comment_{$comment->id}\'></p>".elbp_html($comment->comments, true)."<br><br><small><b>{$comment->firstName} {$comment->lastName}</b></small><br><small>".date('D jS M Y H:i', $comment->time)."</small><br><small><a href=\'#\' onclick=\'$(\"#comment_reply_{$comment->id}\").slideToggle();return false;\'>".get_string('reply', 'block_elbp')."</a></small><br><div id=\'comment_reply_{$comment->id}\' class=\'elbp_comment_textarea\' style=\'display:none;\'><textarea id=\'add_reply_{$comment->id}\'></textarea><br><br><input class=\'elbp_big_button\' type=\'button\' value=\'".get_string('submit', 'block_elbp')."\' onclick=\'ELBP.Targets.add_comment({$target->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;\' /><br><br></div></div>');";
                }


            break;

            case 'delete_comment':

                if (!$params || !isset($params['targetID']) || !isset($params['commentID'])) return false;

                $target = new \ELBP\Plugins\Targets\Target($params['targetID'], $this);
                if (!$target->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($target->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $comment = $target->getComment($params['commentID']);
                if (!$comment) return false;

                // If not our comment we need the delete_any_target_comment capability
                if ( $comment->userid <> $USER->id && !elbp_has_capability('block/elbp:delete_any_target_comment', $access) ) return false;

                // If it is ours, we need delete_my_taret_comment
                if ( $comment->userid == $USER->id && !elbp_has_capability('block/elbp:delete_my_target_comment', $access) ) return false;

                // Delete it
                if (!$target->deleteComment($comment->id)){
                    echo "$('#elbp_comment_generic_output_comment').html('<div class=\"elbp_err_box\" id=\"elbp_comment_generic_output_comment_{$target->getID()}\"></div>');";
                    echo "$('#elbp_comment_generic_output_comment_{$target->getID()}').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                // OK
                echo "$('#elbp_comment_generic_output_comment').html('<div class=\"elbp_success_box\" id=\"elbp_comment_generic_output_comment_{$target->getID()}\"></div>');";
                echo "$('#elbp_comment_generic_output_comment_{$target->getID()}').append('<span>".get_string('commentdeleted', 'block_elbp')."</span><br>');";
                echo "$('#comment_{$comment->id}').remove();";

                exit;

            break;

            case 'update_status':

                if (!$params || !isset($params['targetID']) || !isset($params['statusID'])) return false;

                $target = new \ELBP\Plugins\Targets\Target($params['targetID'], $this);
                if (!$target->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($target->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:edit_target', $access) && !elbp_has_capability('block/elbp:update_target_status', $access)) return false;

                // Valid Status?
                $status = $this->getStatus($params['statusID']);
                if (!$status) return false;

                $target->setStatusID($params['statusID']);

                // If the status is "achieved", set progress to 100
                if (($ach = $target->findAchievedStatus()) && $params['statusID'] == $ach->id && $this->getSetting('target_set_100_progress_when_achieved') == 1){
                    $target->setProgress(100);
                }

                // Doing alerts as could be coming from anywhere, scrolled far down the page, so just easier
                if (!$target->save()){

                    $err = '';
                    foreach($target->getErrors() as $error)
                    {
                        $err .= $error . ", ";
                    }
                    echo "alert('{$err}');";

                }
                else
                {
                    echo "$('tr.target_row_{$target->getID()}').effect( 'highlight', {color: '#ccff66'}, 3000 );";
                }

                exit;

            break;

            case 'forward_email':

                $targetID = $_POST['params']['targetID'];
                $usernames = explode(",", $_POST['params']['usernames']);
                if (!$usernames) exit;

                $errors = array();
                $users = array();

                foreach($usernames as $username)
                {

                    $username = trim($username);
                    if (empty($username)) continue;
                    $user = $DB->get_record("user", array("username" => $username));
                    if (!$user)
                    {
                        $errors[] = get_string('invaliduser', 'block_elbp') . ' - ' . elbp_html($username);
                    }
                    else
                    {
                        $users[] = $user;
                    }

                }


                if ($errors){

                    $str = "";
                    foreach($errors as $error){
                        $str .= $error . "<br>";
                    }
                    echo "$('#email-error-{$targetID}').html('{$str}');";
                    echo "$('#email-error-{$targetID}').show();";
                    exit;

                }

                if (!$users) exit;

                $target = new \ELBP\Plugins\Targets\Target($targetID);
                if (!$target->isValid()) exit;

                $obj = new \ELBP\EmailAlert();
                $subject = get_string('target', 'block_elbp');
                $content = $target->getInfoForEventTrigger(false);
                $htmlContent = nl2br($content);

                echo "$('#email-success-{$targetID}').html('');";

                foreach($users as $user)
                {
                    $obj->queue("email", $user, $subject, $content, $htmlContent);
                    echo "$('#email-success-{$targetID}').append('".get_string('emailsentto', 'block_elbp')." - {$user->email}<br>');";
                }

                echo "$('#email-to-addr-{$targetID}').val('');";
                echo "$('#email-success-{$targetID}').show();";

                exit;

            break;

        }

    }

    /**
     * Save configuration
     * @global type $MSGS
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {

        global $MSGS;

        if (isset($_POST['submit_statuses'])){

            if (!isset($settings['status_ids']) || !isset($settings['status_names'])) return false;

            $ids = array();
            $names = array();
            $imgs = array();
            $order = array();
            $achieved = array();
            $ignore = array();
            $listInSummary = array();

            for($i = 0; $i < count($settings['status_names']); $i++)
            {

                // If it's new and it's empty, skip
                if ($settings['status_ids'][$i] == -1 && $settings['status_names'][$i] == '') continue;

                $ids[] = $settings['status_ids'][$i];
                $names[] = $settings['status_names'][$i];
                $imgs[] = $settings['status_imgs'][$i];
                $order[] = $settings['status_order'][$i];
                $achieved[] = (isset($settings['status_achieved'][$i])) ? true : false;
                $ignore[] = (isset($settings['status_ignore'][$i])) ? true : false;
                $listInSummary[] = (isset($settings['status_listinsummary'][$i])) ? true : false;

            }

            $this->updateStatuses($ids, $names, $imgs, $order, $achieved, $ignore, $listInSummary);

            // Remove from settings so dont get inserted - Dunno why i do this if returning true, but whatever
            unset($settings['submit_statuses']);
            unset($settings['status_ids']);
            unset($settings['status_names']);
            unset($settings['status_imgs']);
            unset($settings['status_order']);
            unset($settings['status_listinsummary']);
            unset($settings['status_ignore']);

            $MSGS['success'] = get_string('statusesupdated', 'block_elbp');
            return true;

        }
        elseif(isset($_POST['submit_attributes'])){

            \elbp_save_attribute_script($this);
            return true;

        }
        elseif(isset($_POST['submit_target_instructions']))
        {

            $instructions = $settings['new_target_instructions'];
            $this->updateSetting("new_target_instructions", $instructions);

            $MSGS['success'] = get_string('instructionsupdated', 'block_elbp');

            return true;

        }
        elseif(isset($_POST['submit_target_hover_attribute']))
        {

            $attribute = $settings['external_target_name_hover_attribute'];
            $this->updateSetting("external_target_name_hover_attribute", $attribute);
            $MSGS['success'] = get_string('attributesupdated', 'block_elbp');
            return true;

        }
        elseif (isset($_POST['submit_target_filter_attribute']))
        {

            $attribute = $settings['target_filter_attribute'];
            $this->updateSetting("target_filter_attribute", $attribute);
            $MSGS['success'] = get_string('attributesupdated', 'block_elbp');
            return true;

        }
        elseif (isset($_POST['submit_target_set']) && strlen($_POST['target_name']) > 1)
        {
            global $USER, $DB;

            $id = $_POST['id'];

            $newtargets = new \ELBP\Plugins\Targets\TargetSets($id);
            $newtargets->setUserID($USER->id);
            $newtargets->setName($_POST['target_name']);
            $newtargets->setDeleted(0);

//            if ($_POST['visibility'] == 'show')
//            {
//                $newtargets->setDeleted(0);
//            }
//            elseif ($_POST['visibility'] == 'hide')
//            {
//                $newtargets->setDeleted(1);
//            }

            $setid = $newtargets->Save();
            $data = \ELBP\Plugins\Targets\Target::getDataForNewTargetForm();
            $clearattributes = $newtargets->ClearAttributes($id);

            foreach ($data['atts'] as $atts)
            {
                $names = $atts->name;
                $n = str_replace(' ', '_', $names);

                if ($atts)
                {
                    $newattribute = new \ELBP\Plugins\Targets\TargetSets();
                    $newattribute->setTargetsetid($setid);
                    $newattribute->setField($names);
                    $newattribute->setValue($_POST[$n]);

                    $newattribute->SaveAttributes();
                }
            }

            redirect('/blocks/elbp/plugins/Targets/config.php?view=targetsets');
        }
        elseif(isset($_POST['delete_target_set']))
        {
            $id = $_POST['delete_id'];

            $deletetarget = new \ELBP\Plugins\Targets\TargetSets($id);
            $deletetarget->setDeleted(1);
            $deletetarget->Save();

            redirect('/blocks/elbp/plugins/Targets/config.php?view=targetsets');
        }




        if (isset($settings['target_set_achieved_when_100_progress'])) $settings['target_set_achieved_when_100_progress'] = 1;
        else $settings['target_set_achieved_when_100_progress'] = 0;

        if (isset($settings['target_set_100_progress_when_achieved'])) $settings['target_set_100_progress_when_achieved'] = 1;
        else $settings['target_set_100_progress_when_achieved'] = 0;

        if (isset($settings['integrate_calendar'])) $settings['integrate_calendar'] = 1;
        else $settings['integrate_calendar'] = 0;





        // Student progress definitions

        // If any of them aren't defined, set their value to 0 for disabled
        if (!isset($settings['student_progress_definitions_each'])){
            $settings['student_progress_definitions_each'] = 0;
            $settings['student_progress_definition_importance_each'] = 0;
        }

        if (!isset($settings['student_progress_definitions_req'])){
            $settings['student_progress_definitions_req'] = 0;
            $settings['student_progress_definition_values_req'] = 0;
            $settings['student_progress_definition_importance_req'] = 0;
        }

        if (!isset($settings['student_progress_definitions_reqach'])){
            $settings['student_progress_definitions_reqach'] = 0;
            $settings['student_progress_definition_values_reqach'] = 0;
            $settings['student_progress_definition_importance_reqach'] = 0;
        }

        // If the req ones don't have a valid number as their value, set to disabled
        if (!isset($settings['student_progress_definition_values_req']) || $settings['student_progress_definition_values_req'] <= 0) $settings['student_progress_definitions_req'] = 0;
        if (!isset($settings['student_progress_definition_values_reqach']) || $settings['student_progress_definition_values_reqach'] <= 0) $settings['student_progress_definitions_reqach'] = 0;


        parent::saveConfig($settings);

    }

    /**
     * Update all the statuses, with names, imgs, settings, etc...
     * @param type $ids
     * @param type $names
     * @param type $imgs
     * @param type $order
     * @param type $achieved
     * @param type $ignore
     * @param type $listInSummary
     */
    private function updateStatuses($ids, $names, $imgs, $order, $achieved, $ignore, $listInSummary){

        for($i = 0; $i < count($ids); $i++){

            // If id is -1 it's new so insert it
            if ($ids[$i] == -1){

                $obj = new \stdClass();
                $obj->status = $names[$i];
                $obj->img = $imgs[$i];
                $obj->ordernum = (int)$order[$i];
                $obj->achieved = (int)$achieved[$i];
                $obj->ignored = (int)$ignore[$i];
                $obj->listinsummary = (int)$listInSummary[$i];
                $this->DB->insert_record("lbp_target_status", $obj);

            }

            // If it's not new but the name is empty, delete it
            elseif ($names[$i] == ''){

                $this->DB->delete_records("lbp_target_status", array("id"=>$ids[$i]));

            }

            // Otherwise update it
            else
            {

                $obj = new \stdClass();
                $obj->id = (int)$ids[$i];
                $obj->status = $names[$i];
                $obj->img = $imgs[$i];
                $obj->ordernum = (int)$order[$i];
                $obj->achieved = (int)$achieved[$i];
                $obj->ignored = (int)$ignore[$i];
                $obj->listinsummary = (int)$listInSummary[$i];

                $this->DB->update_record("lbp_target_status", $obj);

            }

        }

    }


    /**
     * Get the list of statuses from the DB
     */
    public function getStatuses(){

        $records = $this->DB->get_records_select('lbp_target_status', null, null, "ordernum ASC");
        return $records;

    }


    public function getStatusNames(){

        $statuses = $this->getStatuses();
        $statusArray = array();
        if ($statuses)
        {
            foreach($statuses as $status)
            {
                $statusArray[$status->id] = $status->status;
            }
        }

        return $statusArray;

    }

    /**
     * Get record of a given status id
     * @param type $statusID
     * @return type
     */
    public function getStatus($statusID){

        return $this->DB->get_record('lbp_target_status', array('id' => $statusID));

    }

    /**
     * Get the IDs of any statuses which are "achieved"
     */
    private function getAchievedStatusIDs(){
        $records = $this->DB->get_records("lbp_target_status", array("achieved" => 1), '', "id");
        $return = array();
        if ($records)
        {
            foreach($records as $record)
            {
                $return[] = $record->id;
            }
        }
        return $return;
    }

    /**
     * Get the IDs of any statuses which are "ignored"
     */
    private function getIgnoredStatusIDs(){
        $records = $this->DB->get_records("lbp_target_status", array("ignored" => 1), '', "id");
        $return = array();
        if ($records)
        {
            foreach($records as $record)
            {
                $return[] = $record->id;
            }
        }
        return $return;
    }

    /**
     * Get the possible filters, if chosen in settings
     */
    public function getTargetFiltering(){

        $setting = $this->getSetting('target_filter_attribute');
        if (!$setting) return false;

        $attribute = false;

        // Check if it is an element with options, like a select menu, or list of checkboxes
        $attributes = $this->getAttributesForDisplay();
        if ($attributes)
        {
            foreach($attributes as $att)
            {
                if ($att->name == $setting){
                    $attribute = $att;
                }
            }
        }

        if (!$attribute || !$attribute->options) return false;

        return $attribute->options;

    }


    /**
     * Get the student's targets
     * @param type $statusID If set, only targets with this status
     * @param type $courseID If set, only targets linked to this course
     * @return boolean|\ELBP\Plugins\Targets\Target
     */
    public function getUserTargets($statusID = null, $courseID = null){

        if (!$this->student) return false;

        $results = array();

        $params = array("studentid"=>$this->student->id, "del" => 0);
        if (!is_null($statusID)) $params['status'] = $statusID;
        if (!is_null($courseID)) $params['courseid'] = $courseID;

        // Academic Year
        $academicYearUnix = $this->getAcademicYearUnix();

        $status = $this->getStatus($statusID);
        $orderSQL = ($status && $status->achieved == 1) ? "updatedtime DESC" : "deadline ASC";
        $records = $this->DB->get_records('lbp_targets', $params, "{$orderSQL}, id DESC", "id");

        if ($records){

            foreach($records as $record){

                $obj = new Targets\Target($record->id);

                // If using academic year and target was set before start, don't show it
                if ($academicYearUnix && $obj->getSetTime() < $academicYearUnix){
                    continue;
                }

                $results[] = $obj;

            }

        }

        return $results;

    }

    /**
     * Get all of the student's targets which are not achieved and not ignored - basically any that are still pending
     * @param type $courseID
     */
    private function getPendingTargets($courseID = null)
    {

        if (!$this->student) return false;

        $results = array();

        $statusIDs = array_merge($this->getAchievedStatusIDs(), $this->getIgnoredStatusIDs());

        $params = array($this->student->id, 0);

        $where = "studentid = ? AND del = ? ";
        if ($statusIDs)
        {
            $where .= "AND status NOT IN (";
            foreach($statusIDs as $statusID)
            {
                $where .= "?,";
                $params[] = $statusID;
            }
            $where = substr($where, 0, -1);
            $where .= ") ";
        }


        if (!is_null($courseID)){
            $where .= " AND courseid = ? ";
            $params[] = $courseID;
        }

        $records = $this->DB->get_records_select('lbp_targets', $where, $params, "id DESC", "id");

        if ($records){

            foreach($records as $record){

                $obj = new Targets\Target($record->id);

                // Just make sure
                if (!$obj->isAchieved() && !$obj->isIgnored()){
                    $results[] = $obj;
                }

            }

        }

        return $results;

    }

    /**
     * Count number of targets of a given status the student has
     * @param type $statusID
     * @return string
     */
    public function countUserTargetsByStatus($statusID){

        if (!$this->student) return '-';

        // Academic Year
        $academicYearUnix = $this->getAcademicYearUnix();
        if ($academicYearUnix)
        {
            return $this->DB->count_records_select("lbp_targets", "studentid = ? AND status = ? AND del = 0 AND settime >= ?", array($this->student->id, $statusID, $academicYearUnix));
        }
        else
        {
            return $this->DB->count_records("lbp_targets", array("studentid"=>$this->student->id, "status"=>$statusID, "del" => 0));
        }

    }

    /**
     * Get the progress bar/info for the block content
     */
    public function _getBlockProgress()
    {

        global $CFG;

        $output = "";

        // Number of targets achieved
        $ach = 0;
        $achStatus = $this->getAchievedStatusIDs();
        if ($achStatus)
        {
            foreach($achStatus as $status)
            {
                $ach += $this->countUserTargetsByStatus($status);
            }

        }

        // Number waiting to be achieved
        $pending = 0;
        $pending += count( $this->getPendingTargets() );

        // Total
        $total = $ach + $pending;

        $output .= "<div>";
            $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/progress_bar.png' alt='progress_bar' /> {$ach}/{$total} " . get_string('targetsachieved', 'block_elbp');
        $output .= "</div>";

        return $output;

    }


    /**
     * Count the number of Targets in given circumstance
     * @param type $params
     */
    public function _retrieveHook_Count($params)
    {

        global $DB;

        if (!$this->isEnabled()) return false;

        if (!isset($params['table']) || !isset($params['where'])){
            throw new \ELBP\ELBPException( $this->title, get_string('retrievehookinvalidparams', 'block_elbp'), "table,where" );
            return false;
        }

        $records = $DB->count_records($params['table'], $params['where']);
        return $records;

    }

    /**
     * Count the number of achieved Targets in given circumstance
     * @param type $params
     */
    public function _retrieveHook_CountAchieved($params)
    {

        global $DB;

        if (!$this->isEnabled()) return false;

        if (!isset($params['table']) || !isset($params['where']) || !isset($params['value'])){
            throw new \ELBP\ELBPException( $this->title, get_string('retrievehookinvalidparams', 'block_elbp'), "table,where,value" );
            return false;
        }

        $where = "";
        $whereParam = array();
        $cntWhere = count($params['where']);
        $n = 0;

        foreach($params['where'] as $field => $val)
        {
            $n++;

            if (is_array($val))
            {
                $placeHolder = rtrim( str_repeat('?, ', count($val)), ', ' );
                $where .= " {$field} IN ({$placeHolder}) ";
                foreach($val as $v)
                {
                    $whereParam[] = $v;
                }
                if ($n < $cntWhere){
                    $where .= " AND ";
                }
            }
            else
            {
                $where .= " {$field} = ? ";
                if ($n < $cntWhere){
                    $where .= " AND ";
                }
                $whereParam[] = $val;
            }



        }

        $list = $DB->get_records_select($params['table'], $where, $whereParam, $params['value']);

        $cnt = 0;

        if ($list)
        {
            foreach($list as $target)
            {
                $targetObj = new \ELBP\Plugins\Targets\Target($target->$params['value']);
                if ($targetObj->isAchieved()){
                    $cnt++;
                }
            }
        }

        return $cnt;

    }


     /**
     * Count the number of not achieved Targets in given circumstance
     * @param type $params
     */
    public function _retrieveHook_CountNotAchieved($params)
    {

        global $DB;

        if (!$this->isEnabled()) return false;

        if (!isset($params['table']) || !isset($params['where']) || !isset($params['value'])){
            throw new \ELBP\ELBPException( $this->title, get_string('retrievehookinvalidparams', 'block_elbp'), "table,where,value" );
            return false;
        }

        $where = "";
        $whereParam = array();
        $cntWhere = count($params['where']);
        $n = 0;

        foreach($params['where'] as $operator => $arr)
        {

            foreach($arr as $field => $val)
            {

                $n++;

                if (is_array($val))
                {
                    if (!empty($val))
                    {
                        $placeHolder = rtrim( str_repeat('?, ', count($val)), ', ' );
                        $where .= " {$field} IN ({$placeHolder}) ";
                        foreach($val as $v)
                        {
                            $whereParam[] = $v;
                        }
                        if ($n < $cntWhere){
                            $where .= " AND ";
                        }
                    }
                }
                else
                {
                    $where .= " {$field} {$operator} ? ";
                    if ($n < $cntWhere){
                        $where .= " AND ";
                    }
                    $whereParam[] = $val;
                }

            }

            $where .= " AND ";


        }

        $where = rtrim($where, " AND ");

        $list = $DB->get_records_select($params['table'], $where, $whereParam, $params['value']);

        $cnt = 0;

        if ($list)
        {
            foreach($list as $target)
            {
                $targetObj = new \ELBP\Plugins\Targets\Target($target->$params['value']);
                if (!$targetObj->isAchieved()){
                    $cnt++;
                }
            }
        }

        return $cnt;

    }



    /**
     * Retrieve the hooked data from a given table for Target information
     * Return it in just a list format - date and title with link to open up in ELBP
     * @global type $DB
     * @param array $params
     * @return boolean|string
     * @throws \ELBP\ELBPException
     */
    public function _retrieveHook_List($params)
    {

        global $DB;

        if (!$this->isEnabled()) return false;

        if (!isset($params['table']) || !isset($params['where']) || !isset($params['value'])){
            throw new \ELBP\ELBPException( $this->title, get_string('retrievehookinvalidparams', 'block_elbp'), "table,where,value" );
            return false;
        }


        $output = "";

        $where = "";
        $whereParam = array();
        $cntWhere = count($params['where']);
        $n = 0;

        foreach($params['where'] as $field => $val)
        {
            $n++;

            if (is_array($val))
            {
                if (!empty($val))
                {
                    $placeHolder = rtrim( str_repeat('?, ', count($val)), ', ' );
                    $where .= " {$field} IN ({$placeHolder}) ";
                    foreach($val as $v)
                    {
                        $whereParam[] = $v;
                    }
                    if ($n < $cntWhere){
                        $where .= " AND ";
                    }
                }
            }
            else
            {
                $where .= " {$field} = ? ";
                if ($n < $cntWhere){
                    $where .= " AND ";
                }
                $whereParam[] = $val;
            }



        }

        $records = $DB->get_records_select($params['table'], $where, $whereParam, null, $params['value']);
        $results = array();
        if ($records)
        {
            foreach($records as $record)
            {

                $target = new \ELBP\Plugins\Targets\Target($record->$params['value']);
                if ($target->isValid())
                {

                    $results[] = $target;

                }
            }
        }

        // Order by set time
        usort($results, function($a, $b){
            return ($a->getDeadline() < $b->getDeadline()) ? 1 : -1;
        });

        return $results;

    }

    /**
     * Run automated event for if targets pass their deadlines
     * @param type $event
     * @return int Number of users affected
     */
    public function AutomatedEvent_target_deadline_passes($event)
    {

        global $DB;

        $cnt = 0;
        $EmailAlert = new \ELBP\EmailAlert();

        $ELBPDB = new \ELBP\DB();

        // Firstly find the users who have this event enabled for alerts
        $userEvents = $DB->get_records_sql("SELECT a.*, e.name
                                            FROM {lbp_alerts} a
                                            INNER JOIN {lbp_alert_events} e ON e.id = a.eventid
                                            WHERE a.eventid = ?
                                            AND a.value = ?", array($event->id, 1));


        if ($userEvents)
        {

            $studentValues = array(); # Values of attendance from DB
            $userArray = array(); # Users from DB
            $processedStudents = array(); # Students we have got values for and alerted for each user, so as not to repeat
            $courseArray = array();
            $courseStudentsArray = array();

            foreach($userEvents as $userEvent)
            {

                // If already got this user before, get from array instead of another db call
                if (isset($userArray[$userEvent->userid]))
                {
                    $user = $userArray[$userEvent->userid];
                }
                else
                {
                    $user = $DB->get_record("user", array("id" => $userEvent->userid));
                    if (!$user) continue;
                    $userArray[$userEvent->userid] = $user;
                }


                // No attributes required here



                // If the userEvent is for an individual student, great, let's just check their targets
                if (!is_null($userEvent->studentid))
                {

                    // If we have already processed this student for this user, continue
                    if (isset($processedStudents[$user->id]) &&
                       in_array($userEvent->studentid, $processedStudents[$user->id])){
                       continue;
                    }


                    // Continue only if we succeed in loading this student
                    if ($this->loadStudent($userEvent->studentid))
                    {

                        // Find all of students targets that are pending
                        // And that are passed the deadline
                        // If we've already got the targets for this student, get frmo array instead of DB call
                        if (isset($studentValues[$userEvent->studentid])){
                            $targets = $studentValues[$userEvent->studentid];
                        } else {
                            $targets = $this->getPendingTargets();
                            $studentValues[$userEvent->studentid] = $targets;
                        }

                        if ($targets)
                        {
                            foreach($targets as $target)
                            {

                                // Has the target passed its deadline?
                                // Also, was that deadline within the last month? Otherwise we will keep getting alerts every week for targets year old if they never complete them
                                $monthAgo = strtotime('-1 month');
                                if ($target->getDeadline() <= time() && $target->getDeadline() >= $monthAgo)
                                {

                                    // Check that we haven't sent this user an alert about this target recently
                                    $params = array(
                                        "userID" => $user->id,
                                        "studentID" => $userEvent->studentid,
                                        "eventID" => $userEvent->eventid,
                                        "attributes" => array(
                                            "target" => $target->getID()
                                        )
                                    );

                                    // If this returns true that means we have sent this exact alert within the last week, so skip
                                    $checkHistory = \ELBP\Alert::checkHistory( $params );
                                    if ($checkHistory){
                                        continue;
                                    }

                                    // Create the message to send
                                    $subject = $this->title . " :: ".get_string('targetdeadline', 'block_elbp')." :: " . fullname($this->student) . " ({$this->student->username})";
                                    $content = get_string('student', 'block_elbp') . ": " . fullname($this->student) . " ({$this->student->username})\n";
                                    $content .= get_string('targetname', 'block_elbp') . ": " . $target->getName() . "\n" .
                                                get_string('deadline', 'block_elbp') . ": " . $target->getDueDate() . "\n" .
                                                get_string('late', 'block_elbp') . ": " . $target->getLateOrRemaining() . "\n\n" .
                                                str_replace("%event%", $userEvent->name, get_string('alerts:receieving:student', 'block_elbp')) . ": " . fullname($this->student) . " ({$this->student->username})";

                                    // Log the history of this alert, so we don't do the exact same one tomorrow/whenever next run
                                    $params = array(
                                        "userID" => $user->id,
                                        "studentID" => $this->student->id,
                                        "eventID" => $userEvent->eventid,
                                        "attributes" => array(
                                            "target" => $target->getID(),
                                        )
                                    );

                                    $historyID = \ELBP\Alert::logHistory($params);

                                    // Now queue it, sending the history ID as well so we can update when actually sent
                                    $EmailAlert->queue("email", $user, $subject, $content, nl2br($content), $historyID);
                                    $cnt++;

                                }

                            }
                        }

                        // Append info to $processedStudents so that we don't do the same student again for this user
                        if (!isset($processedStudents[$user->id])) $processedStudents[$user->id] = array();
                        $processedStudents[$user->id][] = $userEvent->studentid;


                    }

                }



                // Course
                elseif (!is_null($userEvent->courseid))
                {

                    // If already used, get from array, else get from DB and put into array
                    if (isset($courseArray[$userEvent->courseid])){
                        $course = $courseArray[$userEvent->courseid];
                    } else {
                        $course = $ELBPDB->getCourse(array("type" => "id", "val" => $userEvent->courseid));
                        $courseArray[$userEvent->courseid] = $course;
                    }

                    if (!$course) continue;

                    // Find students on that group
                    if (isset($courseStudentsArray[$course->id])){
                        $students = $courseStudentsArray[$course->id];
                    } else {
                        $students = $ELBPDB->getStudentsOnCourse($course->id);
                        $courseStudentsArray[$course->id] = $students;
                    }

                    if (!$students) continue;

                    // Loop through students on group
                    foreach($students as $student)
                    {

                        // If we have already processed this student for this user, continue
                        if (isset($processedStudents[$user->id]) &&
                           in_array($student->id, $processedStudents[$user->id])){
                           continue;
                        }

                        // Continue only if we succeed in loading this student
                        if ($this->loadStudent($student->id))
                        {

                            // Find all of students targets that are pending and that are passed the deadline
                            // If we've already got the targets for this student, get from array instead of DB call
                            if (isset($studentValues[$student->id])){
                                $targets = $studentValues[$student->id];
                            } else {
                                $targets = $this->getPendingTargets();
                                $studentValues[$student->id] = $targets;
                            }

                            if ($targets)
                            {
                                foreach($targets as $target)
                                {

                                    // Has the target passed its deadline? ANd was it due in the last month?
                                    $monthAgo = strtotime('-1 month');
                                    if ($target->getDeadline() <= time() && $target->getDeadline() >= $monthAgo)
                                    {

                                        // Check that we haven't sent this user an alert about this target recently
                                        $params = array(
                                            "userID" => $user->id,
                                            "studentID" => $student->id,
                                            "eventID" => $userEvent->eventid,
                                            "attributes" => array(
                                                "target" => $target->getID()
                                            )
                                        );

                                        // If this returns true that means we have sent this exact alert within the last week, so skip
                                        $checkHistory = \ELBP\Alert::checkHistory( $params );
                                        if ($checkHistory){
                                            continue;
                                        }

                                        // Create the message to send
                                        $subject = $this->title . " :: ".get_string('targetdeadline', 'block_elbp')." :: " . fullname($this->student) . " ({$this->student->username})";
                                        $content = get_string('student', 'block_elbp') . ": " . fullname($this->student) . " ({$this->student->username})\n";
                                        $content .= get_string('targetname', 'block_elbp') . ": " . $target->getName() . "\n" .
                                                    get_string('deadline', 'block_elbp') . ": " . $target->getDueDate() . "\n" .
                                                    get_string('late', 'block_elbp') . ": " . $target->getLateOrRemaining() . "\n\n" .
                                                    str_replace("%event%", $userEvent->name, get_string('alerts:receieving:course', 'block_elbp')) . ": {$course->fullname} ({$course->shortname})";

                                        // Log the history of this alert, so we don't do the exact same one tomorrow/whenever next run
                                        $params = array(
                                            "userID" => $user->id,
                                            "studentID" => $this->student->id,
                                            "eventID" => $userEvent->eventid,
                                            "attributes" => array(
                                                "target" => $target->getID(),
                                            )
                                        );

                                        $historyID = \ELBP\Alert::logHistory($params);

                                        // Now queue it, sending the history ID as well so we can update when actually sent
                                        $EmailAlert->queue("email", $user, $subject, $content, nl2br($content), $historyID);
                                        $cnt++;

                                    }

                                }

                            }

                            // Append info to $processedStudents so that we don't do the same student again for this user
                            if (!isset($processedStudents[$user->id])) $processedStudents[$user->id] = array();
                            $processedStudents[$user->id][] = $student->id;

                        }

                    }

                }



                // Mentees or Additional Support
                elseif ($userEvent->mass == 'mentees' || $userEvent->mass == 'addsup')
                {

                    // Find all of this user's mentees
                    if ($userEvent->mass == 'mentees'){
                        $students = $ELBPDB->getMenteesOnTutor($userEvent->userid);
                    } elseif ($userEvent->mass == 'addsup'){
                        $students = $ELBPDB->getStudentsOnAsl($userEvent->userid);
                    }

                    if ($students)
                    {
                        foreach($students as $student)
                        {

                            // If we have already processed this student for this user, continue
                            if (isset($processedStudents[$user->id]) &&
                               in_array($student->id, $processedStudents[$user->id])){
                               continue;
                            }

                            // Continue only if we succeed in loading this student
                            if ($this->loadStudent($student->id))
                            {

                                // Find all of students targets that are pending
                                // And that are passed the deadline
                                // If we've already got the targets for this student, get frmo array instead of DB call
                                if (isset($studentValues[$this->student->id])){
                                    $targets = $studentValues[$this->student->id];
                                } else {
                                    $targets = $this->getPendingTargets();
                                    $studentValues[$this->student->id] = $targets;
                                }

                                if ($targets)
                                {
                                    foreach($targets as $target)
                                    {

                                        // Has the target passed its deadline? and due in the last month?
                                        $monthAgo = strtotime('-1 month');
                                        if ($target->getDeadline() <= time() && $target->getDeadline() >= $monthAgo)
                                        {

                                            // Check that we haven't sent this user an alert about this target recently
                                            $params = array(
                                                "userID" => $user->id,
                                                "studentID" => $this->student->id,
                                                "eventID" => $userEvent->eventid,
                                                "attributes" => array(
                                                    "target" => $target->getID()
                                                )
                                            );

                                            // If this returns true that means we have sent this exact alert within the last week, so skip
                                            $checkHistory = \ELBP\Alert::checkHistory( $params );
                                            if ($checkHistory){
                                                continue;
                                            }

                                            // Create the message to send
                                            $subject = $this->title . " :: ".get_string('targetdeadline', 'block_elbp')." :: " . fullname($this->student) . " ({$this->student->username})";
                                            $content = get_string('student', 'block_elbp') . ": " . fullname($this->student) . " ({$this->student->username})\n";
                                            $content .= get_string('targetname', 'block_elbp') . ": " . $target->getName() . "\n" .
                                                        get_string('deadline', 'block_elbp') . ": " . $target->getDueDate() . "\n" .
                                                        get_string('late', 'block_elbp') . ": " . $target->getLateOrRemaining() . "\n\n" .
                                                        str_replace("%event%", $userEvent->name, get_string('alerts:receieving:'.$userEvent->mass, 'block_elbp'));

                                            // Log the history of this alert, so we don't do the exact same one tomorrow/whenever next run
                                            $params = array(
                                                "userID" => $user->id,
                                                "studentID" => $this->student->id,
                                                "eventID" => $userEvent->eventid,
                                                "attributes" => array(
                                                    "target" => $target->getID(),
                                                )
                                            );

                                            $historyID = \ELBP\Alert::logHistory($params);

                                            // Now queue it, sending the history ID as well so we can update when actually sent
                                            $EmailAlert->queue("email", $user, $subject, $content, nl2br($content), $historyID);
                                            $cnt++;

                                        }

                                    }
                                }

                                // Append info to $processedStudents so that we don't do the same student again for this user
                                if (!isset($processedStudents[$user->id])) $processedStudents[$user->id] = array();
                                $processedStudents[$user->id][] = $this->student->id;


                            }

                        }

                    }

                }

            }

        }

        return $cnt;

    }


   /**
    * Print out to simple html
    * @global type $ELBP
    * @param type $targetID If set print this target, otherwise print all
    * @param type $studentID If targetid not set, studentid needs to be set so we know which student to
    * print all targets for
    * @return boolean
    */
    public function printOut($targetID = null, $studentID = null)
    {

        global $ELBP;

        if (!is_null($targetID))
        {

            if (is_numeric($targetID))
            {

                $target = new \ELBP\Plugins\Targets\Target($targetID);
                if (!$target->isValid()){
                    return false;
                }

                // Get our access for the student who this belongs to
                $access = $ELBP->getUserPermissions( $target->getStudentID() );
                if (!elbp_has_capability('block/elbp:print_target', $access)){
                    echo get_string('invalidaccess', 'block_elbp');
                    return false;
                }

                // Carry on
                $target->setTargetsObject($this);
                $target->printOut();
                return true;

            }
            elseif ($targetID == 'all' && is_numeric($studentID))
            {

                // Get our access for the student who this belongs to
                $access = $ELBP->getUserPermissions( $studentID );
                if (!elbp_has_capability('block/elbp:print_all_targets', $access)){
                    echo get_string('invalidaccess', 'block_elbp');
                    return false;
                }

                $this->loadStudent($studentID);
                $targets = $this->getUserTargets();

                // Order DESC instead of ASC
                usort($targets, function($a, $b){
                    return ($a->getDeadline() < $b->getDeadline());
                });

                $this->printOutAll($targets);
                return true;

            }

        }

    }


    /**
     * Print of an array of targets on one page
     * @param type $incidents
     */
    private function printOutAll($targets)
    {

        global $CFG, $USER;


        $attributes = $this->getAttributesForDisplay();

        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('targets', 'block_elbp');
        $title = get_string('targets', 'block_elbp');
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';
        $logo = \ELBP\ELBP::getPrintLogo();

        $txt = "";

        if ($targets)
        {

            foreach($targets as $target)
            {

                $target->loadObjectIntoAttributes($attributes);

                $txt .= "<div class='print_target'>";
                $txt .= "<div class='c'><h3>{$target->getName()}</h3></div>";

                $txt .= "<table class='info'>";
                    $txt .= "<tr><td>".get_string('dateset', 'block_elbp').": {$target->getSetDate()}</td><td>".get_string('setby', 'block_elbp').": {$target->getStaffName()}</td><td>".get_string('deadline', 'block_elbp').": {$target->getDueDate()}</td></tr>";
                    $txt .= "<tr><td>".get_string('targetstatus', 'block_elbp').": {$target->getStatusName()}</td><td></td><td>".get_string('targetprogress', 'block_elbp').": {$target->getProgress()}%</td></tr>";

                    // Side attributes
                    $sideAttributes = $this->getAttributesForDisplayDisplayType("side", $attributes);
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
                $mainAttributes = $this->getAttributesForDisplayDisplayType("main", $attributes);
                if ($mainAttributes)
                {

                    foreach($mainAttributes as $attribute)
                    {

                        if ($attribute->display == 'main')
                        {
                            $txt .= "<div class='attribute-main'><p class='b'>{$attribute->name}</p><p>".$attribute->displayValue(true). "</p></div>";
                        }

                    }
                }

                $txt .= "<hr>";

                // Comments
                $txt .= "<b>".get_string('targetcomments', 'block_elbp')."</b><br><br>";
                $txt .= $target->displayPdfComments();
                $txt .= "</div>";

            }

        }


        $TPL = new \ELBP\Template();
        $TPL->set("logo", $logo);
        $TPL->set("pageTitle", $pageTitle);
        $TPL->set("title", $title);
        $TPL->set("heading", $heading);
        $TPL->set("content", $txt);
        $TPL->set("css", $CFG->wwwroot . '/blocks/elbp/plugins/Targets/print.css');


        $TPL->load( $CFG->dirroot . '/blocks/elbp/tpl/print.html' );
        $TPL->display();
        exit;

    }

    /**
     * Yes
     * @return boolean
     */
    protected function supportsStudentProgress()
    {
        return true;
    }

    /**
     * Targets can have the definitions:
     * - Each target +1
     * - Set number of targets +1
     * - Set number of targets achieved +1
     */
    protected function getStudentProgressDefinitionForm()
    {

        $output = "";

        $output .= "<table class='student-progress-definitions'>";

            $output .= "<tr>";

                $output .= "<th></th>";
                $output .= "<th>".get_string('value', 'block_elbp')."</th>";
                $output .= "<th>".get_string('description')."</th>";
                $output .= "<th>".get_string('importance', 'block_elbp')."</th>";

            $output .= "</tr>";


            $output .= "<tr>";
                $chk = ($this->getSetting('student_progress_definitions_each') == 1) ? 'checked' : '';
                $output .= "<td><input type='checkbox' name='student_progress_definitions_each' value='1' {$chk} /></td>";
                $output .= "<td></td>";
                $output .= "<td>".get_string('studentprogressdefinitions:eachtarget', 'block_elbp')."</td>";
                $output .= "<td><input type='number' min='0.5' step='0.5' class='elbp_smallish' name='student_progress_definition_importance_each' value='{$this->getSetting('student_progress_definition_importance_each')}' /></td>";
            $output .= "</tr>";


            $output .= "<tr>";
                $chk = ($this->getSetting('student_progress_definitions_req') == 1) ? 'checked' : '';
                $output .= "<td><input type='checkbox' name='student_progress_definitions_req' value='1' {$chk} /></td>";
                $output .= "<td><input type='text' class='elbp_small' name='student_progress_definition_values_req' value='{$this->getSetting('student_progress_definition_values_req')}' /></td>";
                $output .= "<td>".get_string('studentprogressdefinitions:reqnumtargets', 'block_elbp')."</td>";
                $output .= "<td><input type='number' min='0.5' step='0.5' class='elbp_smallish' name='student_progress_definition_importance_req' value='{$this->getSetting('student_progress_definition_importance_req')}' /></td>";
            $output .= "</tr>";


            $output .= "<tr>";
                $chk = ($this->getSetting('student_progress_definitions_reqach') == 1) ? 'checked' : '';
                $output .= "<td><input type='checkbox' name='student_progress_definitions_reqach' value='1' {$chk} /></td>";
                $output .= "<td><input type='text' class='elbp_small' name='student_progress_definition_values_reqach' value='{$this->getSetting('student_progress_definition_values_reqach')}' /></td>";
                $output .= "<td>".get_string('studentprogressdefinitions:reqnumtargetsachieved', 'block_elbp')."</td>";
                $output .= "<td><input type='number' min='0.5' step='0.5' class='elbp_smallish' name='student_progress_definition_importance_reqach' value='{$this->getSetting('student_progress_definition_importance_reqach')}' /></td>";
            $output .= "</tr>";

        $output .= "</table>";

        return $output;

    }

    /**
     * Calculate target bits of overall student progress
     * @return type
     */
     public function calculateStudentProgress(){

        $max = 0;
        $num = 0;
        $info = array();

        $targets = $this->getUserTargets();
        $cnt = count($targets);
        $cntAch = 0;

        // Each target
        if ($this->getSetting('student_progress_definitions_each') == 1)
        {

            // Since this is done for each target, it's slightly different
            // E.g. with Importance of 0.5, say we had 5 targets and 3 achieved, that would add 2.5 to max
            // and 1.5 to num
            $importance = $this->getSetting('student_progress_definition_importance_each');
            $max += ($importance * $cnt);

            if ($targets)
            {
                foreach($targets as $target)
                {
                    if ($target->isAchieved())
                    {
                        $num += $importance;
                        $cntAch++;
                    }
                }
            }

            $key = get_string('studentprogress:info:targets:each', 'block_elbp');
            $percent = ($cnt > 0) ? round( ($cntAch / $cnt) * 100 ) : 100;
            $info[$key] = array(
                    'percent' => ($percent > 100) ? 100 : $percent,
                    'value' => $cntAch . '/' . $cnt
                );

        }

        // Set number required
        if ($this->getSetting('student_progress_definitions_req') == 1)
        {

            $req = $this->getSetting('student_progress_definition_values_req');
            if ($req > 0)
            {

                $importance = $this->getSetting('student_progress_definition_importance_req');

                // E.g. if they need to have a minimum of 5, add 5 to the max
                $max += $importance;

                // If they have less, add the amount they do have to the num, e.g. that might be 3, which is then 3/5
                if ($cnt < $req)
                {
                    $diff = ($cnt / $req) * 100;
                    $val = ($diff / 100) * $importance;
                    $num += $val;
                }
                else
                {
                    // Otherwise add the max as we've got all the ones we need
                    $num += $importance;
                }

                $key = get_string('studentprogress:info:targets:req', 'block_elbp');
                $key = str_replace('%n%', $req, $key);
                $percent = round( ($cnt / $req) * 100 );
                $info[$key] = array(
                    'percent' => ($percent > 100) ? 100 : $percent,
                    'value' => $cnt
                );

            }

        }



        // Set number achieved required
        if ($this->getSetting('student_progress_definitions_reqach') == 1)
        {

            $req = $this->getSetting('student_progress_definition_values_reqach');
            if ($req > 0)
            {

                $importance = $this->getSetting('student_progress_definition_importance_reqach');

                // E.g. if they need to have a minimum of 5, add 5 to the max
                $max += $importance;

                // If they have less, add the amount they do have to the num, e.g. that might be 3, which is then 3/5
                $cntAch = 0;
                if ($targets)
                {
                    foreach($targets as $target)
                    {
                        if ($target->isAchieved())
                        {
                            $cntAch++;
                        }
                    }
                }

                if ($cntAch < $req)
                {
                    $diff = ($cntAch / $req) * 100;
                    $val = ($diff / 100) * $importance;
                    $num += $val;
                }
                else
                {
                    $num += $importance;
                }

                $key = get_string('studentprogress:info:targets:reqach', 'block_elbp');
                $key = str_replace('%n%', $req, $key);
                $percent = round( ($cntAch / $req) * 100 );
                $info[$key] = array(
                    'percent' => ($percent > 100) ? 100 : $percent,
                    'value' => $cntAch
                );

            }

        }


        return array(
            'max' => $max,
            'num' => $num,
            'info' => $info
        );

    }


    /**
     * Update attribute names in the actual user attributes data table
     * @param type $newNames
     * @param type $oldNames
     */
    public function updateChangedAttributeNames($newNames, $oldNames)
    {

        global $DB;

        // Loop through attribute names and see if any are different
        if ($newNames)
        {
            for ($i = 0; $i < count($newNames); $i++)
            {

                if (!isset($oldNames[$i])) continue;

                $newName = $newNames[$i];
                $oldName = $oldNames[$i];

                // Name has changed
                if ($newName !== $oldName)
                {

                    // Update all references to the old name to the new name
                    $DB->execute("UPDATE {lbp_target_attributes} SET field = ? WHERE field = ?", array($newName, $oldName));

                }

            }
        }

        return true;

    }


}