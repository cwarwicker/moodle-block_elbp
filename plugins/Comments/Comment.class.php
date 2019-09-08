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

namespace ELBP\Plugins\Comments;

/**
 *
 */
class Comment extends \ELBP\BasePluginObject {

    private $id = false;
    private $studentID;
    private $setByUserID;
    private $setTime;
    private $date;
    private $resolved;
    private $positive;
    private $del;
    private $hidden;
    private $published;

    private $errors = array();
    protected $attributes = array();
    private $Comments;
    private $student;
    private $staff;

    /**
     * Construct Comment object
     * @global \ELBP\Plugins\Comments\type $DB
     * @param type $id
     */
    public function __construct($id) {

        global $DB;

        if (is_numeric($id))
        {

            $record = $DB->get_record("lbp_comments", array("id" => $id));
            if ($record)
            {

                $this->id = $record->id;
                $this->studentID = $record->studentid;
                $this->setByUserID = $record->setbyuserid;
                $this->setTime = $record->settime;
                $this->date = $record->commentdate;
                $this->resolved = $record->resolved;
                $this->positive = $record->positive;
                $this->del = $record->del;
                $this->hidden = $record->hidden;
                $this->published = $record->published;

                $this->staff = $DB->get_record("user", array("id"=>$this->setByUserID));

                $this->loadAttributes();

            }

        }
        elseif (is_array($id))
        {

            // Build new one from data provided
            $this->loadData($id);

        }

    }

    /**
     * Is the Comment valid?
     * @return type
     */
    public function isValid(){
        return ($this->id !== false) ? true : false;
    }

    /**
     * Get the ID of the Comment
     * @return type
     */
    public function getID(){
        return $this->id;
    }

    /**
     * Get the id of the student
     * @return type
     */
    public function getStudentID(){
        return $this->studentID;
    }

    /**
     * Get the student
     * @global \ELBP\Plugins\Comments\type $DB
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
     * Get the id of the user who set the comment
     * @return type
     */
    public function getSetByUserID(){
        return $this->setByUserID;
    }

    /**
     * Get the user who set the comment
     * @global \ELBP\Plugins\Comments\type $DB
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
    public function getSetByUserFullName(){
        return fullname($this->staff);
    }

    /**
     * Get the unix timestamp the comment was created
     * @return type
     */
    public function getSetTime(){
        return $this->setTime;
    }

    /**
     * Get the date defined in the comment
     * @param bool $format If false will return unix timestamp, else will return date($format) of it
     * @return type
     */
    public function getDate($format = false){
        if (!$format) return $this->date;
        else return date($format, $this->date);
    }

    /**
     * Get the date the comment was set
     * @param bool $format If false will return unix timestamp, else will return date($format) of it
     * @return type
     */
    public function getSetDate($format = false){
        if (!$format) return $this->setTime;
        else return date($format, $this->setTime);
    }

    /**
     * Is it deleted?
     * @return type
     */
    public function isDeleted(){
        return ($this->del == 1) ? true : false;
    }

    /**
     * Is it hidden?
     * @return type
     */
    public function isHidden(){
        return ($this->hidden == 1) ? true : false;
    }

    /**
     * Is it resolved?
     * @return type
     */
    public function isResolved(){
        return ($this->resolved == 1) ? true : false;
    }

    /**
     * Is it published for parents/guardians/employers to see on the parent portal?
     * @return type
     */
    public function isPublished(){
        return ($this->published == 1) ? true : false;
    }

    /**
     * Get the name of the image to use for the resolved icon
     * @return type
     */
    public function getResolvedImage(){
        return ($this->isResolved()) ? 'tick' : 'warning';
    }

    /**
     * Get the file to use for the positive/negative icon
     * @return boolean
     */
    public function getPositiveImage(){
        if ($this->positive == 1) return $this->Comments->getPositiveIconImage();
        elseif ($this->positive == -1) return $this->Comments->getNegativeIconImage();
        else return false;
    }

