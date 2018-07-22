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
class PeriodicalCourseReport {
    
    private $id = false;
    private $name;
    private $comments;
    private $status;
    private $studentID;
    private $student;
    private $createdByUserID;
    private $createdTime;
    private $del;
    private $CourseReports;
    
    private $errors = array();
    private $reports = array();
    
    /**
     * Construct periodical report object
     * @global type $DB
     * @param type $id
     */
    public function __construct($id = false) {
        
        global $DB;
        
        if ($id)
        {
            
            $record = $DB->get_record("lbp_termly_creports", array("id" => $id));
            if ($record)
            {
                
                $this->id = $record->id;
                $this->name = $record->name;
                $this->comments = $record->comments;
                $this->status = $record->status;
                $this->studentID = $record->studentid;
                $this->student = $this->getStudent();
                $this->createdByUserID = $record->createdbyuserid;
                $this->createdTime = $record->createdtime;
                $this->del = $record->del;
                
                $this->loadReports();
                
            }
            
        }
        
    }
    
    /**
     * Is the report valid?
     * @return type
     */
    public function isValid(){
        return ($this->id) ? true : false;
    }
    
    /**
     * Is the report deleted?
     * @return type
     */
    public function isDeleted(){
        return ($this->del == 1) ? true : false;
    }
    
    /**
     * Is the report published?
     * @return type
     */
    public function isPublished(){
        return ($this->status == 'Published');
    }
    
    /**
     * Get the id of the periodical report
     * @return type
     */
    public function getID(){
        return $this->id;
    }
    
    /**
     * Get the name of the periodical report
     * @return type
     */
    public function getName(){
        return $this->name;
    }
    
    /**
     * Get any comments on the periodical report
     * @return type
     */
    public function getComments(){
        return $this->comments;
    }
    
    /**
     * Get the status of the periodical report, e.g. Published or Draft
     * @return type
     */
    public function getStatus(){
        return $this->status;
    }
    
    /**
     * Get the student id of the report
     * @return type
     */
    public function getStudentID(){
        return $this->studentID;
    }
    
    /**
     * Get the student record of the report
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
     * Get the id of the user who created the report
     * @return type
     */
    public function getCreatedByUserID(){
        return $this->createdByUserID;
    }
    
    /**
     * Get the unix timestamp of when the report was created
     * @return type
     */
    public function getCreatedTime(){
        return $this->createdTime;
    }
    
    /**
     * Get the user record of the person who created the report
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return type
     */
    public function getCreatedByUser(){
        global $DB;
        return $DB->get_record("user", array("id" => $this->createdByUserID));
    }
    
    /**
     * Get the date the report was created
     * @param type $format date($format)
     * @return type
     */
    public function getCreatedDate($format = 'M jS Y'){
        return date($format, $this->createdTime);
    }
    
    /**
     * Return the reports on this periodical report
     * @return type
     */
    public function getReports(){
        return $this->reports;
    }
        
    /**
     * Get only the reports on this periodical report which are linked to a certain course
     * @param int $courseID
     * @return array
     */
    public function getReportsByCourse($courseID){
        
        $return = array();
        
        if ($this->reports)
        {
            
            foreach($this->reports as $report)
            {
                
                if ($report->getCourseID() == $courseID)
                {
                    $return[] = $report;
                }
                
            }
            
        }
        
        return $return;
        
    }
    
    /**
     * Get any errors
     * @return type
     */
    public function getErrors(){
        return $this->errors;
    }
    
    /**
     * Get the attributes on this periodical report
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return type
     */
    public function getAttributes(){
        
        global $DB;
        
        $records = $DB->get_records("lbp_termly_creport_atts", array("termlyreportid" => $this->id));
        $attributes = array();
        
        if ($records)
        {
            
            foreach($records as $record)
            {
                
                if (isset($attributes[$record->attribute]) && !is_array($attributes[$record->attribute])){
                    $tmp = $attributes[$record->attribute];
                    $attributes[$record->attribute] = array();
                    $attributes[$record->attribute][] = $tmp;
                    $attributes[$record->attribute][] = $record->value;
                } elseif (isset($attributes[$record->attribute])){
                    $attributes[$record->attribute][] = $record->value;
                } else {
                    $attributes[$record->attribute] = $record->value;
                }
                
            }
            
        }
        
        return $attributes;
        
    }
    
