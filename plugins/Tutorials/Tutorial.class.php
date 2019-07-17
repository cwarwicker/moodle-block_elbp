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

namespace ELBP\Plugins\Tutorials;

/**
 *
 */
class Tutorial extends \ELBP\BasePluginObject {

    private $id = false;
    private $studentID;
    private $courseID;
    private $tutorialDate;
    private $setByUserID;
    private $setTime;
    private $confidentialityID;
    private $del;

    private $staff;

    private $targets;

    protected $attributes = array();
    private $Tutorials;
    private $errors = array();

    private $student;
    private $course;

    private $autoSave = false;

    /**
     * Construct tutorial object
     * @global type $CFG
     * @global \ELBP\Plugins\Tutorials\type $DB
     * @param type $param
     * @param type $TutorialsObj
     */
    public function __construct($param, $TutorialsObj = false) {

        global $CFG, $DB;

        // ID
        if (ctype_digit($param))
        {

            // Load from DB
            $record = $DB->get_record("lbp_tutorials", array("id"=>$param));
            if ($record)
            {

                $this->id = (int)$record->id;
                $this->studentID = (int)$record->studentid;
                $this->courseID = (int)$record->courseid;
                $this->tutorialDate = (int)$record->tutorialdate;
                $this->setByUserID = (int)$record->setbyuserid;
                $this->setTime = (int)$record->settime;
                $this->confidentialityID = (int)$record->confidentialityid;
                $this->del = (int)$record->del;

                $this->staff = $this->getSetByUser();

                $this->loadAttributes();

            }


        }
        elseif (is_array($param))
        {

            // Build new one from data provided
            if ($TutorialsObj !== false) $this->setTutorialsObj($TutorialsObj);
            $this->loadData($param);

        }

    }

    /**
     * Is the tutorial valid? Or have we specified an invalid ID?
     * @return type
     */
    public function isValid(){
        return ($this->id) ? true : false;
    }

     /**
     * Is the tutorial deleted?
     * @return type
     */
    public function isDeleted(){
        return ($this->del == 0) ? true : false;
    }

    /**
     * Get the tutorial id
     * @return type
     */
    public function getID(){
        return $this->id;
    }

    /**
     * Return the studentID
     * @return type
     */
    public function getStudentID(){
        return $this->studentID;
    }

    /**
     * Return the courseID
     * @return type
     */
    public function getCourseID(){
        return $this->courseID;
    }

    /**
     * Get the student record
     * @global \ELBP\Plugins\Tutorials\type $DB
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
     * Get the course record if this is linked to a course
     * @global \ELBP\Plugins\Tutorials\type $DB
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
     * Return the date of the tutorial (default returns unix timestamp, otherwise returns date() in supplied format
     * @param string $format
     * @return mixed
     */
    public function getTutorialDate($format = false){

        if (!$format) return $this->tutorialDate;
        else return date($format, $this->tutorialDate);

    }

    /**
     * Return the ID of the user who set the tutorial
     * @return type
     */
    public function getSetByUserID(){
        return $this->setByUserID;
    }

    /**
     * Get the user record of the user who set the tutorial, rather than the id
     * @global type $DB
     * @return type
     */
    public function getSetByUser(){
        global $DB;
        return $DB->get_record("user", array("id" => $this->setByUserID));
    }

    /**
     * Get the name of the member of staff who set the Target
     * @return type
     */
    public function getStaffName(){
        return fullname($this->staff);
    }

    /**
     * Return the time the tutorial was set (default returns unix timestamp, otherwise returns date() in supplied format
     * @param string $format
     * @return mixed
     */
    public function getSetTime($format = false){

        if (!$format) return $this->setTime;
        else return date($format, $this->setTime);

    }

    /**
     * Return the id of the confidentiality status
     * @return type
     */
    public function getConfidentialityID(){
        return $this->confidentialityID;
    }

    /**
     * Return a Confidentiality object of the given lvl
     */
    public function getConfidentiality(){
        // TODO
    }

    /**
     * Get any errors
     * @return type
     */
    public function getErrors(){
        return $this->errors;
    }