    /**
     * Get the background colour for positive/negative
     * @return string
     */
    public function getPositiveColour(){
        if ($this->positive == 1) return 'blue';
        elseif ($this->positive == -1) return 'red';
        else return '#000';
    }

    /**
     * Get the background colour for positive/negative
     * @return string
     */
    public function getPositiveBackgroundColour(){
        if ($this->positive == 1) return '#E5FFE0';
        elseif ($this->positive == -1) return '#FFE0E0';
        else return '#FAFAFA';
    }

    /**
     * Get the string for positive/negative
     * @return string
     */
    public function getPositiveText(){
        if ($this->positive == 1) return get_string('positive', 'block_elbp');
        elseif ($this->positive == -1) return get_string('negative', 'block_elbp');
        else return '';
    }

    /**
     * Get int value for if comment is positive/neutral/negative (1/0/-1)
     * @return type
     */
    public function getPositive(){
        return $this->positive;
    }

    /**
     * Get any errors
     * @return type
     */
    public function getErrors(){
        return $this->errors;
    }

    /**
     * Load the attributes set for this comment
     * @global \ELBP\Plugins\Comments\type $DB
     * @return type
     */
    public function loadAttributes(){

        global $DB;

        $check = $DB->get_records("lbp_comment_attributes", array("commentid" => $this->id), "id ASC");

        $this->attributes = parent::_loadAttributes($check);

        return $this->attributes;

    }

    /**
     * Print the comment out to a simple HTML page
     * @global type $CFG
     * @global \ELBP\Plugins\Comments\type $USER
     */
    public function printOut()
    {

        global $CFG, $USER;

        ob_clean();

        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('comment', 'block_elbp') . ' - ' . $this->getDate('d m Y');
        $logo = \ELBP\ELBP::getPrintLogo();
        $title = get_string('comment', 'block_elbp');
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';

        $attributes = $this->Comments->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);

        $txt = "";

        $txt .= "<table class='info'>";

            if ($this->positive == 1) $posNeg = get_string('positive', 'block_elbp');
            elseif ($this->positive == -1) $posNeg = get_string('negative', 'block_elbp');
            else $posNeg = get_string('na', 'block_elbp');

            $resolved = ($this->isResolved()) ? get_string('resolved', 'block_elbp') : get_string('unresolved', 'block_elbp');

            $txt .= "<tr><td colspan='3'>".$this->getDate('D jS M Y')."</td></tr>";
            $txt .= "<tr><td>".get_string('setby', 'block_elbp').": ".$this->getSetByUserFullName()."</td><td>".get_string('dateset', 'block_elbp').": ".$this->getSetDate('D jS M Y')."</td></tr>";
            $txt .= "<tr><td>{$posNeg}</td><td>{$resolved}</td></tr>";

            // Side attributes
            $sideAttributes = $this->Comments->getAttributesForDisplayDisplayType("side", $attributes);
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

                    $txt .= "<td>{$attribute->name}: ".$attribute->displayValue(true). "</td>";

