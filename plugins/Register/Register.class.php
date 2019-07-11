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

/**
 *
 */
class Register extends Plugin {

    private $info_from_mis = array();
    private $all_results = false;

    protected $tables = array(
        'lbp_register',
        'lbp_register_events'
    );

    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {

        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Register",
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
     * Connect to MIS
     */
    public function connect(){

        if ($this->getSetting("use_direct_mis") == 1){
            $this->loadMISConnection();
            if ($this->connection && $this->connection->connect()){
                $core = $this->getMainMIS();
                if ($core){
                    $pluginConn = new \ELBP\MISConnection($core->id);
                    if ($pluginConn->isValid()){
                        $this->useMIS = true;
                        $this->plugin_connection = $pluginConn;
                        $this->setupMisRequirements();
                    }
                }
            }
        }

    }

    /**
     * Get the start date setting
     * @return type
     */
    public function getStartDate(){
        return $this->getSetting('start_date');
    }

    /**
     * Get the week number of the start date, setting
     * @return type
     */
    public function getStartWeek(){
        $setting = $this->getSetting('start_week');
        return ($setting) ? $setting : 1;
    }

    /**
     * Get the day name of the start date
     * @return boolean
     */
    public function getStartDayName(){

        $setting = $this->getStartDate();

        if ($setting){

            $unix = strtotime($setting);
            return date('l', $unix);

        } else {
            return false;
        }

    }

    /**
     * Get the end date setting
     * @return type
     */
    public function getEndDate(){
        return $this->getSetting('end_date');
    }

    /**
     * Get the week number setting of the end date
     * @return type
     */
    public function getEndWeek(){
        $setting = $this->getSetting('end_week');
        return ($setting) ? $setting : 52;
    }

    /**
     * REMOVE THIS WHEN MIS DO DAYNUMBER ON OUR TABLE
     * @param type $dayName
     */
    private function getDayNumber($dayName){

        $array = array(
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Sunday' => 6,
            'Saturday' => 7
        );

        return $array[$dayName];

    }

    /**
     * Given a day number, get its name
     * This assumes Mon = 1 -> 7 = Sun
     * Needs to not assume really
     * @param type $dayNum
     * @return string
     */
    private function getDayName($dayNum){

        $array = array(
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        );

        return $array[$dayNum];

    }


    /**
     * Get the required fields for setting up an MIS connection
     * @return type
     */
    private function getRequiredMisFields(){

        return array(
            "dayname",
            "course",
            "description",
            "starttime",
            "endtime",
            "week",
            "value"
        );

    }

    /**
     * Get the required settings values of the MIS connection
     */
    private function setupMisRequirements(){

        $this->mis_settings = array();

        // Settings
        $this->mis_settings['view'] = $this->getSetting('mis_view_name');
        $this->mis_settings['postconnection'] = $this->getSetting('mis_post_connection_execute');
        $this->mis_settings['mis_username_or_idnumber'] = $this->getSetting('mis_username_or_idnumber');
        if (!$this->mis_settings['mis_username_or_idnumber']) $this->mis_settings['mis_username_or_idnumber'] = 'username';

        // Mappings
        $reqFields = $this->getRequiredMisFields();
        if ($reqFields)
        {
            foreach($reqFields as $reqField)
            {
                $this->mis_settings['mapping'][$reqField] = $this->plugin_connection->getFieldMap($reqField);
                $this->mis_settings['alias'][$reqField] = $this->plugin_connection->getFieldAlias($reqField);
            }
        }

        // If there are any queries to be executed after connection, run them
        if ($this->mis_settings['postconnection'] && !empty($this->mis_settings['postconnection'])){
            $this->connection->query($this->mis_settings['postconnection']);
        }

    }

    /**
     * Install the plugin
     */
    public function install()
    {

        $this->id = $this->createPlugin();
        $return = true && $this->id;

        // This is a core ELBP plugin, so the extra tables it requires are handled by the core ELBP install.xml


        // Settings
        $this->updateSetting("day_number_format", "w");

        // Attendance Codes
        $this->updateSetting("value_key_#", "Planned Whole/Partial School Closure");
        $this->updateSetting("value_key_/", "Present");
        $this->updateSetting("value_key_\\", "Present");
        $this->updateSetting("value_key_B", "Approved Off-Site Activity");
        $this->updateSetting("value_key_C", "Authorised Leave of Absence");
        $this->updateSetting("value_key_D", "Dual Registered");
        $this->updateSetting("value_key_E", "Excluded");
        $this->updateSetting("value_key_G", "Unauthorised Holiday");
        $this->updateSetting("value_key_H", "Authorised Holiday");
        $this->updateSetting("value_key_I", "Illness");
        $this->updateSetting("value_key_J", "Interview");
        $this->updateSetting("value_key_L", "Late");
        $this->updateSetting("value_key_M", "Medical or Dental Appointment");
        $this->updateSetting("value_key_N", "Reason for Absence Not Yet Provided");
        $this->updateSetting("value_key_O", "Unauthorised Absence");
        $this->updateSetting("value_key_P", "Approved Sporting Activity");
        $this->updateSetting("value_key_R", "Religious Observance");
        $this->updateSetting("value_key_S", "Study Leave");
        $this->updateSetting("value_key_T", "Traveller Absence");
        $this->updateSetting("value_key_U", "Arrived After Registration Closed");
        $this->updateSetting("value_key_V", "Educational Visit/Trip");
        $this->updateSetting("value_key_W", "Work Experience");
        $this->updateSetting("value_key_X", "Not Required to Be In School");
        $this->updateSetting("value_key_Y", "Unable to Attend Due to Exceptional Circumstances");
        $this->updateSetting("value_key_Z", "Pupil Not On Admission Register");


        return $return;
    }

    /**
     * Truncate related tables and uninstall plugin
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
     * Get the content of the expanded view
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()) {

        $TPL = new \ELBP\Template();

        $this->connect();

        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);
        $TPL->set("events", $this->getUserRegisterEvents());
        $TPL->set("start_date", $this->getStartDate());
        $TPL->set("end_date", $this->getEndDate());
        $TPL->set("start_week", $this->getStartWeek());
        $TPL->set("end_week", $this->getEndWeek());
        $TPL->set("display_name_setting", $this->getSetting('display_name'));

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/expanded.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){

        $TPL = new \ELBP\Template();

        $this->connect();

        $TPL->set("obj", $this);
        $TPL->set("events", $this->getUserRegisterEvents());

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Handle ajax requests sent to the plugin
     * @global \ELBP\Plugins\type $DB
     * @global type $USER
     * @param type $action
     * @param type $params
     * @param \ELBP\Plugins\type $ELBP
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

                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("access", $access);

                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/'.$params['type'].'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;

            break;
        }

    }

    /**
     * Get all the user's register events
     * @global \ELBP\Plugins\type $DB
     * @return \Anon|boolean
     */
    public function getAllUserRegisterEvents(){

        if (!$this->isEnabled() || !$this->student) return false;

        global $DB;

        // MIS
        if ($this->isUsingMIS())
        {
            $this->getAllFieldsFromMIS( array("username" => $this->student->username) );

            $reqFields = $this->getRequiredMisFields();

            $results = array();

            if ($this->info_from_mis)
            {

                foreach($this->info_from_mis as $row)
                {

                    $result = new \Anon;

                    foreach($reqFields as $reqField)
                    {

                        $fieldMap = $this->plugin_connection->getFieldMap($reqField);
                        $alias = $this->plugin_connection->getFieldAlias($reqField);
                        $val = $reqField;

                        if ($alias){
                            $result->$val = (isset($row[$alias])) ? $row[$alias] : null;
                        } else {
                            $result->$val = (isset($row[$fieldMap])) ? $row[$fieldMap] : null;
                        }

                    }

                    $results[] = $result;

                }

            }

            return $results;

        }
        else
        {
             // Moodle DB
             $records = $DB->get_records_sql("SELECT DISTINCT e.*
                                            FROM {lbp_register} r
                                            INNER JOIN {lbp_register_events} e ON e.id = r.eventid
                                            WHERE r.studentid = ?
                                            ORDER BY e.daynum", array($this->student->id));

             return $records;
        }


    }

    /**
     * Get user's register events
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    public function getUserRegisterEvents(){

        if (!$this->isEnabled() || !$this->student) return false;

        global $DB;

        // MIS
        if ($this->isUsingMIS())
        {
            return $this->getMisRegisterEvents();
        }
        else
        {
             // Moodle DB
             $records = $DB->get_records_sql("SELECT DISTINCT e.*
                                            FROM {lbp_register} r
                                            INNER JOIN {lbp_register_events} e ON e.id = r.eventid
                                            WHERE r.studentid = ?
                                            ORDER BY e.daynum", array($this->student->id));

             return $records;
        }



    }

    /**
     * Get the value to put in the table cell of a register event
     * @global \ELBP\Plugins\type $DB
     * @param type $event
     * @param type $week
     * @return string|boolean
     */
    public function getUserEventValueTD($event, $week){

        if (!$this->isEnabled() || !$this->student) return false;

        global $DB;

        $output = "";

        // MIS
        if ($this->isUsingMIS())
        {

            // See if we have loaded all events yet
            if ($this->all_results === false){
                $this->all_results = $this->getAllUserRegisterEvents();
            }

            $val = null;

            if ($this->all_results)
            {
                foreach($this->all_results as $result)
                {

                    if ($result->course == $event->course && $result->dayname == $event->day && $result->week == $week && $result->starttime == $event->starttime && $result->endtime == $event->endtime)
                    {
                        $val = $result->value;
                        break;
                    }

                }
            }

            $class = (is_null($val)) ? 'disabled' : '';
            $title = (is_null($val)) ? '' : $this->getValueTitle($val, $event, $week);

            $output .= "<td class='{$class}  week'  style='text-align:center !important;'>";
            $output .= "<div class='elbp_tooltip' title='{$title}'>";
            $output .= $val;
            $output .= "</div></td>";

        }
        else
        {

            // Moodle DB
            $record = $DB->get_record("lbp_register", array("studentid" => $this->student->id, "eventid" => $event->id, "week" => $week));
            $value = ($record) ? $record->value : '';

            $class = (is_null($value)) ? 'disabled' : '';
            $title = $this->getValueTitle($value, $event, $week);

            $output .= "<td class='{$class}' style='text-align:center !important;'>";
                $output .= "<div class='elbp_tooltip' title='{$title}'>";
                $output .= $value;
            $output .= "</div></td>";

        }



        return $output;

    }

    /**
     * Get the hover content for a register event table cell
     * @param type $value
     * @param type $event
     * @param type $week
     * @return string
     */
    public function getValueTitle($value, $event, $week){

        $title = $this->getSetting("value_key_{$value}");

        $startDayName = $this->getStartDayName();
        $startUnix = strtotime($this->getStartDate());
        $unix = false;

        if ($startDayName && $startUnix){

            $startNumeric = $this->getDayNumber($startDayName);
            $thisNumeric = $this->getDayNumber($event->day);

            $diff = $thisNumeric - $startNumeric;

            $thisDayAtStart = strtotime("+ {$diff} days", $startUnix);

            $weekDiff = $week - $this->getStartWeek();

            $unix = strtotime("+ {$weekDiff} weeks", $thisDayAtStart);

        }

        $output = "";
        if ($unix){
            $output .= date('l d/m/Y', $unix) . " (" . get_string('week', 'block_elbp'). " {$week}) ";
        }

        $output .= $event->starttime . ' - ' . $event->endtime;
        $output .=  ' ('.$title.')';

        return $output;

    }

    /**
     * Get all the value keys and their meanings
     * @global \ELBP\Plugins\type $DB
     * @return \stdClass
     */
    public function getAllValueInfo(){

        global $DB;

        $return = array();
        $records = $DB->get_records_sql("SELECT * FROM {lbp_settings} WHERE pluginid = ? AND setting like ?", array($this->id, 'value_key_%'));

        if ($records)
        {

            foreach($records as $record)
            {

                $code = preg_replace('/value_key_/', '', $record->setting);
                $obj = new \stdClass();
                $obj->code = $code;
                $obj->title = $record->value;
                $return[] = $obj;
            }

        }

        return $return;

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

        if (isset($settings['submit_values'])){

            $DB->execute("DELETE FROM {lbp_settings} WHERE pluginid = ? AND setting LIKE ?", array($this->id, 'value_key_%'));

            if (isset($settings['values_code']) && $settings['values_code'])
            {

                $cnt = count($settings['values_code']);

                for ($i = 0; $i < $cnt; $i++)
                {

                    $code = trim($settings['values_code'][$i]);
                    $title = trim($settings['values_title'][$i]);
                    if (!\elbp_is_empty($code) && !\elbp_is_empty($title))
                    {
                        $this->updateSetting("value_key_{$code}", $title);
                    }

                }

            }

            $MSGS['success'] = get_string('valuesupdated', 'block_elbp');
            return true;

        }

        elseif (isset($settings['submitmistest_student']) && !empty($settings['testusername']))
        {
            $username = $settings['testusername'];
            $this->runTestMisQuery($username);
            return true;
        }





        // Mappings first if they are there
        if (isset($settings['mis_map']))
        {

            // Get the plugin's core MIS connection
            $core = $this->getMainMIS();
            if (!$core)
            {
                $MSGS['errors'][] = get_string('nocoremis', 'block_elbp');
                return false;
            }

            // Set the mappings
            $conn = new \ELBP\MISConnection($core->id);
            if ($conn->isValid())
            {

                foreach($settings['mis_map'] as $name => $field)
                {
                    $field = trim($field);
                    $alias = (isset($settings['mis_alias'][$name]) && !empty($settings['mis_alias'][$name])) ? $settings['mis_alias'][$name] : null;
                    $func = (isset($settings['mis_func'][$name]) && !empty($settings['mis_func'][$name])) ? $settings['mis_func'][$name] : null;
                    $conn->setFieldMap($name, trim($field), $alias, $func);
                }


            }

            unset($settings['mis_map']);
            unset($settings['mis_alias']);
            unset($settings['mis_func']);

        }

        elseif (isset($settings['submit_import']) && isset($_FILES['file']) && !$_FILES['file']['error']){

            $result = $this->runImport($_FILES['file']);
            $MSGS['result'] = $result;
            return true;

        }

        parent::saveConfig($settings);

    }

    /**
     * Run the csv data import
     * @global \ELBP\Plugins\type $DB
     * @param type $file
     * @param type $fromCron
     * @return type
     */
    public function runImport($file, $fromCron = false){

        global $DB;

        // If cron, mimic $_FILES element
        if ($fromCron){
            $file = array(
                'tmp_name' => $file
            );
        }

        $output = "";

        $start = explode(" ", microtime());
        $start = $start[1] + $start[0];

        $output .= "*** " . get_string('import:begin', 'block_elbp') . " ".date('H:i:s, D jS M Y')." ***<br>";
        $output .= "*** " . get_string('import:openingfile', 'block_elbp') . " ({$file['tmp_name']}) ***<br>";

        // CHeck file exists
        if (!file_exists($file['tmp_name'])){
            return array('success' => false, 'error' => get_string('filenotfound', 'block_elbp') . " ( {$file['tmp_name']} )");
        }

        // Check mime type of file to make sure it is csv
        $fInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fInfo, $file['tmp_name']);
        finfo_close($fInfo);

        // Has to be csv file, otherwise error and return
        if ($mime != 'text/csv' && $mime != 'text/plain'){
            return array('success' => false, 'error' => get_string('uploads:invalidmimetype', 'block_elbp') . " ( {$mime} )");
        }

        // Open file
        $fh = fopen($file['tmp_name'], 'r');
        if (!$fh){
            return array('success' => false, 'error' => get_string('uploads:cantopenfile', 'block_elbp'));
        }

        // Compare headers
        $headerRow = fgetcsv($fh);
        $headers = $this->getImportCsvHeaders();

        if ($headerRow !== $headers){
            $str = get_string('import:headersdontmatch', 'block_elbp');
            $str = str_replace('%exp%', implode(', ', $headers), $str);
            $str = str_replace('%fnd%', implode(', ', $headerRow), $str);
            return array('success' => false,'error' => $str);
        }


        // Headers are okay, so let's rock and roll
        $i = 0;
        $validUsernames = array(); // Save us checking same username multiple times - saves processing time
        $validCourses = array(); // Save us checking same course multiple times - saves processing time
        $validValues = array(); // ""
        $errorCnt = 0;



        // Which field are we looking at?
        $courseField = $this->getSetting('import_course_field');
        if (!$courseField){
            $courseField = 'shortname';
        }

        $userField = $this->getSetting('import_user_field');
        if (!$userField){
            $userField = 'username';
        }



        while( ($row = fgetcsv($fh, 0, ',', '"', '"')) !== false )
        {

            $i++;

            $row = array_map('trim', $row);

            $username = $row[0];
            $course = $row[1];
            $eventcode = $row[2];
            $description = $row[3];
            $dayname = $row[4];
            $daynumber = $row[5];
            $starttime = $row[6];
            $endtime = $row[7];
            $weeknumber = $row[8];
            $value = $row[9];

            // First check that all columns have something in (except courseshortname, that can be empty)
            for($j = 0; $j < count($headers); $j++){

                if ($j == 1 || $j == 3) continue;

                if (elbp_is_empty($row[$j])){
                    $output .= "[{$i}] " . get_string('import:colsempty', 'block_elbp') . " : (".implode(',', $row).")<br>";
                    $errorCnt++;
                    continue;
                }

            }


            // Make sure times are in correct format: hhmm
            if (!ctype_digit($starttime) || (ctype_digit($starttime) && strlen($starttime) <> 4) ){
                $output .= "[{$i}] " . get_string('import:format:hhmm', 'block_elbp') . " : (".$starttime.")<br>";
                $errorCnt++;
                continue;
            }

            if (!ctype_digit($endtime) || (ctype_digit($endtime) && strlen($endtime) <> 4) ){
                $output .= "[{$i}] " . get_string('import:format:hhmm', 'block_elbp') . " : (".$endtime.")<br>";
                $errorCnt++;
                continue;
            }


            // Now put a dot in the middle of the times, sinec that's how we are doing it for some reason I may have understood at some point when drunk
            $exp = str_split($starttime, 2);
            $starttime = $exp[0] . "." . $exp[1];

            $exp = str_split($endtime, 2);
            $endtime = $exp[0] . "." . $exp[1];


            // Check username exists
            $user = false;

            if (!array_key_exists($username, $validUsernames)){

                $user = $DB->get_record("user", array($userField => $username, "deleted" => 0));
                if ($user){
                    $validUsernames[$username] = $user;
                } else {

                    // If we have set it to create non-existent users, create it now
                    if ($this->getSetting('import_create_user_if_not_exists') == 1){
                        $user = \elbp_create_user_from_username($username);
                    }

                    if ($user){
                        $validUsernames[$username] = $user;
                        $output .= "[{$i}] " . get_string('createduser', 'block_elbp') . " : {$username} [{$user->id}]<br>";
                    } else {
                        $output .= "[{$i}] " . get_string('nosuchuser', 'block_elbp') . " : {$username}<br>";
                        $errorCnt++;
                        continue;
                    }

                }

            } else {
                $user = $validUsernames[$username];
            }

            // Otherwise it IS in validUsernames, so we already know its fine - carry on



            // Course is optional, if it is set, then check if its valid
            $courseRecord = false;

            if (!empty($course)){

                if (!array_key_exists($course, $validCourses)){

                    $courseRecord = $DB->get_record("course", array($courseField => $course), "id, shortname, idnumber, fullname");
                    if ($courseRecord){
                        $validCourses[$course] = $courseRecord;
                    } else {

                        // If we have set it to create non-existent courses, create it now
                        if ($this->getSetting('import_create_course_if_not_exists') == 1){
                            $courseRecord = \elbp_create_course_from_shortname($course);
                        }

                        if ($courseRecord){
                            $validCourses[$course] = $courseRecord;
                            $output .= "[{$i}] " . get_string('createdcourse', 'block_elbp') . " : {$course} [{$courseRecord->id}]<br>";
                        } else {
                            $output .= "[{$i}] " . get_string('nosuchcourse', 'block_elbp') . " : {$course}<br>";
                            $errorCnt++;
                            continue;
                        }


                    }

                } else {
                    $courseRecord = $validCourses[$course];
                }

            }

            // And finally check that the value is a valid one that we created in our settings page
            if (!in_array($value, $validValues)){

                $valueObj = $this->getSetting('value_key_' . $value);
                if ($valueObj){
                    $validValues[] = $value;
                } else {

                    $output .= "[{$i}] " . get_string('nosuchvalue', 'block_elbp') . " : {$value}<br>";
                    $errorCnt++;
                    continue;

                }

            }



            // At this point everything is okay, so let's actually import the data
            $courseID = (isset($courseRecord) && $courseRecord) ? $courseRecord->id : null;

            // See if event exists
            $event = $DB->get_record("lbp_register_events", array("eventcode" => $eventcode, "daynum" => $daynumber, "starttime" => $starttime, "endtime" => $endtime));

            if (!$event)
            {

                if ($description == '') $description = null;

                $obj = new \stdClass();
                $obj->eventcode = $eventcode;
                $obj->description = $description;
                $obj->courseid = $courseID;
                $obj->day = $dayname;
                $obj->daynum = $daynumber;
                $obj->starttime = $starttime;
                $obj->endtime = $endtime;

                $obj->id = $DB->insert_record("lbp_register_events", $obj);
                $event = $obj;

                $output .= "[{$i}] " . get_string('import:insertedevent', 'block_elbp') . " - ({$eventcode})<br>";

            }

            $record = $DB->get_record("lbp_register", array("studentid" => $user->id, "eventid" => $event->id, "week" => $weeknumber));

            // Record for this data already exists, so update it with new value
            if ($record)
            {

                $output .= "[{$i}] " . get_string('import:recordexists', 'block_elbp') . "<br>";

                $record->value = $value;
                $DB->update_record("lbp_register", $record);
                $output .= "[{$i}] " . get_string('import:updatedrecord', 'block_elbp') . " - ".fullname($user)." ({$user->username}) [".implode(',', $row)."]<br>";
            }
            else
            {

                // Insert record
                $obj = new \stdClass();
                $obj->studentid = $user->id;
                $obj->eventid = $event->id;
                $obj->week = $weeknumber;
                $obj->value = $value;
                $DB->insert_record("lbp_register", $obj);
                $output .= "[{$i}] " . get_string('import:insertedrecord', 'block_elbp') . " - ".fullname($user)." ({$user->username}) [".implode(',', $row)."]<br>";
            }

        }

        fclose($fh);

        $str = get_string('import:finished', 'block_elbp');
        $str = str_replace('%num%', $errorCnt, $str);
        $str = str_replace('%ttl%', $i, $str);
        $output .= "*** " . $str . " ***<br>";

        $finish = explode(" ", microtime());
        $finish = $finish[1] + $finish[0];
        $output .= "*** ".str_replace('%s%', ($finish - $start) , get_string('import:scripttime', 'block_elbp'))." ***<br>";

        return array('success' => true, 'output' => $output);

    }



    /**
     * This will take the MIS connection and field details you have provided in the settings and run a test query to see
     * if it returns what you expect
     * @param string $username - The username to run the query against
     */
    public function runTestMisQuery($username){

        global $CFG, $MSGS;

        // This query will select all records it can find for a specified username/idnumber

        $view = $this->getSetting("mis_view_name");
        if (!$view){
            $MSGS['errors'][] = 'mis_view_name';
            return false;
        }

        $username_or_idnumber = $this->getSetting("mis_username_or_idnumber");
        if (!$username_or_idnumber){
            $MSGS['errors'][] = 'mis_username_or_idnumber';
            return false;
        }

        // Core MIS connection
        $core = $this->getMainMIS();
        if (!$core){
            $MSGS['errors'][] = get_string('nocoremis', 'block_elbp');
            return false;
        }

        $conn = new \ELBP\MISConnection($core->id);
        if (!$conn->isValid()){
            $MSGS['errors'][] = get_string('mis:connectioninvalid', 'block_elbp');
            return false;
        }



        $reqFields = $this->getRequiredMisFields();

        foreach($reqFields as $reqField)
        {
            if (!$conn->getFieldMap($reqField) && !$conn->getFieldFunc($reqField)){
                $MSGS['errors'][] = get_string('missingreqfield', 'block_elbp') . ": " . $reqField;
                return false;
            }
        }

        $this->connect();
        $this->getAllFieldsFromMIS( array("username" => $username) );
        $results = array();

        // Debugging on?
        if ($CFG->debug >= 32767){
            $MSGS['sql'] = $this->connection->getLastSQL();
        }


        if ($this->info_from_mis)
        {

            foreach($this->info_from_mis as $row)
            {

                $result = new \Anon;

                foreach($reqFields as $reqField)
                {

                    $fieldMap = $conn->getFieldMap($reqField);
                    $alias = $conn->getFieldAlias($reqField);
                    $val = $reqField;

                    if ($alias){
                        $result->$val = (isset($row[$alias])) ? $row[$alias] : null;
                    } else {
                        $result->$val = (isset($row[$fieldMap])) ? $row[$fieldMap] : null;
                    }

                }

                $results[] = $result;

            }

        }



        $MSGS['testoutput'] = $results;



    }

    /**
     * No idea
     * @param type $event
     * @param type $week
     * @return boolean
     */
    private function getMisRegisterEventWeek($event, $week)
    {

        global $CFG;

        if (!$this->student) return false;
        if (!$this->isUsingMIS()) return true;
        if (!$this->connection) return false;
        if (!$this->plugin_connection) return false;

        // Bedcoll
        if ( isset($CFG->moodleinstance) && ( $this->student->institution != 'Student' || preg_match("/[a-z]/i", $this->student->username) ) ) return false;

        $userField = $this->mis_settings['mis_username_or_idnumber'];
        if (!isset($this->student->$userField)) $this->student->$userField = $this->student->username;

        $query = $this->connection->query("SELECT {$this->plugin_connection->getFieldMapQuerySelect('value')}
                                           FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                           WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                           AND {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('course'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('course')}
                                           AND {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('dayname'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('dayname')}
                                           AND {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('week'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('week')}",
                                                   array(
                                                       $this->plugin_connection->getFieldMap('username') => $this->student->$userField,
                                                       $this->plugin_connection->getFieldMap('course') => $event->course,
                                                       $this->plugin_connection->getFieldMap('dayname') => $event->day,
                                                       $this->plugin_connection->getFieldMap('week') => $week
                                                   ));

        if (!$query){
            return false;
        }

         $results = $this->connection->getRecords($query);




    }

    /**
     * Get the register events from MIS
     * @global \ELBP\Plugins\type $MSGS
     * @return \stdClass|boolean
     */
    private function getMisRegisterEvents()
    {

        global $CFG, $MSGS;

        if (!$this->student) return false;
        if (!$this->isUsingMIS()) return true;
        if (!$this->connection) return false;
        if (!$this->plugin_connection) return false;

        // Bedcoll
        if ( isset($CFG->moodleinstance) && ( $this->student->institution != 'Student' || preg_match("/[a-z]/i", $this->student->username) ) ) return false;

        $userField = $this->mis_settings['mis_username_or_idnumber'];
        if (!isset($this->student->$userField)) $this->student->$userField = $this->student->username;
        $query = $this->connection->query("SELECT DISTINCT {$this->plugin_connection->getFieldMapQuerySelect('course')},
                                                           {$this->plugin_connection->getFieldMapQuerySelect('description')},
                                                           {$this->plugin_connection->getFieldMapQuerySelect('dayname')},
                                                           {$this->plugin_connection->getFieldMapQuerySelect('daynumber')},
                                                           {$this->plugin_connection->getFieldMapQuerySelect('starttime')},
                                                           {$this->plugin_connection->getFieldMapQuerySelect('endtime')}
                                           FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                           WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                           ORDER BY {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('daynumber'))} ASC,
                                                    {$this->connection->wrapValue($this->plugin_connection->getFieldAliasOrMap('starttime'))} ASC",
                                                   array(
                                                       $this->plugin_connection->getFieldMap('username') => $this->student->$userField
                                                   ));

        if (!$query){
            $MSGS['errors'][] = $this->connection->getError();
            return false;
        }

         $results = $this->connection->getRecords($query);

         $return = array();

         if ($results)
         {
             foreach($results as $result)
             {

                 $courseField = $this->plugin_connection->getFieldAliasOrMap('course');
                 $descField = $this->plugin_connection->getFieldAliasOrMap('description');
                 $dayField = $this->plugin_connection->getFieldAliasOrMap('dayname');
                 $dayNumField = $this->plugin_connection->getFieldAliasOrMap('daynumber');
                 $startField = $this->plugin_connection->getFieldAliasOrMap('starttime');
                 $endField = $this->plugin_connection->getFieldAliasOrMap('endtime');

                 $obj = new \stdClass();
                 $obj->course = $result[$courseField];
                 $obj->description = $result[$descField];
                 $obj->day = $result[$dayField];
                 $obj->daynum = $result[$dayNumField];
                 $obj->starttime = $result[$startField];
                 $obj->endtime = $result[$endField];
                 $return[] = $obj;

             }
         }


         return $return;

    }


    /**
     * Since we will almost certainly be using a view/table with only one row of results, with all the different fields in
     * fields, rather than seperate rows like in our table in Moodle, load all of that info up into an array that we can
     * use
     */
    private function getAllFieldsFromMIS($options = null)
    {

        global $CFG, $MSGS;

        if (!$this->isUsingMIS()) return false;
        if (!$this->connection) return false;
        if (!$this->plugin_connection) return false;

        // Bedcoll
        if ( !isset($options['username']) && isset($CFG->moodleinstance) && ( $this->student->institution != 'Student' || preg_match("/[a-z]/i", $this->student->username) ) ) return false;

        // Reset to blank array
        $this->info_from_mis = array();

        $userField = $this->mis_settings['mis_username_or_idnumber'];

        if (isset($options['username'])) $username = $options['username'];
        else $username = $this->student->$userField;

        // Get distinct list of fields to query
        $fields = $this->plugin_connection->getAllMappingsForSelect(true);

        $query = $this->connection->query("SELECT {$fields} FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                           WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                           ORDER BY {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('course'))} ASC,
                                                    {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('week'))} ASC,
                                                    {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('dayname'))} ASC",
                                                   array(
                                                       $this->plugin_connection->getFieldMap('username') => $username
                                                   ));

        if (!$query){
            $MSGS['errors'][] = $this->connection->getError();
            return false;
        }


        // This should only return one row
        $results = $this->connection->getRecords($query);

        // Foreach field returned, set in info
        if ($results)
        {

            foreach($results as $result)
            {
                $arr = array();
                foreach($result as $field => $value)
                {
                    $arr[$field] = $value;
                }
                $this->info_from_mis[] = $arr;
            }
        }

        return true;

    }



    /**
     * Print register out to simple HTML page
     * @global type $ELBP
     * @param type $sessionID
     * @return boolean
     */
    public function printOut($type = 'all', $studentID = null)
    {

        global $ELBP, $CFG;

        if ($type == 'all' && !is_null($studentID))
        {


            // Get our access for the student who this belongs to
            $access = $ELBP->getUserPermissions( $studentID );
            if (!elbp_has_capability('block/elbp:print_register', $access)){
                echo get_string('invalidaccess', 'block_elbp');
                return false;
            }

            $this->loadStudent($studentID);
            $this->connect();

            // Print the register
            $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . get_string('register', 'block_elbp');
            $title = get_string('register', 'block_elbp');
            $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';
            $logo = \ELBP\ELBP::getPrintLogo();

            $start_date = $this->getStartDate();
            $end_date = $this->getEndDate();
            $start_week = $this->getStartWeek();
            $end_week = $this->getEndWeek();
            $events =  $this->getUserRegisterEvents();

            $txt = "";
            $txt .= "<table id='register'>";
            $txt .= "<thead>";
            $txt .= "<tr>";
                $txt .= "<th>".get_string('course')."</th>";
                $txt .= "<th>".get_string('desc', 'block_elbp')."</th>";
                $txt .= "<th>".get_string('day', 'block_elbp')."</th>";
                $txt .= "<th>".get_string('start', 'block_elbp')."</th>";
                $txt .= "<th>".get_string('end', 'block_elbp')."</th>";
                for($i = $start_week; $i <= $end_week; $i++){
                    $txt .= "<th>{$i}</th>";
                }
            $txt .= "</tr>";
            $txt .= "</thead>";

            $txt .= "<tbody>";

            if ($events)
            {
                foreach($events as $event)
                {
                    $txt .= "<tr>";

                        $txt .= "<td>".((isset($event->courseid)) ? \elbp_get_course_fullname($event->courseid) : '-')."</td>";
                        $txt .= "<td>{$event->description}</td>";
                        $txt .= "<td>".((isset($event->dayname)) ? $event->dayname : $event->day)."</td>";
                        $txt .= "<td>{$event->starttime}</td>";
                        $txt .= "<td>{$event->endtime}</td>";

                        for($i = $start_week; $i <= $end_week; $i++)
                        {
                            $txt .= $this->getUserEventValueTD($event, $i);
                        }

                    $txt .= "</tr>";
                }
            }
            else
            {
               $txt .= "<tr>";
                    $txt .= "<td>".get_string('noresults', 'block_elbp')."</td>";
               $txt .= "</tr>";
            }

            $txt .= "</tbody>";
            $txt .= "</table>";


            $txt .= "<style type='text/css'>table#register{ font-size:12px;width:100%; } table#register th{background-color:#000;color:#fff;} table#register td{border:1px solid grey;} table#register td:empty{background-color:grey;} table#register th, table#register th{padding:2px;}</style>";


            $TPL = new \ELBP\Template();
            $TPL->set("logo", $logo);
            $TPL->set("pageTitle", $pageTitle);
            $TPL->set("title", $title);
            $TPL->set("heading", $heading);
            $TPL->set("content", $txt);


            $TPL->load( $CFG->dirroot . '/blocks/elbp/tpl/print.html' );
            $TPL->display();
            exit;

            return true;

        }

    }

    /**
     * Get required headers for csv import
     * @return string
     */
    private function getImportCsvHeaders(){
        $headers = array();
        $headers[] = 'username';
        $headers[] = 'courseshortname';
        $headers[] = 'eventcode';
        $headers[] = 'eventdescription';
        $headers[] = 'dayname';
        $headers[] = 'daynumber';
        $headers[] = 'starttime(hhmm)';
        $headers[] = 'endtime(hhmm)';
        $headers[] = 'weeknumber';
        $headers[] = 'value';
        return $headers;
    }


    /**
     * Create the import csv
     * @global type $CFG
     * @param bool $reload - If i ever change it so it uses the custom attributes as file headers, we can force a reload
     * from the attributes page when its saved
     * @return string|boolean
     */
    public function createTemplateImportCsv($reload = false){

        $file = $this->getDataRoot() . '/templates/template.csv';
        $code = $this->createDataPathCode($file);

        // If it already exists and we don't want to reload it, just return
        if (file_exists($file) && !$reload){
            return $code;
        }

        // Now lets create the new one - The headers are going to be in English so we can easily compare headers
        $headers = $this->getImportCsvHeaders();

        // Using "w" we truncate the file if it already exists
        $fh = fopen($file, 'w');
        if ($fh === false){
            return false;
        }

        $fp = fputcsv($fh, $headers);

        if ($fp === false){
            return false;
        }

        fclose($fh);
        return $code;

    }


     /**
     * Create the import csv
     * @global type $CFG
     * @param bool $reload - If i ever change it so it uses the custom attributes as file headers, we can force a reload
     * from the attributes page when its saved
     * @return string|boolean
     */
    public function createExampleImportCsv($reload = false){

        global $DB;

        $file = $this->getDataRoot() . '/templates/example.csv';
        $code = $this->createDataPathCode($file);

        // If it already exists and we don't want to reload it, just return
        if (file_exists($file) && !$reload){
            return $code;
        }

        // Now lets create the new one - The headers are going to be in English so we can easily compare headers
        $headers = $this->getImportCsvHeaders();

        // Using "w" we truncate the file if it already exists
        $fh = fopen($file, 'w');
        if ($fh === false){
            return false;
        }

        $fp = fputcsv($fh, $headers);

        if ($fp === false){
            return false;
        }

        // Count users
        $cntUsers = $DB->count_records("user");
        $cntCourses = $DB->count_records("course");

        $getValues = $DB->get_records_sql("SELECT setting FROM {lbp_settings} WHERE pluginid = ? AND setting LIKE 'value_key_%'", array($this->id));
        if (!$getValues){
            return false;
        }

        $values = array();
        foreach($getValues as $val){
            $values[] = str_replace("value_key_", "", $val->setting);
        }

        $mins = array('00', '15', '30', '45');


        $courseField = $this->getSetting('import_course_field');
        if (!$courseField){
            $courseField = 'shortname';
        }

        $userField = $this->getSetting('import_user_field');
        if (!$userField){
            $userField = 'username';
        }


        // Now some rows
        for($i = 0; $i <= 50; $i++)
        {

            // Select random user
            $userID = mt_rand(1, $cntUsers);
            $user = $DB->get_record("user", array("id" => $userID, "deleted" => 0));
            if ($user)
            {

                $data = array();
                $data[] = $user->$userField;

                $rand = mt_rand(1,2);
                if ($rand == 1){

                    $courseID = mt_rand(1, $cntCourses);
                    $course = $DB->get_record("course", array("id" => $courseID));
                    if ($course)
                    {
                        $data[] = $course->$courseField;
                    }
                    else
                    {
                        $data[] = 'FakeCourse101';
                    }


                } else {
                    // No course code specified
                    $data[] = '';
                }

                $data[] = strtoupper(\elbp_generate_random_string(6, true));
                $data[] = 'Some Description';

                $dayNum = mt_rand(1,7);
                $data[] = $this->getDayName($dayNum);
                $data[] = $dayNum;



                $startHour = mt_rand(8,17);
                if ($startHour < 10) $startHour = "0".$startHour;

                $startMin = $mins[array_rand($mins)];
                $data[] = ''.$startHour.$startMin.'';



                $endHour = mt_rand(9,18);
                if ($endHour < 10) $endHour = "0".$endHour;

                while($endHour <= $startHour){
                    $endHour++;
                }

                $endMin = $mins[array_rand($mins)];
                $data[] = ''.$endHour.$endMin.'';



                $data[] = mt_rand(1,52);
                $data[] = $values[array_rand($values)];

                fputcsv($fh, $data);


            }

        }



        fclose($fh);
        return $code;

    }


    /**
     * Run the register cron
     * @return boolean
     */
    public function cron(){

        // Work out if it needs running or not
        $cronLastRun = $this->getSetting('cron_last_run');
        if (!$cronLastRun) $cronLastRun = 0;

        $now = time();

        $type = $this->getSetting('cron_timing_type');
        $hour = $this->getSetting('cron_timing_hour');
        $min = $this->getSetting('cron_timing_minute');
        $file = $this->getSetting('cron_file_location');

        if ($type === false || $hour === false || $min === false || $file === false) {
            \mtrace("Cron settings are missing. (Type:{$type})(Hour:{$hour})(Min:{$min})(File:{$file})");
            return false;
        }

        \mtrace("Last run: {$cronLastRun}");
        \mtrace("Current time: " . date('H:i', $now) . " ({$now})");

        switch($type)
        {

            // Run every x hours, y minutes
            case 'every':

                $diff = 60 * $min;
                $diff += (3600 * $hour);

                \mtrace("Cron set to run every {$hour} hours, {$min} mins");

                // If the difference between now and the last time it was run, is more than the "every" soandso, then run it
                /**
                 * For example:
                 *
                 * Run every 1 hours, 30 minutes
                 * diff = 5400 seconds
                 *
                 * Last run: 0 (never)
                 * Time now: 17:45
                 *
                 * (now unixtimestamp - 0) = No. seconds ago it was run (in this case it'll be millions, sinec its never run)
                 * Is that >= 5400? Yes it is, so run it
                 *
                 *
                 * Another example:
                 *
                 * Run every 1 hours, 30 minutes
                 * diff = 5400 seconds
                 *
                 * Last run: 15:00
                 * Time now: 16:45
                 *
                 * (timestamp - timestamp of 15:00) = 6300
                 * Is 6300 >= 5400? - Yes, so run it
                 *
                 *
                 * Another example:
                 *
                 * Run every 3 hours
                 * diff = 10800 seconds
                 *
                 * Last run: 15:00
                 * Time now: 16:00
                 *
                 * (16:00 timestamp - 15:00 timestamp) = 3600 seconds
                 * is 3600 >= 10800? - No, so don't run it
                 *
                 */
                if ( ($now - $cronLastRun) >= $diff )
                {

                    \mtrace("Cron set to run...");
                    $result = $this->runImport($file, true);
                    if ($result['success'])
                    {
                        $result['output'] = str_replace("<br>", "\n", $result['output']);
                        \mtrace($result['output']);

                        // Now we have finished, delete the file
                        if ( unlink($file) === true ){
                            \mtrace("Deleted file: " . $file);
                        } else {
                            \mtrace("Could not delete file: " . $file);
                        }

                    }
                    else
                    {
                        \mtrace('Error: ' . $result['error']);
                    }

                    // Set last run to now
                    $this->updateSetting('cron_last_run', $now);

                }
                else
                {
                    \mtrace("Cron not ready to run");
                }

            break;


            // Run at a specific time every day
            case 'specific':

                if ($hour < 10) $hour = "0".$hour;
                if ($min < 10) $min = "0".$min;

                $hhmm = $hour . $min;
                $nowHHMM = date('Hi');

                $unixToday = strtotime("{$hour}:{$min}:00");

                \mtrace("Cron set to run at {$hour}:{$min}, every day");

                /**
                 *
                 * Example:
                 *
                 * Run at: 15:45 every day
                 * Current time: 15:00
                 * Last run: 0
                 * hhmm = 1545
                 * nowHHMM = 1500
                 * is 1500 >= 1545? - No, don't run
                 *
                 * Another example:
                 *
                 * Run at: 15:45 every day
                 * Current time: 16:00
                 * Last run: 0
                 * is 1600 >= 1545? - yes
                 * is 0 < unixtimestamp of 15:45 today? - yes, okay run it
                 *
                 * Another example:
                 *
                 * Run at: 15:45 every day
                 * Current time: 16:00
                 * Last run: 15:45 today
                 * is 1600 >= 1545 - yes
                 * is (unixtimestamp of 15:45 today < unixtimestamp of 15:45 today? - no
                 *                  *
                 *
                 */


                if ( ( $nowHHMM >= $hhmm ) && $cronLastRun < $unixToday )
                {
                    \mtrace("Cron set to run...");
                    $result = $this->runImport($file, true);
                    if ($result['success'])
                    {
                        $result['output'] = str_replace("<br>", "\n", $result['output']);
                        \mtrace($result['output']);

                        // Now we have finished, delete the file
                        if ( unlink($file) === true ){
                            \mtrace("Deleted file: " . $file);
                        } else {
                            \mtrace("Could not delete file: " . $file);
                        }

                    }
                    else
                    {
                        \mtrace('Error: ' . $result['error']);
                    }

                    // Set last run to now
                    $this->updateSetting('cron_last_run', $now);


                }
                else
                {
                    \mtrace("Cron not ready to run");
                }


            break;

        }




        return true;

    }





}