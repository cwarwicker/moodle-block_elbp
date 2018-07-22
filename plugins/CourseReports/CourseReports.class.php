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

require_once $CFG->dirroot . '/blocks/elbp/plugins/CourseReports/CourseReport.class.php';
require_once $CFG->dirroot . '/blocks/elbp/plugins/CourseReports/Periodical/PeriodicalCourseReport.class.php';

/**
 * 
 */
class CourseReports extends Plugin {
        
    public $supportedHooks;    
    
    protected $tables = array(
        'lbp_course_reports',
        'lbp_course_report_attributes',
        'lbp_course_report_reviews',
        'lbp_review_questions',
        'lbp_review_question_values',
        'lbp_termly_creport_atts',
        'lbp_termly_creport_reports',
        'lbp_termly_creports'
    );
    
    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {
        
        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Course Reports",
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
            'elbp_bcgt' => array(
                'Target Grade',
                'Units',
                'A Level Most Recent CETA',
                'A Level Most Recent Grade',
                'Weighted Target Grade'
            ),
            'Attendance' => array(
                'Averages',
                'Course'
            )
        );

    }
    
    /**
     * Get the setting to see if we can add reports to any course, only meta courses or only child courses
     */
    private function getCourseType(){
        
        $setting = $this->getSetting("course_types");
        if (!$setting) $setting = 'both';
        return $setting;
        
    }
    
    /**
     * Get the shortname/fullname/idnumber of the course, depending on which field we are displaying
     * @param type $course
     * @return type
     */
    public function getCourseName($course){
        
        $field = $this->getCourseNameField();
        return $course->$field;
        
    }
    
    /**
     * Get the field we want to display on the course record
     * @return type
     */
    private function getCourseNameField(){
        
        $setting = $this->getSetting('course_name');
        return ($setting) ? $setting : 'fullname';
        
    }
    
    /**
     * Get all the student's courses, taking into account meta/child/both setting
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    public function getStudentsCourses(){
        
        if (!$this->student) return false;
        
        global $DB;
        
        $DBC = new \ELBP\DB();
        
        $courses = $DBC->getStudentsCourses($this->student->id);
        if (!$courses) return $courses; # Empty array
        
        $courseType = $this->getCourseType();
        
        if ($courseType == 'both') return $courses;
        
        $return = array();
        
        foreach($courses as $course)
        {

            $checkEnrol = $DB->get_records("enrol", array("enrol" => "meta", "courseid" => $course->id));
            
            // Meta
            if ($courseType == 'meta' && $checkEnrol) $return[] = $course;
            
            // Child
            elseif ($courseType == 'child' && !$checkEnrol) $return[] = $course;

        }
       
        
        return $return;
        
    }
    
    /**
     * Get the student's last course report on a given course
     * @global \ELBP\Plugins\type $DB
     * @param type $courseID
     * @return \stdClass|boolean
     */
    public function getLastCourseReport($courseID){
        
        global $DB;
        
        if (!$this->student) return false;
        
        // Academic Year - If this isn't set, the (int) should convert it from false to 0, so it'll get all reports
        $academicYearUnix = (int)$this->getAcademicYearUnix();
                
        $report = $DB->get_record_sql("SELECT id, reportdate FROM {lbp_course_reports} WHERE studentid = ? AND courseid = ? AND del = 0 AND settime > ? ORDER BY reportdate DESC, id DESC", array($this->student->id, $courseID, $academicYearUnix), IGNORE_MULTIPLE);
        if (!$report){
            $obj = new \stdClass();
            $obj->id = null;
            $obj->reportdate = get_string('na', 'block_elbp');
            return $obj;
        } else {
             $obj = new \stdClass();
             $obj->id = $report->id;
             $obj->reportdate = date('d M Y', $report->reportdate);
             return $obj;
        }
        
        
    }
    
    /**
     * Check if the review questions are enabled
     * @return type
     */
    public function reviewQuestionsEnabled(){
        return ($this->getSetting('enable_review_questions') == 1);
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
        $settings['course_types'] = 'both';
        $settings['enable_review_questions'] = 1;
        $settings['attributes'] = '';
        
        
        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }
        
        // Review Questions
        $DB->insert_record("lbp_review_questions", array("question" => "Attitude/effort made towards study"));
        $DB->insert_record("lbp_review_questions", array("question" => "In-class performance"));
        $DB->insert_record("lbp_review_questions", array("question" => "Meets deadlines"));
        $DB->insert_record("lbp_review_questions", array("question" => "Progress made against academic targets"));
        $DB->insert_record("lbp_review_questions", array("question" => "Standard of work produced"));
        $DB->insert_record("lbp_review_questions", array("question" => "Well Organised"));
        $DB->insert_record("lbp_review_questions", array("question" => "Works well with other students"));
        
        // Review Question Values
        $DB->insert_record("lbp_review_question_values", array("value" => "Excellent", "numericvalue" => 4));
        $DB->insert_record("lbp_review_question_values", array("value" => "Good", "numericvalue" => 3));
        $DB->insert_record("lbp_review_question_values", array("value" => "Satisfactory", "numericvalue" => 2));
        $DB->insert_record("lbp_review_question_values", array("value" => "Cause for concern", "numericvalue" => 1));
        
        
        // Alert events
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Course Report Added", "description" => "A new course report is added into the system", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Course Report Updated", "description" => "A course report is updated", "auto" => 0, "enabled" => 1));

        return $return;
    }
    
    /**
     * Truncate related tables and then uninstall plugin
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
        
        $result = true;
        $version = $this->version; # This is the current DB version we will be using to upgrade from     
        
        // [Upgrades here]
        
    }
    
    /**
     * Check if periodical course reports are enabled
     * @return type
     */
    public function isPeriodicalEnabled(){
        
        $setting = $this->getSetting('periodical_enabled');
        return ($setting == 1);
        
    }
    
    /**
     * Yes
     * @return boolean
     */
    public function isUsingBlockProgress(){
        return true;
    }
    
    /**
     * Save configuration
     * @global \ELBP\Plugins\type $DB
     * @global type $MSGS
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {
        
        global $DB, $MSGS;
                
        // Course settings - meta/child/both and which field to display
        if (isset($settings['submit_courses'])){
            
            $type = (isset($settings['course_types']) && !empty($settings['course_types'])) ? $settings['course_types'] : 'both';
            $name = (isset($settings['course_name']) && !empty($settings['course_name'])) ? $settings['course_name'] : 'fullname';
            
            $this->updateSetting('course_types', $type);
            $this->updateSetting('course_name', $name);
                        
            $MSGS['success'] = '<h1>'.get_string('success', 'block_elbp').'</h1><p>'.get_string('settingsupdated', 'block_elbp').'</p>';
            
            return true;
            
        }
        
        // Hook links
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
        
        // Attributes
        elseif(isset($_POST['submit_attributes'])){
            
            \elbp_save_attribute_script($this);
            return true;
            
        }
        
        // Instructions
        elseif(isset($_POST['submit_course_report_instructions']))
        {
            
            $instructions = $settings['new_course_report_instructions'];
            $this->updateSetting("new_course_report_instructions", $instructions);
            
            $MSGS['success'] = get_string('instructionsupdated', 'block_elbp');
            
            return true;
            
        }
        
        // Review questions and options
        elseif (isset($settings['submit_review']))
        {
            
            // Enable Disable
            $enabled = isset($settings['enable_review_questions']) ? 1 : 0;
            $this->updateSetting("enable_review_questions", $enabled);
            unset($settings['enable_review_questions']);
            
            $questionArray = array();
            
            // Questions
            if (isset($settings['review_questions']))
            {
                
                // Update Existing ones
                if (isset($settings['review_questions']['existing']))
                {
                    foreach($settings['review_questions']['existing'] as $ID => $question)
                    {

                        $question = trim($question);
                        if (empty($question)) continue;

                        $obj = new \stdClass();
                        $obj->id = $ID;
                        $obj->question = $question;
                        $DB->update_record("lbp_review_questions", $obj);
                        
                        $questionArray[] = $ID;

                    }
                }
                
                // Insert new ones
                if (isset($settings['review_questions']['new']))
                {
                    foreach($settings['review_questions']['new'] as $question)
                    {

                        $question = trim($question);
                        if (empty($question)) continue;

                        $obj = new \stdClass();
                        $obj->question = $question;
                        $id = $DB->insert_record("lbp_review_questions", $obj);
                        
                        $questionArray[] = $id;

                    }
                }
                
            }
            
            // Delete any that aren't there any more
            if (empty($questionArray)){
                $DB->execute("UPDATE {lbp_review_questions} SET del = 1");
            } else {
                $implode = implode(",", $questionArray);
                $DB->execute("UPDATE {lbp_review_questions} SET del = 1 WHERE id NOT IN ({$implode})");
            }
            
            
            // Values            
            if (isset($settings['review_values']))
            {
                
                $valueArray = array();
                
                // Existing
                if (isset($settings['review_values']['existing']) && isset($settings['review_value_scores']['existing']))
                {
                    foreach($settings['review_values']['existing'] as $ID => $value)
                    {

                        $value = trim($value);
                        $score = trim($settings['review_value_scores']['existing'][$ID]);
                                                
                        if (empty($value) || empty($score)) continue;
                        
                        $obj = new \stdClass();
                        $obj->id = $ID;
                        $obj->value = $value;
                        $obj->numericvalue = $score;
                        $DB->update_record("lbp_review_question_values", $obj);
                        $valueArray[] = $ID;

                    }
                }
                
                
                // New
                if (isset($settings['review_values']['new']) && isset($settings['review_value_scores']['new']))
                {
                    for($i = 0; $i < count($settings['review_values']['new']); $i++)
                    {

                        $value = trim($settings['review_values']['new'][$i]);
                        $score = trim($settings['review_value_scores']['new'][$i]);
                        if (empty($value) || empty($score)) continue;

                        $obj = new \stdClass();
                        $obj->value = $value;
                        $obj->numericvalue = $score;
                        $id = $DB->insert_record("lbp_review_question_values", $obj);
                        $valueArray[] = $id;

                    }
                }
                
            }
            
            // Delete any that aren't there any more
            if (empty($valueArray)){
                $DB->execute("UPDATE {lbp_review_question_values} SET del = 1");
            } else {
                $implode = implode(",", $valueArray);
                $DB->execute("UPDATE {lbp_review_question_values} SET del = 1 WHERE id NOT IN ({$implode})");
            }
            
            $MSGS['success'] = '<h1>'.get_string('success', 'block_elbp').'</h1><p>'.get_string('settingsupdated', 'block_elbp').'</p>';
            
            return true;
            
        }
        
        
        
        
        // Student progress definitions
                 
        // If any of them aren't defined, set their value to 0 for disabled        
        if (!isset($settings['student_progress_definitions_req'])){
            $settings['student_progress_definitions_req'] = 0;
            $settings['student_progress_definition_values_req'] = 0;
            $settings['student_progress_definition_importance_req'] = 0;
        }
       

        // If the req ones don't have a valid number as their value, set to disabled
        if (!isset($settings['student_progress_definition_values_req']) || $settings['student_progress_definition_values_req'] <= 0) $settings['student_progress_definitions_req'] = 0;
        if (!isset($settings['student_progress_definition_importance_req']) || $settings['student_progress_definition_importance_req'] <= 0) $settings['student_progress_definitions_req'] = 0;

        
        if (!isset($settings['student_progress_definitions_reqperiodical'])){
            $settings['student_progress_definitions_reqperiodical'] = 0;
            $settings['student_progress_definition_values_reqperiodical'] = 0;
            $settings['student_progress_definition_importance_reqperiodical'] = 0;
        }
        
        // If the req ones don't have a valid number as their value, set to disabled
        if (!isset($settings['student_progress_definition_values_reqperiodical']) || $settings['student_progress_definition_values_reqperiodical'] <= 0) $settings['student_progress_definitions_reqperiodical'] = 0;
        if (!isset($settings['student_progress_definition_importance_reqperiodical']) || $settings['student_progress_definition_importance_reqperiodical'] <= 0) $settings['student_progress_definitions_reqperiodical'] = 0;

        
        
        
        parent::saveConfig($settings);
        
    }
    
    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){
        
        $TPL = new \ELBP\Template();
        
        $TPL->set("obj", $this);
        $TPL->set("courses", $this->getStudentsCourses());
        $TPL->set("periodicalReports", $this->getPeriodicalReports(3, true));
                
        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }
        
    }
    
  
    
    
    
    /**
     * Get list of possible review questions added
     * @global type $DB
     */
    public function getReviewQuestions(){
        
        global $DB;
        
        return $DB->get_records("lbp_review_questions", array("del" => 0), "question ASC");
        
    }
    
    /**
     * Get list of review question values
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getReviewValues(){
        
        global $DB;
        
        return $DB->get_records("lbp_review_question_values", array("del" => 0), "numericvalue DESC, value ASC");
        
    }
    
    /**
     * Find student's course reports within a given date range
     * @param type $dates
     */
    private function getCourseReportsByDateRange($dates)
    {
        
        global $DB;
        
        $reports = array();
        $results = $DB->get_records_select("lbp_course_reports", "studentid = ? AND del = ? AND reportdate >= ? AND reportdate <= ?", array($this->student->id, 0, $dates['from'], $dates['to']), "reportdate DESC");
        
        if ($results)
        {
            foreach($results as $result)
            {
                $report = new CourseReports\CourseReport($result->id, $this);
                if ($report->isValid()){
                    $reports[] = $report;
                }
            }
        }
        
        
        return $reports;
        
    }
    
    /**
     * Get a list of course reports for this student
     * @global \ELBP\Plugins\type $DB
     * @param type $courseID
     * @return \ELBP\Plugins\CourseReports\CourseReport
     */
    public function getCourseReports($courseID = false)
    {
        
        global $DB;
        
        $reports = array();
        
        // Academic Year
        $academicYearUnix = $this->getAcademicYearUnix();
        
        if ($courseID){
            $results = $DB->get_records("lbp_course_reports", array("studentid" => $this->student->id, "courseid" => $courseID, "del" => 0), "reportdate DESC, id DESC");
        } else {
            $results = $DB->get_records("lbp_course_reports", array("studentid" => $this->student->id, "del" => 0), "reportdate DESC, id DESC");
        }
                
        if ($results)
        {
            foreach($results as $result)
            {
                
                $report = new CourseReports\CourseReport($result->id, $this);
                if ($report->isValid()){
                    if ($academicYearUnix && $report->getSetUnix() < $academicYearUnix){
                        continue;
                    }
                    $reports[] = $report;
                }
                
            }
        }
        
        return $reports;
        
    }
    
    /**
     * Get the student's periodical course reports
     * @global \ELBP\Plugins\type $DB
     * @param int $limit
     * @param bool $published
     * @return \ELBP\Plugins\CourseReports\PeriodicalCourseReport|boolean
     */
    public function getPeriodicalReports($limit = 0, $published = false)
    {
        
        global $DB;
        
        if (!$this->student) return false;
        
        // Academic Year
        $academicYearUnix = (int)$this->getAcademicYearUnix();
        
        $reports = array();
        $params = array("studentid" => $this->student->id, "del" => 0);
        if ($published){
            $params['status'] = 'Published';
        }
        
        $results = $DB->get_records("lbp_termly_creports", $params, "createdtime DESC", "*", 0, $limit);
        
        if ($results)
        {
            foreach($results as $result)
            {
                $obj = new \ELBP\Plugins\CourseReports\PeriodicalCourseReport($result->id);
                if ($obj->isValid())
                {
                    if ($academicYearUnix && $obj->getCreatedTime() < $academicYearUnix){
                        continue;
                    }
                    $reports[] = $obj;
                }
            }
        }
        
        
        return $reports;
        
    }
    
    /**
     * Display the courses page through ajax
     * @param type $params
     * @param type $access
     */
    private function ajax_courses($params, $access)
    {
                
        $courses = $this->getStudentsCourses();
                                
        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this)
            ->set("access", $access)
            ->set("courses", $courses);
        
        try {
                $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/courses.html' );
                $TPL->display();
            } catch (\ELBP\ELBPException $e){
                echo $e->getException();
            }
                
        exit;
        
        
    }
    
    /**
     * Display the reports on a specific course, through ajax
     * @global \ELBP\Plugins\type $DB
     * @param type $params
     * @param type $access
     */
    private function ajax_course($params, $access)
    {
        
        global $DB;
        
        $course = $DB->get_record("course", array("id" => $params['courseIDForReport']));
        $reports = $this->getCourseReports($course->id);
        
        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this)
            ->set("access", $access)
            ->set("course", $course)
            ->set("reports", $reports);
        
        try {
                $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/course.html' );
                $TPL->display();
            } catch (\ELBP\ELBPException $e){
                echo $e->getException();
            }
                
        exit;
        
    }
    
    /**
     * Display the new/edit form through ajax
     * @global \ELBP\Plugins\type $DB
     * @global \ELBP\Plugins\type $ELBP
     * @param type $params
     * @param type $access
     */
    private function ajax_new_edit($params, $access)
    {
        
        global $DB, $ELBP;
        
        $FORM = new \ELBP\ELBPForm();
        $FORM->loadStudentID($this->student->id);
        
        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this)
            ->set("ELBP", $ELBP)
            ->set("access", $access)
            ->set("attributes", $this->getAttributesForDisplay())
            ->set("FORM", $FORM)
            ->set("courses", $this->getStudentsCourses());
                
        $page = $params['type'];
 
        if (isset($params['courseIDForReport'])){
            $reportCourse = $DB->get_record("course", array("id" => $params['courseIDForReport']));
            $TPL->set("reportCourse", $reportCourse);
            $params['courseID'] = $reportCourse->id; # FOr use in hooks
        }

        $reportID = false;

        // If we're editing then there must be a reportID
        if ($page == 'edit'){
            $reportID = $params['reportID'];
            $page = 'new'; # page is called "new" it's the same one for both editing and new
        }

        if ($page == 'new'){
            $data = \ELBP\Plugins\CourseReports\CourseReport::getDataForNewReportForm($reportID);
            $TPL->set("data",  $data);
            $TPL->set("hooks", $this->callAllHooks($params));
        }

        $TPL->set("page", $page);
        $TPL->set("reviewQuestions", $this->getReviewQuestions());
        $TPL->set("reviewValues", $this->getReviewValues());
        
        try {
            $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/new.html' );
            $TPL->display();
        } catch (\ELBP\ELBPException $e){
            echo $e->getException();
        }
                
        exit;
        
        
    }
    
    /**
     * Display the periodical page through ajax
     * @global type $CFG
     * @global \ELBP\Plugins\type $ELBP
     * @param type $params
     * @param type $access
     * @return boolean
     */
    private function ajax_periodical($params, $access){
        
        global $CFG, $ELBP;
        
        $periodicalReport = new \ELBP\Plugins\CourseReports\PeriodicalCourseReport($params['reportID']);
        if (!$periodicalReport->isValid()) return false;
        if (!$this->loadStudent($params['studentID'])) return false;
        
        $periodicalReport->setCourseReportsObj($this);
        
        $TPL = new \ELBP\Template();
        $TPL->set("report", $periodicalReport);
        $TPL->set("obj", $this);
        $TPL->set("access", $this->getAccess());
        $TPL->set("attributes", $periodicalReport->getAttributes());
        
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
        
        $output = $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/CourseReports/Periodical/tpl/view.html' );
        echo $output;
        exit;
        
    }
    
    /**
     * Handle ajax requests sent to the plugin
     * @global \ELBP\Plugins\type $CFG
     * @global type $OUTPUT
     * @param type $action
     * @param type $params
     * @param \ELBP\Plugins\type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){
        
        global $CFG, $OUTPUT;
        
        switch($action)
        {
            
            case 'load_display_type':
                                
                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                                
                if ($params['type'] == 'courses'){
                    $this->ajax_courses($params, $access);
                    exit;
                }
                
                if ($params['type'] == 'course' && isset($params['courseID']))
                {
                    $this->ajax_course($params, $access); 
                    exit;
                }
                
                if ($params['type'] == 'new' || $params['type'] == 'edit'){
                    $this->ajax_new_edit($params, $access);
                    exit;
                }
                
                if ($params['type'] == 'periodical_report' && isset($params['reportID']))
                {
                    $this->ajax_periodical($params, $access);
                    exit;
                }
                
                // Default - just look for that file
                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("access", $access);
                
                if ($params['type'] == 'periods'){
                    $TPL->set("periodicals", $this->getPeriodicalReports());
                }

                try {
                        $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/'.$params['type'].'.html' );
                        $TPL->display();
                    } catch (\ELBP\ELBPException $e){
                        echo $e->getException();
                    }

                exit;
                
                
                
            break;
            
            case 'save_report':
                
                
                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['report_course'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_course_report', $access)) return false;
                
                // Build CourseReport object using params sent
                $report = new \ELBP\Plugins\CourseReports\CourseReport($params, $this);
                                
                if (!$report->save()){
                                                            
                    echo "$('#new_course_report_output').html('<div class=\"elbp_err_box\" id=\"add_course_reports_errors\"></div>');";
                    
                    foreach($report->getErrors() as $error){
                        
                        echo "$('#add_course_reports_errors').append('<span>{$error}</span><br>');";
                        
                    }
                    
                    exit;
                    
                }
                
                // Success message at top
                echo "$('#new_course_report_output').html('<div class=\"elbp_success_box\" id=\"add_course_reports_success\"></div>');";
                echo "$('#add_course_reports_success').append('<span>".get_string('coursereportupdated', 'block_elbp')."</span><br>');";
                
                if ($params['report_id'] <= 0){
                    echo "$('#new_course_report_form')[0].reset();";
                }
                         
                exit;
                
            break;
            
            case 'delete_report':
                                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['reportID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:delete_course_report', $access)) return false;
                
                // Build CourseReport object using params sent
                $report = new \ELBP\Plugins\CourseReports\CourseReport($params['reportID'], $this);
                if (!$report->isValid()) return false;
                
                if (!$report->delete()){
                    echo "$('#course_report_output').html('<div class=\"elbp_err_box\" id=\"add_course_reports_errors\"></div>');";
                    foreach($report->getErrors() as $error){
                        echo "$('#add_course_reports_errors').append('<span>{$error}</span><br>');";
                    }
                    exit;
                }
                
                echo "$('#course_report_output').html('<div class=\"elbp_success_box\" id=\"add_course_reports_success\"></div>');";
                echo "$('#add_course_reports_success').append('<span>".get_string('coursereportdeleted', 'block_elbp')."</span><br>');";
                echo "$('#elbp_course_report_id_{$report->getID()}').remove();";
                exit;
                
            break;
            
            // Search for reports between two dates, to be included in a periodical report
            case 'search':
                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['dateFrom']) || !isset($params['dateTo'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_periodical_course_report', $access)) return false;
                
                $unix = array();
                $unix['from'] = strtotime($params['dateFrom'] . " 00:00:00");
                $unix['to'] = strtotime($params['dateTo'] . " 00:00:00");
                
                // Search for course reports within this date range
                $reports = $this->getCourseReportsByDateRange($unix);
                if ($reports)
                {
                    
                    $TPL = new \ELBP\Template();
                    $TPL->set("reports", $reports);
                    $TPL->set("obj", $this);
                    $output = $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/CourseReports/Periodical/tpl/new.html' );
                    
                }
                else
                {
                    $output = get_string('noresults', 'block_elbp');
                }
                
                echo $output;
                
                exit;
                
            break;
            
            case 'edit_periodical':
                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['reportID'])) return false;
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                
                // No point having seperate permissions for add & edit, just reuse add
                if (!elbp_has_capability('block/elbp:add_periodical_course_report', $access)) return false;
                
                $report = new \ELBP\Plugins\CourseReports\PeriodicalCourseReport($params['reportID']);
                if (!$report->isValid()) return false;
                
                $TPL = new \ELBP\Template();
                $TPL->set("report", $report);
                $TPL->set("reports", $report->getReports());
                $TPL->set("allReports", $this->getCourseReports());
                $TPL->set("obj", $this);
                $output = $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/CourseReports/Periodical/tpl/new.html' );
                
                echo $output;               
                exit;
                
            break;
            
            case 'delete_periodical':
                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['reportID'])) return false;
                                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:delete_periodical_course_report', $access)) return false;
                                
              
                $periodical = new \ELBP\Plugins\CourseReports\PeriodicalCourseReport($params['reportID']);                
                
                if (!$periodical->delete()){
                    echo "$('#elbp_periodical_saving_output').html('<div class=\"elbp_err_box\" id=\"add_course_reports_errors\"></div>');";
                    foreach($periodical->getErrors() as $error){
                        echo "$('#add_course_reports_errors').append('<span>{$error}</span><br>');";
                    }
                    exit;
                }
                                
                echo "$('#elbp_periodical_saving_output').html('<div class=\"elbp_success_box\" id=\"add_course_reports_success\"></div>');";
                echo "$('#add_course_reports_success').append('<span>".get_string('reportdeleted', 'block_elbp')."</span><br>');";
                echo "$('#periodical_row_{$params['reportID']}').remove();";
                echo "$('#elbp_periodical_output').html('');";
                exit;
                
            break;
            
            case 'save_periodical':
                                
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['periodical_report_name']) || !isset($params['periodical_report_comments']) || !isset($params['periodical_report_reports'])) return false;
                                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_periodical_course_report', $access)) return false;
                                
                // Reports to include
                $reports = array();
                $reportIDs = $params['periodical_report_reports'];
                                                
                foreach( (array)$reportIDs as $reportID )
                {
                    
                    $report = new \ELBP\Plugins\CourseReports\CourseReport($reportID);
                    if ($report->isValid() && $report->getStudentID() == $params['studentID'])
                    {
                        $reports[] = $report;
                    }
                    
                }
                                
                if (empty($reports)) return false;
                                
                // Going to use setters instead of an array of params for this. Seems more elegant, will see how it goes
                $id = (isset($params['periodical_report_id'])) ? $params['periodical_report_id'] : false;
                
                $periodical = new \ELBP\Plugins\CourseReports\PeriodicalCourseReport($id);
                $periodical->setCourseReportsObj($this);
                $periodical->setName($params['periodical_report_name']);
                $periodical->setComments($params['periodical_report_comments']);
                $periodical->setStatus($params['periodical_report_status']);
                $periodical->setReports($reports);
                $periodical->setStudentID($params['studentID']);
                
                if (!$periodical->save()){
                    echo "$('#elbp_periodical_saving_output').html('<div class=\"elbp_err_box\" id=\"add_course_reports_errors\"></div>');";
                    foreach($periodical->getErrors() as $error){
                        echo "$('#add_course_reports_errors').append('<span>{$error}</span><br>');";
                    }
                    exit;
                }
                                
                echo "$('#elbp_periodical_saving_output').html('<div class=\"elbp_success_box\" id=\"add_course_reports_success\"></div>');";
                
                if (!isset($params['periodical_report_id'])){
                    echo "$('#add_course_reports_success').append('<span>".get_string('reportcreated', 'block_elbp')."</span><br>');";
                    echo "$('#elbp_list_periodical_reports').append('<tr id=\"periodical_row_".$periodical->getID()."\"><td><a href=\"#\" onclick=\"ELBP.CourseReports.load_display(\'periodical_report\', false, false, {$periodical->getID()});return false;\">".elbp_html($periodical->getName())."</a></td><td>{$periodical->getCreatedDate('M jS Y, H:i:s')}</td><td>".fullname($periodical->getCreatedByUser())."</td><td>{$periodical->getStatus()}</td><td><a href=\"#\" onclick=\"ELBP.CourseReports.edit_periodical({$periodical->getID()});return false;\" title=\"".get_string('edit')."\"><img src=\"".elbp_image_url('t/edit')."\" /></a></td><td><a href=\"#\" onclick=\"ELBP.CourseReports.delete_periodical({$periodical->getID()});return false;\" title=\"".get_string('delete')."\"><img src=\"".elbp_image_url('t/delete')."\" /></a></td></tr>');";
                } else {
                    echo "$('#add_course_reports_success').append('<span>".get_string('reportupdated', 'block_elbp')."</span><br>');";
                }
                
                echo "$('#elbp_periodical_output').html('');";
                exit;
                
            break;
            
            
            
        }
        
    }
    
    
    /**
     * Get the progress bar/info for the block content
     */
    public function _getBlockProgress()
    {
                
        global $CFG;
        
        $output = "";
        
        // Number of tutorials set
        $total = count($this->getCourseReports());
        
        $output .= "<div>";
            $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/progress_bar.png' alt='progress_bar' /> {$total} " . get_string('coursereports', 'block_elbp');
        $output .= "</div>";
               
        return $output;
        
    }
    
    
    
    /**
     * Print out to simple HTML page
     * @global \ELBP\Plugins\type $ELBP
     * @param int $reportID If is null, will print all the student's reports
     * @param int $studentID Doesn't actually seem to be used...
     * @param string $type If null will do normal reports, otherwise should be string of "periodical"
     * @return boolean
     */
    public function printOut($reportID = null, $studentID = null, $type = null)
    {
        
        global $ELBP;
                
        
        if (!is_null($type) && $type == 'periodical')
        {
            
             if (!is_null($reportID))
             {

                if (is_numeric($reportID))
                {
                    
                    $periodicalReport = new \ELBP\Plugins\CourseReports\PeriodicalCourseReport($reportID);
                    if (!$periodicalReport->isValid()){
                        echo get_string('invalidaccess', 'block_elbp');
                        return false;
                    }
                    
                    // Get our access for the student who this belongs to
                    $access = $ELBP->getUserPermissions( $periodicalReport->getStudentID() );
                    if (!elbp_has_capability('block/elbp:print_periodical_course_report', $access) && !$access['parent']){
                        echo get_string('invalidaccess', 'block_elbp');
                        return false;
                    }

                    // Carry on
                    $periodicalReport->setCourseReportsObj($this);
                    $periodicalReport->printOut();
                    return true;
                    
                }
                
             }
            
        }
        else
        {
            
            $report = new \ELBP\Plugins\CourseReports\CourseReport($reportID, $this);
            if (!$report->isValid()){
                echo get_string('invalidaccess', 'block_elbp');
                return false;
            }
            
            $access = $ELBP->getUserPermissions( $report->getStudentID() );
            if (!elbp_has_capability('block/elbp:print_course_report', $access) && !$access['parent']){
                echo get_string('invalidaccess', 'block_elbp');
                return false;
            }
            
            $report->printOut();
            return true;
            
        }
                
                    
    }
    
    /**
     * Yes it does
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
                $chk = ($this->getSetting('student_progress_definitions_req') == 1) ? 'checked' : '';
                $output .= "<td><input type='checkbox' name='student_progress_definitions_req' value='1' {$chk} /></td>";
                $output .= "<td><input type='text' class='elbp_small' name='student_progress_definition_values_req' value='{$this->getSetting('student_progress_definition_values_req')}' /></td>";
                $output .= "<td>".get_string('studentprogressdefinitions:reqnumcoursereports', 'block_elbp')."</td>";
                $output .= "<td><input type='number' min='0.5' step='0.5' class='elbp_smallish' name='student_progress_definition_importance_req' value='{$this->getSetting('student_progress_definition_importance_req')}' /></td>";
            $output .= "</tr>";
            
            
            $output .= "<tr>";
                $chk = ($this->getSetting('student_progress_definitions_reqperiodical') == 1) ? 'checked' : '';
                $output .= "<td><input type='checkbox' name='student_progress_definitions_reqperiodical' value='1' {$chk} /></td>";
                $output .= "<td><input type='text' class='elbp_small' name='student_progress_definition_values_reqperiodical' value='{$this->getSetting('student_progress_definition_values_reqperiodical')}' /></td>";
                $output .= "<td>".get_string('studentprogressdefinitions:reqnumcoursereportsperiodical', 'block_elbp')."</td>";
                $output .= "<td><input type='number' min='0.5' step='0.5' class='elbp_smallish' name='student_progress_definition_importance_reqperiodical' value='{$this->getSetting('student_progress_definition_importance_reqperiodical')}' /></td>";
            $output .= "</tr>";
            
        
        $output .= "</table>";
        
        return $output;
        
    }
    
    /**
     * Calculate the course reports part of the overall student progress
     * @return type
     */
     public function calculateStudentProgress(){
        
        $max = 0;
        $num = 0;
        $info = array();
        
        $reports = $this->getCourseReports();        
        $cnt = count($reports);
        
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
                
                $key = get_string('studentprogress:info:coursereports:req', 'block_elbp');
                $key = str_replace('%n%', $req, $key);
                $percent = round( ($cnt / $req) * 100 );
                $info[$key] = array(
                    'percent' => ($percent > 100) ? 100 : $percent,
                    'value' => $cnt
                );
                
            }
            
        }
             
        
        
        // Periodicals
        
        $reports = $this->getPeriodicalReports();
        $cnt = count($reports);
        
        
        // Set number required
        if ($this->getSetting('student_progress_definitions_reqperiodical') == 1)
        {
            
            $req = $this->getSetting('student_progress_definition_values_reqperiodical');
            if ($req > 0)
            {
                
                $importance = $this->getSetting('student_progress_definition_importance_reqperiodical');
                
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
                
                $key = get_string('studentprogress:info:coursereportsperiodical:req', 'block_elbp');
                $key = str_replace('%n%', $req, $key);
                $percent = round( ($cnt / $req) * 100 );
                $info[$key] = array(
                    'percent' => ($percent > 100) ? 100 : $percent,
                    'value' => $cnt
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
                    $DB->execute("UPDATE {lbp_course_report_attributes} SET field = ? WHERE field = ?", array($newName, $oldName));
                    
                }
                
            }
        }
        
        return true;
        
    }
    
    
}