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

require_once $CFG->dirroot . '/blocks/elbp/plugins/Tutorials/Tutorial.class.php';


/**
 *
 */
class Tutorials extends Plugin {

    public $supportedHooks;
    protected $tables = array(
        'lbp_tutorials',
        'lbp_tutorial_attributes'
    );

    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {

        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Tutorials",
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
            'Attendance' => array(
                'Averages',
                'Course'
            )
        );


    }

    /**
     * Yes
     * @return boolean
     */
    public function isUsingBlockProgress(){
        return true;
    }

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
        $settings['new_tutorial_instructions'] = 'Make sure you enter some comments and at least 1 Target for each tutorial';
        $settings['tutorials_limit_summary_list'] = 5;
        $settings['attributes'] = 'elbpform:[{"id":"YctQ2uudxH","name":"Tutor Comments","type":"Moodle Text Editor","display":"main","default":"","instructions":"","options":false,"validation":["REQUIRED"],"other":[],"studentID":false,"usersValue":false,"obj":null},{"id":"FzkTy12JV5","name":"Student Comments","type":"Moodle Text Editor","display":"main","default":"","instructions":"","options":false,"validation":[false],"other":[],"studentID":false,"usersValue":false,"obj":null}]';

        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }

        // Alert events
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Tutorial Added", "description" => "A new tutorial is added into the system", "auto" => 0, "enabled" => 1));
        $DB->insert_record("lbp_alert_events", array("pluginid" => $pluginID, "name" => "Tutorial Updated", "description" => "A tutorial is updated (date, targets, etc...)", "auto" => 0, "enabled" => 1));


        // Reporting elements for bc_dashboard reporting wizard
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $pluginID, "getstringname" => "reports:tutorials:numtutorials", "getstringcomponent" => "block_elbp"));
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $pluginID, "getstringname" => "reports:tutorials:avgtutorials", "getstringcomponent" => "block_elbp"));
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $pluginID, "getstringname" => "reports:tutorials:percentwithtutorials", "getstringcomponent" => "block_elbp"));
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $pluginID, "getstringname" => "reports:tutorials:lasttutorial", "getstringcomponent" => "block_elbp"));
        $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $pluginID, "getstringname" => "reports:tutorials:percentwithtutorialssincestartofterm", "getstringcomponent" => "block_elbp"));

        return $return;

    }

    /**
     * Truncate related tables and uninstall plugin
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

        // [Upgrades here]
        if ($this->version < 2013111101){

            // Reporting elements for bc_dashboard reporting wizard
            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "reports:tutorials:numtutorials", "getstringcomponent" => "block_elbp"));
            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "reports:tutorials:avgtutorials", "getstringcomponent" => "block_elbp"));

            $this->version = 2013111101;
            $this->updatePlugin();
            \mtrace("## Inserted plugin_report_element data for plugin: {$this->title}");

        }

        if ($this->version < 2013111102){

            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "reports:tutorials:percentwithtutorials", "getstringcomponent" => "block_elbp"));
            $this->version = 2013111102;
            $this->updatePlugin();
            \mtrace("## Inserted plugin_report_element data for plugin: {$this->title}");

        }

        if ($this->version < 2013111103){

            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "reports:tutorials:lasttutorial", "getstringcomponent" => "block_elbp"));
            $this->version = 2013111103;
            $this->updatePlugin();
            \mtrace("## Inserted plugin_report_element data for plugin: {$this->title}");

        }

        if ($this->version < 2014012403){

            $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => $this->id, "getstringname" => "reports:tutorials:percentwithtutorialssincestartofterm", "getstringcomponent" => "block_elbp"));
            $this->version = 2014012403;
            $this->updatePlugin();
            \mtrace("## Inserted plugin_report_element data for plugin: {$this->title}");

        }

    }



    /**
     * Display the configuration settings for this plugin
     */
    public function displayConfig()
    {

        parent::displayConfig();

        $output = "";

        $output .= "<br><br>";

        $output .= "<h2>".get_string('tutorialsconfig', 'block_elbp')."</h2>";

        $output .= "<small><strong>".get_string('tutorialsconfig:limitsummarylist', 'block_elbp')."</strong> - ".get_string('tutorialsconfig:limitsummarylist:desc', 'block_elbp')."</small><br>";
        $output .= "<input class='elbp_small' type='type' name='tutorials_limit_summary_list' value='{$this->getSetting('tutorials_limit_summary_list')}' />";

        echo $output;

    }


    /**
     * Get the expanded view
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()){

        $output = "";

        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);

        try {
            $output .= $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;

    }


    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){

        $TPL = new \ELBP\Template();

        $listLimit = $this->getSetting('tutorials_limit_summary_list');
        $tutorials = $this->getUserTutorials(null, $listLimit);

        $TPL->set("tutorials", $tutorials);
        $TPL->set("obj", $this);

        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }

    /**
     * Get the user's tutorials
     * @param type $courseID
     * @param type $limit
     * @return boolean|\ELBP\Plugins\Tutorials\Tutorial
     */
    public function getUserTutorials($courseID = null, $limit = null)
    {

        if (!$this->student) return false;

        $results = array();

        // Academic Year
        $academicYearUnix = $this->getAcademicYearUnix();


        $params = array("studentid" => $this->student->id, "del" => 0);
        if (!is_null($courseID)) $params['courseid'] = $courseID;

        if (is_null($limit) || empty($limit)){
            $records = $this->DB->get_records('lbp_tutorials', $params, "tutorialdate DESC, id DESC", "id");
        } else {
            $records = $this->DB->get_records('lbp_tutorials', $params, "tutorialdate DESC, id DESC", "id", 0, $limit);
        }

        if ($records){

            foreach($records as $record){

                $tutorial = new Tutorials\Tutorial($record->id);

                if ($academicYearUnix && $tutorial->getSetTime() < $academicYearUnix){
                    continue;
                }

                $tutorial->loadTutorialsObj($this);
                $results[] = $tutorial;

            }

        }

        return $results;

    }

    /**
     * Get all the targets that are NOT linked to ANY tutorials
     * @global type $CFG
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    private function getExistingTargets(){

        global $CFG, $DB;

        if (!$this->student) return false;

        // Find all target the user has that are NOT linked to any tutorials already
        if ($CFG->dbtype == 'pgsql'){
            $targets = $DB->get_records_sql("SELECT t.id, t.name
                                         FROM {lbp_targets} t
                                         LEFT JOIN {lbp_tutorial_attributes} a ON (a.field = 'Targets' AND CAST(a.value as int) = t.id)
                                         WHERE t.studentid = ? AND a.id IS NULL AND t.del = 0
                                         ORDER BY t.name ASC", array($this->student->id));
        } else {

            $targets = $DB->get_records_sql("SELECT t.id, t.name
                                             FROM {lbp_targets} t
                                             LEFT JOIN {lbp_tutorial_attributes} a ON (a.field = 'Targets' AND a.value = t.id)
                                             WHERE t.studentid = ? AND a.id IS NULL AND t.del = 0
                                             ORDER BY t.name ASC", array($this->student->id));

        }

        return $targets;



    }

    /**
     * Handle ajax requests sent to plugin
     * @global \ELBP\Plugins\type $CFG
     * @param type $action
     * @param type $params
     * @param \ELBP\Plugins\type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){

        global $CFG;

        switch($action)
        {

            case 'load_display_type':

                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                // Get the type from the ID
                if (!isset($params['type'])) return false;

                 $page = $params['type'];

                 $TPL = new \ELBP\Template();
                 $TPL->set("obj", $this);
                 $TPL->set("ELBP", $ELBP);
                 $TPL->set("access", $this->access);
                 $TPL->set("page", $page);
                 $TPL->set("tutorials", $this->getUserTutorials());
                 $courses = '1';
                 $params['courses'] = $courses;
                 $tutorialID = false;

                 if ($page == 'edit'){
                     $tutorialID = $params['tutorialID'];
                     $page = 'new'; # Use the same form, just check for different capabilities
                 }

                 // if new or edit target need the data
                 if ($page == 'new'){
                     $FORM = new \ELBP\ELBPForm();
                     $FORM->loadStudentID($this->student->id);
                     $TPL->set("data", \ELBP\Plugins\Tutorials\Tutorial::getDataForNewTutorialForm($tutorialID));
                     $TPL->set("attributes", $this->getAttributesForDisplay());
                     $TPL->set("FORM", $FORM);
                     $TPL->set("hooks", $this->callAllHooks($params));
                     $TPL->set("existingTargets", $this->getExistingTargets());
                 }

                 try {
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp/plugins/Tutorials/tpl/'.$page.'.html' );
                    $TPL->display();
                 } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                 }


                exit;

            break;

            case 'save_tutorial':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:add_tutorial', $access)) return false;

                $tutorial = new \ELBP\Plugins\Tutorials\Tutorial($params, $this);


                $auto = (isset($params['auto']) && $params['auto'] == 1);
                $tutorial->setAutoSave($auto);

                // If the tutorial exists, check to make sure the student ID on it is the same as the one we specified
                if ($tutorial->getID() > 0 && $tutorial->getStudentID() <> $params['studentID']) return false;

                // Failed to save for some reason
                if (!$tutorial->save()){

                    echo "$('#new_tutorial_output').html('<div class=\"elbp_err_box\" id=\"add_tutorial_errors\"></div>');";

                    foreach($tutorial->getErrors() as $error){

                        echo "$('#add_tutorial_errors').append('<span>{$error}</span><br>');";

                    }

                    exit;

                }

                // Saved OK
                // SUccess message at top
                if ($auto){
                    $savedString = get_string('tutorialautosaved', 'block_elbp');
                } else {
                    $savedString = get_string('tutorialupdated', 'block_elbp');
                }

                echo "$('#new_tutorial_output').html('<div class=\"elbp_success_box\" id=\"add_tutorial_success\"></div>');";
                echo "$('#add_tutorial_success').append('<span>".$savedString."</span><br>');";

                // Now we want to reset the form
                // This is awkward since we don't want to reset hidden values like attendance, etc..., and if we've loaded it from
                // a stored state then the default value is infact the value in the input, so resetting won't remove it
                // So we're going to have to go through input type="text" and textarea elements and set their vals to empty string
                // And remove the target hidden elements and the target rows
                if ($params['tutorial_id'] <= 0 && !$auto){
                    echo <<<JS
                        $('#new_tutorial_form :input[type="text"], #new_tutorial_form textarea').val('');
                        $('#new_tutorial_form div.elbp_texteditor').html('');
                        $('.added_target_row').remove();
JS;
                }

                // Set the hidden tutorial id so if we save it again, it updates, doesn't create new one
                if ($params['tutorial_id'] <= 0 && $auto){
                    echo " $('#new_tutorial_form input[name=\"tutorial_id\"]').val('{$tutorial->getID()}'); ";
                }


            break;

            case 'remove_target':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:remove_target', $access)) return false;

                // Load up the tutorial
                $tutorial = new \ELBP\Plugins\Tutorials\Tutorial($params['tutorialID']);
                if (!$tutorial->isValid()) return false;

                // Make sure this tutorial is for the student we've said it is
                if ($tutorial->getStudentID() <> $params['studentID']) return false;

                // Load the target
                $target = new \ELBP\Plugins\Targets\Target($params['targetID']);
                if (!$target->isValid()) return false;

                // Make sure this target is on this tutorial
                $tutorialTargets = $tutorial->getAllTargets();
                if (!isset($tutorialTargets[$target->getID()])) return false;

                if (!$tutorial->removeAttribute("Targets", $target->getID())){
                    echo "$('#new_tutorial_output').html('<div class=\"elbp_err_box\" id=\"add_tutorial_errors\"></div>');";
                    echo "$('#add_tutorial_errors').append('<span>".get_string('errors:couldnotdeleterecord', 'block_elbp')."</span><br>');";
                    return false;
                }

                // Is OK
                echo "$('#new_tutorial_output').html('<div class=\"elbp_success_box\" id=\"add_tutorial_success\"></div>');";
                echo "$('#add_tutorial_success').append('<span>".get_string('targetremoved', 'block_elbp')."</span><br>');";
                echo "$('#new_added_target_id_{$params['targetID']}').remove();";


            break;

            case 'delete_tutorial':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                if (!elbp_has_capability('block/elbp:delete_tutorial', $access)) return false;

                // Load up the tutorial
                $tutorial = new \ELBP\Plugins\Tutorials\Tutorial($params['tutorialID']);
                if (!$tutorial->isValid()) return false;

                // Make sure this tutorial is for the student we've said it is
                if ($tutorial->getStudentID() <> $params['studentID']) return false;

                if (!$tutorial->delete()){

                    echo "$('#elbp_tut_output').html('<div class=\"elbp_err_box\" id=\"add_tutorial_errors\"></div>');";
                    echo "$('#add_tutorial_errors').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    return false;

                }

                // Is OK
                echo "$('#elbp_tut_output').html('<div class=\"elbp_success_box\" id=\"add_tutorial_success\"></div>');";
                echo "$('#add_tutorial_success').append('<span>".get_string('tutorialdeleted', 'block_elbp')."</span><br>');";
                echo "$('#elbp_tutorial_{$tutorial->getID()}').remove();";

            break;



            case 'get_target_row':

                if (!$params || !isset($params['targetID']) || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // Load the target
                $target = new \ELBP\Plugins\Targets\Target($params['targetID']);
                if (!$target->isValid()) return false;

                // Target cannot be linked to any tutorials
                if ($target->isLinkedToTutorial()) return false;

                $output = "";
                $output .= "<tr class='added_target_row' id='new_added_target_id_{$target->getID()}'>";
                    $output .= "<td>{$target->getSetDate()}</td>";
                    $output .= "<td>".elbp_html($target->getName())."</td>";
                    $output .= "<td>{$target->getStatusName()}</td>";
                    $output .= "<td>{$target->getDueDate()}</td>";
                    $output .= "<td><a href='#' onclick='ELBP.Tutorials.remove_target({$target->getID()});return false;' title='".get_string('remove', 'block_elbp')."'><img src='".$CFG->wwwroot."/blocks/elbp/pix/remove.png' alt='".get_string('remove', 'block_elbp')."' /></a><input type='hidden' name='Targets' value='{$target->getID()}' /></td>";
                $output .= "</tr>";

                echo $output;

                exit;

            break;



        }

    }


    /**
     * Save configuration
     * @global type $MSGS
     * @global \ELBP\Plugins\type $ELBP
     * @global \ELBP\Plugins\type $DB
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {

        global $MSGS, $ELBP, $DB;

        if(isset($_POST['submit_attributes'])){

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


        elseif(isset($_POST['submit_tutorial_instructions']))
        {

            $instructions = $settings['new_tutorial_instructions'];
            $this->updateSetting("new_tutorial_instructions", $instructions);

            $MSGS['success'] = get_string('instructionsupdated', 'block_elbp');

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

        parent::saveConfig($settings);

    }

    /**
     * Get tutorials previous to a given tutorial
     * @global type $DB
     * @param type $notID
     * @return boolean
     */
    public function getOldTutorials($tutorialDate, $notID)
    {

        if (!$this->student) return false;
        if (!$this->isEnabled()) return false;
        global $DB;

        // We're going to do this by timeset rather than id, since someone might add a tutorial that was actually done a week ago
        // So then it would register as newer than one done, say 3 days ago.

        // Academic Year
        $academicYearUnix = (int)$this->getAcademicYearUnix();

        $records = $DB->get_records_select("lbp_tutorials", "studentid = ? AND tutorialdate <= ? AND id <> ? AND settime >= ?", array($this->student->id, $tutorialDate, $notID, $academicYearUnix), "tutorialdate DESC, id DESC");
        return $records;

    }


    /**
     * Get the progress bar/info for the block content
     */
    public function _getBlockProgress()
    {

        global $CFG;

        $output = "";

        // Number of tutorials set
        $total = count($this->getUserTutorials());

        $output .= "<div>";
            $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/progress_bar.png' alt='progress_bar' /> {$total} " . get_string('tutorials', 'block_elbp');
        $output .= "</div>";

        return $output;

    }



    /**
     * Print to html
     * @global type $ELBP
     * @param type $tutorialID
     * @return boolean
     */
    public function printOut($tutorialID)
    {

        global $ELBP;

        if (is_numeric($tutorialID))
        {

            $tutorial = new \ELBP\Plugins\Tutorials\Tutorial($tutorialID);
            if (!$tutorial->isValid()){
                return false;
            }

            // Get our access for the student who this belongs to
            $access = $ELBP->getUserPermissions( $tutorial->getStudentID() );
            if (!elbp_has_capability('block/elbp:print_tutorial', $access)){
                echo get_string('invalidaccess', 'block_elbp');
                return false;
            }

            // Carry on
            $tutorial->setTutorialsObj($this);
            $tutorial->printOut();
            return true;

        }

    }

    /**
     * Yeah, why not
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
                $output .= "<td>".get_string('studentprogressdefinitions:reqnumtutorials', 'block_elbp')."</td>";
                $output .= "<td><input type='number' min='0.5' step='0.5' class='elbp_smallish' name='student_progress_definition_importance_req' value='{$this->getSetting('student_progress_definition_importance_req')}' /></td>";
            $output .= "</tr>";


        $output .= "</table>";

        return $output;

    }

    /**
     * Calculate overall student progress for tutorials
     * @return type
     */
     public function calculateStudentProgress(){

        $max = 0;
        $num = 0;
        $info = array();

        $tutorials = $this->getUserTutorials();
        $cnt = count($tutorials);


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

                $key = get_string('studentprogress:info:tutorials:req', 'block_elbp');
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
                    $DB->execute("UPDATE {lbp_tutorial_attributes} SET field = ? WHERE field = ?", array($newName, $oldName));

                }

            }
        }

        return true;

    }


}