                    if ($n == 2 || $num == $cnt){
                        $txt .= "</tr>";
                        $n = 0;
                    }

                }
            }

        $txt .= "</table>";

        $txt .= "&nbsp;<br><hr>&nbsp;<br>";


        // Main central elements
        $mainAttributes = $this->Comments->getAttributesForDisplayDisplayType("main", $attributes);

        if ($mainAttributes)
        {

            foreach($mainAttributes as $attribute)
            {
                $txt .= "<div class='attribute-main'><p class='b'>{$attribute->name}</p><p>".$attribute->displayValue(true). "</p></div>";
            }

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
     * Set the Comments plugin object into the Comment
     * @param type $obj
     */
    public function setCommentsObj($obj)
    {
        $this->Comments = $obj;
    }

    /**
     * Display the Comment info in the expanded view
     */
    public function display(){

        $attributes = $this->Comments->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);

        if (!$attributes) return get_string('noattributesdefined', 'block_elbp');

        $output = "";

        $output .= "<div>";

        // Main central elements
        $output .= "<div class='elbp_comment_main_elements'>";
            $mainAttributes = $this->Comments->getAttributesForDisplayDisplayType("main", $attributes);

            if ($mainAttributes)
            {
                foreach($mainAttributes as $attribute)
                {
                    $output .= "<h2>{$attribute->name}</h2>";
                    $output .= "<div class='elbp_comment_attribute_content'>";
                        $output .= $attribute->displayValue();
                    $output .= "</div>";
                    $output .= "<br>";
                }
            }
        $output .= "</div>";


        // Summary
        $output .= "<div class='elbp_comment_summary_elements'>";

            $sideAttributes = $this->Comments->getAttributesForDisplayDisplayType("side", $attributes);

            if ($sideAttributes)
            {
                $output .= "<b>".get_string('otherattributes', 'block_elbp')."</b><br><br>";
                $output .= "<table class='comment_summary_table'>";
                foreach($sideAttributes as $attribute)
                {
                     $output .= "<tr><td>{$attribute->name}:</td><td>".$attribute->displayValue()."</td></tr>";
                }
                $output .= "</table>";
            }

        $output .= "</div>";
        $output .= "<br class='elbp_cl'>";
        $output .= "</div>";

        echo $output;


    }

    /**
     * Delete Comment
     * @return boolean
     */
    public function delete()
    {

        global $DB;

        $data = new \stdClass();
        $data->id = $this->id;
        $data->del = 1;

        if (!$DB->update_record("lbp_comments", $data)){
            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }

        // Log Action
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COMMENT, LOG_ACTION_ELBP_COMMENTS_DELETED_COMMENT, $this->studentID, array(
            "commentID" => $this->id
        ));

        return true;
    }

    /**
     * Resolve the comment
     * @global \ELBP\Plugins\Comments\type $DB
     * @param type $val
     * @return boolean
     */
    public function resolve($val){

        global $DB;

        if (!$this->id) return false;

        $obj = new \stdClass();
        $obj->id = $this->id;
        $obj->resolved = $val;

        if (!$DB->update_record("lbp_comments", $obj)){
            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }

        $this->resolved = $val;

        // Log Action
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COMMENT, LOG_ACTION_ELBP_COMMENTS_RESOLVED_COMMENT, $this->studentID, array(
             "commentID" => $this->id
        ));

        return true;

    }

    /**
     * Save the comment into the DB
     * @global type $USER
     * @global type $DB
     * @return boolean
     */
    public function save(){

        global $USER, $DB;

        if (!$this->id) return false;

        if (!ctype_digit($this->date)) $this->errors[] = get_string('commenterrors:date', 'block_elbp');
        if (!ctype_digit($this->studentID)) $this->errors[] = get_string('commenterrors:studentid', 'block_elbp');

        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->Comments->getElementsFromAttributeString();

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

        // Tmp object for alerts
        $tmp = new Comment($this->id);

        // Save it

        // New, so insert it
        if ($this->id == -1)
        {

            $obj = new \stdClass();
            $obj->studentid = $this->studentID;
            $obj->commentdate = $this->date;
            $obj->setbyuserid = $USER->id;
            $obj->settime = time();
            $obj->del = 0;
            $obj->resolved = $this->resolved;
            $obj->positive = $this->positive;
            $obj->hidden = $this->hidden;
            $obj->published = $this->published;

            // Insert the target
            if (!$id = $DB->insert_record("lbp_comments", $obj)){
                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }

            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COMMENT, LOG_ACTION_ELBP_COMMENTS_ADDED_COMMENT, $this->studentID, array(
                "commentID" => $id,
                "date" => $this->date,
                "attributes" => http_build_query($this->attributes)
            ));

            $this->id = $id;

            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->Comments)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
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

                            // Insert
                            $ins = new \stdClass();
                            $ins->commentid = $id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_comment_attributes", $ins)){
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
                        $ins->commentid = $id;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_comment_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                    }



                }

            }

            // Trigger alerts
            $alertContent = get_string('alerts:commentadded', 'block_elbp') .
                            $this->getInfoForEventTrigger(false);

            // Staff alerts
            elbp_event_trigger("Comment Added", $this->Comments->getID(), $this->studentID, $alertContent, nl2br($alertContent));



        }
        else
        {

            // Update
            $obj = new \stdClass();
            $obj->id = $this->id;
            $obj->commentdate = $this->date;
            $obj->del = 0;
            $obj->resolved = $this->resolved;
            $obj->positive = $this->positive;
            $obj->hidden = $this->hidden;
            $obj->published = $this->published;

            if (!$DB->update_record("lbp_comments", $obj)){
                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }

            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->Comments)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }

            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COMMENT, LOG_ACTION_ELBP_COMMENTS_UPDATED_COMMENT, $this->studentID, array(
                 "commentID" => $this->id,
                 "date" => $this->date,
                 "attributes" => http_build_query($this->attributes)
            ));

            // Now using that target ID, insert it's attributes
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
                        $DB->delete_records("lbp_comment_attributes", array("commentid" => $this->id, "field" => $field));

                        foreach($value as $val)
                        {

                            if ($val == '') $val = null;

                            $ins = new \stdClass();
                            $ins->commentid = $this->id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_comment_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }

                        }

                    }
                    else
                    {

                        if ($value == '') $value = null;

                        // Get record
                        $check = $DB->get_record("lbp_comment_attributes", array("commentid" => $this->id, "field" => $field));
                        if ($check)
                        {
                            $check->value = $value;
                            if (!$DB->update_record("lbp_comment_attributes", $check)){
                                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                        }
                        else
                        {
                            // Insert
                            $ins = new \stdClass();
                            $ins->commentid = $this->id;
                            $ins->field = $field;
                            $ins->value = $value;
                            if (!$DB->insert_record("lbp_comment_attributes", $ins)){
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
                            $DB->delete_records("lbp_comment_attributes", array("commentid" => $this->id, "field" => $allAttribute->name));
                        }

                    }
                }


            }

            // Trigger alerts
            $alertContent = get_string('alerts:commentupdated', 'block_elbp') .
                            $this->getInfoForEventTrigger(false);
            $htmlContent = get_string('alerts:commentupdated', 'block_elbp') .
                           $this->getInfoForEventTrigger(true, $tmp);

            // Student alert - bedford wanted this off
            elbp_event_trigger_student("Comment Updated", $this->Comments->getID(), $this->studentID, $alertContent, $htmlContent);

            // Staff alerts
            elbp_event_trigger("Comment Updated", $this->Comments->getID(), $this->studentID, $alertContent, $htmlContent);



        }


        return true;


    }


    /**
     * Given an array of data, build up the Comment object based on that instead of a DB record
     * This is used for creating a new Comment or editing an existing one
     * @param type $data
     */
    public function loadData($data){

        $this->id = (isset($data['comment_id'])) ? $data['comment_id'] : -1; # Set to -1 if not set, as probably new incident
        if (isset($data['studentID'])) $this->studentID = $data['studentID'];
        if (isset($data['comment_date']) && !elbp_is_empty($data['comment_date'])){
            $date =  \DateTime::createFromFormat('d-m-Y H:i:s', $data['comment_date'] . ' 00:00:00');
            $this->date = $date->format("U"); # Unix
        }

        $this->resolved = (isset($data['comment_resolved'])) ? 0: 1;
        $this->hidden = (isset($data['comment_hidden'])) ? 1: 0;
        $this->positive = (isset($data['comment_positive'])) ? $data['comment_positive']: 0;
        $this->published = (isset($data['comment_published_portal'])) ? 1 : 0;

        unset($data['comment_id']);
        unset($data['studentID']);
        unset($data['comment_date']);
        unset($data['comment_resolved']);
        unset($data['comment_hidden']);
        unset($data['comment_positive']);
        unset($data['comment_published_portal']);

        // Attributes - FIrstly get all possible attributes and loop through them
        $OBJ = new \ELBP\Plugins\Comments();
        $this->setSubmittedAttributes($data, $OBJ);

    }

    /**
     * Get the content for the triggered alert emails
     * @global \ELBP\Plugins\Comments\type $CFG
     * @global \ELBP\Plugins\Comments\type $USER
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

            // Old target name
            $output .= get_string('commentdate', 'block_elbp') . ": " . $this->getDate('M jS Y') . "<br>";


            // Attributes
            if ($this->attributes)
            {

                foreach($this->attributes as $field => $value)
                {

                    if (is_array($value)) $value = implode(",", $value);
                    $value = preg_replace("/\n/", " ", $value);

                     // Old attribute value
                    $output .= "<del style='color:red;'>{$field}: " . $tmp->getAttribute($field) . "</del><br>";

                    // New attrribute value
                    $output .= "<ins style='color:blue;'>{$field}: " . $value . "</ins><br>";

                }

            }

            // Resolved?
                // Old
                $output .= "<del style='color:red;'>" . get_string('resolved', 'block_elbp') . ": " . ( ($tmp->isResolved()) ? get_string('yes', 'block_elbp') : get_string('no', 'block_elbp') ) . "</del><br>";

                // New
                $output .= "<ins style='color:blue;'>" . get_string('resolved', 'block_elbp') . ": " . ( ($this->isResolved()) ? get_string('yes', 'block_elbp') : get_string('no', 'block_elbp') ) . "</ins><br>";


            $output .= "----------<br>";
            $output .= get_string('updatedby', 'block_elbp') . ": " . fullname($USER) . "<br>";
            $output .= get_string('link', 'block_elbp') . ": " . "<a href='{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->studentID}'>{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->studentID}</a><br>";

        }

        // Otherwise
        else
        {
            $output .= "\n----------\n";
            $output .= get_string('student', 'block_elbp') . ": " . fullname($this->getStudent()) . " ({$this->getStudent()->username})\n";

            $output .= get_string('commentdate', 'block_elbp') . ": " . $this->getDate('M jS Y') . "\n";

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

            // Resolved?
            $output .= get_string('resolved', 'block_elbp') . ": " . ( ($this->isResolved()) ? get_string('yes', 'block_elbp') : get_string('no', 'block_elbp') ) . "\n";

            $output .= "----------\n";
            $output .= get_string('updatedby', 'block_elbp') . ": " . fullname($USER) . "\n";
            $output .= get_string('link', 'block_elbp') . ": " . "{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->studentID}\n";

        }


        return $output;

    }



    /**
     * Get an array of data to be put into a new/edit comment form
     * @param int $incidentID
     */
    public static function getDataForNewCommentForm($commentID = false, $commentsObj = false)
    {

        global $ELBP;

        if (!$ELBP && !$commentsObj) return false;

        if ($commentsObj){
            $comments = $commentsObj;
        } else {
            $comments = $ELBP->getPlugin("Comments");
        }

        $data = array();

        $attributes = $comments->getAttributesForDisplay();

        if ($commentID)
        {

            $comment = new Comment($commentID);
            if (!$comment->isValid()) return false;

            $data['id'] = $comment->getID();
            $data['date'] = $comment->getDate("d-m-Y");
            $data['isresolved'] = (!$comment->isResolved());
            $data['ishidden'] = $comment->isHidden();
            $data['positive'] = $comment->getPositive();
            $data['ispublishedportal'] = $comment->isPublished();

            // If it's a real Comment from the DB, let's get the actual attributes we have for it
            $data['atts'] = array();

            // Since it's a real Session, get all the actual attributes stored for it, not just the ones we think it should have from the config
            $definedAttributes = $comment->getAttributes();

            $processedAttributes = array();

            // Loop through default attributes
            if ($attributes)
            {
                foreach($attributes as $attribute)
                {

                    $attribute->loadObject($comments);

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
            $data['isresolved'] = false;
            $data['ishidden'] = false;
            $data['positive'] = false;
            $data['ispublishedportal'] = true;

            if ($attributes){
                foreach($attributes as $attribute){
                    $attribute->loadObject($comments);
                }
            }

            $data['atts'] = $attributes;

        }

        return $data;

    }



}