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

namespace ELBP\Plugins\CourseReports;

/**
 * 
 */
class CourseReport extends \ELBP\BasePluginObject {
    
    private $id = false;
    private $studentID;
    private $courseID;
    private $reportDate;
    private $comments;
    private $setTime;
    private $setByUserID;
    private $del;
    
    private $reviewQuestions;
    
    private $CourseReports; # CourseReports object
    private $errors = array();
    
    private $student;
    private $staff;
    private $course;
    
    protected $attributes = array();
    
    /**
     * Construct report object
     * @global type $DB
     * @param type $params
     * @param type $CourseReports
     */
    public function __construct($params, $CourseReports = false) {
        
        global $DB;
        
        // Set the CourseReports plugin object into the report object
        $this->CourseReports = $CourseReports;
        
        if (ctype_digit($params)){
            
            $record = $DB->get_record("lbp_course_reports", array("id" => $params));
            
            if ($record)
            {
                
                $this->id = $record->id;
                $this->studentID = $record->studentid;
                $this->courseID = $record->courseid;
                $this->reportDate = $record->reportdate;
                $this->comments = $record->comments;
                $this->setTime = $record->settime;
                $this->setByUserID = $record->setbyuserid;
                $this->del = $record->del;
                $this->loadAllReviews();
                $this->loadAttributes();
                
                $this->staff = $this->getSetByUser();
                
            }
            
        }
        elseif (is_array($params))
        {
            $this->loadData($params);
        }
        
    }
    
    /**
     * Is it a valid course report?
     * @return type
     */
    public function isValid(){
        
        if (!$this->id) return false;
        
        // Check if they are still enrolled on this course
        $ELBPDB = new \ELBP\DB();
        if (!$ELBPDB->isUserOnCourse($this->studentID, $this->courseID)) return false;       
        
        return true;
        
    }
    
    /**
     * Is the report deleted?
     * @return bool
     */
    public function isDeleted(){
        return ($this->del == 1) ? true : false;
    }
    
    /**
     * Get the id of the course report
     * @return type
     */
    public function getID(){
        return $this->id;
    }
    
    /**
     * Get the student id of the course report
     * @return type
     */
    public function getStudentID(){
        return $this->studentID;
    }
    
    /**
     * Get the course id of the course report
     * @return type
     */
    public function getCourseID(){
        return $this->courseID;
    }
    
    /**
     * Get the student record
     * @global \ELBP\Plugins\CourseReports\type $DB
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
     * @global \ELBP\Plugins\CourseReports\type $DB
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
     * Get the course report date
     * @param type $format To be used in date($format)
     * @return type
     */
    public function getDate($format = 'M jS Y'){
        return date($format, $this->reportDate);
    }
    
    /**
     * Get the unix timestamp of the report date
     * @return type
     */
    public function getDateUnix(){
        return $this->reportDate;
    }
    
    /**
     * Get any comments on the course report
     * @return type
     */
    public function getComments(){
        return $this->comments;
    }
    
    /**
     * Get the unix timestamp of the date the report was set
     * @return type
     */
    public function getSetUnix(){
        return $this->setTime;
    }
    
    /**
     * Get the date the report was set
     * @param type $format date($format)
     * @return type
     */
    public function getSetTime($format = 'M jS Y'){
        return date($format, $this->setTime);
    }
    
    /**
     * Get the id of the user who set the report
     * @return type
     */
    public function getSetByID(){
        return $this->setByUserID;
    }
    
    /**
     * Get the user record of the person who set the report
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return type
     */
    public function getSetByUser(){
        global $DB;
        return $DB->get_record("user", array("id" => $this->setByUserID));
    }
    
    /**
     * Get the fullname of the user who set the report
     * @return type
     */
    public function getStaffName(){
        return fullname($this->staff);
    }
    
    /**
     * Get the name of the course and the date of the report in a string
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return string
     */
    public function getShortDetail(){
        
        global $DB;
        $course = $DB->get_record("course", array("id" => $this->courseID));
        
        $output = $course->fullname . " ({$course->shortname}) - " . $this->getDate();
        return elbp_html($output);
        
    }
    
    /**
     * Get any errors
     * @return type
     */
    public function getErrors(){
        return $this->errors;
    }
    