    /**
     * Get all the targets linked to this tutorial
     * @global \ELBP\Plugins\Tutorials\type $DB
     * @return \ELBP\Plugins\Targets\Target
     */
    public function getAllTargets(){

        global $DB, $ELBP;

        if ($this->targets){
            return $this->targets;
        }

        // If Targets is enabled
        if ($ELBP->getPlugin("Targets"))
        {

            $check = $DB->get_records_select("lbp_tutorial_attributes", "tutorialid = ? AND field = 'Targets'", array($this->id));
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
     * Count the targets linked to this tutorial
     * @return type
     */
    public function countTargets(){
        return count($this->getAllTargets());
    }



    /**
     * Load TUtorials plugin object to use
     * WHY HAVE I GOT THE SAME METHOD TWICE
     * @param type $obj
     */
    public function loadTutorialsObj($obj){
        $this->Tutorials = $obj;
    }

    /**
     * Set TUtorials plugin object to use
     * @param type $obj
     */
    public function setTutorialsObj($obj){
        $this->Tutorials = $obj;
    }

    /**
     * Set boolean true/false whether we should try to autosave the tutorial
     * @param type $val
     */
    public function setAutoSave($val){
        $this->autoSave = $val;
    }

    /**
     * Load the tutorial's attributes
     * @global \ELBP\Plugins\Tutorials\type $DB
     * @return type
     */
    public function loadAttributes(){

        global $DB;

        $check = $DB->get_records("lbp_tutorial_attributes", array("tutorialid" => $this->id));

        $this->attributes = parent::_loadAttributes($check);
        return $this->attributes;

    }

    /**
     * Remove an attribute from the Tutorial
     * @param string $attribute Field
     * @param mixed $value If false we remove any with that field name, otherwise remove those with specifically this value
     */
    public function removeAttribute($attribute, $value = false)
    {

        global $DB;

        // If value is specified delete where value as well, otherwise only where field
        if ($value){
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TUTORIAL, LOG_ACTION_ELBP_TUTORIAL_DELETED_ATTRIBUTE, $this->studentID, array(
                "tutorialID" => $this->id,
                "attribute" => $attribute,
                "value" => $value
            ));
            return $DB->delete_records_select("lbp_tutorial_attributes", "tutorialid = ? AND field = ? AND value = ?", array($this->id, $attribute, $value));
        } else {
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TUTORIAL, LOG_ACTION_ELBP_TUTORIAL_DELETED_ATTRIBUTE, $this->studentID, array(
                "tutorialID" => $this->id,
                "attribute" => $attribute
            ));
            return $DB->delete_records_select("lbp_tutorial_attributes", "tutorialid = ? AND field = ?", array($this->id, $attribute));
        }


    }


    /**
     * Given an array of data, build up the Tutorial object based on that instead of a DB record
     * This is used for creating a new Tutorial or editing an existing one
     * @param type $data
     */
    public function loadData($data){

        $this->id = (isset($data['tutorial_id'])) ? $data['tutorial_id'] : -1; # Set to -1 if not set, as probably new tutorial
        if (isset($data['studentID'])) $this->studentID = $data['studentID'];
        if (isset($data['courseID'])) $this->courseID = $data['courseID'];
        if (isset($data['setTime'])) $this->setTime = $data['setTime'];
        if (isset($data['tutorial_date'])) $this->tutorialDate = strtotime($data['tutorial_date'] . " 00:00:00");

        unset($data['tutorial_id']);
        unset($data['studentID']);
        unset($data['courseID']);
        unset($data['setTime']);
        unset($data['tutorial_date']);

        $this->setSubmittedAttributes($data, $this->Tutorials);

    }

