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
class StudentProfile extends Plugin {

    private $info_from_mis = array();
    protected $tables = array(
        'lbp_student_profile'
    );


    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {

        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Student Profile",
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
     * Should it have a plugin box and popup?
     * @return boolean
     */
    public function hasPluginBox(){
        // StudentProfile is different
        return false;
    }

    /**
     * Doesn't use headers like the other plugins
     * @return boolean
     */
    public function isUsingHeaders(){
        return false;
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
        $settings['summary_enabled'] = 1;
        $settings['allow_profile_editing'] = 1;


        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }

        // Reporting elements
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "reports:studentprofile:numelbadgesawarded", "getstringcomponent" => "block_elbp"));

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

        global $DB;

        $result = true;
        $version = $this->version; # This is the current DB version we will be using to upgrade from

        // [Upgrades here]
        if ($version < 2014100801)
        {
            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "reports:studentprofile:numelbadgesawarded", "getstringcomponent" => "block_elbp"));
            $this->version = 2014100801;
            $this->updatePlugin();
            \mtrace("## Inserted plugin_report_element data for plugin: {$this->title}");
        }

    }

    /**
     * Get the MIS settings and values
     */
    private function setupMisRequirements(){

        $this->mis_settings = array();

        // Settings
        $this->mis_settings['view'] = $this->getSetting('mis_view_name');
        $this->mis_settings['postconnection'] = $this->getSetting('mis_post_connection_execute');
        $this->mis_settings['dateformat'] = $this->getSetting('mis_date_format');
        if (!$this->mis_settings['dateformat']) $this->mis_settings['dateformat'] = 'd-m-Y';
        $this->mis_settings['mis_username_or_idnumber'] = $this->getSetting('mis_username_or_idnumber');
        if (!$this->mis_settings['mis_username_or_idnumber']) $this->mis_settings['mis_username_or_idnumber'] = 'username';

        // Mappings
        $reqFields = $this->getRequiredProfileFields();
        if ($reqFields)
        {
            foreach($reqFields as $reqField)
            {
                $this->mis_settings['mapping'][$reqField->field] = $this->plugin_connection->getFieldMap($reqField->field);
                $this->mis_settings['alias'][$reqField->field] = $this->plugin_connection->getFieldAlias($reqField->field);
            }
        }

        // If there are any queries to be executed after connection, run them
        if ($this->mis_settings['postconnection'] && !empty($this->mis_settings['postconnection'])){
            $this->connection->query($this->mis_settings['postconnection']);
        }

    }



    /**
     * Get the current student's profile field
     * @param string $field
     */
    private function getProfileField($field)
    {

        // If we're using Moodle database, get frmo there (student_info) is always stored in moodle DB
        if (!$this->isUsingMIS()){
            return $this->DB->get_record("lbp_student_profile", array("field"=>$field, "studentid"=>$this->student->id));
        } else {

            // Else get it from our MIS connection
            if (!$this->info_from_mis){
                $this->getAllFieldsFromMIS();
            }

            $map = $this->plugin_connection->getFieldMap($field);
            $alias = $this->plugin_connection->getFieldAlias($field);

            if ($alias){
                $value = (isset($this->info_from_mis[$alias])) ? $this->info_from_mis[$alias] : '';
            } else {
                $value = (isset($this->info_from_mis[$map])) ? $this->info_from_mis[$map] : '';
            }

            $obj = new \stdClass();
            $obj->id = -1;
            $obj->value = $value;
            return $obj;

        }
    }

    /**
     * Update the current user's profile field
     * @param string $field
     * @param string $value
     */
    private function updateProfileField($field, $value)
    {

        $record = $this->getProfileField($field);

        // Log action
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_PROFILE, LOG_ACTION_ELBP_PROFILE_UPDATED_PROFILE_FIELD, $this->student->id, array(
            "field" => $field,
            "value" => $value
        ));

        if ($record)
        {
            $record->value = $value;
            return $this->DB->update_record("lbp_student_profile", $record);
        }
        else
        {
            $obj = new \stdClass();
            $obj->studentid = $this->student->id;
            $obj->field = $field;
            $obj->value = $value;
            return $this->DB->insert_record("lbp_student_profile", $obj);
        }
    }

    /**
     * Get the profile fields defined in settings
     * @return type
     */
    public function getRequiredProfileFields()
    {
        return $this->DB->get_records("lbp_student_profile", array("studentid"=>null), "ordernum ASC");
    }

    /**
     * The Student Profile isn't like the other plugins, it doesn't have an expandable window, it's always @ the top, so we use
     * display() here rather than displaySUmmary()
     * @return string
     */
    public function display($params = array())
    {

        global $CFG, $OUTPUT, $access;

        if (!$this->isEnabled()) return;
        if (!$this->student) return;

        $TPL = new \ELBP\Template();
        $conf = new \ELBP\Confidentiality();

        if (is_null($access)) $access = $this->access;

        // Set up variables for template

        // Student details section
        // Firstly get a list of the fields the admin has decided we want to display. In the DB these will be with studentid NULL
        $details = "";

        $requiredFields = $this->getRequiredProfileFields();
        if ($requiredFields)
        {
            foreach($requiredFields as $field)
            {

                // If this field has a confidentiality level and you don't meet it, you ain't seeing it brah
                if ( !is_null($field->confidentialityid) && !$conf->meetsConfidentialityRequirement($access, $field->confidentialityid) ){
                    continue;
                }

                // Get the student's field value, if there is one
                $value = $this->getProfileField($field->field);

                $details .= "<small>{$field->value}</small><br>";

                if ($value)
                {
                    $details .= "<span id='elbp_studentprofile_details_simple_{$field->field}' class='elbp_studentprofile_details_simple' title='{$field->value}'>&nbsp;&nbsp;&nbsp;&nbsp;".elbp_html($value->value, true) . "<br></span>";
                }
                else
                {
                    $value = new \stdClass();
                    $value->value = '';
                    $details .= "<span id='elbp_studentprofile_details_simple_{$field->field}' class='elbp_studentprofile_details_simple' title='{$field->value}'>&nbsp;&nbsp;&nbsp;&nbsp;-<br></span>";
                }

                // Field for editing
                $details .= "<span class='elbp_studentprofile_details_edit' style='display:none;'>";
                    $details .= "<input type='text' class='elbp_studentprofile_details_edit_values elbp_max' name='{$field->field}' value='{$value->value}' />&nbsp;&nbsp;&nbsp;&nbsp;";
                $details .= "<br></span>";


            }
        }

        // Student Info section
        $info = $this->getProfileField("student_info");
        $student_info = ($info) ? format_text($info->value, FORMAT_HTML, array('filter' => false)) : get_string('pleasefillmein', 'block_elbp');

        $extra_info = "";

        // Bedford want enrolments
        if (($enrolments = $this->loadExtra('enrolments')) !== false){

            $userEnrolments = $enrolments->getUserEnrolments($this->student->username);
            if ($userEnrolments)
            {

                $extra_info .= "<br><br><b>".get_string('myenrolments', 'block_elbp')."</b><br>";

                foreach($userEnrolments as $enrolment)
                {
                    $start = new \DateTime($enrolment['Start_Date']);
                    $end = new \DateTime($enrolment['End_Date']);
                    $extra_info .= "<small>".$enrolment['Course_Description'] . " ({$start->format('d/m/Y')} - {$end->format('d/m/Y')})</small><br>";
                }
            }

        }

        // Student Summary
        $student_summary = '';
        if ($this->getSetting("summary_enabled"))
        {

            $student_summary = "";

            // Personal Tutors
            if ($this->getSetting('show_tutors_summary') == 1)
            {

                $tutors = $this->ELBPDB->getTutorsOnStudent($this->student->id);
                $student_summary .= "<p class='tutors'><b>".get_string('personaltutors', 'block_elbp')."</b> - <small>";
                if ($tutors)
                {
                    foreach($tutors as $tutor)
                    {
                        $student_summary .= fullname($tutor) . ", ";
                    }
                }
                else
                {
                    $student_summary .= get_string('na', 'block_elbp');
                }

                $student_summary .= "</small></p>";

            }


            // Course Tutors
            if ($this->getSetting('show_course_tutors_summary') == 1)
            {

                $tutors = $this->ELBPDB->getCourseTutorsOnStudent($this->student->id);
                $student_summary .= "<p class='tutors'><b>".get_string('coursetutors', 'block_elbp')."</b> - <small>";
                if ($tutors)
                {
                    foreach($tutors as $tutor)
                    {
                        $student_summary .= fullname($tutor) . ", ";
                    }
                }
                else
                {
                    $student_summary .= get_string('na', 'block_elbp');
                }

                $student_summary .= "</small></p>";

            }

            $student_summary .= $this->displayPluginSummarys();

        }

        require_once $CFG->dirroot.'/lib/form/editor.php';
        require_once $CFG->dirroot . '/lib/editorlib.php';
        $editor = \editors_get_preferred_editor();
        $editor->use_editor('student_info_textarea', array('autosave' => false));

        $badges = array();

        if ( $this->getSetting('badges_enabled') == 1 && !isset($params['noBadges']) ){

            require_once $CFG->dirroot . '/lib/badgeslib.php';
            $badgeArray = \badges_get_user_badges($this->student->id);

            usort($badgeArray, function($a, $b){
                return ($b->id < $a->id);
            });

            if ($badgeArray){

                foreach($badgeArray as $badge) {

                    $badgeObj = new \badge($badge->id);
                    $badgeObj->hash = $badge->uniquehash;
                    $badges[$badge->id] = $badgeObj;

                }

            }

        }

        $hideBadges = (isset($_COOKIE['hide_elbp_badges']) && $_COOKIE['hide_elbp_badges'] == 1) ? true : false;


        usort($badges, function($a, $b){
            return ( strnatcmp($a->name, $b->name) );
        });


        if ($this->getSetting("user_or_id") == 'idnumber' && $this->student->idnumber != '') {
            $user_or_id = "(".$this->student->idnumber.")";
        }
        else {
            $user_or_id = "(".$this->student->username.")";
        }


        $TPL->set("obj", $this)
            ->set("user_or_id", $user_or_id)
            ->set("picture", $OUTPUT->user_picture($this->student, array("courseid"=>1, "size"=>150)))
            ->set("student", $this->student)
            ->set("details", $details)
            ->set("student_info", $student_info)
            ->set("extra_info", $extra_info)
            ->set("student_summary", $student_summary)
            ->set("access", $access)
            ->set("badges", $badges)
            ->set("hideBadges", $hideBadges)
            ;

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/StudentProfile/tpl/profile.html');
        } catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Save configuration
     * @global type $MSGS
     * @global \ELBP\Plugins\type $DB
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {

        global $MSGS, $DB;

        // Profile Fields
        if (isset($settings['submit_fields'], $settings['profile_fields_field'], $settings['profile_fields_value'], $settings['profile_fields_order']))
        {

            foreach($settings['profile_fields_field'] as $id => $field)
            {

                $title = $settings['profile_fields_value'][$id];
                $order = $settings['profile_fields_order'][$id];
                $confidentiality = $settings['profile_fields_confidentiality'][$id];

                $core = $this->getMainMIS();

                $original = $DB->get_record("lbp_student_profile", array("id" => $id));

                $obj = new \stdClass();
                $obj->id = $id;
                $obj->field = $field;
                $obj->value = $title;
                $obj->ordernum = $order;
                $obj->confidentialityid = $confidentiality;
                $this->DB->update_record("lbp_student_profile", $obj);

                // Since we've updated the profile field, let's see if we had set a mis_mappings for this and change
                // that as well
                if ($core)
                {
                    $check = $DB->get_record("lbp_mis_mappings", array("pluginmisid" => $core->id, "name" => $original->field));
                    if ($check)
                    {
                        $check->name = $field;
                        $DB->update_record("lbp_mis_mappings", $check);
                    }
                }


            }

            // Remove so parent saevConfig doesn't try to put them into lbp_settings
            unset($settings['profile_fields_field']);
            unset($settings['profile_fields_value']);
            unset($settings['profile_fields_order']);
            unset($settings['profile_fields_confidentiality']);

            // Enable/disable editing
            $allow_editing = $settings['allow_profile_editing'];
            $this->updateSetting('allow_profile_editing', $allow_editing);

            unset($settings['allow_profile_editing']);

            $MSGS['success'] = '<h1>'.get_string('success', 'block_elbp').'</h1><p>'.get_string('updated', 'block_elbp').'</p>';
            return true;

        }

        // New profile fields
        elseif (isset($settings['submit_new_fields'], $settings['new_profile_fields_field'], $settings['new_profile_fields_value'], $settings['new_profile_fields_order'])){

            foreach($settings['new_profile_fields_field'] as $id => $field)
            {
                $title = $settings['new_profile_fields_value'][$id];
                $order = $settings['new_profile_fields_order'][$id];
                $confidentiality = $settings['profile_fields_confidentiality'][$id];

                $obj = new \stdClass();
                $obj->field = $field;
                $obj->value = $title;
                $obj->ordernum = $order;
                $obj->confidentialityid = $confidentiality;
                $obj->studentid = null;
                $this->DB->insert_record("lbp_student_profile", $obj);

            }

            // Remove so parent saevConfig doesn't try to put them into lbp_settings
            unset($settings['new_profile_fields_field']);
            unset($settings['new_profile_fields_value']);
            unset($settings['new_profile_fields_order']);

            $MSGS['success'] = '<h1>'.get_string('success', 'block_elbp').'</h1><p>'.get_string('updated', 'block_elbp').'</p>';
            return true;

        }

        elseif (isset( $settings['delete_field_x'], $settings['delete_field_y'] ) && ctype_digit($settings['field_id']) && $settings['field_id'] > 0)
        {

            $id = $settings['field_id'];
            $this->DB->delete_records("lbp_student_profile", array("id"=>$id));

            // Remove from settings
            unset($settings['delete_field_x']);
            unset($settings['delete_field_y']);
            unset($settings['field_id']);

            $MSGS['success'] = '<h1>'.get_string('success', 'block_elbp').'</h1><p>'.get_string('updated', 'block_elbp').'</p>';
            return true;

        }

        elseif (isset($settings['submitmistest_student']) && !empty($settings['testusername']))
        {
            $username = $settings['testusername'];
            $this->runTestMisQuery($username, "student_info");
            return true;
        }

        elseif (isset($settings['submit_import']) && isset($_FILES['file']) && !$_FILES['file']['error']){

            $result = $this->runImport($_FILES['file']);
            $MSGS['result'] = $result;
            return true;

        }

        elseif (isset($settings['submitconfig']))
        {

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



            // Student progress definitions

            // If any of them aren't defined, set their value to 0 for disabled
            if (!isset($settings['student_progress_definitions_req'])){
                $settings['student_progress_definitions_req'] = 0;
                $settings['student_progress_definition_importance_req'] = 0;
            }


            // If the req ones don't have a valid number as their value, set to disabled
            if (!isset($settings['student_progress_definition_importance_req']) || $settings['student_progress_definition_importance_req'] <= 0) $settings['student_progress_definitions_req'] = 0;




            parent::saveConfig($settings);
            $MSGS['success'] = '<h1>'.get_string('success', 'block_elbp').'</h1><p>'.get_string('settingsupdated', 'block_elbp').'</p>';

        }


    }



    /**
     * This will take the MIS connection and field details you have provided in the settings and run a test query to see
     * if it returns what you expect
     * @param string $username - The username to run the query against
     */
    public function runTestMisQuery($username, $query){

        global $MSGS;

        // This query will select all records it can find for a specified username/idnumber

        $view = $this->getSetting("mis_view_name");
        if (!$view){
            $MSGS['errors'][] = 'mis_view_name';
            return false;
        }

//        $dateformat = $this->getSetting("mis_date_format");
//        if (!$dateformat){
//            $MSGS['errors'][] = 'mis_date_format';
//            return false;
//        }

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



        $reqFields = $this->getRequiredProfileFields();

        if ($reqFields)
        {
            foreach($reqFields as $reqField)
            {
                if (!$conn->getFieldMap($reqField->field) && !$conn->getFieldFunc($reqField->field)){
                    $MSGS['errors'][] = get_string('missingreqfield', 'block_elbp') . ": " . $reqField->field;
                    return false;
                }
            }
        }

        switch($query)
        {
            case 'student_info':

                // Run the query
                $this->getAllFieldsFromMIS( array("username" => $username) );

                $results = new \Anon;

                foreach($reqFields as $reqField)
                {

                    $fieldMap = $conn->getFieldMap($reqField->field);
                    $alias = $conn->getFieldAlias($reqField->field);
                    $val = $reqField->value;

                    if ($alias){
                        $results->$val = (isset($this->info_from_mis[$alias])) ? $this->info_from_mis[$alias] : false;
                    } else {
                        $results->$val = (isset($this->info_from_mis[$fieldMap])) ? $this->info_from_mis[$fieldMap] : false;
                    }

                }

                // Student info
                $sInfoField = $conn->getFieldMap("student_info");
                if (!$sInfoField){
                    $sInfoField = $conn->getFieldAlias("student_info");
                }

                $sInfoString = get_string('studentinfo', 'block_elbp');
                $results->$sInfoString = (isset($this->info_from_mis[$sInfoField])) ? $this->info_from_mis[$sInfoField] : false;


                $MSGS['testoutput'] = $results;

            break;

        }


    }

    /**
     * Load student
     * @param type $studentID
     * @param type $fromBlock
     * @return type
     */
    public function loadStudent($studentID, $fromBlock = false) {
        return (parent::loadStudent($studentID) && $this->getAllFieldsFromMIS() );
    }

    /**
     * Since we will almost certainly be using a view/table with only one row of results, with all the different fields in
     * fields, rather than seperate rows like in our table in Moodle, load all of that info up into an array that we can
     * use
     */
    private function getAllFieldsFromMIS($options = null)
    {

        global $CFG, $MSGS;

        $this->connect();

        if (!$this->isUsingMIS()) return true;
        if (!$this->connection) return false;
        if (!$this->plugin_connection) return false;

        // Bedcoll
        if (isset($CFG->moodleinstance)){
            if ( !isset($options['username']) && ( $this->student->institution != 'Student' || preg_match("/[a-z]/i", $this->student->username) ) ) return true;
        }

        // Reset to blank array
        $this->info_from_mis = array();

        $userField = $this->mis_settings['mis_username_or_idnumber'];

        if (isset($options['username'])) $username = $options['username'];
        else $username = $this->student->$userField;

        // Get distinct list of fields to query
        $fields = $this->plugin_connection->getAllMappingsForSelect(true);

        $query = $this->connection->query("SELECT {$fields} FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                           WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}",
                                                   array(
                                                       $this->plugin_connection->getFieldMap('username') => $username
                                                   ));

        // Debugging on?
        if ($CFG->debug >= 32767){
            $MSGS['sql'] = $this->connection->getLastSQL();
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
            $results = $results[0];
            foreach($results as $field => $value)
            {
                $this->info_from_mis[$field] = $value;
            }
        }

        return true;

    }

    /**
     * Not used for this plugin
     */
    public function getDisplay($params = array()){
        ;
    }

    /**
     * Not used for this plugin
     */
    public function getSummaryBox(){
        ;
    }

    /**
     * Handle ajax requests sent to plugin
     * @global \ELBP\Plugins\type $DB
     * @param type $action
     * @param type $params
     * @param type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP)
    {

        global $DB, $USER;

        switch($action)
        {

            case 'update_details':


                // If not allowing profile editing, stop right there mister
                if ($this->getSetting('allow_profile_editing') != 1) return false;

                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // Do we have permission to edit this? - Are they the student themselves, or have the capability?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$access['user'] && !\elbp_has_capability('block/elbp:change_others_profile', $access)) return false;

                // Remove studentID from params
                unset($params['studentID']);

                // Loop through parameters and see if it's a required param
                $requiredFields = $this->getRequiredProfileFields();
                if (!$requiredFields) return false;

                foreach($requiredFields as $required)
                {

                    // If we're using the Moodle database, just update the value:

                    if (!$this->isUsingMIS())
                    {
                        // See if we have this field submitted
                        if (isset($params[$required->field]) && !empty($params[$required->field]))
                        {

                            $field = $required->field;
                            $value = $params[$required->field];

                            $this->updateProfileField($field, $value);

                        }
                    }

                }


                // Is using MIS
                if ($this->isUsingMIS())
                {

                    // If we are using MIS connection, we don't want to be UPDATING anything in that ourselves
                    // So we will send an email to the relevant member of the MIS team with the info that the
                    // student wants to be changed

                    // If no MIS email supplied in config settings, can't do it
                    $contacts = $this->getSetting('mis_contact_emails');
                    if (!$contacts || empty($contacts)){
                        echo "alert('".get_string('missingreqfield', 'block_elbp')." [mis_contact_emails]. ".get_string('contactsystemadmin', 'block_elbp')."');";
                        return false;
                    }

                    $contacts = explode(",", $contacts);

                    // Build email
                    $subject = get_string('studentdetailschangerequest', 'block_elbp');

                    $content = "";
                    $content .= $this->getSetting('mis_contact_body');
                    $content .= "\n\n";
                    $content .= get_string('student', 'block_elbp') . ": " . fullname($this->student) . " ({$this->student->username})\n";

                    foreach($requiredFields as $required)
                    {
                        $content .= $required->value . ": " . $params[$required->field] . "\n";
                    }

                    // Send email to contacts
                    foreach($contacts as $contact)
                    {
                        $user = $DB->get_record("user", array("username" => $contact));
                        if ($user){
                            email_to_user($user, $user, $subject, $content, nl2br($content));
                        }
                    }

                    echo "alert('".get_string('studentdetailsposted', 'block_elbp').": {$this->getSetting('mis_contact_return_msg')}');";


                }



                return true;

            break;



            case 'update_info':

                // If not allowing profile editing, stop right there mister
                if ($this->getSetting('allow_profile_editing') != 1) return false;

                // Params are set?
                if (!$params || !isset($params['info']) || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$access['user'] && !\elbp_has_capability('block/elbp:change_others_profile', $access)) return false;

                // Remove studentID from params
                unset($params['studentID']);

                $info = trim($params['info']);

                if (!empty($info))
                {
                    $this->updateProfileField("student_info", $info);
                }

                // Clear any atto drafts
                $editor = \editors_get_preferred_editor();
                $editor = \get_class($editor);
                $editor = str_replace("_texteditor", "", $editor);
                if ($editor == 'atto' && isset($params['element'])){
                    $DB->delete_records("editor_atto_autosave", array("elementid" => $params['element'], "userid" => $USER->id));
                }

                echo 'OK';

                return true;

            break;


        }

    }

    /**
     * Get the summary elements from the various plugins
     * @global type $ELBP
     * @return string
     */
    private function displayPluginSummarys()
    {

        global $ELBP;

        $plugins = $ELBP->getPlugins();
        $summary = array();
        $cnt = 0;
        $cntTtl = 0;
        $output = "";

        if ($plugins)
        {
            foreach($plugins as $plugin)
            {
                if (method_exists($plugin, 'getSummaryInfo'))
                {
                    $plugin->loadStudent( $this->student->id );
                    $summary[] = $plugin->getSummaryInfo();
                }
            }
        }

        if ($summary)
        {

            $ttl = count($summary, COUNT_RECURSIVE);
            $output .= "<table>";

                foreach($summary as $pluginSummary => $summ)
                {

                    if ($summ)
                    {

                        foreach($summ as $s)
                        {

                            $cnt++;
                            $cntTtl++;

                            if ($cnt == 1)
                            {
                                $output .= "<tr>";
                            }

                            $output .= "<td class='elbp_title'>{$s['name']}</td>";
                            $output .= "<td>{$s['value']}</td>";

                            if ($cnt == 3 || $cntTtl == $ttl)
                            {
                                $output .= "</tr>";
                                $cnt = 0;
                            }

                        }

                    }

                }

            $output .= "</table>";

        }

        return $output;

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
                $output .= "<td></td>";
                $output .= "<td>".get_string('studentprogressdefinitions:reqprofile', 'block_elbp')."</td>";
                $output .= "<td><input type='number' min='0.5' step='0.5' class='elbp_smallish' name='student_progress_definition_importance_req' value='{$this->getSetting('student_progress_definition_importance_req')}' /></td>";
            $output .= "</tr>";


        $output .= "</table>";

        return $output;

    }

    /**
     * Calculate student profile parts of overall student progress
     * @return type
     */
     public function calculateStudentProgress(){

        $max = 0;
        $num = 0;
        $theInfo = array();

        // Enabled
        if ($this->getSetting('student_progress_definitions_req') == 1)
        {

            $importance = $this->getSetting('student_progress_definition_importance_req');

            if ($importance > 0)
            {

                // E.g. if they need to have a minimum of 5, add 5 to the max
                $max += $importance;

                $info = $this->getProfileField('student_info');

                // If profile isn't empty and isn't set to the default, set num to max
                if ($info && strip_tags($info->value) != '' && strip_tags($info->value) != get_string('pleasefillmein', 'block_elbp'))
                {
                    $num += $importance;
                    $key = get_string('studentprogress:info:profile:yes', 'block_elbp');
                    $v = get_string('yes', 'block_elbp');
                }
                else
                {
                    $key = get_string('studentprogress:info:profile:no', 'block_elbp');
                    $v = get_string('no', 'block_elbp');
                }

                $percent = round(($num / $importance) * 100); // Either 0 or 100 - it's either done or not
                $theInfo[$key] = array(
                    'percent' => $percent,
                    'value' => $v
                );

            }

        }


        return array(
            'max' => $max,
            'num' => $num,
            'info' => $theInfo
        );

    }

    /**
     * Get required headers for csv import
     * @return string
     */
    private function getImportCsvHeaders(){
        $headers = array();
        $headers[] = 'username';
        $headers[] = 'field';
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
        $fields = $this->getRequiredProfileFields();
        $cntFields = count($fields);

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

                $key = array_rand($fields);
                $field = $fields[$key];
                $data[] = $field->field;

                $data[] = 'Some value here';

                fputcsv($fh, $data);


            }

        }



        fclose($fh);
        return $code;

    }


    /**
     * Run csv data import
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
        $i = 1;
        $validUsernames = array(); // Save us checking same username multiple times - saves processing time
        $errorCnt = 0;

        $userField = $this->getSetting('import_user_field');
        if (!$userField){
            $userField = 'username';
        }

        $fields = $this->getRequiredProfileFields();
        $validFields = array();
        if ($fields)
        {
            foreach($fields as $field)
            {
                $validFields[] = $field->field;
            }
        }

        while( ($row = fgetcsv($fh)) !== false )
        {

            $i++;

            $row = array_map('trim', $row);

            $username = $row[0];
            $field = $row[1];
            $value = $row[2];


            // First check that all columns have something in (except courseshortname, that can be empty)
            $emptycnt = 0;
            for($j = 0; $j < count($headers); $j++){
                if (elbp_is_empty($row[$j])){
                    $emptycnt++;
                }
            }



            // Check username exists
            $user = false;

            if (!array_key_exists($username, $validUsernames)){

                $user = $DB->get_record("user", array($userField => $username, "deleted" => 0), "id, username, idnumber, firstname, lastname");
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

            // Is the field valid?
            if (!in_array($field, $validFields) && $field != 'student_info'){
                $output .= "[{$i}] " . get_string('invalidfield', 'block_elbp') . " : {$field}<br>";
                $errorCnt++;
                continue;
            }

            // Update student profile info
            $this->loadStudent($user->id);
            $this->updateProfileField($field, $value);
            $output .= "[{$i}] " . get_string('import:updatedrecord', 'block_elbp') . " - ".fullname($user)." ({$user->username}) [".implode(',', $row)."]<br>";



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
     * Run the cron
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


    /**
     * For the bc_dashboard reporting wizard - get all the data we can about Targets for these students,
     * then return the elements that we want.
     * @param type $students
     * @param type $elements
     * @param type $filters
     * @param $obj - If a qual level is being used in the report then this is an arry of quals
     * @param $obj2 - If a qual level is being used in the report then this ia an array of courses that was used to find the students
     */
    public function getAllReportingData($students, $elements, $filters = false, $obj = false, $obj2 = false)
    {

        global $DB;

        if (!$students || !$elements) return false;

        $courseIDsArray = array();

        if (is_array($obj2)){
            foreach($obj2 as $o){
                $courseIDsArray[] = $o->id;
            }
        } elseif (is_object($obj2)){
            $courseIDsArray[] = $obj2->id;
        }

        if (!$courseIDsArray) $courseIDsArray = false;



        $cnt = 0;

        // Loop students and find all their targets
        foreach($students as $student)
        {

            // If a quallevel, only check the courses sent
            if ($courseIDsArray)
            {

                $courseIn = str_repeat('?,', count($courseIDsArray) - 1) . '?';

                $cnt += $DB->count_records_sql("SELECT COUNT(DISTINCT b.id)
                                                FROM {badge} b
                                                INNER JOIN {badge_issued} bi ON b.id = bi.badgeid
                                                WHERE bi.userid = ? AND b.courseid IN ({$courseIn}) AND b.name REGEXP '\[eLearn_[0-9]+_[0-9]+\]'", array_merge(array($student->id), $courseIDsArray));

            }
            else
            {

                $cnt += $DB->count_records_sql("SELECT COUNT(DISTINCT b.id)
                                                FROM {badge} b
                                                INNER JOIN {badge_issued} bi ON b.id = bi.badgeid
                                                WHERE bi.userid = ? AND b.name REGEXP '\[eLearn_[0-9]+_[0-9]+\]'", array($student->id));
            }

        }

        $data = array();
        $data['reports:studentprofile:numelbadgesawarded'] = $cnt;

        $names = array();
        $els = array();

        foreach($elements as $element)
        {
            $record = $DB->get_record("lbp_plugin_report_elements", array("id" => $element));
            $names[] = $record->getstringname;
            $els[$record->getstringname] = $record->getstringcomponent;
        }

        $return = array();
        foreach($names as $name)
        {
            if (isset($data[$name])){
                $newname = \get_string($name, $els[$name]);
                $return["{$newname}"] = $data[$name];
            }
        }

        return $return;

    }




}