    /**
     * Get a specific attribute's value
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @param type $attribute
     * @return type
     */
    public function getAttribute($attribute){
        global $DB;
        $records = $DB->get_records("lbp_termly_creport_atts", array("termlyreportid" => $this->id, "attribute" => $attribute));
        $array = array();
        if ($records)
        {
            foreach($records as $record)
            {
                $array[] = $record->value;
            }
        }
        
        if (count($array) == 1){
            return $array[0];
        } else {
            return $array;
        }
        
    }
    
    /**
     * Get a list of all the courses linked to reports on this periodical report
     * @return type
     */
    public function getCourses(){
        
        $courses = array();
        
        if ($this->reports)
        {
            
            foreach($this->reports as $report)
            {
                
                $courseID = $report->getCourseID();
                
                if (!array_key_exists($courseID, $courses))
                {
                    $courses[$courseID] = $report->getCourse();
                }
                
            }
            
        }
        
        return $courses;
        
    }
    
    /**
     * Set the id of the student on this report
     * @param type $id
     */
    public function setStudentID($id){
        $this->studentID = $id;
    }
    
    /**
     * Set the name of this report
     * @param type $name
     */
    public function setName($name){
        $this->name = $name;
    }
    
    /**
     * Set any comments on this report
     * @param type $comments
     */
    public function setComments($comments){
        $this->comments = $comments;
    }
    
    /**
     * Set the status of this report
     * @param type $status
     */
    public function setStatus($status){
        $this->status = $status;
    }
    
    /**
     * Add a course report onto this periodical report
     * @param \ELBP\Plugins\CourseReports\CourseReport $report
     */
    public function setReport( \ELBP\Plugins\CourseReports\CourseReport $report){
        $this->reports[$report->getID()] = $report;
    }
    
    /**
     * Set an array of course reports onto this periodical report
     * @param array $reports
     */
    public function setReports( array $reports ){
        foreach($reports as $report)
        {
            $this->setReport($report);
        }
    }
    
    /**
     * Set the CourseReports plugin onto this periodical report object
     * @param type $obj
     */
    public function setCourseReportsObj($obj){
        $this->CourseReports = $obj;
        // Reload reports with this obj
        $this->loadReports();
    }
    
    /**
     * Load all the course reports linked to this periodical report
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @return \ELBP\Plugins\CourseReports\CourseReport
     */
    public function loadReports(){
        
        global $DB;
        
        $reports = $DB->get_records("lbp_termly_creport_reports", array("termlyreportid" => $this->id));
        $return = array();
        
        if ($reports)
        {
            foreach($reports as $report)
            {
                $reportObj = new \ELBP\Plugins\CourseReports\CourseReport($report->reportid, $this->CourseReports);
                if ($reportObj->isValid())
                {
                    $return[$report->reportid] = $reportObj;
                }
            }
        }
        
        // Order by date asc
        uasort($return, function($a, $b){
            return ($a->getDateUnix() > $b->getDateUnix());
        });
        
        $this->reports = $return;
        return $return;
        
    }
    
    /**
     * Delete this periodical report
     * @return type
     */
    public function delete(){
        
        $this->del = 1;
        return $this->save();
        
    }
    