    /**
     * Save the tutorial
     * @global type $USER
     * @global \ELBP\Plugins\Tutorials\type $DB
     * @return boolean
     */
    public function save()
    {

        global $USER, $DB;

        if (!$this->id) return false;

        if (!ctype_digit($this->studentID)) $this->errors[] = get_string('tutorialerrors:studentid', 'block_elbp');
        if (!ctype_digit($this->tutorialDate)) $this->errors[] = get_string('tutorialerrors:timeset', 'block_elbp');

        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->Tutorials->getElementsFromAttributeString($this);

        // Only do checks if not auto save
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

        $now = time();

        // Create a tmp object of the object's current DB state before we change anything
        $tmp = new Tutorial($this->id);

        // New, so insert it
        if ($this->id == -1)
        {

            $data = new \stdClass();
            $data->studentid = $this->studentID;
            $data->courseid = $this->courseID;
            $data->tutorialdate = $this->tutorialDate;
            $data->setbyuserid = $USER->id;
            $data->settime = $now;

            if (!$id = $DB->insert_record("lbp_tutorials", $data)){
                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }


            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TUTORIAL, LOG_ACTION_ELBP_TUTORIAL_CREATED_TUTORIAL, $this->studentID, array(
                "tutorialID" => $id,
                "courseID" => $this->courseID,
                "tutorialDate" => $this->tutorialDate,
                "attributes" => http_build_query($this->attributes)
            ));


            // Set vars
            $this->id = $id;
            $this->setTime = $now;
            $this->setByUserID = $USER->id;

            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->Tutorials)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }

            // Attributes
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
                            $ins->tutorialid = $id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_tutorial_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }

                        }

                    }
                    else
                    {

                        if ($value == '') $value = null;

                        $ins = new \stdClass();
                        $ins->tutorialid = $id;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_tutorial_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }

                    }

                }

            }

            // Trigger alerts
            $alertContent = get_string('alerts:tutorialadded', 'block_elbp') .
                            $this->getInfoForEventTrigger(false);
            elbp_event_trigger("Tutorial Added", $this->Tutorials->getID(), $this->studentID, $alertContent, nl2br($alertContent));


            return true;


        }
        else
        {

            // Updating an existing one
            $data = new \stdClass();
            $data->id = $this->id;
            $data->tutorialdate = $this->tutorialDate;
            if (!$DB->update_record("lbp_tutorials", $data)){
                $this->errors[] = get_string('errors:couldnotupdatedrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }


            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TUTORIAL, LOG_ACTION_ELBP_TUTORIAL_UPDATED_TUTORIAL, $this->studentID, array(
                "tutorialID" => $this->id,
                "tutorialDate" => $this->tutorialDate,
                "attributes" => http_build_query($this->attributes)
            ));

            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->Tutorials)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }


            // Attributes
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
                        $DB->delete_records("lbp_tutorial_attributes", array("tutorialid" => $this->id, "field" => $field));


                        foreach($value as $val)
                        {

                            if ($val == '') $val = null;

                            $ins = new \stdClass();
                            $ins->tutorialid = $this->id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_tutorial_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }

                        }

                    }
                    else
                    {

                        if ($value == '') $value = null;

                        // Get att from DB
                        $attribute = $DB->get_record_select("lbp_tutorial_attributes", "tutorialid = ? AND field = ?", array($this->id, $field));

                        // if it exists, update it
                        if ($attribute)
                        {
                            $ins = new \stdClass();
                            $ins->id = $attribute->id;
                            $ins->value = $value;
                            if (!$DB->update_record("lbp_tutorial_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                        }

                        // Else, insert it
                        else
                        {
                            $ins = new \stdClass();
                            $ins->tutorialid = $this->id;
                            $ins->field = $field;
                            $ins->value = $value;
                            if (!$DB->insert_record("lbp_tutorial_attributes", $ins)){
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
                            $DB->delete_records("lbp_tutorial_attributes", array("tutorialid" => $this->id, "field" => $allAttribute->name));
                        }

                    }
                }

            }

            // Trigger alerts
            $alertContent = get_string('alerts:tutorialupdated', 'block_elbp') .
                            $this->getInfoForEventTrigger(false);
            $htmlContent = get_string('alerts:tutorialupdated', 'block_elbp') .
                           $this->getInfoForEventTrigger(true, $tmp);
            elbp_event_trigger("Tutorial Updated", $this->Tutorials->getID(), $this->studentID, $alertContent, $htmlContent);



            return true;

        }


    }

    /**
     * Delete this tutorial
     */
    public function delete(){

        global $DB;

        $obj = new \stdClass();
        $obj->id = $this->id;
        $obj->del = 1;

        if($DB->update_record("lbp_tutorials", $obj)){

            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_TUTORIAL, LOG_ACTION_ELBP_TUTORIAL_DELETED_TUTORIAL, $this->studentID, array(
                "tutorialID" => $this->id
            ));

            return true;
        }

        return false;

    }


    /**
     * Display the tutorial information in the expanded view
     * @global type $ELBP
     * @return type
     */
    public function display(){

        global $ELBP, $DB;

        $ELBPDB = new \ELBP\DB();

        $tutorials = $this->Tutorials;

        $attributes = $tutorials->getAttributesForDisplay($this);

        if (!$attributes) return get_string('noattributesdefinedtutorial', 'block_elbp');

        $previousTutorials = $tutorials->getOldTutorials($this->tutorialDate, $this->id);
        $previousIDs = array();
        if ($previousTutorials)
        {
            foreach($previousTutorials as $previous)
            {
                $previousIDs[] = $previous->id;
            }
        }




        $output = "";
        $output .= "<p class='elbp_centre'><small>".get_string('setby', 'block_elbp').": ".fullname($this->getSetByUser())."</small></p>";



        // Main central elements
        $output .= "<div>";
        $output .= "<div class='elbp_tutorial_main_elements'>";
        $mainAttributes = $this->Tutorials->getAttributesForDisplayDisplayType("main", $attributes);
        if ($mainAttributes)
        {
            foreach($mainAttributes as $attribute)
            {
                $output .= "<h2>{$attribute->name}</h2>";
                $output .= "<div class='elbp_tutorial_attribute_content'>";
                    $output .= $attribute->displayValue();
                $output .= "</div>";
                $output .= "<br>";
            }
        }
        $output .= "</div>";

        // Summary
        $output .= "<div class='elbp_tutorial_summary_elements'>";



        // Attendance Hook First
        if ($tutorials->hasHookEnabled("Attendance/Averages"))
        {


            $ATT = $ELBP->getPlugin("Attendance");

            if ($ATT)
            {

                $data = $ATT->_retrieveHook_Averages();
                $output .= "<table class='elbp_tutorial_attribute_table'>";
                $output .= "<tr>";
                $output .= "<th></th>";
                    foreach($data['periods'] as $period)
                    {
                        $output .= "<th>{$period}</th>";
                    }
                $output .= "</tr>";

                    foreach($data['types'] as $type)
                    {

                        $output .= "<tr>";
                        $output .= "<td>{$type}</td>";

                        foreach($data['periods'] as $period)
                        {
                            $field = $type . " " . $period;
                            $output .= "<td>".$this->getAttribute($field)."</td>";
                        }

                        $output .= "</tr>";

                    }

                $output .= "</table>";

            }


        }

        $output .= "<br>";

        if ($tutorials->hasHookEnabled("Attendance/Course")){

            $ELBPDB = new \ELBP\DB();
            $ATT = $ELBP->getPlugin("Attendance");

            if ($ATT){

                $dataHooks = $ATT->_retrieveHook_Course();
                $dataSet = $this->attributes;
                $entries = array();

                foreach($dataSet as $key => $data){

                    if(strpos($key,':') !== false){

                        $exp1 = explode(':',$key);
                        $courseID = $exp1[1];
                        $courseQ = $ELBPDB->getCourse( array("type" => "id", "val" => $courseID) );

                        if(!empty($courseQ)){
                            $c = $ATT->getCourseName($courseQ);
                            $entries[$courseID]['courseid'] = $c;
                            foreach($dataHooks['types'] as $type){
                                if(strpos($key,$type) !== false){
                                    $entries[$courseID][$type] = $data;
                                }
                            }
                        }

                    }

                }

                if(!empty($courseID)){

                    $output .= '<table><tr><th>'.get_string('course', 'block_elbp').'</th>';
                    foreach($dataHooks['types'] as $type){
                        $output .= "<th>{$type}</th>";
                    }
                    $output .= '</tr>';

                    foreach($entries as $c){
                        $output .= '<tr>';
                        foreach($c as $k => $v){
                           $output .= '<td class="elbp_centre">'.$v.'</td>';
                        }
                        $output .= '</tr>';
                    }
                    $output .= "</table>";

                }

            }

        }

        // Targets hook can't be done automatically
        if ($tutorials->hasHookEnabled("Targets/Targets") && $ELBP->getPlugin("Targets"))
        {

            $achievedStatus = false;
            $check = $DB->get_record("lbp_target_status", array("achieved" => 1));
            if ($check)
            {
                $achievedStatus = $check->id;
            }

            // Number of targets set
            $numSet = (isset($this->attributes['Targets'])) ? @count($this->attributes['Targets']) : 0;
            $output .= "<b>{$ELBP->getPlugin("Targets")->getTitle()}</b><br><br>";
            $output .= "<table class='tutorial_summary_table'><tr><td>".get_string('numberoftargetsset', 'block_elbp').":</td><td>{$numSet}</td></tr></table>";

            $oldTargets = array();

            // List of targets in previous tutorials
            if ($previousIDs)
            {
                $check = $DB->get_records_select("lbp_tutorial_attributes", "tutorialid IN (".implode(',', $previousIDs).") AND field = 'Targets'");
                if ($check)
                {
                    foreach($check as $target)
                    {
                        $obj = new \ELBP\Plugins\Targets\Target($target->value);
                        if ($obj->isValid())
                        {
                            $oldTargets[] = $obj;
                        }
                    }
                }
            }

            // Targets set in previous tutorials that either HAVE or HAVE NOT been achieved
            if ($achievedStatus)
            {

                $numOldUnachieved = 0;
                $numOldAchieved = 0;

                if ($oldTargets)
                {

                    foreach($oldTargets as $target)
                    {

                        if ($target->isAchieved()) $numOldAchieved++;
                        else $numOldUnachieved++;

                    }

                }

            }

            $output .= "<table class='tutorial_summary_table'><tr><td>".get_string('numberofoldachievedtargets', 'block_elbp').":</td><td>{$numOldAchieved}</td></tr></table>";
            $output .= "<table class='tutorial_summary_table'><tr><td>".get_string('numberofoldunachievdtargets', 'block_elbp').":</td><td>{$numOldUnachieved}</td></tr></table>";


        }


        $output .= "<br>";

        $sideAttributes = $this->Tutorials->getAttributesForDisplayDisplayType("side", $attributes);
        if ($sideAttributes)
        {
            $output .= "<b>".get_string('otherattributes', 'block_elbp')."</b><br><br>";
            $output .= "<table class='tutorial_summary_table'>";
            foreach($sideAttributes as $attribute)
            {
                $output .= "<tr><td>{$attribute->name}:</td><td>".$attribute->displayValue()."</td></tr>";
            }
            $output .= "</table>";
        }


        $output .= "</div>";
        $output .= "<br class='elbp_cl'>";
        $output .= "</div>";

        // Targets

        if ($tutorials->hasHookEnabled("Targets/Targets") && $ELBP->getPlugin("Targets"))
        {

            $targetsObj = \ELBP\Plugins\Plugin::instaniate("Targets");

            $targetList = array();

            $output .= "<div class='elbp_tutorial_targets_this'>";
                $output .= "<h3>".get_string('targetssetinthistutorial', 'block_elbp')."</h3>";

                if (isset($this->attributes['Targets']))
                {
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

                    $output .= "<table class='tutorial_targets_table'>";
                    $output .= "<tr>";
                    $output .= "<th>".get_string('targetname', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('status', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('deadline', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('numberofcomments', 'block_elbp')."</th>";
                    $output .= "</tr>";

                    foreach($targetList as $target)
                    {
                        $output .= "<tr class='target_row_{$target->getID()}'>";
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
                        $output .= "<a href='#' onclick='ELBP.save_state(\"Tutorials\");ELBP.dock(\"Tutorials\", \"".elbp_html($tutorials->getTitle())."\");ELBP.Targets.load_targets({$target->getStatus()}, {$target->getID()});return false;' title='{$title}' class='target_name_tooltip'>".elbp_html($target->getName())."</a>";
                        $output .= "</td>";
                        $output .= "<td>";

                            if (elbp_has_capability('block/elbp:edit_target', $this->Tutorials->getAccess()) || elbp_has_capability('block/elbp:update_target_status', $this->Tutorials->getAccess()))
                            {
                                $output .= "<select onchange='ELBP.Targets.update_status({$target->getID()}, this.value);return false;'>";

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
                            }

                            if ($target->isAchieved())
                            {
                                $output .= "<br><small>{$target->getUpdatedDate()}</small>";
                            }
                        $output .= "</td>";
                        $output .= "<td>{$target->getDueDate('j M Y')}</td>";
                        $output .= "<td>{$target->countComments()}</td>";
                        $output .= "</tr>";
                    }

                    $output .= "</table>";

                }
                else
                {
                    $output .= get_string('noresults', 'block_elbp');
                }

            $output .= "</div>";


            $output .= "<div class='elbp_tutorial_targets_previous'>";
                $output .= "<h3>".get_string('targetssetinprevioustutorials', 'block_elbp')."</h3>";

                $targetList = $oldTargets;

                if ($targetList)
                {

                    $output .= "<table class='tutorial_targets_table'>";
                    $output .= "<tr>";
                    $output .= "<th>".get_string('date', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('targetname', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('status', 'block_elbp')."</th>";
                    $output .= "<th>".get_string('deadline', 'block_elbp')."</th>";
                    $output .= "</tr>";

                    if (!empty($previousIDs))
                    {
                        foreach($targetList as $target)
                        {
                            $output .= "<tr class='target_row_{$target->getID()}'>";
                            $output .= "<td>{$target->getSetDate('j M Y')}</td>";
                            $title = '';
                            if ($targetsObj)
                            {
                                $hoverAttribute = $target->getAttribute($targetsObj->getSetting('external_target_name_hover_attribute'));
                                if ($hoverAttribute)
                                {
                                    $title .= elbp_html($hoverAttribute);
                                }
                            }
                            $output .= "<td><a href='#' onclick='ELBP.save_state(\"Tutorials\");ELBP.dock(\"Tutorials\", \"".elbp_html($tutorials->getTitle())."\");ELBP.Targets.load_targets({$target->getStatus()}, {$target->getID()});return false;' title='{$title}' class='target_name_tooltip'>".elbp_html($target->getName())."</a></td>";
                            $output .= "<td>";
                                if (elbp_has_capability('block/elbp:edit_target', $this->Tutorials->getAccess()) || elbp_has_capability('block/elbp:update_target_status', $this->Tutorials->getAccess()))
                                {
                                    $output .= "<select onchange='ELBP.Targets.update_status({$target->getID()}, this.value);return false;'>";

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
                                }
                                if ($target->isAchieved())
                                {
                                    $output .= "<br><small>{$target->getUpdatedDate()}</small>";
                                }
                            $output .= "</td>";
                            $output .= "<td>{$target->getDueDate('j M Y')}</td>";
                            $output .= "</tr>";
                        }
                    }

                    $output .= "</table>";

                }
                else
                {
                    $output .= get_string('noresults', 'block_elbp');
                }

            $output .= "</div>";

        }

        $output .= "<br style='clear:both;' /><br>";
        $output .= "<script>$('.target_name_tooltip').tooltip();</script>";


        echo $output;

    }

    /**
     * Get content for event triggered alert emails
     * @global \ELBP\Plugins\Tutorials\type $CFG
     * @global \ELBP\Plugins\Tutorials\type $USER
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
            if ($this->courseID > 1){
                $output .= get_string('course') . ": " . $this->getCourse()->fullname . "<br>";
            }

            // Old target name
            $output .= get_string('tutorialdate', 'block_elbp') . ": " . $this->getTutorialDate('M jS Y') . "<br>";


            // Attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {

                    if (is_array($value)) $value = implode(",", $value);
                    $value = preg_replace("/\n/", " ", $value);

                    if ($field == 'Targets')
                    {

                        // Old
                        $targetList = $tmp->getAttributeAsIs($field);
                        $oldTargets = '';
                        if ($targetList)
                        {
                            if (!is_array($targetList)) $targetList = array($targetList);
                            foreach($targetList as $targetListed)
                            {
                                $oldTargetObj = new \ELBP\Plugins\Targets\Target($targetListed);
                                if ($oldTargetObj->isValid())
                                {
                                    $oldTargets .= $oldTargetObj->getName() . ", ";
                                }
                            }
                        }
                        $output .= "<del style='color:red;'>{$field}: " . $oldTargets . "</del><br>";

                        // New
                        $targetList = $this->getAllTargets();
                        $newTargets = '';
                        if ($targetList)
                        {
                            foreach($targetList as $targetListed)
                            {
                                $newTargets .= $targetListed->getName() . ", ";
                            }
                        }
                        $output .= "<ins style='color:blue;'>{$field}: " . $newTargets . "</ins><br>";


                    }
                    else
                    {
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
            if ($this->courseID > 1){
                $output .= get_string('course') . ": " . $this->getCourse()->fullname . "\n";
            }
            $output .= get_string('tutorialdate', 'block_elbp') . ": " . $this->getTutorialDate('M jS Y') . "\n";

            // Attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {

                    if ($field == 'Targets')
                    {

                        if (!is_array($value)) $value = array($value);

                        $targetOutput = '';
                        foreach($value as $targetID)
                        {
                            $targetObj = new \ELBP\Plugins\Targets\Target($targetID);
                            if ($targetObj->isValid())
                            {
                                $targetOutput .= $targetObj->getName() . ", ";
                            }
                        }
                        $output .= $field . ": " . $targetOutput . "\n";
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
     * Print out to html
     */
    public function printOut()
    {

        global $CFG, $ELBP;

        ob_clean();

        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('tutorial', 'block_elbp') . ' - ' . $this->getTutorialDate('D jS M Y');
        $logo = \ELBP\ELBP::getPrintLogo();
        $title = get_string('tutorial', 'block_elbp');
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';

        $attributes = $this->Tutorials->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);

        $txt = "";
        $txt .= "<table class='info'>";
            $txt .= "<tr><td>".get_string('dateset', 'block_elbp').": {$this->getTutorialDate('D jS M Y')}</td><td>".get_string('setby', 'block_elbp').": {$this->getStaffName()}</td><td>".get_string('targetsset', 'block_elbp').": {$this->countTargets()}</td></tr>";
        $txt .= "</table>";

        $txt .= "<br><br><br><br>";

            // Attendance Hook First
            if ($this->Tutorials->hasHookEnabled("Attendance/Averages"))
            {

                $data = $ELBP->getPlugin("Attendance")->_retrieveHook_Averages();

                $txt .= "<p class='c'><b>".$ELBP->getPlugin("Attendance")->getTitle()."</b></p>";
                $txt .= "<table class='info'>";
                $txt .= "<tr>";
                $txt .= "<th></th>";
                    foreach($data['periods'] as $period)
                    {
                        $txt .= "<th>{$period}</th>";
                    }
                $txt .= "</tr>";

                    foreach($data['types'] as $type)
                    {

                        $txt .= "<tr>";
                        $txt .= "<td>{$type}</td>";

                        foreach($data['periods'] as $period)
                        {
                            $field = $type . " " . $period;
                            $txt .= "<td>".$this->getAttribute($field)."</td>";
                        }

                        $txt .= "</tr>";

                    }

                $txt .= "</table>";


            }

            $txt .= "<br>";

            if ($this->Tutorials->hasHookEnabled("Attendance/Course")){
                $ATT = $ELBP->getPlugin("Attendance");

                $dataHooks = $ATT->_retrieveHook_Course();
                global $DB;
                if ($ATT){
                    $dataSet = $this->attributes;
                    $entries = array();
                    foreach($dataSet as $key => $data){
                        if(strpos($key,':') !== false){
                            $exp1 = explode(':',$key);
                            $courseID = $exp1[1];
                            $courseQ = $DB->get_record('course', array('id'=>$courseID));
                            if(!empty($courseQ)){
                                $c = $ATT->getCourseName($courseQ);
                                $entries[$courseID]['courseid'] = $c;
                                foreach($dataHooks['types'] as $type){
                                    if(strpos($key,$type) !== false){
                                        $entries[$courseID][$type] = $data;
                                    }
                                }
                            }
                        }
                    }
                    if(!empty($courseID)){
                        $txt .= '<table class="info"><tr><th>'.get_string('course', 'block_elbp').'</th>';
                        foreach($dataHooks['types'] as $type){
                            $txt .= "<th>$type</th>";
                        }
                        $txt .= '</tr>';
                        foreach($entries as $c){
                            $txt .= '<tr>';
                            foreach($c as $k => $v){
                               $txt .= '<td class="elbp_centre">'.$v.'</td>';
                            }
                            $txt .= '</tr>';
                        }
                        $txt .= "</table>";
                    }
                }
            }

            $txt .= "<br><hr><br>";


            // Side attributes
            $sideAttributes = $this->Tutorials->getAttributesForDisplayDisplayType("side", $attributes);
            if ($sideAttributes)
            {
                $n = 0;
                $num = 0;
                $cnt = count($sideAttributes);

                $txt .= "<table class='info'>";

                foreach($sideAttributes as $attribute)
                {

                    $n++;
                    $num++;

                    if ($n == 1){
                        $txt .= "<tr num='{$n}'>";
                    }

                    if ($attribute->display == 'side')
                    {
                        $txt .= "<td>{$attribute->name}: ".$attribute->displayValue(true) . "</td>";
                    }

                    if ($n == 2 || $num == $cnt){
                        $txt .= "</tr>";
                        $n = 0;
                    }

                }

                $txt .= "</table>";

            }

        $txt .= "<br><br>";

        // Main attributes
        $mainAttributes = $this->Tutorials->getAttributesForDisplayDisplayType("main", $attributes);
        if ($mainAttributes)
        {

            foreach($mainAttributes as $attribute)
            {

                if ($attribute->display == 'main')
                {
                    $txt .= "<div class='attribute-main'><p class='b'>{$attribute->name}</p><p>".$attribute->displayValue(true) . "</p></div>";
                }

            }
        }

        $txt .= "<br><br>";

        $txt .= "<hr>";



        if ($this->Tutorials->hasHookEnabled("Targets/Targets"))
        {

            $targetsObj = \ELBP\Plugins\Plugin::instaniate("Targets");
            $targetAttributes = $targetsObj->getAttributesForDisplay();

            $targetList = array();

            $txt .= "<div>";
                $txt .= "<p class='c'><b>".get_string('targetssetinthistutorial', 'block_elbp')."</b></p>";

                if (isset($this->attributes['Targets']))
                {
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

                    foreach($targetList as $target)
                    {

                        $target->loadObjectIntoAttributes($targetAttributes);

                        $txt .= "<b>".elbp_html($target->getName()) . "</b><br>";

                        $txt .= $target->getStatusName();

                        if ($target->isAchieved())
                        {
                            $txt .= " - <small>{$target->getUpdatedDate()}</small><br>";
                        }
                        else
                        {
                            $txt .= " - <small>" . get_string('deadline', 'block_elbp') . ": {$target->getDueDate()}</small><br>";
                        }

                        $txt .= "<br>";

                        $sideTargetAttributes = $targetsObj->getAttributesForDisplayDisplayType("side", $targetAttributes);
                        if ($sideTargetAttributes)
                        {
                            foreach($sideTargetAttributes as $attribute)
                            {
                                $txt .= "<span>{$attribute->name}: ".$attribute->displayValue(true). "</span><br>";
                            }
                        }

                        $txt .= "<br>";

                        $mainTargetAttributes = $targetsObj->getAttributesForDisplayDisplayType("main", $targetAttributes);
                        if ($mainTargetAttributes)
                        {
                            foreach($mainTargetAttributes as $attribute)
                            {
                                $txt .= "<u>{$attribute->name}</u><br>";
                                $txt .= $attribute->displayValue(true);
                                $txt .= "<br><br>";
                            }
                        }


                        $txt .= "<br><br>";

                    }


                }
                else
                {
                    $txt .= get_string('noresults', 'block_elbp');
                }

            $txt .= "</div>";

        }



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
     * Get an array of data to be put into a new/edit tutorial form
     * @param int $tutorialID
     */
    public static function getDataForNewTutorialForm($tutorialID = false)
    {

        global $ELBP, $DB;

        $tutorials = $ELBP->getPlugin("Tutorials");
        $data = array();

        $attributes = $tutorials->getAttributesForDisplay();

        if ($tutorialID)
        {

            $tutorial = new Tutorial($tutorialID);
            if (!$tutorial->isValid()) return false;

            $data['id'] = $tutorial->getID();
            $data['date'] = $tutorial->getTutorialDate("d-m-Y");
            $data['atts'] = array();
            $data['hookAtts'] = array();

            // Since it's a real Session, get all the actual attributes stored for it, not just the ones we think it should have from the config
            $definedAttributes = $tutorial->getAttributes();
            $processedAttributes = array();

            // Loop through all possible attributes defined in the system
            if ($attributes)
            {
                foreach($attributes as $attribute)
                {

                    $attribute->loadObject($tutorials);

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
            $data['atts'] = array();
            $data['hookAtts'] = array();
            $data['atts'] = $attributes;

        }

        return $data;

    }



}