    /**
     * Load all of the review questions which have been set on this report
     * @return type
     */
    public function loadAllReviews(){
        
        $array = array();
        
        $reviews = $this->getAllReviews();
        
        if ($reviews)
        {
            foreach($reviews as $review)
            {
                $array[$review->questionid] = $review->valueid;
            }
        }
        
        $this->reviewQuestions = $array;
        return $this->reviewQuestions;
        
    }
    
    /**
     * Return the review questions and values
     * @return type
     */
    public function getReviewQuestionsValues(){
        return $this->reviewQuestions;
    }
    
    /**
     * Get all the review questions that have been set on this report, out of the db
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return type
     */
    private function getAllReviews(){
        
        global $DB;
        
        $records = $DB->get_records_sql("SELECT crr.id, q.question, q.id as questionid, v.value, v.id as valueid
                                        FROM {lbp_course_report_reviews} crr
                                        INNER JOIN {lbp_course_reports} r ON r.id = crr.reportid
                                        INNER JOIN {lbp_review_questions} q ON q.id = crr.questionid
                                        INNER JOIN {lbp_review_question_values} v ON v.id = crr.valueid
                                        WHERE r.id = ?
                                        ORDER BY q.id", array($this->id));
        
        return $records;
        
    }
    
    /**
     * Get just the report review records for this report
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return type
     */
    public function getAllReviewValues(){
        
        global $DB;
        
        $check = $DB->get_records("lbp_course_report_reviews", array("reportid" => $this->id));
        return $check;
        
    }
    
    /**
     * Get the valueID entered for a given question if there was one
     * @param int $question
     */
    public function getReviewValueID($question){
        
        global $DB;
        
        $check = $DB->get_record("lbp_course_report_reviews", array("reportid" => $this->id, "questionid" => $question));
        return ($check) ? $check->valueid : false;
        
    }
    
    
    /**
     * Given an array of data, build up the object based on that instead of a DB record
     * This is used for creating a new report or editing an existing one
     * @param type $data
     */
    public function loadData($data){
                
        $this->id = (isset($data['report_id'])) ? $data['report_id'] : -1;
        
        if (isset($data['student_id']))     $this->studentID = $data['student_id'];
        if (isset($data['report_course']))  $this->courseID = $data['report_course'];
        if (isset($data['report_date']))    $this->reportDate = $data['report_date'];
        if (isset($data['comments']))       $this->comments = $data['comments'];
        if (isset($data['set_time']))       $this->setTime = $data['set_time'];
        if (isset($data['set_by']))         $this->setByUserID = $data['set_by'];
        if (isset($data['del']))            $this->del = $data['del'];
        if (isset($data['studentID']))      $this->studentID = $data['studentID']; # Why this twice?
        
        unset($data['report_id']);
        unset($data['student_id']);
        unset($data['report_course']);
        unset($data['report_date']);
        unset($data['comments']);
        unset($data['set_time']);
        unset($data['set_by']);
        unset($data['del']);
        unset($data['studentID']);
        unset($data['courseID']);
        
        // Review questions
        if ($this->CourseReports->reviewQuestionsEnabled() && isset($data['review_questions'])){
            
            $this->reviewQuestions = array();
            
            foreach($data['review_questions'] as $questionID => $valueID)
            {
                                
                if (!ctype_digit($valueID) || !is_int($questionID)) continue;
                
                $this->reviewQuestions[$questionID] = $valueID;
                
            }
            
            unset($data['review_questions']);
            
        }
        
        $this->setSubmittedAttributes($data, $this->CourseReports);
                             
        
    }
    
    /**
     * Delete the course report
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return boolean
     */
    public function delete(){
        
        global $DB;
        
        $obj = new \stdClass();
        $obj->id = $this->id;
        $obj->del = 1;
        
        if($DB->update_record("lbp_course_reports", $obj)){
            
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COURSE_REPORT, LOG_ACTION_ELBP_COURSE_REPORT_DELETED_REPORT, $this->studentID, array(
                "reportID" => $this->id
            ));
            
            return true;
        }
        
        $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp');
        return false;
        
    }
    