    /**
     * Save this periodical report (insert/update)
     * @global \ELBP\Plugins\CourseReports\type $DB
     * @global type $USER
     * @global type $ELBP
     * @return boolean
     */
    public function save(){
        
        global $DB, $USER, $ELBP;
        
        if (!isset($this->studentID)) $this->errors[] = get_string('studentidnotfound', 'block_elbp');
        if (!isset($this->name)) $this->errors[] = get_string('coursereports:pleaseentername', 'block_elbp');
        if (!isset($this->status)) $this->errors[] = get_string('coursereports:pleaseenterstatus', 'block_elbp');
        if (!isset($this->comments)) $this->errors[] = get_string('coursereports:pleaseentercomments', 'block_elbp');
        if (!isset($this->reports)) $this->errors[] = get_string('coursereports:pleasechoosereport', 'block_elbp');
            
        if (!empty($this->errors)) return false;
        
        // Insert new one
        if (!$this->id)
        {
            
            $obj = new \stdClass();
            $obj->studentid = $this->studentID;
            $obj->name = $this->name;
            $obj->status = $this->status;
            $obj->comments = $this->comments;
            $obj->createdbyuserid = $USER->id;
            $obj->createdtime = time();
            $obj->del = 0;
            
            if (!$id = $DB->insert_record("lbp_termly_creports", $obj)){
                $this->errors[] = get_string('couldnotinsert', 'block_elbp');
                return false;
            }
            
            $this->id = $id;
            
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COURSE_REPORT, LOG_ACTION_ELBP_COURSE_REPORT_ADDED_TERMLY_REPORT, $this->studentID, array(
                "id" => $id,
                "name" => $this->name,
                "comments" => $this->comments,
                "reports" => http_build_query($this->reports)
            ));
            
            $this->createdByUserID = $USER->id;
            $this->createdTime = time();
            
            // Reports
            if ($this->reports)
            {
                
                foreach($this->reports as $report)
                {
                    
                    $obj = new \stdClass();
                    $obj->termlyreportid = $id;
                    $obj->reportid = $report->getID();
                    if (!$DB->insert_record("lbp_termly_creport_reports", $obj)){
                        $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp');
                        return false;
                    }
                    
                }
                
            }
            
            
            // Attributes
            // I am going to save all the info and we'll just decide whether we want to actually see it or not
            
            // Attendance
            $att = $ELBP->getPlugin("Attendance");
            if ($att)
            {
                
                $attData = $att->_callHook_Averages($this->CourseReports, array());

                if ($att->getTypes())
                {
                    foreach($att->getTypes() as $type)
                    {
                        $setting = "student_summary_display_{$type}";
                        $setting = $att->getSetting($setting);
                        $displayPeriod = $setting;
                        $val = $attData['values'][$type][$displayPeriod];

                        $obj = new \stdClass();
                        $obj->termlyreportid = $this->id;
                        $obj->attribute = $type . ' ' . $displayPeriod;
                        $obj->value = $val;
                        $DB->insert_record("lbp_termly_creport_atts", $obj);

                    }
                }
                
            }
            
            // Gradetracker
            $gt = $ELBP->getPlugin("elbp_bcgt");
            if ($gt)
            {
                
                // Target grades
                $targetGrades = $gt->_callHook_Target_Grade($this->CourseReports, array());
                if ($targetGrades)
                {
                    
                    foreach($targetGrades['grades'] as $qualName => $grade)
                    {
                        
                        $obj = new \stdClass();
                        $obj->termlyreportid = $this->id;
                        $obj->attribute = 'Target Grades';
                        $obj->value = $qualName . ': ' . $grade;
                        $DB->insert_record("lbp_termly_creport_atts", $obj);
                        
                    }
                    
                }
                
                // QUalifications
                $quals = \get_users_quals($this->studentID, 5);
                if ($quals)
                {
                    foreach($quals as $qual)
                    {
                        
                        if (!isset($qual->isbespoke)){
                            $level = str_replace("Level ", "", $qual->trackinglevel);
                            $qualName = $qual->type . ' ' . 'L' . $level . ' ' . $qual->name;
                        } elseif (isset($qual->isbespoke)){
                            $qualName = $qual->displaytype . ' ' . $qual->subtype . ' L' . $qual->level . ' '. $qual->name;
                        } else {
                            $qualName = $qual->name;
                        }
                        
                        $obj = new \stdClass();
                        $obj->termlyreportid = $this->id;
                        $obj->attribute = 'Qualifications';
                        $obj->value = $qualName;
                        $DB->insert_record("lbp_termly_creport_atts", $obj);
                        
                    }
                    
                    
                }
                
            }
            
                        
        }
        else
        {
            
            // Update existing
            $obj = new \stdClass();
            $obj->id = $this->id;
            $obj->name = $this->name;
            $obj->status = $this->status;
            $obj->comments = $this->comments;
            $obj->del = $this->del;
            
            if (!$id = $DB->update_record("lbp_termly_creports", $obj)){
                $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp');
                return false;
            }
            
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_COURSE_REPORT, LOG_ACTION_ELBP_COURSE_REPORT_UPDATED_TERMLY_REPORT, $this->studentID, array(
                "id" => $this->id,
                "name" => $this->name,
                "comments" => $this->comments,
                "deleted" => $this->del,
                "reports" => http_build_query($this->reports)
            ));
            
            
            // Laziness
            $DB->delete_records("lbp_termly_creport_reports", array("termlyreportid" => $this->id));
                        
