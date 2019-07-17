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

namespace ELBP\Plugins\AdditionalSupport;

/**
 *
 */
class Session extends \ELBP\BasePluginObject {

    private $id = false;
    private $studentID;
    private $setByUserID;
    private $setTime;
    private $date;
    private $deadline;

    private $comments = array();

    private $errors = array();
    protected $attributes = array();
    private $AdditionalSupport;
    private $student;
    private $staff;
    private $autoSave = false;

    private $targets;


    public function __construct($id) {

        global $DB;

        // If $id is an int, load up that session
        if (is_numeric($id))
        {

            $record = $DB->get_record("lbp_add_sup_sessions", array("id" => $id));
            if ($record)
            {

                $this->id = $record->id;
                $this->studentID = $record->studentid;
                $this->setByUserID = $record->setbyuserid;
                $this->setTime = $record->settime;
                $this->date = $record->sessiondate;
                $this->deadline = $record->deadline;
                $this->del = $record->del;

                $this->staff = $this->getSetByUser();

                $this->loadAttributes();

            }

        }
        // Otherwise we are creating a temp object with data provided in an array
        elseif (is_array($id))
        {

            // Build new one from data provided
            $this->loadData($id);

        }

    }

    /**
     * Is the session valid?
     * @return type
     */
    public function isValid(){
        return ($this->id !== false) ? true : false;
    }

    /**
     * Get the session id
     * @return type
     */
    public function getID(){
        return $this->id;
    }

    /**
     * Get the id of the student loaded in the object
     * @return type
     */
    public function getStudentID(){
        return $this->studentID;
    }

    /**
     * Get the student loaded in the object
     * @global \ELBP\Plugins\AdditionalSupport\type $DB
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
     * Get the id of the user who set this session
     * @return type
     */
    public function getSetByUserID(){
        return $this->setByUserID;
    }

    /**
     * Get the user who set this session
     * @global \ELBP\Plugins\AdditionalSupport\type $DB
     * @return type
     */
    public function getSetByUser(){
        global $DB;
        return $DB->get_record("user", array("id" => $this->setByUserID));
    }

    /**
     * Get the unix timestamp this session was set
     * @return type
     */
    public function getSetTime(){
        return $this->setTime;
    }

    /**
     * Get the date this session was set for
     * @param bool $format If false will return timestamp, else will return date() using specified format
     * @return type
     */
    public function getDate($format = false){
        if (!$format) return $this->date;
        else return date($format, $this->date);
    }

    /**
     * Get the deadline set for this session
     * @param type $format If false will return timestamp, else will return date() using specified format
     * @return type
     */
    public function getDeadline($format = false){
        if (!$format) return $this->deadline;
        else return date($format, $this->deadline);
    }

    /**
     * Get the loaded attributes
     * @return type
     */
    public function getAttributes(){
        return $this->attributes;
    }

    /**
     * Get the name of the member of staff who set the session
     * @return type
     */
    public function getStaffName(){
        return fullname($this->staff);
    }

    /**
     * Get any errors
     * @return type
     */
    public function getErrors(){
        return $this->errors;
    }

    /**
     * Load session attributes
     * @global \ELBP\Plugins\AdditionalSupport\type $DB
     * @return type
     */
    public function loadAttributes(){

        global $DB;

        $check = $DB->get_records("lbp_add_sup_attributes", array("sessionid" => $this->id));

        $this->attributes = parent::_loadAttributes($check);
        return $this->attributes;

    }

    /**
     * Set the Plugin object into the session
     * @param type $obj
     */
    public function setAdditionalSupportObj($obj)
    {
        $this->AdditionalSupport = $obj;
    }

    /**
     * Count the number of targets on this session which have been achieved
     * @return int
     */
    public function countAchievedTargets(){

        if (is_null($this->targets) || empty($this->targets)){
            $this->getAllTargets();
        }

        $cnt = 0;

        if ($this->targets)
        {
            foreach($this->targets as $target)
            {
                if ($target->isAchieved())
                {
                    $cnt++;
                }
            }
        }

        return $cnt;

    }

    /**
     * Get all the targets assigned to this session
     * @global \ELBP\Plugins\AdditionalSupport\type $DB
     * @return \ELBP\Plugins\Targets\Target
     */
    public function getAllTargets(){

        global $DB, $ELBP;

        if ($this->targets){
            return $this->targets;
        }

        if ($ELBP->getPlugin("Targets"))
        {

            $check = $DB->get_records_select("lbp_add_sup_attributes", "sessionid = ? AND field = 'Targets'", array($this->id));
            $results = array();
            if ($check)
            {
                foreach($check as $record)
                {
                    $target = new \ELBP\Plugins\Targets\Target($record->value);
                    if ($target->isValid()){
                        $results[$target->getID()] = $target;
                    }
                }
            }

            $this->targets = $results;
            return $results;

            }

        return false;

    }

    /**
     * Count the targets assigned to this session
     * @return type
     */
    public function countTargets(){
        return count($this->getAllTargets());
    }