    /**
     * Save the course report (insert/update)
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @global type $USER
     * @return boolean
     */
    public function save(){
        
        global $DB, $USER;
        
        if (!$this->id) return false;
                
        if (!isset($this->reportDate)|| !$this->reportDate) $this->errors[] = get_string('coursereports:pleaseenterdate', 'block_elbp');
        if (!isset($this->comments)|| empty($this->comments)) $this->errors[] = get_string('coursereports:pleaseentercomments', 'block_elbp');
        if (!isset($this->studentID)|| !$this->studentID) $this->errors[] = get_string('studentidnotfound', 'block_elbp');
        if (!isset($this->courseID)|| !$this->courseID) $this->errors[] = get_string('courseidnotfound', 'block_elbp');

        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->CourseReports->getElementsFromAttributeString();
                
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
        
        // Tmp object for comparing old & new values
        $tmp = new CourseReport($this->id);
                
        
        // Insert new course report
        if ($this->id == -1){
            
            $obj = new \stdClass();
            $obj->studentid = $this->studentID;
            $obj->courseid = $this->courseID;
            $obj->reportdate = strtotime($this->reportDate . " 00:00:00");
            $obj->comments = $this->comments;
            $obj->settime = time();
            $obj->setbyuserid = $USER->id;
            $obj->del = 0;
            
            if (!$id = $DB->insert_record("lbp_course_reports", $obj)){
                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            $this->reportDate = $obj->reportdate;
            
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COURSE_REPORT, LOG_ACTION_ELBP_COURSE_REPORT_ADDED_REPORT, $this->studentID, array(
                "reportID" => $id,
                "date" => strtotime($this->reportDate . " 00:00:00"),
                "comments" => $this->comments,
                "reviewQuestions" => http_build_query($this->reviewQuestions)
            ));
            
            $this->id = $id;
            
            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->CourseReports)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            // Review Questions
            if ($this->reviewQuestions)
            {
                foreach($this->reviewQuestions as $question => $value)
                {
                    
                    $obj = new \stdClass();
                    $obj->reportid = $id;
                    $obj->questionid = $question;
                    $obj->valueid = $value;
                    if (!$DB->insert_record("lbp_course_report_reviews", $obj)){
                        $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                        return false;
                    }
                    
                }
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
                            
                            $ins = new \stdClass();
                            $ins->reportid = $id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_course_report_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                            
                        }
                        
                    }
                    else
                    {
                        
                        $ins = new \stdClass();
                        $ins->reportid = $id;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_course_report_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                        
                    }
                    
                }
                
            }
            
            // Trigger alerts
            $alertContent = get_string('alerts:coursereportadded', 'block_elbp') . 
                            $this->getInfoForEventTrigger(false);
            
            // Student alert
            elbp_event_trigger_student("Course Report Added", $this->CourseReports->getID(), $this->studentID, $alertContent, nl2br($alertContent));
            
            // Staff alerts
            elbp_event_trigger("Course Report Added", $this->CourseReports->getID(), $this->studentID, $alertContent, nl2br($alertContent));
            
            
            
        }
        else
        {
            
            $obj = new \stdClass();
            $obj->id = $this->id;
            $obj->reportdate = strtotime($this->reportDate . " 00:00:00");
            $obj->comments = $this->comments;
            $obj->del = 0;
            
            
            if (!$id = $DB->update_record("lbp_course_reports", $obj)){
                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            $this->reportDate = $obj->reportdate;
            
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COURSE_REPORT, LOG_ACTION_ELBP_COURSE_REPORT_UPDATED_REPORT, $this->studentID, array(
                "reportID" => $id,
                "date" => strtotime($this->reportDate . " 00:00:00"),
                "comments" => $this->comments,
                "reviewQuestions" => http_build_query($this->reviewQuestions)
            ));
            
            // Move any tmp files
            if (!$this->moveTmpUploadedFiles($allAttributes, $this->CourseReports)){
                $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                return false;
            }
            
            // Review questions
            if ($this->reviewQuestions)
            {
                                
                foreach($this->reviewQuestions as $question => $value)
                {
                    
                    // If exists update
                    $check = $DB->get_record("lbp_course_report_reviews", array("reportid" => $this->id, "questionid" => $question));
                    if ($check)
                    {
                        
                        $obj = new \stdClass();
                        $obj->id = $check->id;
                        $obj->valueid = $value;
                        if (!$DB->update_record("lbp_course_report_reviews", $obj)){
                            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                        
                    }
                    
                    // Else insert
                    else
                    {
                        $obj = new \stdClass();
                        $obj->reportid = $this->id;
                        $obj->questionid = $question;
                        $obj->valueid = $value;
                        if (!$DB->insert_record("lbp_course_report_reviews", $obj)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                    }
                    
                }
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
                        $DB->delete_records("lbp_course_report_attributes", array("reportid" => $this->id, "field" => $field));
                        
                        foreach($value as $val)
                        {
                         
                            $ins = new \stdClass();
                            $ins->reportid = $this->id;
                            $ins->field = $field;
                            $ins->value = $val;
                            if (!$DB->insert_record("lbp_course_report_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                                                        
                        }
                                                
                    }
                    else
                    {
                        
                        // Get att from DB
                        $attribute = $DB->get_record_select("lbp_course_report_attributes", "reportid = ? AND field = ?", array($this->id, $field));
                                                    
                        // If empty, set to NULL in DB
                        if ($value == "") $value = null;
                        
                        // if it exists, update it
                        if ($attribute)
                        {
                            $ins = new \stdClass();
                            $ins->id = $attribute->id;
                            $ins->value = $value;
                            if (!$DB->update_record("lbp_course_report_attributes", $ins)){
                                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                                return false;
                            }
                        }
                        
                        // Else, insert it
                        else
                        {
                            $ins = new \stdClass();
                            $ins->reportid = $this->id;
                            $ins->field = $field;
                            $ins->value = $value;
                            if (!$DB->insert_record("lbp_course_report_attributes", $ins)){
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
                            $DB->delete_records("lbp_course_report_attributes", array("reportid" => $this->id, "field" => $allAttribute->name));
                        }

                    }
                }
                
                
            }
            
            // Trigger alerts
            $alertContent = get_string('alerts:coursereportupdated', 'block_elbp') . 
                            $this->getInfoForEventTrigger(false);
            $htmlContent = get_string('alerts:coursereportupdated', 'block_elbp') . 
                           $this->getInfoForEventTrigger(true, $tmp);
            
            // Student alert
            elbp_event_trigger_student("Course Report Updated", $this->CourseReports->getID(), $this->studentID, $alertContent, $htmlContent);
            
            // Staff Alerts
            elbp_event_trigger("Course Report Updated", $this->CourseReports->getID(), $this->studentID, $alertContent, $htmlContent);
            
            
        }
        
        return true;
        
    }
    
    /**
     * Load the course report's attributes into the object
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return type
     */
    public function loadAttributes(){
        
        global $DB;
                
        $check = $DB->get_records("lbp_course_report_attributes", array("reportid" => $this->id));
        
        $this->attributes = parent::_loadAttributes($check);
        return $this->attributes;
        
    }
    
        
    /**
     * Get the content for the main expanded view
     * @global type $ELBP
     * @global type $CFG
     */
    public function display(){
        
        global $ELBP, $CFG;
       
        $attributes = $this->CourseReports->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);
        
        $output = "";

        // Main central elements
        $output .= "<div>"; // Div 1
        $output .= "<div class='elbp_course_report_main_elements'>"; // Div 2
        
        // Review Questions
        $output .= "<h2>".get_string('reviewquestions', 'block_elbp')."</h2>";
        $output .= "<div class='elbp_course_report_attribute_content'>"; // Div 3
            
            $reviews = $this->getAllReviews();
            if ($reviews)
            {
                $output .= "<table class='elbp_review_question_table'>";
                    
                    foreach($reviews as $review)
                    {
                        $output .= "<tr><td>{$review->question}</td><td>{$review->value}</td></tr>";
                    }
                
                $output .= "</table>";
            }
            else
            {
                $output .= get_string('noresults', 'block_elbp');
            }
        
        $output .= "</div>"; // End div 3
        $output .= "<br>";
        
        // Comments
        $output .= "<h2>".get_string('comments', 'block_elbp')."</h2>";
        $output .= "<div class='elbp_course_report_attribute_content'>"; // Div 4
            $output .= elbp_html( $this->getComments(), true );
        $output .= "</div>"; // End div 4
        $output .= "<br>";
        
            $mainAttributes = $this->CourseReports->getAttributesForDisplayDisplayType("main", $attributes);
            if ($mainAttributes)
            {
                foreach($mainAttributes as $attribute)
                {
                    $output .= "<h2>{$attribute->name}</h2>";
                    $output .= "<div class='elbp_course_report_attribute_content'>"; // Div 5
                        $output .= $attribute->displayValue();
                    $output .= "</div>"; // End Div 5
                    $output .= "<br>";
                }
            }
            
        $output .= "</div>"; // End Div 2
        
        // Summary
        $output .= "<div class='elbp_course_report_summary_elements'>"; // Div 6
        $output .= "<h2>".get_string('reviewdetails', 'block_elbp')."</h2>";
        
        // Attendance Hook - Averages
        if ($this->CourseReports->hasHookEnabled("Attendance/Averages"))
        {
            
            $plg = $ELBP->getPlugin("Attendance");
            if ($plg)
            {
                $data = $plg->_retrieveHook_Averages();

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
                            $field = get_string('average', 'block_elbp') . " " . $type . " " . $period;
                            $output .= "<td>".$this->getAttribute($field)."</td>";
                        }

                        $output .= "</tr>";

                    }

                $output .= "</table>";
                $output .= "<br><br>";
            }
                        
        }     
        
        
        // Attendance Hook - Course
        if ($this->CourseReports->hasHookEnabled("Attendance/Course"))
        {
                        
            $plg = $ELBP->getPlugin("Attendance");
            if ($plg)
            {
                $data = $plg->_retrieveHook_Course();

                $output .= "<table class='attendance_periods_table_course_reports'>";
                $output .= "<tr>";
                    foreach($data['types'] as $type)
                    {
                        $output .= "<th>{$type}</th>";
                    }
                $output .= "</tr>";
                $output .= "<tr>";

                foreach($data['types'] as $type)
                {
                    $field = get_string('average', 'block_elbp') . ' ' . $type;
                    $output .= "<td>{$this->getAttribute($field)}</td>";
                }

                $output .= "</tr>";
                $output .= "</table>";
                $output .= "<br><br>";
            }
                        
        }
        
        // Target grade hook
        if ($this->CourseReports->hasHookEnabled("elbp_bcgt/Target Grade"))
        {
                        
            $output .= "<p class='elbp_centre'><b>".get_string('targetgrades', 'block_bcgt')."</b></p>";
            
            if (isset($this->attributes['Target Grades']))
            {
                
                $output .= "<table class='target_grades_table_course_reports'>";
                
                
                foreach((array)$this->attributes['Target Grades'] as $info)
                {
                    
                    $info = explode("|", $info);
                    $qual = $info[0];
                    $grade = $info[1];
                    $output .= "<tr><td>{$qual}</td><td>{$grade}</td></tr>";
                    
                }
                
                $output .= "</table>";
                
            }
            else
            {
                $output .= "<p class='elbp_centre'>".get_string('na', 'block_elbp')."</p>";
            }
            
            $output .= "<br><br>";
            
        }
        
        // A lvl hook recent grade
        if ($this->CourseReports->hasHookEnabled("elbp_bcgt/A Level Most Recent Grade"))
        {
                        
            $output .= "<p class='elbp_centre'><b>".get_string('mostrecentgrade', 'block_bcgt')."</b></p>";
            
            if (isset($this->attributes['A Level Most Recent Grade']))
            {
                
                $output .= "<table class='target_grades_table_course_reports'>";
                
                
                foreach((array)$this->attributes['A Level Most Recent Grade'] as $info)
                {
                    
                    $info = explode("|", $info);
                    $qual = $info[0];
                    $grade = $info[1];
                    $output .= "<tr><td>{$qual}</td><td>{$grade}</td></tr>";
                    
                }
                
                $output .= "</table>";
                
            }
            else
            {
                $output .= "<p class='elbp_centre'>".get_string('na', 'block_elbp')."</p>";
            }
            
            $output .= "<br><br>";
            
        }
        
        // A lvl hook recent ceta
        if ($this->CourseReports->hasHookEnabled("elbp_bcgt/A Level Most Recent CETA"))
        {
                        
            $output .= "<p class='elbp_centre'><b>".get_string('mostrecentceta', 'block_bcgt')."</b></p>";
            
            if (isset($this->attributes['A Level Most Recent CETA']))
            {
                
                $output .= "<table class='target_grades_table_course_reports'>";
                
                
                foreach((array)$this->attributes['A Level Most Recent CETA'] as $info)
                {
                    
                    $info = explode("|", $info);
                    $qual = $info[0];
                    $grade = $info[1];
                    $output .= "<tr><td>{$qual}</td><td>{$grade}</td></tr>";
                    
                }
                
                $output .= "</table>";
                
            }
            else
            {
                $output .= "<p class='elbp_centre'>".get_string('na', 'block_elbp')."</p>";
            }
            
            $output .= "<br><br>";
            
        }
        
        // Units hook - nay units on quals linked to this course (which have been ticked on creation of report)
        if ($this->CourseReports->hasHookEnabled("elbp_bcgt/Units"))
        {
                        
            $output .= "<p class='elbp_centre'><b>".get_string('units', 'block_bcgt')."</b></p>";
            
            if (isset($this->attributes['Units']))
            {
                
                require_once $CFG->dirroot . '/blocks/bcgt/classes/core/Qualification.class.php';
                require_once $CFG->dirroot . '/blocks/bcgt/classes/core/Unit.class.php';
                $loadParams = new \stdClass();
                $loadParams->loadLevel = \Qualification::LOADLEVELMIN;
                
                foreach((array)$this->attributes['Units'] as $unitID)
                {
                    
                    $unit = \Unit::get_unit_class_id($unitID, $loadParams);
                    if ($unit)
                    {
                        $output .= "<div class='elbp_centre'>".$unit->get_name() . "</div>";
                    }
                    
                }
                
            }
            else
            {
                $output .= "<p class='elbp_centre'>".get_string('na', 'block_elbp')."</p>";
            }
            
            $output .= "<br><br>";
            
        }
        
        
        
        // The rest of the attributes
                
        // Side attributes
        $sideAttributes = $this->CourseReports->getAttributesForDisplayDisplayType("side", $attributes);
                
        if ($sideAttributes)
        {
            $output .= "<h2>".get_string('otherattributes', 'block_elbp')."</h2>";
            $output .= "<table class='elbp_course_report_attribute_table'>";
            foreach($sideAttributes as $attribute)
            {
                 $output .= "<tr><td>{$attribute->name}:</td><td>".$attribute->displayValue()."</td></tr>";
            }
            $output .= "</table>";
        }
        
        
        
                
          
        $output .= "</div>"; // End Div 6
        $output .= "<br class='elbp_cl'>";
        $output .= "</div>"; // End Div 1
        
        echo $output;
        
    }
    
    /**
     * Get the content for the event triggered emails
     * @global \ELBP\Plugins\CourseReports\type $CFG
     * @global \ELBP\Plugins\CourseReports\type $USER
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @param type $useHtml
     * @param type $tmp
     * @return string
     */
    private function getInfoForEventTrigger($useHtml = false, $tmp = false)
    {
        global $CFG, $USER, $DB;
            
        $output = "";
        
        // If using HTML
        if ($useHtml && $tmp)
        {
            $output .= "<br>----------<br>";
            $output .= get_string('student', 'block_elbp') . ": " . fullname($this->getStudent()) . " ({$this->getStudent()->username})<br>";
            if ($this->courseID > 1){
                $output .= get_string('course') . ": " . $this->getCourse()->fullname . "<br>";
            }
            
            // Report date
            $output .= "<del style='color:red;'>".get_string('reportdate', 'block_elbp') . ": " . $tmp->getDate() . "</del><br>";
            $output .= "<ins style='color:blue;'>".get_string('reportdate', 'block_elbp') . ": " . $this->getDate() . "</ins><br>";
            
            
            // Comments
            $output .= "<del style='color:red;'>".get_string('comments', 'block_elbp') . ": " . $tmp->getComments() . "</del><br>";
            $output .= "<ins style='color:blue;'>".get_string('comments', 'block_elbp') . ": " . $this->getComments() . "</ins><br>";
                        

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
            
            // Review questions
            $questions = $this->CourseReports->getReviewQuestions();
            $values = $this->CourseReports->getReviewValues();
            $tmpReviews = $tmp->getReviewQuestionsValues();
                                                
            if ($questions)
            {
                foreach($questions as $question)
                {
                    
                    // Old
                    if (isset($tmpReviews[$question->id]))
                    {
                        $output .= "<del style='color:red;'>{$question->question}: " . $values[$tmpReviews[$question->id]]->value . "</del><br>";
                    }
                    
                    // New
                    if (isset($this->reviewQuestions[$question->id]))
                    {
                        $output .= "<ins style='color:blue;'>{$question->question}: " . $values[$this->reviewQuestions[$question->id]]->value . "</ins><br>";
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
            $output .= get_string('reportdate', 'block_elbp') . ": " . $this->getDate() . "\n";
            $output .= get_string('comments', 'block_elbp') . ": " . $this->getComments() . "\n";

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
            
            // Review questions
            $questions = $this->CourseReports->getReviewQuestions();
            $values = $this->CourseReports->getReviewValues();
                        
            if ($questions)
            {
                foreach($this->reviewQuestions as $questionID => $valueID)
                {
                    if (isset($questions[$questionID]))
                    {
                        $output .= $questions[$questionID]->question . ": " . $values[$valueID]->value . "\n";
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
     * Generate HTML output to be printed
     */
    public function printOut()
    {
        
        global $CFG, $ELBP;
                
        ob_clean();
        
        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('coursereport', 'block_elbp');
        $logo = \ELBP\ELBP::getPrintLogo();
        $title = get_string('coursereport', 'block_elbp');
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';
        $attributes = $this->CourseReports->getAttributesForDisplay();
        $this->loadObjectIntoAttributes($attributes);
        
        $txt = "";
        $txt .= "<table class='info'>";
            $txt .= "<tr><td colspan='3'>".$this->getDate('D jS M Y')."</td></tr>";
            $txt .= "<tr><td>".get_string('setby', 'block_elbp').": ".$this->getStaffName()."</td><td>".get_string('dateset', 'block_elbp').": ".$this->getSetTime('D jS M Y')."</td></tr>";
        
            // Side attributes
            $sideAttributes = $this->CourseReports->getAttributesForDisplayDisplayType("side", $attributes);
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
                
        // Attendance Hook - Averages
        if ($this->CourseReports->hasHookEnabled("Attendance/Averages"))
        {
            
            $data = $ELBP->getPlugin("Attendance")->_retrieveHook_Averages();
            
            $txt .= "<br>";
            $txt .= "<table style='margin:auto;text-align:center;'>";
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
                        $val = $this->getAttribute($field);
                        $txt .= "<td>". ( ($val != "") ? $val : '-' ) ."</td>";
                    }
                    
                    $txt .= "</tr>";
                    
                }
            
            $txt .= "</table>";
            $txt .= "<br><br>";
                        
        }     
        
        
        // Attendance Hook - Course
        if ($this->CourseReports->hasHookEnabled("Attendance/Course"))
        {
                        
            $data = $ELBP->getPlugin("Attendance")->_retrieveHook_Course();
            $txt .= "<br>";
            $txt .= "<table style='margin:auto;text-align:center;'>";
            $txt .= "<tr>";
                foreach($data['types'] as $type)
                {
                    $txt .= "<th>{$type}</th>";
                }
            $txt .= "</tr>";
            $txt .= "<tr>";
            
            foreach($data['types'] as $type)
            {
                $field = get_string('average', 'block_elbp') . ' ' . $type;
                $txt .= "<td>{$this->getAttribute($field)}</td>";
            }
            
            $txt .= "</tr>";
            $txt .= "</table>";
            $txt .= "<br><br>";
                        
        }
        
        
        if ($this->CourseReports->hasHookEnabled("elbp_bcgt/Target Grade"))
        {
                        
            $txt .= "<div style='text-align:center;'>";
            $txt .= "<b>".get_string('targetgrades', 'block_bcgt')."</b>";
            
            if (isset($this->attributes['Target Grades']))
            {
                
                $txt .= "<table style='margin:auto;'>";
                
                foreach((array)$this->attributes['Target Grades'] as $info)
                {
                    
                    $info = explode("|", $info);
                    $qual = $info[0];
                    $grade = $info[1];
                    $txt .= "<tr><td>{$qual}</td><td>{$grade}</td></tr>";
                    
                }
                
                $txt .= "</table>";
                
            }
            else
            {
                $txt .= "<p class='elbp_centre'>".get_string('na', 'block_elbp')."</p>";
            }
            
            $txt .= "</div>";
            $txt .= "<br><br>";
            
        }
        
        
        
        if ($this->CourseReports->hasHookEnabled("elbp_bcgt/Units"))
        {
            
            $txt .= "<div style='text-align:center;'>";
            $txt .= "<b>".get_string('units', 'block_bcgt')."</b>";
            
            if (isset($this->attributes['Units']))
            {
                
                require_once $CFG->dirroot . '/blocks/bcgt/classes/core/Qualification.class.php';
                require_once $CFG->dirroot . '/blocks/bcgt/classes/core/Unit.class.php';
                $loadParams = new \stdClass();
                $loadParams->loadLevel = \Qualification::LOADLEVELMIN;
                
                foreach((array)$this->attributes['Units'] as $unitID)
                {
                    
                    $unit = \Unit::get_unit_class_id($unitID, $loadParams);
                    if ($unit)
                    {
                        $txt .= $unit->get_name() . "<br>";
                    }
                    
                }
                
            }
            else
            {
                $txt .= "<p class='elbp_centre'>".get_string('na', 'block_elbp')."</p>";
            }
            
            $txt .= "</div>";
            $txt .= "<br><br>";
            
        }
                        
        $txt .= "&nbsp;<br><hr>&nbsp;<br>";
        
        
        
        
        
        
        
        // Review questions
        $txt .= "<div class='attribute-main'>";
        $txt .= "<p class='b'>".get_string('reviewquestions', 'block_elbp')."</p>";
        $reviews = $this->getAllReviews();
        if ($reviews)
        {
            foreach($reviews as $review)
            {
                $txt .= "<span>{$review->question}: <u>{$review->value}</u><br></span>";
            }
        }
        else
        {
            $txt .= "<p>".get_string('noresults', 'block_elbp')."</p>";
        }
        $txt .= "</div>";
        
        // Comments
        $txt .= "<div class='attribute-main'><p class='b'>".get_string('comments', 'block_elbp')."</p><p>".elbp_html( $this->getComments(), true ) . "</p></div>";

        
        
        
        // Main central elements
        $mainAttributes = $this->CourseReports->getAttributesForDisplayDisplayType("main", $attributes);

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
     * Get either the blank required data if we're creating a new report, or the current data if editing
     * @global \ELBP\Plugins\CourseReports\type $ELBP
     * @param type $id
     * @return boolean
     */
    public static function getDataForNewReportForm($id = false)
    {
        
        global $ELBP;
        
        $courseReports = $ELBP->getPlugin("CourseReports");
        $attributes = $courseReports->getAttributesForDisplay();
                
        $data = array();
        
        // Build data to put into form (will be blank if new form instead of editing existing one)
        if ($id)
        {
            
            $report = new CourseReport($id);
            if (!$report->isValid()) return false;
            
            $data['id'] = $report->getID();
            $data['report_date'] = $report->getDate('d-m-Y');
            $data['comments'] = $report->getComments();
            $data['courseID'] = $report->getCourseID();
            $data['reviews'] = array();
            
            if ($courseReports->reviewQuestionsEnabled()){
                
                // Get review question values for this report
                $reviews = $report->getAllReviewValues();
                                
                if ($reviews)
                {
                    foreach($reviews as $review)
                    {
                        $data['reviews'][$review->questionid] = $review->valueid;
                    }
                }
                
            }
            
            // If it's a real Course Report from the DB, let's get the actual attributes we have for it
            $data['atts'] = array();
            $data['hookAtts'] = array();
            
            // Since it's a real Session, get all the actual attributes stored for it, not just the ones we think it should have from the config
            $definedAttributes = $report->getAttributes();
                        
            $processedAttributes = array();
                        
            // Loop through all possible attributes defined in the system
            $data['atts'] = array();
                        
            // Loop through default attributes
            if ($attributes)
            {
                foreach($attributes as $attribute)
                {
                    
                    $attribute->loadObject($courseReports);
                    
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
            $data['report_date'] = date('d-m-Y');
            $data['comments'] = '';
            $data['reviews'] = array();
            $data['atts'] = array();
            
            if ($attributes){
                foreach($attributes as $attribute){
                    $attribute->loadObject($courseReports);
                }
            }
            
            $data['atts'] = $attributes;
            
        }
                        
        
        return $data;
        
    }
    
    
    
}