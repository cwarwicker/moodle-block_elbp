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
class Attendance extends Plugin {

    private $types = false; # The enabled types of records. By default this would be "Attendance" and "Punctuality" but can add whatever
    private $periods; # Periods to recrod against, e.g. "Last 7 Days", "Last 28 Days", "Term 1", "Term 2", etc...

    protected $tables = array(
        'lbp_att_punc',
        'lbp_att_punc_history'
    );

    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {

        $this->requiredExtensions = array(
            'core' => array(),
            'optional' => array('fileinfo')
        );

        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Attendance & Punctuality",
                "path" => null,
                "version" => \ELBP\ELBP::getBlockVersionStatic()
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
            $this->loadEnabledTypes();
            $this->loadEnabledPeriods();
        }

    }

    /**
     * Get the little bit of info we want to display in the Student Profile summary section
     * @return mixed
     */
    public function getSummaryInfo(){

        if (!$this->student) return false;

        $return = array();

        // Average values
        if ($this->types)
        {
            foreach($this->types as $type)
            {
                $disp = $this->getSetting('student_summary_display_'.$type);
                if ($disp)
                {
                    $return[] = array(
                        'name' => $type,
                        'value' => $this->getRecord( array("type" => $type, "period" => $disp) )
                    );
                }
            }
        }

        return $return;

    }

    /**
     * Load all the enabled types into the types property
     */
    public function loadEnabledTypes()
    {

        $this->types = array();

        $setting = \ELBP\Setting::getSetting("enabled_types", null, $this->id);
        $types = explode("|", $setting);

        $setting = \ELBP\Setting::getSetting("enabled_types_short", null, $this->id);
        $short = explode("|", $setting);

        for ($i = 0; $i < count($types); $i++)
        {
            if (!empty($types[$i])){
                $this->types[$short[$i]] = $types[$i];
            }
        }

    }

    /**
     * Load the enabled periods into the periods property
     */
    public function loadEnabledPeriods()
    {

        $this->periods = array();

        $setting = \ELBP\Setting::getSetting("enabled_periods", null, $this->id);
        $periods = explode("|", $setting);

        for ($i = 0; $i < count($periods); $i++)
        {
            if (!empty($periods[$i])){
                $this->periods[] = $periods[$i];
            }
        }

    }

    /**
     * Get the loaded types
     * @return type
     */
    public function getTypes()
    {
        return ($this->types) ? $this->types : array();
    }

    /**
     * Get the loaded periods
     * @return type
     */
    public function getPeriods()
    {
        return ($this->periods) ? $this->periods : array();
    }

    /**
     * Get a record from the att_punc table
     * This doesn't have to actually be "Attendance" or "Punctuality" if the insitituion want anything else to be stored they can just
     * store a different type against it.
     * @param type $params This needs: type, period and optionally courseid
     * @param type bool Should we expect multiple records for this?
     * @return type
     */
    public function getRecord($params, $multiple = false)
    {

        $params['studentid'] = $this->student->id;
        if (!isset($params['courseid'])) $params['courseid'] = null;

        // Try to connect to MIS if we are using it and its valid
        $this->connect();

        if ($this->isUsingMIS())
        {

            $course = null;

            if (isset($params['courseid']) && !is_null($params['courseid']))
            {
                $courseRecord = $this->DB->get_record("course", array("id" => $params['courseid']));
                if ($courseRecord)
                {
                    $courseField = $this->mis_settings['mis_course_shortname_or_idnumber'];
                    if (isset($courseRecord->$courseField))
                    {
                        $course = $courseRecord->$courseField;
                    }
                }
            }

            if (!isset($params['period'])) $params['period'] = null;
            if (!isset($params['type'])) $params['type'] = null;

            $this->getAllFieldsFromMIS( array("username" => $this->student->username, "course" => $course, "period" => $params['period'], "type" => $params['type']) );
            $results = $this->info_from_mis;

            if ($multiple){
                return $results;
            } else {
                $results = reset($results);
                $valueField = $this->plugin_connection->getFieldMap('value');
                return ($results) ? $results[$valueField] : '-';
            }

        }
        else
        {

            if ($multiple){
                $records = $this->DB->get_records_select("lbp_att_punc", "studentid = :studentid AND courseid = :courseid
                                                                         AND type = :type AND period = :period", $params, "type");
                return $records;
            }
            else
            {
                $record = $this->DB->get_record("lbp_att_punc", $params);
                return ($record) ? $record->value : '-';
            }

        }




    }

    /**
     * Get a record from the att_punc table
     * This doesn't have to actually be "Attendance" or "Punctuality" if the insitituion want anything else to be stored they can just
     * store a different type against it.
     * @param type $params This needs: type, period and optionally courseid
     * @param type int $unix Unix timestamp
     * @return type
     */
    public function getRecordHistory($params, $unix)
    {

        $params['studentid'] = $this->student->id;
        if (!isset($params['courseid'])) $params['courseid'] = null;

        // Get the first record that is less than or equal to the unix timestamp we send it, so that we are getting
        // what the record said at 23:59 on that day
        if (is_null($params['courseid'])){
            $record = $this->DB->get_records_sql("SELECT * FROM {lbp_att_punc_history}
                                                 WHERE type = ? AND period = ?
                                                 AND courseid IS NULL AND studentid = ?
                                                 AND timestamp <= ?
                                                 ORDER BY timestamp DESC", array($params['type'], $params['period'], $params['studentid'], $unix), 0, 1);
        } else {
            $record = $this->DB->get_records_sql("SELECT * FROM {lbp_att_punc_history}
                                                 WHERE type = ? AND period = ?
                                                 AND courseid = ? AND studentid = ?
                                                 AND timestamp <= ?
                                                 ORDER BY timestamp DESC", array($params['type'], $params['period'], $params['courseid'], $params['studentid'], $unix), 0, 1);
        }

        if (is_array($record) && !empty($record))
        {
            $record = reset($record);
        }

        return ($record) ? $record->value : '-';

    }

    /**
     * Get multiple records
     * @param type $params
     * @return type
     */
    public function getRecords($params){
        return $this->getRecord($params, true);
    }

    /**
     * Get the updated date of a given record
     * @param type $params
     * @param type $multiple
     * @return type
     */
    public function getUpdatedDate($params, $multiple = false)
    {

        $params['studentid'] = $this->student->id;
        if (!isset($params['courseid'])) $params['courseid'] = null;

        if ($multiple){
            $records = $this->DB->get_records_select("lbp_att_punc", "studentid = :studentid AND courseid = :courseid
                                                                     AND type = :type AND period = :period", $params, "type");
            return $records;
        }
        else
        {
            $record = $this->DB->get_record("lbp_att_punc", $params);
            return ($record && $record->lastupdated > 0) ? date('D M Y', $record->lastupdated) : 'N.a';
        }

    }


    /**
     * Check if tracking is enabled
     * @return type
     */
    public function isTrackingEnabled()
    {
        $setting = $this->getSetting('tracking_enabled');
        return ($setting == 1);
    }

    /**
     * Get difference between last value and current value
     * @param type $lastValue
     * @param type $value#
     */
    public function getTrackingDifference($lastValue, $value)
    {

        if (!is_numeric($value)) return '-';

        $diff = $value - $lastValue;

        if ($diff > 0){
            return "<span class='elbp_good'>+{$diff}</span>";
        } elseif ($diff < 0) {
            return "<span class='elbp_bad'>{$diff}</span>";
        } else {
            return "<span><strong>{$diff}</strong></span>";
        }

    }

    /**
     * Get the name of the given course
     * @param type $course
     * @return type
     */
    public function getCourseName($course){

        $field = $this->getCourseNameField();
        return $course->$field;

    }

    /**
     * Get which field we are using for importing when refering to a course
     * @return type
     */
    public function getCourseNameField(){

        $setting = $this->getSetting('course_name');
        return ($setting) ? $setting : 'fullname';

    }

    /**
     * Get if we are using meta/child/both courses
     * @return type
     */
    public function getCourseType(){

        $setting = $this->getSetting('course_type');
        return ($setting) ? $setting : 'both';

    }

    /**
     * Get the expanded view
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()){

        $output = "";

        $TPL = new \ELBP\Template();
        $TPL->set("ATT", $this);


        try {
            $output .= $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/Attendance/tpl/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;

    }

    /**
     * Save the config data
     * @global type $MSGS
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {

        global $MSGS;

        if (isset($settings['submit_settings'])){

            // Course name
            if (!isset($settings['course_name']) || empty($settings['course_name']))
            {
                $settings['course_name'] = 'fullname';
            }

            $this->updateSetting('course_name', $settings['course_name']);


            // Course Type
            if (!isset($settings['course_type']) || empty($settings['course_type']))
            {
                $settings['course_type'] = 'both';
            }

            $this->updateSetting('course_type', $settings['course_type']);


            // Course Attendance Hook
            $this->updateSetting('search_children_if_no_course_data', $settings['search_children_if_no_course_data']);


            $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
            return true;

        }

        elseif (isset($settings['submit_tracking'])){

            // Enabled/Disabled
            if (isset($settings['tracking_enabled']))
            {
                $this->updateSetting('tracking_enabled', $settings['tracking_enabled']);
            }

            unset($settings['tracking_enabled']);
            unset($settings['submit_tracking']);

            if (isset($settings['track_days']) && $settings['track_days'] > 0)
            {
                $this->updateSetting('track_days', $settings['track_days']);
            }

            unset($settings['track_days']);

            if (isset($settings['tracking_period']) && $settings['tracking_period'] > 0)
            {
                $this->updateSetting('tracking_period', $settings['tracking_period']);
            }

            unset($settings['tracking_period']);

            if (isset($settings['tracking_start_date']) && !empty($settings['tracking_start_date']))
            {
                $startdate = strtotime($settings['tracking_start_date'] . " 00:00");
                $this->updateSetting('tracking_start_date', $startdate);
            }

            unset($settings['tracking_start_date']);


            $MSGS['success'] = get_string('trackingupdated', 'block_elbp');
            return true;

        }

        elseif (isset($settings['submit_types'])){

            $types = array();
            $codes = array();

            for ($i = 1; $i <= count($settings['type_names']); $i++)
            {
                // If boht are empty, skip
                if (empty($settings['type_names'][$i]) && empty($settings['type_codes'][$i])) continue;

                // If one is empty, error
                if (empty($settings['type_names'][$i]) || empty($settings['type_codes'][$i])){
                    $MSGS['errors'] = get_string('fieldsnotfilledin', 'block_elbp');
                    return false;
                }

                $types[] = $settings['type_names'][$i];
                $codes[] = $settings['type_codes'][$i];

            }

            // insert as lbp_settings
            $types = implode("|", $types);
            $codes = implode("|", $codes);
            $this->updateSetting('enabled_types', $types);
            $this->updateSetting('enabled_types_short', $codes);

            // >>BEDCOLL TODO: Update reporting elements in Attendance when you add/edit/remove types


            // Remove from settings so dont get inserted
            unset($settings['submit_types']);
            unset($settings['type_names']);
            unset($settings['type_codes']);

            $MSGS['success'] = get_string('typesupdated', 'block_elbp');
            return true;

        }

        elseif (isset($settings['submit_periods'])){

            $periods = array();

            for ($i = 1; $i <= count($settings['periods']); $i++)
            {
                // If empty, skip
                if (empty($settings['periods'][$i])) continue;
                $periods[] = $settings['periods'][$i];
            }

            // insert as lbp_settings
            $periods = implode("|", $periods);
            $this->updateSetting('enabled_periods', $periods);

            // Remove from settings so dont get inserted
            unset($settings['submit_periods']);
            unset($settings['periods']);

            $MSGS['success'] = get_string('periodsupdated', 'block_elbp');
            return true;

        }

        elseif (isset($settings['submit_summary_display'])){

            unset($settings['submit_summary_display']);

            if ($this->types)
            {
                foreach($this->types as $type)
                {
                    if (isset($settings['student_summary_display_'.$type]))
                    {
                        $disp = $settings['student_summary_display_'.$type];
                        $this->updateSetting('student_summary_display_'.$type, $disp);
                        unset($settings['student_summary_display_'.$type]);
                    }
                }
            }

            $MSGS['success'] = get_string('summarysettingsupdated', 'block_elbp');
            return true;

        }

        elseif (isset($settings['submit_import'])){

            if (isset($_FILES['file']) && !$_FILES['file']['error']){
                $result = $this->runImport($_FILES['file']);
                $MSGS['result'] = $result;
                return true;
            } else {
                return false;
            }

        }


        // Mappings first if they are there
        elseif (isset($settings['mis_map']))
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

        elseif (isset($settings['submitmistest_student']) && !empty($settings['testusername']))
        {
            $username = $settings['testusername'];
            $this->runTestMisQuery($username);
            return true;
        }



        // Student progress definitions
        $types = $this->getTypes();
        $periods = $this->getPeriods();

        // All combinations of Types and Periods
        if ($types)
        {

            foreach($types as $type)
            {

                $typeString = str_replace(' ', '_', $type);

                if ($periods)
                {

                    foreach($periods as $period)
                    {

                        $periodString = str_replace(' ', '_', $period);

                        $setting = 'student_progress_definitions_'.$typeString.'~'.$periodString;
                        $settingvalue = 'student_progress_definition_values_'.$typeString.'~'.$periodString;
                        $settingimportance = 'student_progress_definition_importance_'.$typeString.'~'.$periodString;

                        // If any of them aren't defined, set their value to 0 for disabled
                        if (!isset($settings[$setting])){
                            $settings[$setting] = 0;
                            $settings[$settingvalue] = 0;
                            $settings[$settingimportance] = 0;
                        }

                        // If the req ones don't have a valid number as their value, set to disabled
                        if (!isset($settings[$settingvalue]) || (int)$settings[$settingvalue] < 1) $settings[$settingvalue] = 0;
                        if (!isset($settings[$settingimportance]) || (int)$settings[$settingimportance] <= 0) $settings[$settingimportance] = 0;


                    }

                }

            }

        }

        parent::saveConfig($settings);
        $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
        return true;

    }

    /**
     * Get the fields required for MIS connection
     * @return type
     */
    private function getRequiredMisFields(){

        return array(
            "username",
            "course",
            "type",
            "period",
            "value"
        );

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
     * Load all the settings and requirements for the MIS connection
     */
    private function setupMisRequirements(){

        $this->mis_settings = array();

        // Settings
        $this->mis_settings['view'] = $this->getSetting('mis_view_name');
        $this->mis_settings['postconnection'] = $this->getSetting('mis_post_connection_execute');
        $this->mis_settings['mis_username_or_idnumber'] = $this->getSetting('mis_username_or_idnumber');
        $this->mis_settings['mis_course_shortname_or_idnumber'] = $this->getSetting('mis_course_shortname_or_idnumber');
        if (!$this->mis_settings['mis_username_or_idnumber']) $this->mis_settings['mis_username_or_idnumber'] = 'username';
        if (!$this->mis_settings['mis_course_shortname_or_idnumber']) $this->mis_settings['mis_course_shortname_or_idnumber'] = 'shortname';

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

        $course_shortname_or_idnumber = $this->getSetting("mis_course_shortname_or_idnumber");
        if (!$course_shortname_or_idnumber){
            $MSGS['errors'][] = 'mis_course_shortname_or_idnumber';
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
        $this->getAllFieldsFromMIS( array("username" => $username), true );
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

        // Put content into global $MSGS variable
        $MSGS['testoutput'] = $results;

    }


    /**
     * Since we will almost certainly be using a view/table with only one row of results, with all the different fields in
     * fields, rather than seperate rows like in our table in Moodle, load all of that info up into an array that we can
     * use
     */
    private function getAllFieldsFromMIS($options = null, $test = false)
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
        $fields = $this->plugin_connection->getAllMappingsForSelect(false);

        if ($fields)
        {
            foreach($fields as &$field)
            {
                $field = $this->connection->wrapValue($field);
            }
        }

        $fields = implode(", ", $fields);



        // Run it in the MIS settings page to test
        if ($test)
        {

            $query = $this->connection->query("SELECT {$fields} FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                           WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                           ORDER BY {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('course'))} ASC,
                                                    {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('type'))} ASC,
                                                    {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('period'))} ASC",
                                            array(
                                                $this->plugin_connection->getFieldMap('username') => $username,
                                           ));

        }
        else
        {



            $params = array(
                                $this->plugin_connection->getFieldMap('username') => $username,
                                $this->plugin_connection->getFieldMap('period') => $options['period'],
                                $this->plugin_connection->getFieldMap('type') => $options['type']
                           );

            $extraSQL = "";

            if (array_key_exists('course', $options))
            {

                if (is_null($options['course']))
                {
                    $extraSQL = " AND {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('course'))} IS NULL ";
                }
                else
                {
                    $extraSQL = " AND {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('course'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('course')} ";
                    $params[$this->plugin_connection->getFieldMap('course')] = $options['course'];
                }


            }


            // Run it normally
            $query = $this->connection->query("SELECT {$fields} FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                           WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                           AND {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('period'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('period')}
                                           AND {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('type'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('type')}
                                           {$extraSQL}
                                           ORDER BY {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('course'))} ASC,
                                                    {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('type'))} ASC,
                                                    {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('period'))} ASC",
                                           $params);

        }






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
     * Get the courses the given student is on, taking into account whether we want meta/child/both
     * @global type $DB
     * @return boolean
     */
    protected function getStudentsCourses()
    {

        if (!$this->student) return false;

        global $DB;

        $courses = $this->ELBPDB->getStudentsCourses($this->student->id);

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
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){

        $TPL = new \ELBP\Template();

        $courses = $this->getStudentsCourses();

        // Get avg course att & punc - This is done by courseID being set but period being NULL
        if ($courses)
        {
            foreach($courses as $course)
            {
                // Get the record for each enabled types
                if ($this->types)
                {
                    foreach($this->types as $type)
                    {
                        $course->avg[$type] = $this->getRecord( array("courseid" => $course->id, "type"=>$type, "period"=>"Total") );
                    }
                }

            }
        }

        $TPL->set("courses", $courses);
        $TPL->set("obj", $this);
        $TPL->set("types", $this->getTypes());

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/Attendance/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Handle ajax requests sent to the plugin
     * @param type $action
     * @param type $params
     * @param type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){

        switch($action)
        {

            case 'load_display_type':

                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                 // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                // Student's courses
                $courses = $this->getStudentsCourses();

                $trackingColspan = 1 + ( count($this->getTypes()) * count($this->getPeriods()) );
                $trackingDays = $this->getSetting('track_days');

                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("types", $this->getTypes())
                    ->set("periods", $this->getPeriods())
                    ->set("courses", $courses)
                    ->set("colspan", $trackingColspan)
                    ->set("trackingDays", $trackingDays);

                try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/Attendance/tpl/'.$params['type'].'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                
            break;

        }

    }


    /**
     * Install the plugin
     */
    public function install()
    {

        global $DB;

        $this->id = $this->createPlugin();
        $return = true && $this->id;

        // This is a core ELBP plugin, so the extra tables it requires are handled by the core ELBP install.xml


        // Default settings
        $settings = array();
        $settings['enabled_types'] = 'Attendance|Punctuality';
        $settings['enabled_types_short'] = 'A|P';
        $settings['enabled_periods'] = 'Last 7 Days|Last 28 Days|Total';
        $settings['student_summary_display_Attendance'] = 'Total';
        $settings['student_summary_display_Punctuality'] = 'Total';


        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $this->id, "setting" => $setting, "value" => $value));
        }

        // Insert hooks that we can use in other plugins
        $DB->insert_record("lbp_hooks", array("pluginid" => $this->id, "name" => "Averages"));
        $DB->insert_record("lbp_hooks", array("pluginid" => $this->id, "name" => "Course"));

        // Alert events
        $DB->insert_record("lbp_alert_events", array("pluginid" => $this->id, "name" => "Drops Below X", "description" => "Attendance/Punctuality/etc... drops below a specified percentage", "auto" => 1, "enabled" => 1));

        // Reporting data
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "Attendance", "getstringcomponent" => ""));
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "Punctuality", "getstringcomponent" => ""));

        return $return;
    }

    /**
     * Truncate the attendance tables and then uninstall the plugin
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

        $dbman = $DB->get_manager();

        $version = $this->version; # This is the current DB version we will be using to upgrade from

        if ($version < 2013101601)
        {

            // Define table lbp_att_punc_history to be created
            $table = new \xmldb_table('lbp_att_punc_history');

            // Adding fields to table lbp_att_punc_history
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('period', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('value', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timestamp', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_att_punc_history
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('sid_fk', XMLDB_KEY_FOREIGN, array('studentid'), 'user', array('id'));
            $table->add_key('cid_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));

            // Adding indexes to table lbp_att_punc_history
            $table->add_index('sct', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'courseid', 'type'));
            $table->add_index('sctp', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'courseid', 'type', 'period'));
            $table->add_index('st', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'type'));
            $table->add_index('stp', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'type', 'period'));

            // Conditionally launch create table for lbp_att_punc_history
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            $this->version = 2013101601;
            $this->updatePlugin();
            \mtrace("~~ Created lbp_att_punc_history table ~~");


        }

        if ($version < 2013102502)
        {

            // Reporting data
            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "Attendance", "getstringcomponent" => ""));
            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "Punctuality", "getstringcomponent" => ""));
            $this->version = 2013102502;
            $this->updatePlugin();
            \mtrace("## Inserted plugin_report_element data for plugin: {$this->title}");

        }


        return true;

    }

    /**
     * Get the current total att, punc data for a given student on a given course
     */
    public function _callHook_Course($obj, $params)
    {

        global $DB;

        if (!$this->isEnabled()) return false;
        if (!isset($obj->student->id)) return false;
        if (!isset($params['courseID'])) return false;

        // Load student
        $this->loadStudent($obj->student->id);
        $this->loadCourse($params['courseID']);
        $studentID = $this->loadStudent($obj->student->id);
        $courses = $this->getStudentsCourses($studentID);

        $found = false;
        $types = $this->getTypes();
        $return = array();
        $return['types'] = $types;
        $return['couseData'] = array();
        $return['values'] = array();

        if(!empty($courses)){
            foreach($courses as $course){
                $courseID = $course->id;
                if($types){
                    foreach($types as $type){
                        $record = $this->getRecord( array("type"=>$type, "courseid"=>$courseID, "period"=>"Total") );
                        $course->$type = $record;
                        if ($record != '-'){
                            $found = true;
                        }
                    }
                }
                $return['values'][] = $course;

            }

        }else{
            if ($types)
            {
                foreach($types as $type)
                {
                    $record = $this->getRecord( array("type"=>$type, "courseid"=>$this->course->id, "period"=>"Total") );
                    $return['values'][$type] = $record;
                    if ($record != '-'){
                        $found = true;
                    }
                }
            }
        }
        // If we didn't find any attendance data for this course, see if it is a meta course with children
        // And if so, try and find data from those
        if (!$found && $this->getSetting('search_children_if_no_course_data') == 1)
        {

            // Child Courses
            $children = $this->ELBPDB->getChildCourses( $this->course->id );
            $foundInChild = false;

            if ($children && $types)
            {

                foreach($children as $child)
                {

                    if (!$foundInChild)
                    {

                        // Reset values - Stop at the first one we find with data
                        $return['values'] = array();

                        foreach($types as $type)
                        {

                            $record = $this->getRecord( array("type"=>$type, "courseid"=>$child->id, "period"=>"Total") );
                            $return['values'][$type] = $record;

                            if ($record != '-')
                            {
                                $foundInChild = true;
                            }

                        }

                    }

                }

            }



        }

        return $return;

    }

    /**
     * Get the current Avg attendance data to put into a New Tutorial/Target/whatever
     * This differs from the _retrieveHook in that the _callHook goes and gets live data to be inserted as an attribute of something else (e.g. tutorial)
     * Whereas the _retreiveHook goes and gets that static data that was already stored as an attribute, and thus needs things like table & field specified to find it
     */
    public function _callHook_Averages($obj, $params)
    {

        global $DB;

        if (!$this->isEnabled()) return false;
        if (!isset($obj->student->id)) return false;

        // Load student
        $this->loadStudent($obj->student->id);

        // Get types
        $types = $this->getTypes();
        $periods = $this->getPeriods();


        $avgString = get_string('average', 'block_elbp');

        $return = array();
        $return['periods'] = $periods;
        $return['types'] = $types;
        $return['values'] = array();


        if ($types)
        {

            foreach($types as $type)
            {

                // If periods, do them as well
                if ($periods)
                {

                    foreach($periods as $period)
                    {

                        $record = $this->getRecord( array("type"=>$type, "courseid"=>null, "period"=>$period) );
                        $return['values'][$type][$period] = $record;

                    }

                }
                else
                {
                    $record = $this->getRecord( array("type"=>$type, "courseid"=>null, "period"=>null) );
                    $return['values'][$type] = $record;
                }


            }
        }


        return $return;

    }

    /**
     * The data is not actually being retrieved here, as it will be in the attributes table for whatever plugin
     * All this is doing is returning the name of the attributes which we will want to return, e.g. Average Attendance, Average Punctuality, etc...
     */
    public function _retrieveHook_Averages()
    {

        if (!$this->isEnabled()) return false;

        // Get types
        $types = $this->getTypes();
        $periods = $this->getPeriods();

        $return = array();
        $return['periods'] = $periods;
        $return['types'] = $types;

        return $return;

    }

     /**
     * The data is not actually being retrieved here, as it will be in the attributes table for whatever plugin
     * All this is doing is returning the name of the attributes which we will want to return, e.g. Average Attendance, Average Punctuality, etc...
     */
    public function _retrieveHook_Course()
    {

        if (!$this->isEnabled()) return false;

        // Get types
        $types = $this->getTypes();
        $periods = $this->getPeriods();

        $return = array();
        $return['periods'] = $periods;
        $return['types'] = $types;

        return $return;

    }


    /**
     * Run the automated event for if attendance, pucntuality, etc... drops below a given percentage
     * @param $event the event record from the DB (just pass it in sow e don't have to get it again)
     * @return int Number of users affected
     */
    public function AutomatedEvent_drops_below_x($event)
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

        // Loop through them
        if ($userEvents)
        {

            $studentValues = array(); # Values of attendance from DB
            $userArray = array(); # Users from DB
            $processedStudents = array(); # Students we have got values for and alerted for each user, so as not to repeat

            foreach($userEvents as $userEvent)
            {

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


                // Check that they have the required attributes defined - type, period and value
                $getType = $DB->get_record("lbp_alert_attributes", array("useralertid" => $userEvent->id, "field" => "type"));
                if (!$getType || $getType->value == '') continue;
                $type = $getType->value;


                $getPeriod = $DB->get_record("lbp_alert_attributes", array("useralertid" => $userEvent->id, "field" => "period"));
                if (!$getPeriod || $getPeriod->value == '') continue;
                $period = $getPeriod->value;

                $getValue = $DB->get_record("lbp_alert_attributes", array("useralertid" => $userEvent->id, "field" => "value"));
                if (!$getValue || !ctype_digit($getValue->value)) continue;
                $value = $getValue->value;

                # Optional - courseid
                $getCourse = $DB->get_record("lbp_alert_attributes", array("useralertid" => $userEvent->id, "field" => "course"));
                $courseID = ($getCourse) ? $getCourse->value : 0;
                if ($courseID > 0){
                    $attrCourse = $ELBPDB->getCourse( array("type" => "id", "val" => $courseID) );
                }

                // So we've got the period and the value, let's continue



                // If the userEvent is for an individual student, great, let's just check theirs
                if (!is_null($userEvent->studentid))
                {

                    // First thing's first, make sure we haven't had an alert for this exact thing recently
                    // Use the value in Alert::history_time to define how long ago we should check, default is 1 week
                    $recordCourse = ($courseID == 0) ? null : $courseID;

                    $params = array(
                        "userID" => $user->id,
                        "studentID" => $userEvent->studentid,
                        "eventID" => $userEvent->eventid,
                        "attributes" => array(
                            "type" => $type,
                            "period" => $period
                        )
                    );

                    if ($courseID > 0){
                        $params['attributes']['course'] = $courseID;
                    }

                    // If this returns true that means we have sent this exact alert within the last week, so skip
                    $checkHistory = \ELBP\Alert::checkHistory( $params );
                    if ($checkHistory){
                        continue;
                    }

                    // If we have already processed this student for this user, continue
                    if (isset($processedStudents[$user->id][$courseID][$period]) &&
                       in_array($userEvent->studentid, $processedStudents[$user->id][$courseID][$period])){
                       continue;
                    }

                    // Continue only if we succeed in loading this student
                    if ($this->loadStudent($userEvent->studentid))
                    {

                        // If we've already got this value, get from array, else get from DB and put into the array
                        if (isset($studentValues[$this->student->id][$courseID][$period]))
                        {
                            $record = $studentValues[$this->student->id][$courseID][$period];
                        }
                        else
                        {
                            $recordCourse = ($courseID == 0) ? null : $courseID;
                            $record = $this->getRecord( array("type" => $type, "period" => $period, "courseid" => $recordCourse) );
                            if ($record == '-') $record = false;

                            if (!$record) continue; # No record found - skip

                            $record = (int)$record; # Convert to int

                            if (!isset($studentValues[$this->student->id])) $studentValues[$this->student->id] = array();
                            if (!isset($studentValues[$this->student->id][$courseID])) $studentValues[$this->student->id][$courseID] = array();
                            $studentValues[$this->student->id][$courseID][$period] = $record;
                        }

                        // If the record from the DB has dropped below the value we wanted to check, send the alert
                        if ($record < $value)
                        {

                            // I'm not sure i've even going to do SMS alerts, so for now we'll just hard code EmailAlert here
                            $subject = $this->title . " :: ".get_string(strtolower($type), 'block_elbp')." :: " . fullname($this->student) . " ({$this->student->username})";
                            $content = get_string('student', 'block_elbp') . ": " . fullname($this->student) . " ({$this->student->username})\n";
                            if ($courseID > 0 && $attrCourse){
                                $content .= get_string('course') . ": " . $attrCourse->fullname . "\n";
                            }
                            $content .= get_string('type', 'block_elbp') . ": " . get_string(strtolower($type), 'block_elbp') . "\n" .
                                        get_string('period', 'block_elbp') . ": " . $period . "\n" .
                                        get_string('alertvalue:below', 'block_elbp') . ": " . $value . "%\n" .
                                        get_string(strtolower($type), 'block_elbp') . ": " . $record . "%\n\n" .
                                        str_replace("%event%", $userEvent->name, get_string('alerts:receieving:student', 'block_elbp')) . ": " . fullname($this->student) . " ({$this->student->username})";

                            // Log the history of this alert, so we don't do the exact same one tomorrow/whenever next run
                            $params = array(
                                "userID" => $user->id,
                                "studentID" => $this->student->id,
                                "eventID" => $userEvent->eventid,
                                "attributes" => array(
                                    "type" => $type,
                                    "period" => $period,
                                    "value" => $record
                                )
                            );

                            if ($courseID > 0) $params['attributes']['course'] = $courseID;

                            $historyID = \ELBP\Alert::logHistory($params);

                            // Now queue it, sending the history ID as well so we can update when actually sent
                            $EmailAlert->queue("email", $user, $subject, $content, nl2br($content), $historyID);
                            $cnt++;

                        }


                    }

                    // Append student so we don't try and get the same record again
                    if (!isset($processedStudents[$user->id])) $processedStudents[$user->id] = array();
                    if (!isset($processedStudents[$user->id][$courseID])) $processedStudents[$user->id][$courseID] = array();
                    if (!isset($processedStudents[$user->id][$courseID][$period])) $processedStudents[$user->id][$courseID][$period] = array();
                    $processedStudents[$user->id][$courseID][$period][] = $userEvent->studentid;

                }



                // CHeck a whole course
                elseif (!is_null($userEvent->courseid))
                {

                    $course = $ELBPDB->getCourse(array("type" => "id", "val" => $userEvent->courseid));
                    if (!$course) continue;

                    // Find all the students on that course
                    $students = $ELBPDB->getStudentsOnCourse($userEvent->courseid);
                    if ($students)
                    {
                        foreach($students as $student)
                        {


                            // First thing's first, make sure we haven't had an alert for this exact thing recently
                            // Use the value in Alert::history_time to define how long ago we should check, default is 1 week
                            $params = array(
                                "userID" => $user->id,
                                "studentID" => $student->id,
                                "eventID" => $userEvent->eventid,
                                "attributes" => array(
                                    "type" => $type,
                                    "period" => $period,
                                    "course" => $userEvent->courseid
                                )
                            );

                            // If this returns true that means we have sent this exact alert within the last week, so skip
                            $checkHistory = \ELBP\Alert::checkHistory( $params );
                            if ($checkHistory){
                                continue;
                            }


                           // If we have already processed this student for this user, continue
                            if (isset($processedStudents[$user->id][$userEvent->courseid][$period]) &&
                               in_array($student->id, $processedStudents[$user->id][$userEvent->courseid][$period])){
                               continue;
                            }

                            // Continue only if we succeed in loading this student
                            if ($this->loadStudent($student->id))
                            {

                                // If we've already got this value, get from array, else get from DB and put into the array
                                if (isset($studentValues[$this->student->id][$userEvent->courseid][$period]))
                                {
                                    $record = $studentValues[$this->student->id][$userEvent->courseid][$period];
                                }
                                else
                                {
                                    $record = $this->getRecord( array("type" => $type, "period" => $period, "courseid" => $userEvent->courseid) );
                                    if ($record == '-') $record = false;

                                    if (!$record) continue; # Skip

                                    $record = (int)$record;

                                    if (!isset($studentValues[$this->student->id])) $studentValues[$this->student->id] = array();
                                    if (!isset($studentValues[$this->student->id][$userEvent->courseid])) $studentValues[$this->student->id][$userEvent->courseid] = array();
                                    $studentValues[$this->student->id][$userEvent->courseid][$period] = $record;

                                }

                                // If the record from the DB has dropped below the value we wanted to check, send the alert
                                if ($record < $value)
                                {

                                    // I'm not sure i've even going to do SMS alerts, so for now we'll just hard code EmailAlert here
                                    $subject = $this->title . " :: ".get_string(strtolower($type), 'block_elbp')." :: " . fullname($this->student) . " ({$this->student->username})";
                                    $content = get_string('course') . ": {$course->fullname} ({$course->shortname})\n" .
                                               get_string('student', 'block_elbp') . ": " . fullname($this->student) . " ({$this->student->username})\n" .
                                               get_string('type', 'block_elbp') . ": " . get_string(strtolower($type), 'block_elbp') . "\n" .
                                               get_string('period', 'block_elbp') . ": " . $period . "\n" .
                                               get_string('alertvalue:below', 'block_elbp') . ": " . $value . "%\n" .
                                               get_string(strtolower($type), 'block_elbp') . ": " . $record . "%\n\n" .
                                               str_replace("%event%", $userEvent->name, get_string('alerts:receieving:course', 'block_elbp')) . ": {$course->fullname}";

                                    // Log the history of this alert, so we don't do the exact same one tomorrow/whenever next run
                                    $params = array(
                                        "userID" => $user->id,
                                        "studentID" => $this->student->id,
                                        "eventID" => $userEvent->eventid,
                                        "attributes" => array(
                                            "type" => $type,
                                            "period" => $period,
                                            "value" => $record,
                                            "course" => $userEvent->courseid
                                        )
                                    );

                                    $historyID = \ELBP\Alert::logHistory($params);

                                    $EmailAlert->queue("email", $user, $subject, $content, nl2br($content), $historyID);
                                    $cnt++;

                                }


                            }

                            // Append student so we don't try and get the same record again
                            if (!isset($processedStudents[$user->id])) $processedStudents[$user->id] = array();
                            if (!isset($processedStudents[$user->id][$userEvent->courseid])) $processedStudents[$user->id][$userEvent->courseid] = array();
                            if (!isset($processedStudents[$user->id][$userEvent->courseid][$period])) $processedStudents[$user->id][$userEvent->courseid][$period] = array();
                            $processedStudents[$user->id][$userEvent->courseid][$period][] = $student->id;

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

                            // This will be NULL, as its an overall, not for a specific course, but using same code as from student bit above
                            $recordCourse = ($courseID == 0) ? null : $courseID;

                            // First thing's first, make sure we haven't had an alert for this exact thing recently
                            // Use the value in Alert::history_time to define how long ago we should check, default is 1 week
                            $params = array(
                                "userID" => $user->id,
                                "studentID" => $student->id,
                                "eventID" => $userEvent->eventid,
                                "attributes" => array(
                                    "type" => $type,
                                    "period" => $period
                                )
                            );

                            // If this returns true that means we have sent this exact alert within the last week, so skip
                            $checkHistory = \ELBP\Alert::checkHistory( $params );
                            if ($checkHistory){
                                continue;
                            }

                            // If we have already processed this student for this user, continue
                            if (isset($processedStudents[$user->id][$userEvent->courseid][$period]) &&
                               in_array($student->id, $processedStudents[$user->id][$userEvent->courseid][$period])){
                               continue;
                            }

                            // Continue only if we succeed in loading this student
                            if ($this->loadStudent($student->id))
                            {

                                // If we've already got this value, get from array, else get from DB and put into the array
                                if (isset($studentValues[$this->student->id][$courseID][$period]))
                                {
                                    $record = $studentValues[$this->student->id][$courseID][$period];
                                }
                                else
                                {
                                    $recordCourse = ($courseID == 0) ? null : $courseID;
                                    $record = $this->getRecord( array("type" => $type, "period" => $period, "courseid" => $recordCourse) );
                                    if ($record == '-') $record = false;

                                    if (!$record) continue; # No record found - skip

                                    $record = (int)$record; # Convert to int

                                    if (!isset($studentValues[$this->student->id])) $studentValues[$this->student->id] = array();
                                    if (!isset($studentValues[$this->student->id][$courseID])) $studentValues[$this->student->id][$courseID] = array();
                                    $studentValues[$this->student->id][$courseID][$period] = $record;
                                }


                                // If the record from the DB has dropped below the value we wanted to check, send the alert
                                if ($record < $value)
                                {

                                    // I'm not sure i've even going to do SMS alerts, so for now we'll just hard code EmailAlert here
                                    $subject = $this->title . " :: ".get_string(strtolower($type), 'block_elbp')." :: " . fullname($this->student) . " ({$this->student->username})";
                                    $content = get_string('student', 'block_elbp') . ": " . fullname($this->student) . " ({$this->student->username})\n";
                                    if ($courseID > 0 && $attrCourse){
                                        $content .= get_string('course') . ": " . $attrCourse->fullname . "\n";
                                    }
                                    $content .= get_string('type', 'block_elbp') . ": " . get_string(strtolower($type), 'block_elbp') . "\n" .
                                                get_string('period', 'block_elbp') . ": " . $period . "\n" .
                                                get_string('alertvalue:below', 'block_elbp') . ": " . $value . "%\n" .
                                                get_string(strtolower($type), 'block_elbp') . ": " . $record . "%\n\n" .
                                                str_replace("%event%", $userEvent->name, get_string('alerts:receieving:'.$userEvent->mass, 'block_elbp'));

                                    // Log the history of this alert, so we don't do the exact same one tomorrow/whenever next run
                                    $params = array(
                                        "userID" => $user->id,
                                        "studentID" => $this->student->id,
                                        "eventID" => $userEvent->eventid,
                                        "attributes" => array(
                                            "type" => $type,
                                            "period" => $period,
                                            "value" => $record
                                        )
                                    );

                                    if ($courseID > 0){
                                        $params['attributes']['course'] = $courseID;
                                    }

                                    $historyID = \ELBP\Alert::logHistory($params);

                                    // Now queue it, sending the history ID as well so we can update when actually sent
                                    $EmailAlert->queue("email", $user, $subject, $content, nl2br($content), $historyID);
                                    $cnt++;

                                }


                            }


                            // Append student so we don't try and get the same record again
                            if (!isset($processedStudents[$user->id])) $processedStudents[$user->id] = array();
                            if (!isset($processedStudents[$user->id][$courseID])) $processedStudents[$user->id][$courseID] = array();
                            if (!isset($processedStudents[$user->id][$courseID][$period])) $processedStudents[$user->id][$courseID][$period] = array();
                            $processedStudents[$user->id][$courseID][$period][] = $student->id;

                        }
                    }

                }


            }
        }


        return $cnt;

    }

    /**
     * Call the event if the student's type/period drops below a certain amount
     * @param type $event
     * @param type $userEvents
     * @return string
     */
    public function _getEventRequiredScripts_drops_below_x($event, $userEvents)
    {


        $types = $this->getTypes();
        $periods = $this->getPeriods();

        $typeOutput = "";
        $periodOutput = "";

        foreach($types as $type)
        {
            $typeOutput .= "<option value=\"{$type}\">{$type}</option>";
        }

        foreach($periods as $period)
        {
            $periodOutput .= "<option value=\"{$period}\">{$period}</option>";
        }

        $num = count($userEvents);

        $output = "<script>

        var num = {$num};

        function clone_drops_below_x_row(el){

            num++;

            var newRow = '';
            newRow += '<tr class=\"drops_below_x_appended_row\">';

                newRow += '<td></td>';
                newRow += '<td>{$event->name}</td>';
                newRow += '<td class=\"align-middle\">';

                    newRow += '<select style=\"width:35%;margin-bottom:0px;\" name=\"alert_attributes[{$event->id}]['+num+'][type]\">';
                        newRow += '{$typeOutput}';
                    newRow += '</select>';

                    newRow += '&nbsp;';

                    newRow += '<select style=\"width:35%;margin-bottom:0px;\" name=\"alert_attributes[{$event->id}]['+num+'][period]\">';
                        newRow += '{$periodOutput}';
                    newRow += '</select>';

                    newRow += '&nbsp;';

                    newRow += '<input type=\"text\" style=\"width:10%;margin-bottom:0px;\" placeholder=\"%\" name=\"alert_attributes[{$event->id}]['+num+'][value]\" />';

                    newRow += '&nbsp;<a href=\"#\" onclick=\"$($(this).parents(\'tr\')[0]).remove();return false;\" title=\"".get_string('removerow', 'block_elbp')."\"><i class=\"icon-remove\"></i></a> ';

                newRow += '</td>';

                newRow += '<td> </td>';

            newRow += '</tr>';

            // See if we appended one before
            if ( $('.drops_below_x_appended_row').length > 0 ){
                $('.drops_below_x_appended_row:last').after( newRow );
            } else {
                $($(el).parents(\"tr\")[0]).after( newRow );
            }

        }
        </script>";

        return $output;

    }


    /**
     * This method is called by the dashboard page form to bring in the extra form elements to define custom values
     */
    public function _getEventCustomValueFormInfo_drops_below_x($event, $userEvent = false, $num = 0){

        global $USER, $DB;

        $output = "";
        $typeOutput = "";
        $periodOutput = "";

        $types = $this->getTypes();
        $periods = $this->getPeriods();





        $userEventType = false;
        $userEventPeriod = false;
        $userEventValue = false;

        if ($userEvent)
        {
            $userEventType = $DB->get_record("lbp_alert_attributes", array("useralertid" => $userEvent->id, "field" => "type"), "value");
            if ($userEventType) $userEventType = $userEventType->value;

            $userEventPeriod = $DB->get_record("lbp_alert_attributes", array("useralertid" => $userEvent->id, "field" => "period"), "value");
            if ($userEventPeriod) $userEventPeriod = $userEventPeriod->value;

            $userEventValue = $DB->get_record("lbp_alert_attributes", array("useralertid" => $userEvent->id, "field" => "value"), "value");
            if ($userEventValue) $userEventValue = $userEventValue->value;

        }

        $output .= "<select style='width:35%;margin-bottom:0px;' name='alert_attributes[{$event->id}][{$num}][type]'>";
            foreach($types as $type)
            {
                $sel = ($userEventType && $userEventType == $type) ? 'selected' : '';
                $output .= "<option value='{$type}' {$sel} >{$type}</option>";
            }
        $output .= "</select>";

        $output .= "&nbsp;";

        $output .= "<select style='width:35%;margin-bottom:0px;' name='alert_attributes[{$event->id}][{$num}][period]'>";
            foreach($periods as $period)
            {
                $sel = ($userEventPeriod && $userEventPeriod == $period) ? 'selected' : '';
                $output .= "<option value='{$period}' {$sel} >{$period}</option>";
            }
        $output .= "</select>";

        $output .= "&nbsp;";

            $output .= "<input type='text' style='width:10%;margin-bottom:0px;' placeholder='%' name='alert_attributes[{$event->id}][{$num}][value]' value='{$userEventValue}' />";
            $output .= "&nbsp;";

            if ($num == 0)
            {
                $output .= "<span class='add_more'>";
                    $output .= "<a href='#' onclick='clone_drops_below_x_row( $(this) );return false;' title='".get_string('addanotherrow', 'block_elbp')."'><i class='icon-plus'></i></a>";
                    $output .= "&nbsp;&nbsp;";
                $output .= "</span>";
            }

        $output .= "<a href='#' onclick='$($(this).parents(\"tr\")[0]).remove();return false;' title='".get_string('removerow', 'block_elbp')."'><i class='icon-remove'></i></a>";


        return $output;

    }






    /**
     * Get the unix timestamp of the date to start tracking from
     * @return boolean
     */
    public function getTrackingFromDate(){

        if (!$this->isTrackingEnabled()) return false;

        $period = $this->getSetting('tracking_period');
        $defaultstart = $this->getSetting('tracking_start_date');
        $now = time();

        $periodUnix = $period * 86400;

        if ( ($now - $periodUnix) < $defaultstart ){
            $start = $defaultstart;
        } else {
            $start = $now - $periodUnix;
        }

        return $start;

    }

    /**
     * Get the required headers for the import csv
     * @return string
     */
    private function getImportCsvHeaders(){
        $headers = array();
        $headers[] = 'username';
        $headers[] = 'type';
        $headers[] = 'period';
        $headers[] = 'courseshortname';
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

        global $CFG;

        $file = $CFG->dataroot . '/ELBP/' . $this->name . '/templates/template.csv';
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

        global $CFG, $DB;

        $file = $CFG->dataroot . '/ELBP/' . $this->name . '/templates/example.csv';
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
        $types = $this->getTypes();
        $periods = $this->getPeriods();


        $userField = $this->getSetting('import_user_field');
        if (!$userField){
            $userField = 'username';
        }

        $courseField = $this->getSetting('import_course_field');
        if (!$courseField){
            $courseField = 'shortname';
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
                $data[] = $types[array_rand($types)];
                $data[] = $periods[array_rand($periods)];

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
                    // Overall, not for a course
                    $data[] = '';
                }

                $data[] = mt_rand(1, 100);
                fputcsv($fh, $data);


            }

        }



        fclose($fh);
        return $code;

    }



    /**
     * Run the data import to import CSV into database
     * @global \ELBP\Plugins\type $DB
     * @param type $file If not from cron this will be a $_FILES file, otherwise we'll mimic that with the path
     * @param bool $fromCron
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

        $types = $this->getTypes();
        $periods = $this->getPeriods();

        // Headers are okay, so let's rock and roll
        $i = 1;
        $validUsernames = array(); // Save us checking same username multiple times - saves processing time
        $validCourses = array(); // Save us checking same course multiple times - saves processing time
        $time = time();
        $processed = array();
        $errorCnt = 0;

        // Is tracking enabled?
        if ($this->isTrackingEnabled())
        {

            $trackEvery = $this->getSetting('track_days');
            if (!$trackEvery || $trackEvery < 1){
                return array('success' => false, 'error' => get_string('attendance:import:notrackevery', 'block_elbp'));
            }

            // If we are tracking changes every 7 days for example, this would be a unix timestamp
            // for 00:00:00 7 days ago, so we could then compare the unix timestamp of the last tracking
            // update for each user, then we can update only those whose last tracking update was before this
            $lastTrackUnix = strtotime("-{$trackEvery} days 00:00:00", $time);

        }


        // Which field are we looking at?
        $courseField = $this->getSetting('import_course_field');
        if (!$courseField){
            $courseField = 'shortname';
        }

        $userField = $this->getSetting('import_user_field');
        if (!$userField){
            $userField = 'username';
        }

        while( ($row = fgetcsv($fh)) !== false )
        {

            $i++;

            $row = array_map('trim', $row);

            $username = $row[0];
            $type = $row[1];
            $period = $row[2];
            $course = $row[3];
            $value = $row[4];

            // First check that all columns have something in (except courseshortname, that can be empty)
            $emptycnt = 0;
            for($j = 0; $j < count($headers); $j++){
                if (elbp_is_empty($row[$j])){
                    $emptycnt++;
                }
            }

            // If more than 1 is empty, there is a problem, as only course can be empty
            if ($emptycnt > 1 || ($emptycnt == 1 && !empty($course))){
                $output .= "[{$i}] " . get_string('import:colsempty', 'block_elbp') . " : (".implode(',', $row).")<br>";
                $errorCnt++;
                continue;
            }

            // Check username exists
            $user = false;

            // if we haven';t come across this username, check the db to make sure it exists
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
                // We have comr across this username, so just get the id frmo the array
                $user = $validUsernames[$username];
            }

            // Otherwise it IS in validUsernames, so we already know its fine - carry on

            // Now check type is valid
            if (!in_array($type, $types)){
                $output .= "[{$i}] " . get_string('nosuchtype', 'block_elbp') . " : {$type}<br>";
                $errorCnt++;
                continue;
            }


            // Now check period is valid
            if (!in_array($period, $periods)){
                $output .= "[{$i}] " . get_string('nosuchperiod', 'block_elbp') . " : {$period}<br>";
                $errorCnt++;
                continue;
            }

            // Course is optional, if it is set, then check if its valid
            $courseRecord = false;

            if (!empty($course)){

                // If we haven't come across this course in the csv yet, check it exists
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
                    // We have come across it, so we know it is fine, therefore just get the courseid frmo the array
                    $courseRecord = $validCourses[$course];
                }

            }

            // And finally check that the value is a int
            if (!ctype_digit($value)){
                $output .= "[{$i}] " . get_string('import:valuenotdigit', 'block_elbp') . " : {$value}<br>";
                $errorCnt++;
                continue;
            }



            // At this point everything is okay, so let's actually import the data
            $courseID = (isset($courseRecord) && $courseRecord) ? $courseRecord->id : null;

            $record = $DB->get_record("lbp_att_punc", array("studentid" => $user->id, "courseid" => $courseID, "type" => $type, "period" => $period));

            // Record for this data already exists, so update it with new value
            if ($record)
            {

                $output .= "[{$i}] " . get_string('import:recordexists', 'block_elbp') . "<br>";

                // Is tracking enabled?
                if ($this->isTrackingEnabled())
                {

                    // Check when the last tracking update was for this student
                    $lastTrackingUpdateUser = $this->getSetting("last_tracking_update", $record->studentid);

                    // Only update the tracking for this user if they have no record of a last tracking
                    // Or if the last time theirs was updated was more than x number of days ago, where x is the number of days between trackings
                    if (!$lastTrackingUpdateUser || $lastTrackingUpdateUser <= $lastTrackUnix)
                    {

                        // Insert history for tracking, if we haven't already done this
                        if (!isset($processed[$record->studentid.'_'.$record->courseid.'_'.$record->type.'_'.$record->period]))
                        {

                            $history = new \stdClass();
                            $history->studentid = $record->studentid;
                            $history->courseid = $record->courseid;
                            $history->type = $record->type;
                            $history->period = $record->period;
                            $history->value = $record->value;
                            $history->timestamp = $record->lastupdated;
                            $DB->insert_record("lbp_att_punc_history", $history);
                            $processed[$record->studentid.'_'.$record->courseid.'_'.$record->type.'_'.$record->period] = true;
                            $output .= "[{$i}] " . get_string('import:insertedhistory', 'block_elbp') . "<br>";

                            // Update setting
                            $this->updateSetting("last_tracking_update", $time, $record->studentid);

                        }

                    }

                }

                $record->value = $value;
                $record->lastupdated = $time;
                $DB->update_record("lbp_att_punc", $record);
                $output .= "[{$i}] " . get_string('import:updatedrecord', 'block_elbp') . " - ".fullname($user)." ({$user->username}) [".implode(',', $row)."]<br>";
            }
            else
            {

                // Insert record
                $obj = new \stdClass();
                $obj->studentid = $user->id;
                $obj->courseid = $courseID;
                $obj->type = $type;
                $obj->period = $period;
                $obj->value = $value;
                $obj->lastupdated = $time;
                $DB->insert_record("lbp_att_punc", $obj);
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
     * Run the cron for this plugin
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
            mtrace("Cron settings are missing. (Type:{$type})(Hour:{$hour})(Min:{$min})(File:{$file})");
            return false;
        }

        mtrace("Last run: {$cronLastRun}");
        mtrace("Current time: " . date('H:i', $now) . " ({$now})");

        switch($type)
        {

            // Run every x hours, y minutes
            case 'every':

                $diff = 60 * $min;
                $diff += (3600 * $hour);

                mtrace("Cron set to run every {$hour} hours, {$min} mins");

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

                    mtrace("Cron set to run...");
                    $result = $this->runImport($file, true);
                    if ($result['success'])
                    {
                        $result['output'] = str_replace("<br>", "\n", $result['output']);
                        mtrace($result['output']);

                        // Now we have finished, delete the file
                        if ( unlink($file) === true ){
                            mtrace("Deleted file: " . $file);
                        } else {
                            mtrace("Could not delete file: " . $file);
                        }

                    }
                    else
                    {
                        mtrace('Error: ' . $result['error']);
                    }

                    // Set last run to now
                    $this->updateSetting('cron_last_run', $now);

                }
                else
                {
                    mtrace("Cron not ready to run");
                }

            break;


            // Run at a specific time every day
            case 'specific':

                if ($hour < 10) $hour = "0".$hour;
                if ($min < 10) $min = "0".$min;

                $hhmm = $hour . $min;
                $nowHHMM = date('Hi');

                $unixToday = strtotime("{$hour}:{$min}:00");

                mtrace("Cron set to run at {$hour}:{$min}, every day");

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
                    mtrace("Cron set to run...");
                    $result = $this->runImport($file, true);
                    if ($result['success'])
                    {
                        $result['output'] = str_replace("<br>", "\n", $result['output']);
                        mtrace($result['output']);

                        // Now we have finished, delete the file
                        if ( unlink($file) === true ){
                            mtrace("Deleted file: " . $file);
                        } else {
                            mtrace("Could not delete file: " . $file);
                        }

                    }
                    else
                    {
                        mtrace('Error: ' . $result['error']);
                    }

                    // Set last run to now
                    $this->updateSetting('cron_last_run', $now);


                }
                else
                {
                    mtrace("Cron not ready to run");
                }


            break;

        }


        return true;

    }



    /**
     * Attendance does support overall student progress bar
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

        $types = $this->getTypes();
        $periods = $this->getPeriods();

        $output = "";

        $output .= "<table class='student-progress-definitions'>";

        $output .= "<tr>";

            $output .= "<th></th>";
            $output .= "<th>".get_string('value', 'block_elbp')."</th>";
            $output .= "<th>".get_string('description')."</th>";
            $output .= "<th>".get_string('importance', 'block_elbp')."</th>";

        $output .= "</tr>";

        // All combinations of Types and Periods
        if ($types)
        {

            foreach($types as $type)
            {

                $typeString = str_replace(' ', '_', $type);

                if ($periods)
                {

                    foreach($periods as $period)
                    {

                        $periodString = str_replace(' ', '_', $period);

                        $setting = 'student_progress_definitions_'.$typeString.'~'.$periodString;
                        $settingvalue = 'student_progress_definition_values_'.$typeString.'~'.$periodString;
                        $settingimportance = 'student_progress_definition_importance_'.$typeString.'~'.$periodString;

                        $str = get_string('studentprogressdefinitions:attpunc', 'block_elbp');
                        $str = str_replace('%t%', $type, $str);
                        $str = str_replace('%p%', $period, $str);

                        $output .= "<tr>";
                            $chk = ($this->getSetting($setting) == 1) ? 'checked' : '';
                            $output .= "<td><input type='checkbox' name='{$setting}' value='1' {$chk} /></td>";
                            $output .= "<td><input type='text' class='elbp_small' name='{$settingvalue}' value='{$this->getSetting($settingvalue)}' /></td>";
                            $output .= "<td>".$str."</td>";
                            $output .= "<td><input type='number' class='elbp_smallish' name='{$settingimportance}' value='{$this->getSetting($settingimportance)}' min='0.5' step='0.5' /></td>";
                        $output .= "</tr>";

                    }

                }

            }

        }


        $output .= "</table>";

        return $output;

    }

    /**
     * Calculate Attendance bits of overall student progress
     * @return type
     */
     public function calculateStudentProgress(){

        $max = 0;
        $num = 0;
        $theInfo = array();

        // Student progress definitions
        $types = $this->getTypes();
        $periods = $this->getPeriods();

        // All combinations of Types and Periods
        if ($types)
        {

            foreach($types as $type)
            {

                $typeString = str_replace(' ', '_', $type);

                if ($periods)
                {

                    foreach($periods as $period)
                    {

                        $periodString = str_replace(' ', '_', $period);

                        $setting = 'student_progress_definitions_'.$typeString.'~'.$periodString;
                        $settingvalue = 'student_progress_definition_values_'.$typeString.'~'.$periodString;
                        $settingimportance = 'student_progress_definition_importance_'.$typeString.'~'.$periodString;

                        if ($this->getSetting($setting) == 1 && (int)$this->getSetting($settingvalue) > 0)
                        {

                            $req = (int)$this->getSetting($settingvalue);
                            $importance = (int)$this->getSetting($settingimportance);

                            if ($importance <= 0) continue; // If it's of 0 importance, then skip it

                            $info = explode('student_progress_definitions_', $setting);
                            $info = $info[1];
                            $info = explode("~", $info);
                            $info['type'] = str_replace('_', ' ', $info[0]);
                            $info['period'] = str_replace('_', ' ', $info[1]);

                            $value = (int)$this->getRecord( array(
                                'type' => $info['type'],
                                'period' => $info['period']
                            ) );

                            $max += $importance;

                            // If we have >= the required value, set to max value, e.g. 5/5
                            if ($value >= $req)
                            {
                                $num += $importance;
                            }
                            else
                            {

                                // Otherwise, work out the difference between them and then use that
                                // To get that % of the importance achieved
                                // E.g. Req: 90%, Actual: 45%. Difference: (45/90)*100 = 50%
                                // 50% of importance (5) = 2.5
                                $diff = ($value / $req) * 100;
                                $val = ($diff / 100) * $importance;
                                $num += $val;

                            }

                            $key = get_string('studentprogress:info:attendance', 'block_elbp');
                            $key = str_replace('%t%', $type, $key);
                            $key = str_replace('%p%', $period, $key);
                            $key = str_replace('%v%', $req, $key);
                            $percent = round( ($value / $req) * 100 );
                            $theInfo[$key] = array(
                                'percent' => ($percent > 100) ? 100 : $percent,
                                'value' => $value
                            );


                        }

                    }

                }

            }

        }


        return array(
            'max' => $max,
            'num' => $num,
            'info' => $theInfo
        );


    }




}