            // Reports
            if ($this->reports)
            {
                                
                foreach($this->reports as $report)
                {
                                        
                    $obj = new \stdClass();
                    $obj->termlyreportid = $this->id;
                    $obj->reportid = $report->getID();
                    if (!$DB->insert_record("lbp_termly_creport_reports", $obj)){
                        $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp');
                        return false;
                    }
                                        
                }
                
            }
                        
            
        }
        
        return true;
        
    }
    
    /**
     * Print the periodical report out to simple HTML page
     * @global type $CFG
     * @global \ELBP\Plugins\CourseReports\type $ELBP
     */
    public function printOut(){
        
        global $CFG, $ELBP;
        
        ob_clean();
        
        echo $ELBP->loadJavascript(true);
                
        $pageTitle = fullname($this->student) . ' (' . $this->student->username . ') - ' . get_string('coursereport:periodical', 'block_elbp');
        $logo = \ELBP\ELBP::getPrintLogo();
        $title = get_string('coursereport:periodical', 'block_elbp');
        $heading = fullname($this->student) . ' (' . $this->student->username . ')';
        $this->CourseReports->loadStudent( $this->student->id );
        
        $txt = "";
        
        
        $TPL = new \ELBP\Template();
        $TPL->set("report", $this);
        $TPL->set("obj", $this->CourseReports);
        $TPL->set("access", $this->CourseReports->getAccess());
        $TPL->set("attributes", $this->getAttributes());
        
        $att = $ELBP->getPlugin("Attendance");
        if ($att)
        {
            $TPL->set("attTypes", $att->getTypes());
            $attDisplayPeriods = array();
            if ($att->getTypes())
            {
                foreach($att->getTypes() as $type)
                {
                    $setting = "student_summary_display_{$type}";
                    $setting = $att->getSetting($setting);
                    $attDisplayPeriods[$type] = $setting;
                }
            }
            $TPL->set("attDisplayPeriods", $attDisplayPeriods);
        }
        
        
        $txt = $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/CourseReports/Periodical/tpl/view.html' );
        
        
        
        $TPL = new \ELBP\Template();
        $TPL->set("logo", $logo);
        $TPL->set("pageTitle", $pageTitle);
        $TPL->set("title", $title);
        $TPL->set("heading", $heading);
        $TPL->set("content", $txt);
        $TPL->set("css", $CFG->wwwroot . '/blocks/elbp/plugins/CourseReports/Periodical/print.css');
        
        
        $TPL->load( $CFG->dirroot . '/blocks/elbp/tpl/print.html' );
        $TPL->display();
        
        echo "<script>
                $('#elbp_print').remove();
                $('#return_to_reports').remove();
                $('#elbp_periodical_report_title').html( $('#elbp_periodical_report_title').text() );
              </script>";
                
        
        exit;
        
    }
    
    
}