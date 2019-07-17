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

require_once 'Session.class.php';

/**
 *
 */
class AdditionalSupport extends Plugin {

    const default_confidence_limit = 5;

    public $supportedHooks;

    protected $tables = array(
        'lbp_add_sup_attributes',
        'lbp_add_sup_comments',
        'lbp_add_sup_sessions'
    );

    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {

        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Additional Support",
                "path" => null,
                "version" => \ELBP\ELBP::getBlockVersionStatic()
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
            $this->loadDefaultAttributes();
        }

        $this->supportedHooks = array(
            'Targets' => array(
                'Targets'
            ),
            'elbp_bksb' => array(
                'English IA',
                'Maths IA',
                'ICT IA'
            ),
            'elbp_bksblive' => array(
                'English IA',
                'Maths IA',
                'ICT IA'
            )
        );

    }

    /**
     * Override loadStudent to disable this plugin if student has no additional support
     * @param type $studentID
     */
//    public function loadStudent($studentID, $fromBlock = false) {
//
//        if (!parent::loadStudent($studentID)) return false;
//
//        // If student not linked to an ASL, disable on this student's ELBP
//        $ELBPDB = new \ELBP\DB();
//        if ($ELBPDB->countStudentAsls($this->student->id) == 0){
//            $this->disable();
//            return false;
//        }
//
//        return true;
//
//    }

    /**
     * Install the plugin
     */
    public function install()
    {

        global $DB;

        $pluginID = $this->createPlugin();
        $return = true && $pluginID;

        // This is a core ELBP plugin, so the extra tables it requires are handled by the core ELBP install.xml


        // Default settings
        $settings = array();
        $settings['limit_summary_list'] = 5;
        $settings['confidence_enabled'] = 1;
        $settings['limit_confidence'] = 5;
        $settings['confidence_target_progress_enabled'] = 1;
        $settings['lock_targets_after_deadline'] = 1;
        $settings['delete_targets_on_delete'] = 1;
        $settings['interested_parties_enabled'] = 0;
        $settings['long_term_aim_enabled'] = 1;
        $settings['attributes'] = 'elbpform:[{"id":"SS4RH6Z919","name":"Session Information","type":"Moodle Text Editor","display":"main","default":"","instructions":"Enter the details of what was discussed in the Additional Support session here","options":false,"validation":["REQUIRED"],"other":[],"studentID":false,"usersValue":false,"obj":null}]';

        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }

        // Alert events
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Additional Support Session Added", "description" => "A new additional support session is added into the system", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Additional Support Session Comment Added", "description" => "A comment is added onto a additional support session", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Additional Support Session Updated", "description" => "An additional support session is updated", "auto" => 0, "enabled" => 1));

        return $return;

    }

    /**
     * Truncate all the related tables and then run normal uninstall
     * @global type $DB
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

        $result = true;
        $version = $this->version; # This is the current DB version we will be using to upgrade from

        // [Upgrades here]

    }


    /**
     * Save the config
     * @global type $MSGS
     * @global \ELBP\Plugins\type $DB
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {

        global $MSGS, $DB;

        // Save the attributes
        if(isset($_POST['submit_attributes']))
        {
            \elbp_save_attribute_script($this);
            return true;
        }

        // Save the plugin hooks
        elseif(isset($_POST['submit_hooks']))
        {

            $hooks = (isset($_POST['hooks'])) ? $_POST['hooks'] : false;

            // Clear all records - We could just check afterwards which ones are in the DB that weren't specified here and delete those, but cba
            $DB->delete_records("lbp_plugin_hooks", array("pluginid" => $this->id));

            if($hooks)
            {
                foreach($hooks as $hook)
                {

                    $data = new \stdClass();
                    $data->pluginid = $this->id;
                    $data->hookid = $hook;
                    $DB->insert_record("lbp_plugin_hooks", $data);

                }
            }

            return true;

        }

        // Do the rest of the saving
        parent::saveConfig($settings);
        return true;

     }


    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){

        global $USER;

        $TPL = new \ELBP\Template();

        $TPL->set("obj", $this);

        $listLimit = $this->getSetting('limit_summary_list');
        $sessions = $this->getUserSessions($listLimit);

        $TPL->set("sessions", $sessions);

        $hasAdditionalSupport = false;

        $ELBPDB = new \ELBP\DB();
        if ($ELBPDB->countStudentAsls($this->student->id) > 0){
            $hasAdditionalSupport = true;
        }

        $TPL->set("hasAdditionalSupport", $hasAdditionalSupport);

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Get all the user's additional support sessions
     * @global \ELBP\Plugins\type $DB
     * @param int $limit
     * @return boolean|\ELBP\Plugins\AdditionalSupport\Session
     */
    private function getUserSessions($limit = null)
    {

        global $DB;

        if (!$this->student) return false;

        // Academic Year
        $academicYearUnix = $this->getAcademicYearUnix();

        $results = array();

        $records = $this->DB->get_records('lbp_add_sup_sessions', array("studentid" => $this->student->id, "del" => 0), "sessiondate DESC, id DESC", "id", 0, $limit);

        if ($records)
        {
            foreach($records as $record)
            {

                $obj = new \ELBP\Plugins\AdditionalSupport\Session($record->id);

                // If we're using academic year and the date of this session was prior to that, don't include
                if ($academicYearUnix && $obj->getSetTime() < $academicYearUnix){
                    continue;
                }

                if ($obj->isValid())
                {
                    $obj->setAdditionalSupportObj($this);
                    $results[] = $obj;
                }
            }
        }


        return $results;

    }

    /**
     *
     * @return type
     */
    public function getConfidenceLimit(){
        $setting = $this->getSetting('limit_confidence');
        return ($setting && $setting > 1) ? $setting : self::default_confidence_limit;
    }

    /**
     * Print the session out
     * @global type $ELBP
     * @param int $sessionID If set print out this session. Otherwise print all (not done yet)
     * @return boolean
     */
    public function printOut($sessionID = null)
    {

        global $ELBP;

        if (!is_null($sessionID))
        {

            $session = new \ELBP\Plugins\AdditionalSupport\Session($sessionID);
            if (!$session->isValid()){
                return false;
            }

            // Get our access for the student who this belongs to
            $access = $ELBP->getUserPermissions( $session->getStudentID() );
            if (!elbp_has_capability('block/elbp:print_additional_support_session', $access)){
                echo get_string('invalidaccess', 'block_elbp');
                return false;
            }

            // Carry on
            $session->setAdditionalSupportObj($this);
            $session->printOut();
            return true;


        }

    }


    /**
     * Handle ajax requests sent to the plugin
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

                if (!isset($params['type'])) return false;
                $page = $params['type'];

                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("access", $access)
                    ->set("page", $page)
                    ->set("ELBP", $ELBP);

                $sessionID = false;

                 if ($page == 'edit'){
                     $sessionID = $params['sessionID'];
                     $page = 'new'; # Use the same form, just check for different capabilities
                 }

                 // if new or edit target need the data
                 if ($page == 'new'){
                     $FORM = new \ELBP\ELBPForm();
                     $FORM->loadStudentID($this->student->id);

                     $data = \ELBP\Plugins\AdditionalSupport\Session::getDataForNewSessionForm($sessionID);
                     $TPL->set("data", $data);
                     $TPL->set("attributes", $this->getAttributesForDisplay());
                     $TPL->set("FORM", $FORM);
                     $TPL->set("hooks", $this->callAllHooks($params));
                     $TPL->set("existingTargets", $this->getExistingTargets());
                 }

                 if ($page == 'all'){
                     $TPL->set("sessions", $this->getUserSessions());
                 }

                 if ($page == 'new' && $this->getSetting('interested_parties_enabled') == 1){

                      $value = (isset($data['hookAtts']['Interested Parties'])) ? $data['hookAtts']['Interested Parties'] : '';
                      $element = new \ELBP\ELBPFORMElement();
                      $element->setType("User Picker");
                      $element->setName( get_string('interestedparties', 'block_elbp') );
                      $element->setValue($value);
                      $TPL->set("interestedParties", $element);

                 }


                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/'.$page.'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;

            break;

            case 'save':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_additional_support_session', $access)) return false;

                $session = new \ELBP\Plugins\AdditionalSupport\Session($params);
                $session->setAdditionalSupportObj($this);

                $auto = (isset($params['auto']) && $params['auto'] == 1);
                $session->setAutoSave($auto);

                // If the session exists, check to make sure the student ID on it is the same as the one we specified
                if ($session->getID() > 0 && $session->getStudentID() <> $params['studentID']) return false;

                // Failed to save for some reason
                if (!$session->save()){

                    echo "$('#new_additional_support_output').html('<div class=\"elbp_err_box\" id=\"add_errors\"></div>');";

                    foreach($session->getErrors() as $error){

                        echo "$('#add_errors').append('<span>{$error}</span><br>');";

                    }

                    exit;

                }


                // Saved OK
                if ($auto){
                    $savedString = get_string('sessionautosaved', 'block_elbp');
                } else {
                    $savedString = get_string('sessionsaved', 'block_elbp');
                }

                echo "$('#new_additional_support_output').html('<div class=\"elbp_success_box\" id=\"add_success\"></div>');";
                echo "$('#add_success').append('<span>{$savedString}</span><br>');";

                // Now we want to reset the form
                // This is awkward since we don't want to reset hidden values like attendance, etc..., and if we've loaded it from
                // a stored state then the default value is infact the value in the input, so resetting won't remove it
                // So we're going to have to go through input type="text" and textarea elements and set their vals to empty string
                // And remove the target hidden elements and the target rows
                if ($params['session_id'] <= 0 && !$auto){
                    echo <<<JS
                        $('#new_additional_support_form :input[type="text"], #new_additional_support_form textarea').val('');
                        $('#new_additional_support_form div.elbp_texteditor').html('');
                        $('.added_target_row').remove();
JS;
                }

                // Set the hidden session id so if we save it again, it updates, doesn't create new one
                if ($params['session_id'] <= 0 && $auto){
                    echo " $('#new_additional_support_form input[name=\"session_id\"]').val('{$session->getID()}'); ";
                }


            break;

            case 'remove_target':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:edit_additional_support_session', $access)) return false;

                // Load up the Session
                $session = new \ELBP\Plugins\AdditionalSupport\Session($params['sessionID']);
                if (!$session->isValid()) return false;

                // Make sure this session is for the student we've said it is
                if ($session->getStudentID() <> $params['studentID']) return false;

                // Load the target
                $target = new \ELBP\Plugins\Targets\Target($params['targetID']);
                if (!$target->isValid()) return false;

                // Make sure this target is in this session
                $sessionTargets = $session->getAllTargets();
                if (!isset($sessionTargets[$target->getID()])) return false;

                if (!$session->removeAttribute("Targets", $target->getID())){
                    echo "$('#new_additional_support_output').html('<div class=\"elbp_err_box\" id=\"add_errors\"></div>');";
                    echo "$('#add_errors').append('<span>".get_string('errors:couldnotdeleterecord', 'block_elbp')."</span><br>');";
                    return false;
                }

                // If confidence is enabled, remove those as well
                if ($this->getSetting('confidence_enabled') == 1){
                    $session->removeAttribute("Targets Confidence Start {$target->getID()}");
                    $session->removeAttribute("Targets Confidence End {$target->getID()}");
                }

                // Is OK
                echo "$('#new_additional_support_output').html('<div class=\"elbp_success_box\" id=\"add_success\"></div>');";
                echo "$('#add_success').append('<span>".get_string('targetremoved', 'block_elbp')."</span><br>');";
                echo "$('#new_added_target_id_{$params['targetID']}').remove();";
                return true;

            break;

            case 'update_target_confidence':


                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['sessionID']) || !isset($params['targetID']) || !isset($params['type']) || !isset($params['value'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:edit_additional_support_target_confidence', $access)) return false;

                // Load up the Session
                $session = new \ELBP\Plugins\AdditionalSupport\Session($params['sessionID']);
                if (!$session->isValid()) return false;

                // Make sure this session is for the student we've said it is
                if ($session->getStudentID() <> $params['studentID']) return false;

                // Load the target
                $targetsObj = $ELBP->getPlugin("Targets");
                $target = new \ELBP\Plugins\Targets\Target($params['targetID']);
                $target->setTargetsObject($targetsObj);
                if (!$target->isValid()) return false;

                // Make sure this target is in this session
                $sessionTargets = $session->getAllTargets();
                if (!isset($sessionTargets[$target->getID()])) return false;

                $session->setAdditionalSupportObj($this);
                $session->setAttribute("Targets Confidence {$params['type']} {$params['targetID']}", $params['value']);

                $outputID = "additional_support_target_output_session_{$params['sessionID']}";

                // Failed to save for some reason
                if (!$session->save()){
                    echo "$('#{$outputID}').html('<div class=\"elbp_err_box\" id=\"update_errors\"></div>');";
                    foreach($session->getErrors() as $error){
                        echo "$('#update_errors').append('<span>{$error}</span><br>');";
                    }
                    exit;
                }

                // If we have target progress bars linked to confidence, update that
                if ($this->getSetting('confidence_target_progress_enabled') == 1 && $params['type'] == 'End'){

                    $oldStatus = $target->getStatus();

                    $percent = round( ($params['value'] / $this->getConfidenceLimit()) * 100 );
                    $target->setProgress($percent);

                    // If progress set to 100% set it to status achieved
                    if ($percent == 100 && ($ach = $target->findAchievedStatus()) && $targetsObj && $targetsObj->getSetting('target_set_achieved_when_100_progress') == 1){
                        $target->setStatusID($ach->id);
                    }

                    if (!$target->save()){
                        echo "$('#{$outputID}').html('<div class=\"elbp_err_box\" id=\"update_errors\"></div>');";
                        foreach($target->getErrors() as $error){
                            echo "$('#update_errors').append('<span>{$error}</span><br>');";
                        }
                        exit;
                    }

                    // Update target link and status
                    echo "$('#update_status_{$target->getID()}').val('{$target->getStatus()}');";
                    echo " var oc = $('#target_link_{$target->getID()}').attr('onclick'); ";
                    echo " $('#target_link_{$target->getID()}').attr('onclick', oc.replace(\"load_targets({$oldStatus},\", \"load_targets({$target->getStatus()},\")); ";

                }

                // Saved OK
                echo "$('#{$outputID}').html('<div class=\"elbp_success_box\" id=\"update_success\"></div>');";
                echo "$('#update_success').append('<span>".get_string('confidenceupdated', 'block_elbp')."</span><br>');";

                exit;

            break;

            case 'update_target_status':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['sessionID']) || !isset($params['targetID']) || !isset($params['statusID'])) return false;

                $target = new \ELBP\Plugins\Targets\Target($params['targetID'], $ELBP->getPlugin("Targets"));
                if (!$target->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($target->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:edit_additional_support_target_status', $access)) return false;

                // Valid Status?
                $status = $target->getStatus($params['statusID']);
                if (!$status) return false;

                $outputID = "additional_support_target_output_session_{$params['sessionID']}";

                $target->setStatusID($params['statusID']);

                if (!$target->save()){
                    echo "$('#{$outputID}').html('<div class=\"elbp_err_box\" id=\"update_errors\"></div>');";
                    foreach($target->getErrors() as $error){
                        echo "$('#update_errors').append('<span>{$error}</span><br>');";
                    }
                    exit;
                }

                // Saved OK
                echo "$('#{$outputID}').html('<div class=\"elbp_success_box\" id=\"update_success\"></div>');";
                echo "$('#update_success').append('<span>".get_string('statusupdated', 'block_elbp')."</span><br>');";

                exit;

            break;

            case 'delete':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['sessionID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:delete_additional_support_session', $access)) return false;

                $session = new \ELBP\Plugins\AdditionalSupport\Session($params['sessionID']);
                if (!$session->isValid()) return false;
                if ($session->getStudentID() <> $params['studentID']) return false;

                $session->setAdditionalSupportObj($this);
                $outputID = 'elbp_additional_support_output';

                if (!$session->delete()){
                    echo "$('#{$outputID}').html('<div class=\"elbp_err_box\" id=\"update_errors\"></div>');";
                    foreach($session->getErrors() as $error){
                        echo "$('#update_errors').append('<span>{$error}</span><br>');";
                    }
                    exit;
                }

                // Saved OK
                echo "$('#{$outputID}').html('<div class=\"elbp_success_box\" id=\"update_success\"></div>');";
                echo "$('#update_success').append('<span>".get_string('sessiondeleted', 'block_elbp')."</span><br>');";
                echo "$('#elbp_additional_support_{$params['sessionID']}').remove();";

                exit;

            break;

            case 'add_comment':

                if (!$params || !isset($params['sessionID']) || !isset($params['comment'])) return false;

                if (elbp_is_empty($params['comment'])) return false;

                $session = new \ELBP\Plugins\AdditionalSupport\Session($params['sessionID'], $this);
                if (!$session->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($session->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_additional_support_session_comment', $access)) return false;

                $session->setAdditionalSupportObj($this);

                // If parent ID is set, make sure that comment is on this target
                if (isset($params['parentID'])){
                    $checkParent = $DB->get_record("lbp_add_sup_comments", array("id" => $params['parentID'], "sessionid" => $session->getID()));
                    if (!$checkParent) return false;
                } else {
                    $params['parentID'] = null;
                }

                // If problem, error message
                if (!$comment = $session->addComment($params['comment'], $params['parentID'])){
                    echo "$('#elbp_comment_add_output_{$session->getID()}').html('<div class=\"elbp_err_box\" id=\"generic_err_box_{$session->getID()}\"></div>');";
                    echo "$('#generic_err_box_{$session->getID()}').append('<span>".get_string('errors:couldnotinsertrecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                // Was OK
                $commentText = substr($comment->comments, 0, 30) . '...';

                // Append new comment box
                if (isset($params['parentID'])){
                    echo "$('#elbp_comment_add_output_comment_{$params['parentID']}').html('<div class=\"elbp_success_box\" id=\"generic_success_box_comment_{$comment->id}\"></div>');";
                    echo "$('#generic_success_box_comment_{$comment->id}').append('<span>".get_string('commentadded', 'block_elbp').": ".elbp_html($commentText, true)."</span><br>');";
                    echo "$('#add_reply_{$params['parentID']}').val('');";
                    echo "$('#comment_{$params['parentID']}').append('<div id=\'comment_{$comment->id}\' class=\'elbp_comment_box\' style=\'width:90%;background-color:{$comment->css->bg};border: 1px solid {$comment->css->bdr};\'><p id=\'elbp_comment_add_output_comment_{$comment->id}\'></p>".elbp_html($comment->comments, true)."<br><br><small><b>{$comment->firstName} {$comment->lastName}</b></small><br><small>".date('D jS M Y H:i', $comment->time)."</small><br><small><a href=\'#\' onclick=\'$(\"#comment_reply_{$comment->id}\").slideToggle();return false;\'>".get_string('reply', 'block_elbp')."</a></small><br><div id=\'comment_reply_{$comment->id}\' class=\'elbp_comment_textarea\' style=\'display:none;\'><textarea id=\'add_reply_{$comment->id}\'></textarea><br><br><input class=\'elbp_big_button\' type=\'button\' value=\'".get_string('submit', 'block_elbp')."\' onclick=\'ELBP.AdditionalSupport.add_comment({$session->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;\' /><br><br></div></div>');";
                } else {
                    echo "$('#elbp_comment_add_output_{$session->getID()}').html('<div class=\"elbp_success_box\" id=\"generic_success_box_{$session->getID()}\"></div>');";
                    echo "$('#generic_success_box_{$session->getID()}').append('<span>".get_string('commentadded', 'block_elbp').": ".elbp_html($commentText, true)."</span><br>');";
                    echo "$('#add_comment_{$session->getID()}').val('');";
                    echo "$('#elbp_comments_content_{$session->getID()}').append('<div id=\'comment_{$comment->id}\' class=\'elbp_comment_box\' style=\'width:90%;background-color:{$comment->css->bg};border: 1px solid {$comment->css->bdr};\'><p id=\'elbp_comment_add_output_comment_{$comment->id}\'></p>".elbp_html($comment->comments, true)."<br><br><small><b>{$comment->firstName} {$comment->lastName}</b></small><br><small>".date('D jS M Y H:i', $comment->time)."</small><br><small><a href=\'#\' onclick=\'$(\"#comment_reply_{$comment->id}\").slideToggle();return false;\'>".get_string('reply', 'block_elbp')."</a></small><br><div id=\'comment_reply_{$comment->id}\' class=\'elbp_comment_textarea\' style=\'display:none;\'><textarea id=\'add_reply_{$comment->id}\'></textarea><br><br><input class=\'elbp_big_button\' type=\'button\' value=\'".get_string('submit', 'block_elbp')."\' onclick=\'ELBP.AdditionalSupport.add_comment({$session->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;\' /><br><br></div></div>');";
                }

                exit;

            break;

            case 'delete_comment':


                if (!$params || !isset($params['sessionID']) || !isset($params['commentID'])) return false;

                $session = new \ELBP\Plugins\AdditionalSupport\Session($params['sessionID'], $this);
                if (!$session->isValid()) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($session->getStudentID());
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $comment = $session->getComment($params['commentID']);
                if (!$comment) return false;

                // If not our comment we need the delete_any_target_comment capability
                if ( $comment->userid <> $USER->id && !elbp_has_capability('block/elbp:delete_any_additional_support_session_comment', $access) ) return false;

                // If it is ours, we need delete_my_taret_comment
                if ( $comment->userid == $USER->id && !elbp_has_capability('block/elbp:delete_my_additional_support_session_comment', $access) ) return false;

                // Delete it
                if (!$session->deleteComment($comment->id)){
                    echo "$('#elbp_comment_generic_output_comment').html('<div class=\"elbp_err_box\" id=\"elbp_comment_generic_output_comment_{$session->getID()}\"></div>');";
                    echo "$('#elbp_comment_generic_output_comment_{$session->getID()}').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                // OK
                echo "$('#elbp_comment_generic_output_comment').html('<div class=\"elbp_success_box\" id=\"elbp_comment_generic_output_comment_{$session->getID()}\"></div>');";
                echo "$('#elbp_comment_generic_output_comment_{$session->getID()}').append('<span>".get_string('commentdeleted', 'block_elbp')."</span><br>');";
                echo "$('#comment_{$comment->id}').remove();";

                exit;

            break;


            case 'save_attribute':

                if (!$params || !isset($params['attribute']) || \elbp_is_empty($params['attribute']) || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:update_additional_support_long_term_aim', $access)) return false;

                $value = (isset($params['value'])) ? $params['value'] : false;

                // Accepted attributes through this form:
                $atts = array('long_term_aim');
                if (!in_array($params['attribute'], $atts)) return false;

                $this->updateSetting($params['attribute'], $value, $params['studentID']);

                // Log Action
                elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_SETTINGS_UPDATED_SETTING, $params['studentID'], array(
                    "attribute" => $params['attribute'],
                    "value" => $value,
                ));


                // Saved OK
                echo "$('#elbp_additional_support_output').html('<div class=\"elbp_success_box\" id=\"add_success\"></div>');";
                echo "$('#add_success').append('<span>".get_string('saved', 'block_elbp')."</span><br>');";

                exit;

            break;


             case 'get_target_row':

                if (!$params || !isset($params['targetID']) || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // Load the target
                $target = new \ELBP\Plugins\Targets\Target($params['targetID']);
                if (!$target->isValid()) return false;

                // Target cannot be linked to any tutorials
                if ($target->isLinkedToAdditionalSupport()) return false;

                $output = "";
                $output .= "<tr class='added_target_row' id='new_added_target_id_{$target->getID()}'>";
                    $output .= "<td><a href='#' onclick='ELBP.AdditionalSupport.edit_target({$target->getID()}, \"Tutorials\");return false;'>".elbp_html($target->getName())."</a></td>";

                    if ($this->getSetting('confidence_enabled') == 1)
                    {


                        $output .= "<td>".get_string('atthestart', 'block_elbp').": <select name='Targets Confidence Start {$target->getID()}'><option value=''></option>";
                        for ($i = 1; $i <= $this->getConfidenceLimit(); $i++)
                        {
                            $output .= "<option value='{$i}' ".((isset($data['hookAtts']['Targets Confidence Start ' . $target->getID()]) && $data['hookAtts']['Targets Confidence Start ' . $target->getID()] == $i) ? 'selected' : '')." >{$i}</option>";
                        }
                        $output .= "</select> &nbsp;&nbsp; ";

                        $output .= get_string('now', 'block_elbp') . ": <select name='Targets Confidence End {$target->getID()}'><option value=''></option>";
                        for ($i = 1; $i <= $this->getConfidenceLimit(); $i++)
                        {
                            $output .= "<option value='{$i}' ".((isset($data['hookAtts']['Targets Confidence End ' . $target->getID()]) && $data['hookAtts']['Targets Confidence End ' . $target->getID()] == $i) ? 'selected' : '')." >{$i}</option>";
                        }

                        $output .= "</select></td>";

                    }

                    $output .= "<td><a href='#' onclick='ELBP.AdditionalSupport.remove_target({$target->getID()});return false;' title='".get_string('remove', 'block_elbp')."'><img src='".$CFG->wwwroot."/blocks/elbp/pix/remove.png' alt='".get_string('remove', 'block_elbp')."' /></a><input type='hidden' name='Targets' value='{$target->getID()}' /></td>";
                $output .= "</tr>";

                echo $output;

                exit;

            break;



        }

    }


    /**
     * Get the progress bar/info for the block content
     * This is pointless
     */
    public function _getBlockProgress()
    {

        $output = "";

        // Number of tutorials set
        $total = count($this->getUserSessions());

        if ($total == 0){
            $percent = 0;
        } else {
            $percent = 100; # This is just a count, not a x / y, so set to 100
        }

        $colours = $this->ELBP->getProgressColours($percent);

        $output .= "<div class='progress-bar {$colours['background']}' style='height: 20px !important;color: {$colours['text']} !important;padding: 2px;width:90%;'>";
            $output .= "<div style='width:{$percent}%;'>";
            $output .= "<small class='elbp_block_progress'>".get_string('additionalsupports', 'block_elbp').": {$total} </small>";
            $output .= "</div>";
        $output .= "</div>";

        return $output;

    }


    /**
     * Get all the targets that are NOT linked to ANY additional support sessions
     * @global type $CFG
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    private function getExistingTargets(){

        global $CFG, $DB;

        if (!$this->student) return false;

        // Find all target the user has that are NOT linked to any tutorials already
        if ($CFG->dbtype == 'pgsql'){
            $targets = $DB->get_records_sql("SELECT t.id, t.name, t.settime
                                         FROM {lbp_targets} t
                                         LEFT JOIN {lbp_add_sup_attributes} a ON (a.field = 'Targets' AND CAST(a.value as int) = t.id)
                                         WHERE t.studentid = ? AND a.id IS NULL AND t.del = 0
                                         ORDER BY t.settime DESC", array($this->student->id));
        } else {

            $targets = $DB->get_records_sql("SELECT t.id, t.name, t.settime
                                             FROM {lbp_targets} t
                                             LEFT JOIN {lbp_add_sup_attributes} a ON (a.field = 'Targets' AND a.value = t.id)
                                             WHERE t.studentid = ? AND a.id IS NULL AND t.del = 0
                                             ORDER BY t.settime DESC", array($this->student->id));

        }

        return $targets;



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
                    $DB->execute("UPDATE {lbp_add_sup_attributes} SET field = ? WHERE field = ?", array($newName, $oldName));

                }

            }
        }

        return true;

    }


}