    /**
     * Remove an attribute from the Session
     * @param string $attribute Field
     * @param mixed $value If false we remove any with that field name, otherwise remove those with specifically this value
     */
    public function removeAttribute($attribute, $value = false)
    {

        global $DB;

        // If value is specified delete where value as well, otherwise only where field
        if ($value){
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_DELETED_ATTRIBUTE, $this->studentID, array(
                "sessionID" => $this->id,
                "attribute" => $attribute,
                "value" => $value
            ));
            return $DB->delete_records_select("lbp_add_sup_attributes", "sessionid = ? AND field = ? AND value = ?", array($this->id, $attribute, $value));
        } else {
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_DELETED_ATTRIBUTE, $this->studentID, array(
                "sessionID" => $this->id,
                "attribute" => $attribute
            ));
            return $DB->delete_records_select("lbp_add_sup_attributes", "sessionid = ? AND field = ?", array($this->id, $attribute));
        }


    }

    /**
     * Set the value of an attribute
     * @param type $attribute
     * @param type $value
     */
    public function setAttribute($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
    }

    /**
     * Set boolean true/false whether we should try to autosave the tutorial
     * @param type $val
     */
    public function setAutoSave($val){
        $this->autoSave = $val;
    }

    /**
     * Display the Session info in the expanded view
     */
    public function display(){

        global $DB, $ELBP;

        $access = $ELBP->getUserPermissions($this->studentID);

        $attributes = $this->AdditionalSupport->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);

        if (!$attributes) return get_string('noattributesdefined', 'block_elbp');

        $output = "";

        $output .= "<div>";

        // Main central elements
        $output .= "<div class='elbp_additional_support_main_elements'>";

            $mainAttributes = $this->AdditionalSupport->getAttributesForDisplayDisplayType("main", $attributes);
            if ($mainAttributes)
            {
                foreach($mainAttributes as $attribute)
                {
                    $output .= "<h2>{$attribute->name}</h2>";
                    $output .= "<div class='elbp_additional_support_attribute_content'>";
                        $output .= $attribute->displayValue();
                    $output .= "</div>";
                    $output .= "<br>";
                }
            }
        $output .= "</div>";


        // Summary
        $sideAttributes = $this->AdditionalSupport->getAttributesForDisplayDisplayType("side", $attributes);
        if ($sideAttributes)
        {
            $output .= "<div class='elbp_additional_support_summary_elements'>";
            $output .= "<b>".get_string('otherattributes', 'block_elbp')."</b><br><br>";
            $output .= "<table class='tutorial_summary_table'>";
            foreach($sideAttributes as $attribute)
            {
                $output .= "<tr><td>{$attribute->name}:</td><td>".$attribute->displayValue(). "</td></tr>";
            }
            $output .= "</table>";
            $output .= "<br><br><br>";
            $output .= "</div>";
        }


        // BKSBLive Hook
        if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/English IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksblive/Maths IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksblive/ICT IA"))
        {

            $bksb = $ELBP->getPlugin("elbp_bksblive");

            $output .= "<div class='elbp_additional_support_summary_elements'>";
                $output .= "<b>".get_string('initassessments', 'block_elbp_bksblive')."</b><br><br>";
                $output .= "<table class='additional_support_bksb_table'>";

                    if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/English IA"))
                    {
                        $value = $this->getAttribute('English IA', true);
                        if ($value && $value != get_string('na', 'block_elbp'))
                        {
                            $explode = explode("|", $value);
                            $value = $explode[0] . "<br><small>{$explode[1]}</small>";
                        }
                        $output .= "<tr><td>".get_string('engia', 'block_elbp_bksblive')."</td><td>{$value}</td></tr>";
                    }


                    if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/Maths IA"))
                    {
                        $value = $this->getAttribute('Maths IA', true);
                        if ($value && $value != get_string('na', 'block_elbp'))
                        {
                            $explode = explode("|", $value);
                            $value = $explode[0] . "<br><small>{$explode[1]}</small>";
                        }
                        $output .= "<tr><td>".get_string('mathsia', 'block_elbp_bksblive')."</td><td>{$value}</td></tr>";
                    }


                    if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/ICT IA"))
                    {
                        $value = $this->getAttribute('ICT IA', true);
                        if ($value && $value != get_string('na', 'block_elbp'))
                        {
                            $explode = explode("|", $value);
                            $value = $explode[0] . "<br><small>{$explode[1]}</small>";
                        }
                        $output .= "<tr><td>".get_string('ictia', 'block_elbp_bksblive')."</td><td>{$value}</td></tr>";
                    }


                $output .= "</table>";
            $output .= "</div>";

        }


        // BKSB Hook
        elseif ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/English IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksb/Maths IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksb/ICT IA"))
        {

            $bksb = $ELBP->getPlugin("elbp_bksb");

            $output .= "<div class='elbp_additional_support_summary_elements'>";
                $output .= "<b>".get_string('initassessments', 'block_elbp_bksb')."</b><br><br>";
                $output .= "<table class='additional_support_bksb_table'>";

                    if ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/English IA"))
                    {
                        $value = $this->getAttribute('English IA', true);
                        if ($value && $value != get_string('na', 'block_elbp'))
                        {
                            $explode = explode("|", $value);
                            $value = $explode[0] . "<br><small>{$explode[1]}</small>";
                        }
                        $output .= "<tr><td>".get_string('engia', 'block_elbp_bksb')."</td><td>{$value}</td></tr>";
                    }


                    if ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/Maths IA"))
                    {
                        $value = $this->getAttribute('Maths IA', true);
                        if ($value && $value != get_string('na', 'block_elbp'))
                        {
                            $explode = explode("|", $value);
                            $value = $explode[0] . "<br><small>{$explode[1]}</small>";
                        }
                        $output .= "<tr><td>".get_string('mathsia', 'block_elbp_bksb')."</td><td>{$value}</td></tr>";
                    }


                    if ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/ICT IA"))
                    {
                        $value = $this->getAttribute('ICT IA', true);
                        if ($value && $value != get_string('na', 'block_elbp'))
                        {
                            $explode = explode("|", $value);
                            $value = $explode[0] . "<br><small>{$explode[1]}</small>";
                        }
                        $output .= "<tr><td>".get_string('ictia', 'block_elbp_bksb')."</td><td>{$value}</td></tr>";
                    }


                $output .= "</table>";
            $output .= "</div>";

        }

        $output .= "<br class='elbp_cl'>";

        // Targets hook
        if ($this->AdditionalSupport->hasHookEnabled("Targets/Targets"))
        {

            $targetList = array();
            $targetsObj = \ELBP\Plugins\Plugin::instaniate("Targets");

            $output .= "<div class='elbp_summary_table'>";
                $output .= "<p class='elbp_centre'><b>".get_string('targetssetinthissession', 'block_elbp')."</b></p><br>";
                $output .= "<p id='additional_support_target_output_session_{$this->id}'></p>";

                if (isset($this->attributes['Targets'])){
                    if (!is_array($this->attributes['Targets'])) $this->attributes['Targets'] = array($this->attributes['Targets']);
                    foreach($this->attributes['Targets'] as $targetID)
                    {

                        $obj = new \ELBP\Plugins\Targets\Target($targetID);
                        if ($obj->isValid())
                        {
                            $targetList[] = $obj;
                        }

                    }
                }

                if ($targetList)
                {

                    $output .= "<table class='additional_support_targets_table'>";
                    $output .= "<tr>";
                    $output .= "<th>".get_string('targetname', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('status', 'block_elbp')."</th>";
                    if ($this->AdditionalSupport->getSetting('confidence_enabled') == 1) $output .= "<th colspan='2'>".get_string('confidence', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('deadline', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('numberofcomments', 'block_elbp')."</th>";
                    $output .= "</tr>";

                    foreach($targetList as $target)
                    {
                        $output .= "<tr>";
                        $output .= "<td>";
                        $title = '';
                        if ($targetsObj)
                        {
                            $hoverAttribute = $target->getAttribute($targetsObj->getSetting('external_target_name_hover_attribute'));
                            if ($hoverAttribute)
                            {
                                $title .= strip_tags($hoverAttribute);
                            }
                        }
                        $output .= "<a id='target_link_{$target->getID()}' href='#' onclick='ELBP.save_state(\"AdditionalSupport\");ELBP.dock(\"AdditionalSupport\", \"".elbp_html($this->AdditionalSupport->getTitle())."\");ELBP.Targets.load_targets({$target->getStatus()}, {$target->getID()});return false;' title='{$title}' class='target_name_tooltip'>".elbp_html($target->getName())."</a></td>";
                        $output .= "<td>";

                            if (!$target->isAchieved() && elbp_has_capability('block/elbp:edit_additional_support_target_status', $this->AdditionalSupport->getAccess()) && ($target->getDeadline() > time() || $this->AdditionalSupport->getSetting('lock_targets_after_deadline') != 1))
                            {
                                $output .= "<select id='update_status_{$target->getID()}' onchange='ELBP.AdditionalSupport.update_target_status(this.value, {$target->getID()}, {$this->id});return false;'>";

                                    $statuses = $ELBP->getPlugin("Targets")->getStatuses();
                                    foreach ($statuses as $status)
                                    {
                                        $sel = ($target->getStatus() == $status->id) ? "selected" : "";
                                        $output .= "<option value='{$status->id}' {$sel}>{$status->status}</option>";
                                    }

                                $output .= "</select>";
                            }
                            else
                            {
                                $output .= "{$target->getStatusName()}";
                                if ($target->isAchieved())
                                {
                                    $output .= "<br><small>{$target->getUpdatedDate()}</small>";
                                }
                            }


                        $output .= "</td>";

                        if ($this->AdditionalSupport->getSetting('confidence_enabled') == 1)
                        {

                            $output .= "<td>".get_string('start', 'block_elbp').": ";

                            $attName = 'Targets Confidence Start ' . $target->getID();

                            if (elbp_has_capability('block/elbp:edit_additional_support_target_confidence', $access) && ($target->getDeadline() > time() || $this->AdditionalSupport->getSetting('lock_targets_after_deadline') != 1))
                            {
                                $output .= "<select onchange='ELBP.AdditionalSupport.update_target_confidence(\"Start\", this.value, {$this->id}, {$target->getID()});return false;'>";
                                $output .= "<option value=''></option>";
                                    for($i = 1; $i <= $this->AdditionalSupport->getConfidenceLimit(); $i++)
                                    {
                                        $sel = (isset($this->attributes[$attName]) && $this->attributes[$attName] == $i) ? 'selected' : '';
                                        $output .= "<option value='{$i}' {$sel}>{$i}</option>";
                                    }
                                $output .= "</select>";
                            }
                            else
                            {
                                $output .= $this->getAttribute($attName, true);
                            }


                            $output .= "</td>";

                            $output .= "<td>".get_string('now', 'block_elbp').": ";

                            $attName = 'Targets Confidence End ' . $target->getID();

                            if (elbp_has_capability('block/elbp:edit_additional_support_target_confidence', $access) && ($target->getDeadline() > time() || $this->AdditionalSupport->getSetting('lock_targets_after_deadline') != 1))
                            {
                                $output .= "<select onchange='ELBP.AdditionalSupport.update_target_confidence(\"End\", this.value, {$this->id}, {$target->getID()});return false;'>";
                                $output .= "<option value=''></option>";
                                    for($i = 1; $i <= $this->AdditionalSupport->getConfidenceLimit(); $i++)
                                    {
                                        $sel = (isset($this->attributes[$attName]) && $this->attributes[$attName] == $i) ? 'selected' : '';
                                        $output .= "<option value='{$i}' {$sel}>{$i}</option>";
                                    }
                                $output .= "</select>";
                            }
                            else
                            {
                                $output .= $this->getAttribute($attName, true);
                            }

                            $output .= "</td>";

                        }

                        $output .= "<td>{$target->getDueDate()}</td>";
                        $output .= "<td>{$target->countComments()}</td>";
                        $output .= "</tr>";
                    }

                    $output .= "</table>";

                }
                else
                {
                    $output .= "<p class='elbp_centre'>".get_string('noresults', 'block_elbp')."</p>";
                }

            $output .= "</div>";

        }

        $output .= "</div>";
        $output .= "<script>$('.target_name_tooltip').tooltip();</script>";

        echo $output;

    }

    /**
     * Delete Session
     * @return boolean
     */
    public function delete()
    {

        global $DB;

        $data = new \stdClass();
        $data->id = $this->id;
        $data->del = 1;

        if (!$DB->update_record("lbp_add_sup_sessions", $data)){
            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }

        // Log Action
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_DELETED_SESSION, $this->studentID, array(
            "sessionID" => $this->id
        ));

        // If we want to then delete targets, do that as well
        if ($this->AdditionalSupport->getSetting('delete_targets_on_delete') == 1){

            $targets = $this->getAllTargets();
            if ($targets)
            {
                foreach($targets as $target)
                {
                    if (!$target->delete()){
                        $this->errors[] = $target->getErrors();
                        return false;
                    }
                }
            }

        }

        return true;
    }

    /**
     * Save session into the DB
     * @global type $USER
     * @global type $DB
     * @return boolean
     */
    public function save(){

        global $USER, $DB;

        if (!$this->id) return false;
        if (!$this->AdditionalSupport) return false;

        if (!ctype_digit($this->date)) $this->errors[] = get_string('additionalsupporterrors:date', 'block_elbp');
        if (!ctype_digit($this->studentID)) $this->errors[] = get_string('additionalsupporterrors:studentid', 'block_elbp');

        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->AdditionalSupport->getElementsFromAttributeString();

        // If auto save, don't check for errors, just save it
        if (!$this->autoSave)
        {

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

        }

        $tmp = new Session($this->id);
        $now = time();

        if (is_null($this->deadline)){
          $this->deadline = $now + 604800; // Now + 1 week
        }

        if (is_null($this->date)){
          $this->date = $now;
        }

        // Save it

        // New, so insert it
        if ($this->id == -1)
        {

            $obj = new \stdClass();
            $obj->studentid = $this->studentID;
            $obj->sessiondate = $this->date;
            $obj->deadline = $this->deadline;
            $obj->setbyuserid = $USER->id;
            $obj->settime = $now;
            $obj->del = 0;

            // Insert the target
            if (!$id = $DB->insert_record("lbp_add_sup_sessions", $obj)){
                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }

            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_ADDED_SESSION, $this->studentID, array(
                "sessionID" => $id,
                "date" => $this->date,
                "deadline" => $this->deadline,
                "attributes" => http_build_query($this->attributes)
            ));

            // Set vars
            $this->id = $id;
            $this->setTime = $now;
            $this->setByUserID = $USER->id;

            // Now using that target ID, insert it's attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {


                    // First check for interested parties and do that differently if found
                    if ($field == 'Interested Parties')
                    {

                        // For each interested party, set their alert preferences so that they get alerts about
                        // This additional support session

                        $events = $this->AdditionalSupport->getEvents();
                        $DBC = new \ELBP\DB();

                        if (!is_array($value)) $value = array($value);
                        foreach($value as $val)
                        {

                            $user = $DBC->getUser(array("type" => "username", "val" => $val));
                            if ($user)
                            {

                                if ($events)
                                {
                                    foreach($events as $event)
                                    {

                                        $this->AdditionalSupport->setUserEventPreference($event->id, 1, array("studentID" => $this->studentID), false, $user->id);

                                    }
                                }

                            }
                        }

                    }



                    // If array, do each of them
                    if (is_array($value))
                    {

                        foreach($value as $val)
                        {

                            $ins = new \stdClass();
                            $ins->sessionid = $id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_add_sup_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }

                        }

                    }
                    else
                    {

                        // If empty, set to NULL in DB
                        if ($value == "") $value = null;

                        $ins = new \stdClass();
                        $ins->sessionid = $id;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_add_sup_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }

                    }

                }

            }

            // Trigger alerts
            $alertContent = get_string('alerts:additionalsupportsessionadded', 'block_elbp') .
                            $this->getInfoForEventTrigger(false);
            elbp_event_trigger("Additional Support Session Added", $this->AdditionalSupport->getID(), $this->studentID, $alertContent, nl2br($alertContent));



        }
        else
        {

            // Update
            $obj = new \stdClass();
            $obj->id = $this->id;
            $obj->sessiondate = $this->date;
            $obj->deadline = $this->deadline;
            $obj->del = 0;

            if (!$DB->update_record("lbp_add_sup_sessions", $obj)){
                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }

            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_UPDATED_SESSION, $this->studentID, array(
                 "sessionID" => $this->id,
                 "date" => $this->date,
                 "deadline" => $this->deadline,
                 "attributes" => http_build_query($this->attributes)
            ));

            // Now using that target ID, insert it's attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {

                    // First check for interested parties and do that differently if found
                    if ($field == 'Interested Parties')
                    {

                        // For each interested party, set their alert preferences so that they get alerts about
                        // This additional support session

                        $events = $this->AdditionalSupport->getEvents();
                        $DBC = new \ELBP\DB();

                        if (!is_array($value)) $value = array($value);

                        $newParties = array();

                        foreach($value as $val)
                        {

                            $user = $DBC->getUser(array("type" => "username", "val" => $val));
                            if ($user)
                            {

                                if ($events)
                                {
                                    foreach($events as $event)
                                    {

                                        $this->AdditionalSupport->setUserEventPreference($event->id, 1, array("studentID" => $this->studentID), false, $user->id);
                                        $newParties[] = $user->id;

                                    }
                                }

                            }
                        }

                        // Now find any interested parties who were previously assigned but are not any more, and remove
                        // alert preference (set to 0)

                        $currentParties = $DB->get_records("lbp_add_sup_attributes", array("sessionid" => $this->id, "field" => "Interested Parties"));
                        if ($currentParties)
                        {
                            foreach($currentParties as $party)
                            {
                                $user = $DBC->getUser(array("type" => "username", "val" => $party->value));
                                if ($user)
                                {
                                    // If not in submitted array, disable preference
                                    if (!in_array($user->id, $newParties))
                                    {

                                        foreach ($events as $event)
                                        {
                                            $this->AdditionalSupport->setUserEventPreference($event->id, 0, array("studentID" => $this->studentID), false, $user->id);
                                        }

                                    }
                                }
                            }
                        }

                    }




                    // If array, do each of them
                    if (is_array($value))
                    {


                        // If it's an array then we're going to have to delete all records of this att first
                        // Otherwise, say we saved 4 values: one, two, three, four oringally, then we update to: one, four
                        // The two & thre would still be in there
                        $DB->delete_records("lbp_add_sup_attributes", array("sessionid" => $this->id, "field" => $field));

                        foreach($value as $val)
                        {

                            $ins = new \stdClass();
                            $ins->sessionid = $this->id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_add_sup_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }

                        }

                    }
                    else
                    {

                        // Get att from DB
                        $attribute = $DB->get_record_select("lbp_add_sup_attributes", "sessionid = ? AND field = ?", array($this->id, $field));

                        // If empty, set to NULL in DB
                        if ($value == "") $value = null;

                        // if it exists, update it
                        if ($attribute)
                        {
                            $ins = new \stdClass();
                            $ins->id = $attribute->id;
                            $ins->value = $value;
                            if (!$DB->update_record("lbp_add_sup_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                        }

                        // Else, insert it
                        else
                        {
                            $ins = new \stdClass();
                            $ins->sessionid = $this->id;
                            $ins->field = $field;
                            $ins->value = $value;
                            if (!$DB->insert_record("lbp_add_sup_attributes", $ins)){
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
                            $DB->delete_records("lbp_add_sup_attributes", array("sessionid" => $this->id, "field" => $allAttribute->name));
                        }

                    }
                }


            }

            // Trigger alerts
            $alertContent = get_string('alerts:additionalsupportsessionupdated', 'block_elbp') .
                            $this->getInfoForEventTrigger(false);
            $htmlContent = get_string('alerts:additionalsupportsessionupdated', 'block_elbp') .
                            $this->getInfoForEventTrigger(true, $tmp);
            elbp_event_trigger("Additional Support Session Updated", $this->AdditionalSupport->getID(), $this->studentID, $alertContent, $htmlContent);



        }


        return true;


    }


    /**
     * Generate simple HTML output to be printed
     */
    public function printOut()
    {

        global $CFG, $USER, $ELBP;

        ob_clean();

        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('additionalsupportsession', 'block_elbp') . ' - ' . $this->getDate('d M Y');
        $logo = \ELBP\ELBP::getPrintLogo();
        $title = get_string('additionalsupportsession', 'block_elbp');
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';

        $attributes = $this->AdditionalSupport->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);

        $strings = array();
        $strings['startdate'] = get_string('startdate', 'block_elbp');
        $strings['deadline'] = get_string('deadline', 'block_elbp');
        $strings['setby'] = get_string('setby', 'block_elbp');
        $strings['targetsset'] = get_string('numberoftargetsset', 'block_elbp');

        $txt = "";
        $txt .= "<table class='info'>";
            $txt .= "<tr>";
                $txt .= "<td>".get_string('startdate', 'block_elbp') . ": " . $this->getDate('D jS M Y') . "</td>";
                $txt .= "<td>".get_string('deadline', 'block_elbp') . ": " . $this->getDeadline('D jS M Y') . "</td>";
            $txt .= "</tr>";
            $txt .= "<tr>";
                $txt .= "<td>".get_string('setby', 'block_elbp') . ": " . $this->getStaffName() . "</td>";
                $txt .= "<td>".get_string('numberoftargetsset', 'block_elbp') . ": " . $this->countTargets() . "</td>";
            $txt .= "</tr>";
            $txt .= "<tr>";
                $txt .= "<td colspan='2'>".get_string('longtermaim', 'block_elbp').": ".$this->AdditionalSupport->getSetting('long_term_aim', $this->student->id)."</td>";
            $txt .= "</tr>";
        $txt .= "</table>";

        $txt .= "<hr>";
        $txt .= "<table>";

        if ($attributes)
        {
            foreach($attributes as $attribute)
            {
                $txt .= "<tr><td><u>{$attribute->name}</u></td></tr>";
                $txt .= "<tr><td>".$attribute->displayValue(true)."</td></tr>";
            }
        }

        $txt .= "</table>";


        // BKSB hook
        if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/English IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksblive/Maths IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksblive/ICT IA"))
        {

            $txt .= "<hr>";
            $txt .= "<u>".get_string('initassessments', 'block_elbp_bksblive')."</u><br>";

            if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/English IA")){
                $txt .= get_string('engia', 'block_elbp_bksblive') . ': ';
                $val = $this->getAttribute('English IA', true);
                $val = str_replace("|", " - ", $val);
                $txt .= $val;
                $txt .= "<br>";
            }

            if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/Maths IA")){
                $txt .= get_string('mathsia', 'block_elbp_bksblive') . ': ';
                $val = $this->getAttribute('Maths IA', true);
                $val = str_replace("|", " - ", $val);
                $txt .= $val;
                $txt .= "<br>";
            }

            if ($this->AdditionalSupport->hasHookEnabled("elbp_bksblive/ICT IA")){
                $txt .= get_string('ictia', 'block_elbp_bksblive') . ': ';
                $val = $this->getAttribute('ICT IA', true);
                $val = str_replace("|", " - ", $val);
                $txt .= $val;
                $txt .= "<br>";
            }

        }

        elseif ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/English IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksb/Maths IA") || $this->AdditionalSupport->hasHookEnabled("elbp_bksb/ICT IA"))
        {

            $txt .= "<hr>";
            $txt .= "<u>".get_string('initassessments', 'block_elbp_bksb')."</u><br>";

            if ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/English IA")){
                $txt .= get_string('engia', 'block_elbp_bksb') . ': ';
                $val = $this->getAttribute('English IA', true);
                $val = str_replace("|", " - ", $val);
                $txt .= $val;
                $txt .= "<br>";
            }

            if ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/Maths IA")){
                $txt .= get_string('mathsia', 'block_elbp_bksb') . ': ';
                $val = $this->getAttribute('Maths IA', true);
                $val = str_replace("|", " - ", $val);
                $txt .= $val;
                $txt .= "<br>";
            }

            if ($this->AdditionalSupport->hasHookEnabled("elbp_bksb/ICT IA")){
                $txt .= get_string('ictia', 'block_elbp_bksb') . ': ';
                $val = $this->getAttribute('ICT IA', true);
                $val = str_replace("|", " - ", $val);
                $txt .= $val;
                $txt .= "<br>";
            }

        }

        // Targets hook
        if ($this->AdditionalSupport->hasHookEnabled("Targets/Targets"))
        {

            $txt .= "<hr>";
            $txt .= "<u>".get_string('targets', 'block_elbp')."</u><br>";

            // Loop targets
            if ($this->getAllTargets())
            {

                $cnt = count($this->targets);
                $n = 0;

                foreach($this->targets as $target)
                {

                    $n++;

                    $descAtt = $ELBP->getPlugin("Targets")->getSetting('external_target_name_hover_attribute');
                    $desc = $target->getAttribute($descAtt);

                    $txt .= "<table style='min-width:50%;'>";
                        $txt .= "<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;".get_string('targetname', 'block_elbp').":</td><td>{$target->getName()} ({$target->getStatusName()})</td></tr>";
                        $txt .= "<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;".$descAtt.":</td><td>{$desc}</td></tr>";

                        if ($this->AdditionalSupport->getSetting('confidence_enabled') == 1)
                        {

                            $attName = 'Targets Confidence Start ' . $target->getID();
                            $txt .= "<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;".get_string('confidenceatstart', 'block_elbp').":</td><td>{$this->getAttribute($attName)} / {$this->AdditionalSupport->getConfidenceLimit()}</td></tr>";

                            $attName = 'Targets Confidence End ' . $target->getID();
                            $txt .= "<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;".get_string('confidencenow', 'block_elbp').":</td><td>{$this->getAttribute($attName)} / {$this->AdditionalSupport->getConfidenceLimit()}</td></tr>";

                        }

                        // Comments
                        if ($target->getComments())
                        {
                            $txt .= "<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;".get_string('comments', 'block_elbp').":</td><td>{$target->displayPdfComments()}</td></tr>";
                        }

                    $txt .= "</table>";

                    if ($cnt > $n){
                        $txt .= "<br>";
                    }
                }
            }
            else
            {
                $txt .= get_string('noresults', 'block_elbp');
            }

        }


        // Overall session comments
        if ($this->getComments()){
            $txt .= "<hr>";
            $txt .= "<u>".get_string('sessioncomments', 'block_elbp')."</u><br>";
            $txt .= $this->displayPdfComments();
        }

        // Anything else they want to write in
        $txt .= "<hr>";
        $txt .= "<u>".get_string('optionalextracomments', 'block_elbp')."</u><br><div style='height:50px;'></div>";
        $txt .= "<hr>";
        $txt .= "<div class='c'>";
        $txt .= "<small>".get_string('studentsignature', 'block_elbp')."_______________________________________________________________________ ".get_string('date')."___________________</small><br><br>";
        $txt .= "<small>".get_string('tutorsignature', 'block_elbp')."_________________________________________________________________________ ".get_string('date')."___________________</small><br>";
        $txt .= "</div>";

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
     * Get a particular comment on the session
     * @param int $id
     */
    public function getComment($id){

        // Comment might be parent level or child level, so have to loop through and look for it
        if (!$this->comments) $this->loadComments();

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
     * Get comment from any level
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

        $results = $DB->get_records_select("lbp_add_sup_comments", "sessionid = ? AND parent IS NULL AND del = 0", array($this->id), "time ASC");

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

        $results = $DB->get_records_select("lbp_add_sup_comments", "sessionid = ? AND parent = ? AND del = 0", array($this->id, $parentID), "time ASC");

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
        if (!$this->comments) $this->loadComments();
        return count($this->comments);
    }

    /**
     * Return a list of comments
     */
    public function getComments(){
        if (!$this->comments) $this->loadComments();
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
        $obj->sessionid = $this->id;
        $obj->userid = $USER->id;
        $obj->parent = $parentID;
        $obj->comments = $comment;
        $obj->time = time();
        $obj->del = 0;
        if($id = $DB->insert_record("lbp_add_sup_comments", $obj)){


            // Trigger alerts
            $alertContent = get_string('alerts:additionalsupportsessioncommentadded', 'block_elbp') . "\n" .
                            get_string('sessiondate', 'block_elbp') . ": " . $this->getDate('M jS Y') . "\n" .
                            get_string('user') . ": " . fullname($USER) . "\n" .
                            get_string('comment', 'block_elbp') . ": " . $comment . "\n";

            // Student alert
            elbp_event_trigger_student("Additional Support Session Comment Added", $this->AdditionalSupport->getID(), $this->studentID, $alertContent, nl2br($alertContent));

            // Staff alerts
            elbp_event_trigger("Additional Support Session Comment Added", $this->AdditionalSupport->getID(), $this->studentID, $alertContent, nl2br($alertContent));



            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_ADDED_COMMENT, $this->studentID, array(
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

        if ($DB->update_record("lbp_add_sup_comments", $obj)){

            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_ADDITIONAL_SUPPORT, LOG_ACTION_ELBP_ADDITIONAL_SUPPORT_DELETED_COMMENT, $this->studentID, array(
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

        $check = $DB->get_record("lbp_add_sup_comments", array("id" => $commentID));
        if (!is_null($check->parent)){

            $cnt++;
            $cnt += $this->countCommentThreading($check->parent);

        }

        return $cnt;

    }

    /**
     * Display comments for printing
     * @param type $childComments
     */
    public function displayPdfComments($childComments = false, $childLevel = 0){

        $output = "";

        $comments = (!$childComments) ? $this->getComments() : $childComments;

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

        return $output;

    }


    /**
     * Display the comments, recursively going through all levels of child comments
     * @param mixed $childComments False for the first level, then an object of child comments
     */
    public function displayComments($childComments = false){

        global $ELBP, $USER;

        $output = "";

        if (!$this->comments) $this->loadComments();

        $comments = (!$childComments) ? $this->getComments() : $childComments;
        $access = $ELBP->getUserPermissions($this->studentID);

        foreach($comments as $comment)
        {

            $output .= "<div id='comment_{$comment->id}' class='elbp_comment_box' style='width:90%;" . ((isset($comment->css->bdr)) ? "border: 1px solid {$comment->css->bdr};" : "") . ((isset($comment->css->bg)) ? "background-color:{$comment->css->bg};" : "") . "'>";
            $output .= "<p id='elbp_comment_add_output_comment_{$comment->id}'></p>";
            $output .= elbp_html($comment->comments, true);
            $output .= "<br><br>";
            $output .= "<small><b>{$comment->firstName} {$comment->lastName}</b></small><br>";
            $output .= "<small>".date('D jS M Y H:i', $comment->time)."</small><br>";

            if (elbp_has_capability('block/elbp:add_additional_support_session_comment', $access)){
                $output .= "<small><a href='#' onclick='$(\"#comment_reply_{$comment->id}\").slideToggle();return false;'>".get_string('reply', 'block_elbp')."</a></small><br>";
                $output .= "<div id='comment_reply_{$comment->id}' class='elbp_comment_textarea' style='display:none;'><textarea id='add_reply_{$comment->id}'></textarea><br><br><input class='elbp_big_button' type='button' value='".get_string('submit', 'block_elbp')."' onclick='ELBP.AdditionalSupport.add_comment({$this->getID()}, $(\"#add_reply_{$comment->id}\").val(), {$comment->id});return false;' /><br><br></div>";
            }

            // We either need the delete_any_target_comment capability if the comment is not ours, or if it is ours, we need the delete_my_target_comment
            if ( ($comment->userid <> $USER->id && elbp_has_capability('block/elbp:delete_any_additional_support_session_comment', $access) ) || ( $comment->userid == $USER->id && elbp_has_capability('block/elbp:delete_my_additional_support_session_comment', $access) ) ){
                $output .= "<small><a href='#' onclick='ELBP.AdditionalSupport.delete_comment({$this->id}, {$comment->id});return false;'>".get_string('delete', 'block_elbp')."</a></small><br><br>";
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
     * Get the string to display for whether or not a target is late or still has time left to be met
     * @return type
     */
    public function getLateOrRemaining(){

        // Past the dseadline
        if ($this->deadline > time()){

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

        // The session itself has a deadline, but there is no actual marking of the session as finished
        // So we don't do late, or all of them would appear late

        return "";

    }


    /**
     * Given an array of data, build up the Incident object based on that instead of a DB record
     * This is used for creating a new Incident or editing an existing one
     * @param type $data
     */
    public function loadData($data){

        $this->id = (isset($data['session_id'])) ? $data['session_id'] : -1; # Set to -1 if not set, as probably new incident
        if (isset($data['studentID'])) $this->studentID = $data['studentID'];
        if (isset($data['session_date']) && !elbp_is_empty($data['session_date'])){
            $date =  \DateTime::createFromFormat('d-m-Y H:i:s', $data['session_date'] . ' 00:00:00');
            $this->date = $date->format("U"); # Unix
        }

        if (isset($data['deadline']) && !elbp_is_empty($data['deadline'])){
            $deadline =  \DateTime::createFromFormat('d-m-Y H:i:s', $data['deadline'] . ' 00:00:00');
            $this->deadline = $deadline->format("U"); # Unix
        }


        unset($data['session_id']);
        unset($data['studentID']);
        unset($data['session_date']);
        unset($data['deadline']);
        unset($data['courseID']);

        // Attributes - FIrstly get all possible attributes and loop through them
        $OBJ = new \ELBP\Plugins\AdditionalSupport();
        $this->setSubmittedAttributes($data, $OBJ);


    }

    /**
     * Get the content to go in the event trigger email
     * @global type $CFG
     * @global \ELBP\Plugins\AdditionalSupport\type $USER
     * @param type $useHtml
     * @param type $tmp
     * @return string
     */
    private function getInfoForEventTrigger($useHtml = false, $tmp = false)
    {
        global $CFG, $USER;

        $output = "";

        // If using HTML
        if ($useHtml)
        {
            $output .= "<br>----------<br>";
            $output .= get_string('student', 'block_elbp') . ": " . fullname($this->getStudent()) . " ({$this->getStudent()->username})<br>";

            // Old Date
            $output .= "<del style='color:red;'>".get_string('sessiondate', 'block_elbp') . ": " . $tmp->getDate('M jS Y') . "</del><br>";
            // New Date
            $output .= "<ins style='color:blue;'>".get_string('sessiondate', 'block_elbp') . ": " . $this->getDate('M jS Y') . "</ins><br>";


            // Attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {

                    // Targets
                    if ($field == 'Targets')
                    {

                        $oldTargetOutput = '';
                        $newTargetOutput = '';

                        if (!is_array($value)) $value = array($value);
                        foreach($value as $targetID)
                        {
                            $targetObj = new \ELBP\Plugins\Targets\Target($targetID);
                            if ($targetObj->isValid())
                            {

                                // Old
                                $oldTargetOutput .= $targetObj->getName();
                                $startConfidence = $tmp->getAttribute('Targets Confidence Start ' . $targetID, true);
                                $endConfidence = $tmp->getAttribute('Targets Confidence End ' . $targetID, true);
                                $oldTargetOutput .= " (".get_string('confidence', 'block_elbp').": [{$startConfidence}] [{$endConfidence}]), ";

                                // New
                                $newTargetOutput .= $targetObj->getName();
                                $startConfidence = (isset($this->attributes['Targets Confidence Start ' . $targetID])) ? $this->attributes['Targets Confidence Start ' . $targetID] : '-';
                                $endConfidence = (isset($this->attributes['Targets Confidence End ' . $targetID])) ? $this->attributes['Targets Confidence End ' . $targetID] : '-';
                                $newTargetOutput .= " (".get_string('confidence', 'block_elbp').": [{$startConfidence}] [{$endConfidence}]), ";

                            }
                        }

                        // Old e
                        $output .= "<del style='color:red;'>{$field}: " . $oldTargetOutput . "</del><br>";

                        // New
                        $output .= "<ins style='color:blue;'>{$field}: " . $newTargetOutput . "</ins><br>";


                    }
                    elseif (preg_match("/Targets Confidence/", $field))
                    {
                        continue;
                    }
                    else
                    {

                         if (is_array($value)) $value = implode(",", $value);
                        $value = preg_replace("/\n/", " ", $value);

                         // Old attribute value
                        $output .= "<del style='color:red;'>{$field}: " . $tmp->getAttribute($field) . "</del><br>";

                        // New attrribute value
                        $output .= "<ins style='color:blue;'>{$field}: " . $value . "</ins><br>";

                    }


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

            $output .= get_string('sessiondate', 'block_elbp') . ": " . $this->getDate('M jS Y') . "\n";
            $output .= get_string('deadline', 'block_elbp') . ": " . $this->getDeadline('M jS Y') . "\n";

            // Attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {


                    // Targets
                    if ($field == 'Targets')
                    {

                        $targetOutput = '';
                        if (!is_array($value)) $value = array($value);

                        foreach($value as $targetID)
                        {
                            $targetObj = new \ELBP\Plugins\Targets\Target($targetID);
                            if ($targetObj->isValid())
                            {
                                $targetOutput .= $targetObj->getName();
                                $startConfidence = (isset($this->attributes['Targets Confidence Start ' . $targetID])) ? $this->attributes['Targets Confidence Start ' . $targetID] : '-';
                                $endConfidence = (isset($this->attributes['Targets Confidence End ' . $targetID])) ? $this->attributes['Targets Confidence End ' . $targetID] : '-';
                                $targetOutput .= " (".get_string('confidence', 'block_elbp').": [{$startConfidence}] [{$endConfidence}]), ";
                            }
                        }
                        $output .= $field . ": " . $targetOutput . "\n";

                    }
                    elseif (preg_match("/Targets Confidence/", $field))
                    {
                        continue;
                    }
                    else
                    {

                        if (is_array($value)) $value = implode(",", $value);
                        $value = preg_replace("/\n/", " ", $value);
                        $output .= $field . ": " . $value . "\n";

                    }


                }

            }

            $output .= "----------\n";
            $output .= get_string('updatedby', 'block_elbp') . ": " . fullname($USER) . "\n";
            $output .= get_string('link', 'block_elbp') . ": " . "{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->studentID}\n";

        }


        return $output;

    }


    /**
     * Get an array of data to be put into a new/edit form
     * @param int $incidentID
     */
    public static function getDataForNewSessionForm($sessionID = false)
    {

        global $ELBP;

        $support = $ELBP->getPlugin("AdditionalSupport");
        $data = array();

        $attributes = $support->getAttributesForDisplay();

        if ($sessionID)
        {

            $session = new Session($sessionID);
            if (!$session->isValid()) return false;

            $data['id'] = $session->getID();
            $data['date'] = $session->getDate("d-m-Y");
            $data['deadline'] = $session->getDeadline("d-m-Y");
            $data['atts'] = array();
            $data['hookAtts'] = array();

            // Since it's a real Session, get all the actual attributes stored for it, not just the ones we think it should have from the config
            $definedAttributes = $session->getAttributes();

            $processedAttributes = array();

            // Loop through all possible attributes defined in the system
            $data['atts'] = array();

            // Loop through default attributes
            if ($attributes)
            {
                foreach($attributes as $attribute)
                {

                    $attribute->loadObject($support);

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
            $data['date'] = date("d-m-Y");
            $data['deadline'] = '';

            if ($attributes){
                foreach($attributes as $attribute){
                    $attribute->loadObject($support);
                }
            }

            $data['atts'] = $attributes;


        }

        return $data;

    }



}