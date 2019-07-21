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

namespace ELBP;

if(!defined('ELBP')) define('ELBP', true);

/**
 *
 */
class ELBP
{

    const MAJOR_VERSION = 1;
    const MINOR_VERSION = 5;
    const BUILD_NUMBER = 0;

    const REMOTE_HOST_URL = 'https://github.com/cwarwicker/moodle-block_elbp';
    const REMOTE_VERSION_URL = 'https://raw.githubusercontent.com/cwarwicker/moodle-block_elbp/master/v.txt';
    const REMOTE_DOC_URL = 'https://github.com/cwarwicker/moodle-block_elbp/wiki';
    const REMOTE_HUB = '';
    const REMOTE_HUB_TOKEN = '';

    private $CFG;
    private $DB;
    private $ELBPDB;

    private $student;
    private $courseID;
    private $group;
    private $plugins = array();
    private $errorMsg;

    private $string;

    public $dir;

    /**
     * Construct the global ELBP object
     * @global type $CFG
     * @global type $DB
     * @param type $options
     */
    public function __construct($options = null)
    {
        global $CFG, $DB;

        $this->CFG = $CFG;
        $this->DB = $DB;
        $this->ELBPDB = new DB();

        $this->student = false;
        $this->courseID = false;
        $this->group = false;
        $this->dir = $CFG->dataroot . DIRECTORY_SEPARATOR . 'ELBP';

        if (is_null($options) || !isset($options['load_plugins']) || $options['load_plugins'] == true){

            $loadCustom = (isset($options['load_custom']) && $options['load_custom'] == false) ? false : true;
            $this->loadPlugins($loadCustom, $options);
        }

        $this->string = get_string_manager()->load_component_strings('block_elbp', $this->CFG->lang, true);

    }

    /**
     * Get the a.b.c plugin version number
     * @return type
     */
    public function getPluginVersion(){
        return self::MAJOR_VERSION . '.' . self::MINOR_VERSION . '.' . self::BUILD_NUMBER;
    }

    /**
     * Print out a message if there are new updates
     * @return string
     */
    public function printVersionCheck($full = false){

        global $CFG;

        $remote = @file_get_contents(REMOTE_VERSION_URL);
        if (!$remote) return "<span class='elbp_err'>".get_string('unabletocheckforupdates', 'block_elbp')."</span>";

        $remote = json_decode(trim($remote));
        if (!$remote || is_null($remote)){
            return "<span class='elbp_err'>".get_string('unabletocheckforupdates', 'block_elbp') . "</span>";
        }

        $result = version_compare($this->getPluginVersion(), $remote->version, '<');
        if ($result){
            $img = (file_exists($CFG->dirroot . '/blocks/elbp/pix/update_'.$remote->update.'.png')) ? $CFG->wwwroot . '/blocks/elbp/pix/update_'.$remote->update.'.png' : $CFG->wwwroot . '/blocks/elbp/pix/update_general.png';
            $link = (isset($remote->file) && $remote->file != '') ? $remote->file : self::REMOTE_HOST_URL;
            if ($full){
                return "<span class='elbp_update_notification_full_{$remote->update}'>".get_string('newversionavailable', 'block_elbp').": {$remote->version} [".\get_string('versionupdatetype_'.$remote->update, 'block_elbp')."]</span> <a href='{$link}'><img src='".\elbp_image_url('t/download')."' alt='download' /></a>";
            } else {
                return "&nbsp;&nbsp;&nbsp;&nbsp;<span class='elbp_update_notification'><a href='{$link}'><img src='{$img}' alt='update' title='".get_string('newversionavailable', 'block_elbp').": {$remote->version} [".\get_string('versionupdatetype_'.$remote->update, 'block_elbp')."]' /></a></span>";
            }
        }

    }

    /**
     * Get the shortname (acronmym) you want to use for the ELBP
     */
    public function getELBPShortName(){

        $setting = \ELBP\Setting::getSetting('elbp_title_short');
        return ($setting) ? $setting : get_string('elbp', 'block_elbp');

    }

    /**
     * Get the defined full name of the ELBP
     * @return type
     */
    public function getELBPFullName(){

        $setting = \ELBP\Setting::getSetting('elbp_title_full');
        return ($setting) ? $setting : get_string('elbpex', 'block_elbp');

    }

    /**
     * Get the defined "my" name of the ELBP, e.g. "My ELBP"
     * @return type
     */
    public function getELBPMyName(){

        $setting = \ELBP\Setting::getSetting('elbp_title_my');
        return ($setting) ? $setting : get_string('myelbp', 'block_elbp');

    }

    /**
     * Get the theme layout setting for the full page views. Or "login" by default if undefined.
     * @return type
     */
    public function getThemeLayout(){

        $setting = \ELBP\Setting::getSetting('theme_layout');
        return ($setting) ? $setting : 'login';

    }

    /**
     * Get the defined dock position setting, or "left" by default if undefined
     * @return type
     */
    public function getDockPosition(){

        $setting = \ELBP\Setting::getSetting('dock_position');
        return ($setting) ? $setting : 'bottom';

    }

    /**
     * Get the www path to the logo we want to use when printing things
     * @global \ELBP\type $CFG
     * @return type
     */
    public static function getPrintLogo(){

        global $CFG;
        $logo = \ELBP\Setting::getSetting('print_logo');
        return ($logo) ? $CFG->wwwroot . '/blocks/elbp/download.php?f=' . \elbp_get_data_path_code($CFG->dataroot . DIRECTORY_SEPARATOR . 'ELBP' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $logo) : false;

    }

    /**
     * Get a global setting
     * @param type $setting
     * @return type
     */
    public function getSetting($setting){

        return \ELBP\Setting::getSetting($setting);

    }

    /**
     * Get the block_elbp strings
     * @return type
     */
    public function getString(){
        return $this->string;
    }

    /**
     * Get the \ELBP\DB() object loaded in the constructor
     * @return type
     */
    public function getDB(){
        return $this->ELBPDB;
    }

    /**
     * Ge tthe permissions array
     * @return type
     */
    public function getAccess(){
        return $this->access;
    }

    /**
     * What was the point of that? Could have just made loadPlugins public
     */
    public function reloadPlugins()
    {
        $this->loadPlugins();
    }

    /**
     * Select all the currently enabled plugins (except Student Profile as that is different and always @ the top) and put into an aray
     * @param bool $loadCustom (optional) (default: true)
     */
    private function loadPlugins($loadCustom = true, $options = array())
    {

        global $_SUPPRESS_ELBP_ERR;
        $_SUPPRESS_ELBP_ERR= false;
        if (isset($options['suppress_errors']) && $options['suppress_errors'] === true){
            $_SUPPRESS_ELBP_ERR = true;
        }

        // Reset it
        $this->plugins = false;

        $pluginArray = array();

        // If a group has been specified, only look for those
        if ($this->group){
            $plugins = $this->DB->get_records_select("lbp_plugins", "enabled = ? AND groupid = ?", array(1, $this->group), "ordernum ASC");
            if ($loadCustom){
                $custom = $this->DB->get_records_select("lbp_custom_plugins", "enabled = ? AND groupid = ?", array(1, $this->group), "ordernum ASC");
            }
        }
        else
        {
            $plugins = $this->DB->get_records_select("lbp_plugins", "enabled = ?", array(1), "name ASC");
            if ($loadCustom){
                $custom = $this->DB->get_records_select("lbp_custom_plugins", "enabled = ?", array(1), "name ASC");
            }
        }

        foreach($plugins as $plugin){
            $pluginArray[] = $plugin;
        }

        if ($loadCustom){
            foreach($custom as $plugin){
                $plugin->custom = true;
                $pluginArray[] = $plugin;
            }
        }

        usort($pluginArray, function($a, $b){
            return strnatcasecmp($a->name, $b->name);
        });



        foreach($pluginArray as $plugin)
        {


            if (isset($plugin->custom))
            {

                $obj = new Plugins\CustomPlugin($plugin->id);
                if ($obj->isValid())
                {
                    $this->plugins[] = $obj;
                }

            }
            else
            {

                try
                {
                    $obj = Plugins\Plugin::instaniate($plugin->name, $plugin->path);
                    if ($obj)
                    {
                        $this->plugins[] = $obj;
                    }
                }
                catch (ELBPException $e){
                    echo $e->getException();
                }

            }

        }

    }

    /**
     * If something in the exclude array, don't include that named plugin in the results
     * @param type $exclude
     * @return type
     */
    public function getAllPlugins( $exclude = array(), $orderBy = 'name' )
    {

        $results = array();
        $plugins = $this->DB->get_records_select("lbp_plugins", false, array(), "{$orderBy} ASC");
        $custom = $this->DB->get_records_select("lbp_custom_plugins", false, array(), "{$orderBy} ASC");

        $array = array();
        foreach($plugins as $plugin)
        {
            $array[] = $plugin;
        }

        foreach($custom as $plugin)
        {
            $plugin->custom = true;
            $array[] = $plugin;
        }

        usort($array, function($a, $b){
            return strnatcasecmp($a->name, $b->name);
        });

        foreach($array as $plugin)
        {

            if (isset($plugin->name) && in_array($plugin->name, $exclude)) continue;

            if (isset($plugin->custom))
            {

                $obj = new Plugins\CustomPlugin($plugin->id);
                if ($obj->isValid())
                {
                    $results[] = $obj;
                }

            }
            else
            {

                try
                {
                    $obj = Plugins\Plugin::instaniate($plugin->name, $plugin->path);
                    $results[] = $obj;
                }
                catch (ELBPException $e){
                    echo $e->getException();
                }

            }

        }

        return $results;

    }

    /**
     * Get all the groups in the db
     * @return type
     */
    public function getAllPluginGroups()
    {
        $groups = $this->DB->get_records_select("lbp_plugin_groups", false, array(), "ordernum ASC");
        return $groups;
    }


    /**
     * Get all the plugins assigned to a specific groupID
     * @param type $groupID
     * @return type
     */
    public function getPlugins($groupID = null){

        // If there is a group ID specified, loop through them and only use those with that group
        if (!is_null($groupID)){

            $pluginGroup = $this->DB->get_record("lbp_plugin_groups", array("id" => $groupID));
            if ($pluginGroup)
            {
                $layout = new \ELBP\PluginLayout($pluginGroup->layoutid);
                $group = $layout->getGroup($groupID);
                if ($group)
                {
                    return $group->plugins;
                }

            }

        }

        return $this->plugins;
    }

    /**
     * Go through the elbp/plugins directory and find all plugin folders
     */
    public function getDirectoryPlugins(){

        $results = array();

        $dir = $this->CFG->dirroot . '/blocks/elbp/plugins';

        $handle = opendir($dir);
        if ($handle)
        {

            while (false !== ($entry = readdir($handle)))
            {
                if ($entry == '.' || $entry == '..' || !is_dir($dir . '/' . $entry) || $entry == 'Custom' || $entry == 'Example') continue;
                $results[] = $entry;
            }

        }

        usort($results, function($a, $b){
            return strnatcasecmp($a, $b);
        });

        return $results;

    }

    /**
     * Get a list of all the directories in the /blocks directory
     * @return type
     */
    public function getListOfBlocks(){

        $dir = $this->CFG->dirroot . '/blocks';

        $results = array();
        $handle = opendir($dir);
        if ($handle)
        {

            while (false !== ($entry = readdir($handle)))
            {
                if ($entry == '.' || $entry == '..' || !is_dir($dir . '/' . $entry)) continue;
                $results[] = $entry;
            }

        }

        sort($results);
        return $results;

    }

    /**
     * Get list of local plugins
     * @return [type] [description]
     */
    public function getListOfLocal(){

        $dir = $this->CFG->dirroot . '/local';

        $results = array();
        $handle = opendir($dir);
        if ($handle)
        {

            while (false !== ($entry = readdir($handle)))
            {
                if ($entry == '.' || $entry == '..' || !is_dir($dir . '/' . $entry)) continue;
                $results[] = $entry;
            }

        }

        sort($results);
        return $results;

    }

    /**
     * Get a list of all the directories in the /mod directory
     * @return type
     */
    public function getListOfMods(){

        $dir = $this->CFG->dirroot . '/mod';

        $results = array();
        $handle = opendir($dir);
        if ($handle)
        {

            while (false !== ($entry = readdir($handle)))
            {
                if ($entry == '.' || $entry == '..' || !is_dir($dir . '/' . $entry)) continue;
                $results[] = $entry;
            }

        }

        sort($results);
        return $results;

    }

    /**
     * Load a given student into the plugin
     * @param int $studentID
     * @return boolean
     */
    public function loadStudent($studentID)
    {
        $user = $this->ELBPDB->getUser( array("type"=>"id", "val"=>$studentID) );
        if ($user){
            $this->student = $user;
            return true;
        }
        return false;
    }

    /**
     * Load a given course into the ELBP object
     * @param type $courseID
     */
    public function loadCourse($courseID)
    {
        $this->courseID = $courseID;
    }

    /**
     * Get all general reporting elements, not specific to a plugin
     * @global \ELBP\type $DB
     * @return type
     */
    public function getReportingElements(){

        global $DB;

        return $DB->get_records("lbp_plugin_report_elements", array("pluginid" => null), "id ASC");

    }


     /**
     * For the bc_dashboard reporting wizard - get all the data we can about Targets for these students,
     * then return the elements that we want.
     * @param type $students
     * @param type $elements
     */
    public function getAllReportingData($students, $elements)
    {

        global $DB;

        if (!$students || !$elements) return false;

        $data = array();
        $names = array();
        $els = array();

        foreach($elements as $element)
        {
            $record = $DB->get_record("lbp_plugin_report_elements", array("id" => $element));
            $names[] = $record->getstringname;
            $els[$record->getstringname] = $record->getstringcomponent;
        }

        $count = count($students);

        // Personal tutor
        if (in_array( 'reports:elbp:personaltutors', $names ))
        {

            $data['reports:elbp:personaltutors'] = '-';

            if ($count == 1)
            {

                $student = reset($students);
                $getPTs = elbp_get_users_personaltutors($student->id);
                if ($getPTs)
                {
                    $pts = array();
                    foreach($getPTs as $pt)
                    {
                        $pts[] = fullname($pt);
                    }
                    $data['reports:elbp:personaltutors'] = implode(', ', $pts);
                }

            }

        }

        // Traffic light status
        if ( in_array('reports:elbp:trafficlightstatus', $names) )
        {

            $data['reports:elbp:trafficlightstatus'] = '-';

            // Get all possible options
            $options = $this->getSetting('manual_student_progress');
            $options = unserialize($options);

            // Do we have some options the status can be?
            if ($options)
            {

                // For now, only do for individual students, don't average for classes - though we can do this later
                if ($count == 1)
                {

                    $student = reset($students);
                    $currentRank = \ELBP\Setting::getSetting('student_progress_rank', $student->id);

                    // Is this rank one of the options?
                    if (in_array($currentRank, $options['ranks']))
                    {

                        $key = array_search($currentRank, $options['ranks']);
                        $title = \elbp_html($options['titles'][$key]);
                        $data['reports:elbp:trafficlightstatus'] = $title;

                    }

                }

            }

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

    /**
     * Loop through all installed plugins and get a list of all the PHP extensions required
     * @return type
     */
    public function getAllRequiredExtensions(){

        $return = array();

        $plugins = $this->getPlugins();
        if ($plugins)
        {
            foreach($plugins as $plugin)
            {
                $extensions = $plugin->getRequiredExtensions();

                if ($extensions)
                {
                    foreach($extensions['core'] as $ext)
                    {
                        if (!array_key_exists($ext, $return))
                        {
                            $return[$ext] = array();
                        }
                        $return[$ext][$plugin->getTitle()] = get_string('required', 'block_elbp');
                    }
                    foreach($extensions['optional'] as $ext)
                    {
                        if (!array_key_exists($ext, $return))
                        {
                            $return[$ext] = array();
                        }
                        $return[$ext][$plugin->getTitle()] = get_string('optional', 'block_elbp');
                    }
                }
            }
        }

        return $return;

    }

    /**
     * Display the configuration page
     * @global \ELBP\type $CFG
     * @global type $MSGS
     * @global type $FORMVALS
     * @global type $OUTPUT
     * @global \ELBP\type $DB
     * @param type $view
     * @return boolean
     */
    public function displayConfig($view)
    {
        global $CFG, $DB, $MSGS, $FORMVALS, $OUTPUT, $PAGE, $DBC;

        $TPL = new \ELBP\Template();
        $TPL->set("ELBP", $this);
        try {
            $TPL->set("MSGS", $MSGS)->set("FORMVALS", $FORMVALS)->set("OUTPUT", $OUTPUT);

            switch($view)
            {

                case 'main':

                  // Require hub & stats
                  require_once $CFG->dirroot . '/local/df_hub/lib.php';
                  require_once './classes/df_hub/stats.php';

                  // Recent activity
                  $TPL->set("logs", \ELBP\Log::parseListOfLogs( \ELBP\Log::getRecentLogs(15) ));

                  // DF Hub Site
                  $site = new \DF\Site();
                  $TPL->set("site", $site);

                  // Plugins
                  $plugins = $this->getAllPlugins();
                  $TPL->set("plugins", $plugins);

                  // Stats
                  $TPL->set("stats", $stats);

                break;

                // MIS configuration
                case 'mis':

                    $TPL->set("connections", \ELBP\MIS\Manager::listConnections());

                    $plugins = $this->getAllPlugins();
                    usort($plugins, function($a, $b){
                        return strnatcasecmp($a->getTitle(), $b->getTitle());
                    });

                    $TPL->set("plugins", $plugins);

                    $dbTypes = array();

                    // Get a list of supported DB types
                    foreach( glob($CFG->dirroot . '/blocks/elbp/classes/db/*.class.php') as $type ){
                        $typeName = str_replace($CFG->dirroot . '/blocks/elbp/classes/db/', "", $type);
                        $typeName = str_replace(".class.php", "", $typeName);

                        // Include class
                        require_once $type;

                        $className = '\ELBP\MIS\\'.$typeName;

                        $types = call_user_func( array($className, 'getAcceptedTypes') );

                        $dbTypes[$typeName] = $types;

                    }

                    $TPL->set("dbTypes", $dbTypes);

                break;

                case 'settings':

                    ksort($PAGE->theme->layouts);
                    $categories = \core_course_category::make_categories_list();
                    $includedCategories = explode(",", \ELBP\Setting::getSetting('specific_course_cats'));

                    $setting = $this->getSetting('manual_student_progress');

                    $TPL->set("manualProgressColours", unserialize($setting));
                    $TPL->set("themeLayouts", $PAGE->theme->layouts);
                    $TPL->set("categories", $categories);
                    $TPL->set("includedCategories", $includedCategories);


                break;

                case 'plugins':

                    // Get all the plugins and the various other variables we need to install more plugins
                    $plugins = $this->getAllPlugins();
                    $nameOrderedPlugins = $plugins;
                    usort($nameOrderedPlugins, function($a, $b){
                        return strnatcasecmp($a->getTitle(), $b->getTitle());
                    });

                    $external = array(
                      'blocks' => $this->getListOfBlocks(),
                      'local' => $this->getListOfLocal(),
                      'mod' => $this->getListOfMods()
                    );

                    $TPL->set("plugins", $plugins);
                    $TPL->set("nameOrderedPlugins", $nameOrderedPlugins);
                    $TPL->set("dir_plugins", $this->getDirectoryPlugins());
                    $TPL->set("dir_external", $external);
                    $TPL->set("groups", $this->getAllPluginGroups());
                    $TPL->set("block_version", $this->getBlockVersion());
                    $TPL->set("layouts", \ELBP\PluginLayout::getAllPluginLayouts());

                break;

                case 'environment':

                    $extensions = $this->getAllRequiredExtensions();
                    $TPL->set("extensions", $extensions);

                    $tables = false;

                    // Use an MIS connection DB object to connect to the actual Moodle database
                    $conn = \ELBP\MIS\Manager::getMoodleConnectionType();

                    // Only supports MySQL at the moment, as I don't know how to replicate it with others
                    if ($conn){

                        try {

                            $conn->connect( array('host' => $CFG->dbhost, 'user' => $CFG->dbuser, 'pass' => $CFG->dbpass, 'db' => $CFG->dbname) );

                            // Get the information about all the ELBP tables in the Moodle database
                            $tables = $conn->getTableInfo(null, $CFG->prefix . 'lbp_');
                            $TPL->set("tables", $tables);
                            $TPL->set("purgeTables", self::getSupportDBTablesForPurging());

                        } catch (\ELBP\ELBPException $e){

                        }

                    } else {
                        print_error( get_string('env:mysqlonly', 'block_elbp') );
                    }




                break;

                case 'uninstall':

                    // Uninstall a plugin
                    $pluginID = optional_param('plugin', false, PARAM_CLEAN);
                    $customPluginID = optional_param('customplugin', false, PARAM_INT);
                    $force = optional_param('force', false, PARAM_INT);

                    if (!$pluginID && !$customPluginID) return false;

                    // If we are forcing an uninstall, that's because we messed something up and can't load
                    // the plugin object
                    if ($pluginID && $force == 1){
                        \ELBP\Plugins\Plugin::forceUninstall($pluginID);
                        $MSGS['success'] = get_string('pluginuninstalled', 'block_elbp') . ' - ' . $pluginID;
                        $this->displayConfig('plugins');
                        return false;
                    } else {

                        if ($pluginID){
                            $plugin = $this->getPluginByID($pluginID, false, true);
                        } elseif ($customPluginID){
                            $plugin = $this->getPluginByID($customPluginID, true, true);
                        }

                        $TPL->set("plugin", $plugin);
                        $TPL->set("affectedTables", $plugin->getDBTables());

                    }

                break;

                case 'actions':

                    // User actions
                    $capabilities = elbp_get_all_capabilities();
                    $userCapabilities = elbp_get_all_user_capabilities();

                    $TPL->set("capabilities", $capabilities);
                    $TPL->set("userCapabilities", $userCapabilities);

                break;



                case 'course':

                    $id = optional_param('id', false, PARAM_INT);

                    $course = $this->DB->get_record("course", array("id" => $id));
                    if (!$course){
                        print_error( get_string('invalidcourse', 'block_elbp') . '!' );
                    }

                    $layouts = \ELBP\PluginLayout::getAllPluginLayouts(true);

                    $default = $this->getSetting('course_' . $id . '_plugins_layout');

                    $TPL->set("id", $id)
                        ->set("course", $course)
                        ->set("layouts", $layouts)
                        ->set("default", $default);

                break;


            }

            $TPL->load($this->CFG->dirroot . '/blocks/elbp/tpl/config/'.$view.'.html');
            $TPL->display();
        } catch (\ELBP\ELBPException $e){
            echo $e->getException();
        }
    }

    /**
     * Update setting value
     * @param type $setting
     * @param type $value
     * @param type $userID
     * @return type
     */
    public function updateSetting($setting, $value, $userID = null)
    {
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_SETTINGS, LOG_ACTION_ELBP_SETTINGS_UPDATED_SETTING, $userID, array(
            "setting" => $setting,
            "value" => (is_null($value)) ? '' : $value
        ));
        return \ELBP\Setting::setSetting($setting, $value, $userID);
    }

    /**
     * Delete a setting completely
     * @param type $setting
     * @param type $userID
     * @return type
     */
    public function deleteSetting($setting, $userID = null)
    {

        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_SETTINGS, LOG_ACTION_ELBP_SETTINGS_DELETED_SETTING, $userID, array(
            "setting" => $setting
        ));
        return \ELBP\Setting::deleteSetting($setting, $userID);

    }

    /**
     * Save the data from the configuration page
     * @param type $view
     */
    public function saveConfig($view)
    {

        switch($view)
        {
            case 'main':
                $this->saveConfigMain();
            break;
            case 'settings':
                $this->saveConfigSettings();
            break;
            case 'mis':
                $this->saveConfigMIS();
            break;
            case 'plugins':
                $this->saveConfigPlugins();
            break;
            case 'actions':
                $this->saveConfigActions();
            break;
            case 'environment':
                $this->saveConfigEnv();
            break;
            case 'import':
                $this->saveConfigImport();
            break;
            case 'course':
                $this->saveConfigCourse();
            break;
        }

    }

    /**
     * Save Environment data
     * @global \ELBP\type $DB
     * @global \ELBP\type $MSGS
     * @return boolean
     */
    private function saveConfigEnv()
    {

        global $DB, $MSGS;

        $settings = $_POST;

        // Purge database tables of old data
        if (isset($_POST['submit_purge_db_tables']) && !empty($settings['purge_table']) && !empty($settings['purge_date']))
        {

            $datetime =  \DateTime::createFromFormat('d-m-Y H:i:s', $settings['purge_date'] . ' 00:00:00');
            $unix = $datetime->format("U");

            // If this table can be purged
            if (in_array($settings['purge_table'], self::getSupportDBTablesForPurging()))
            {

                $field = false;

                switch($settings['purge_table'])
                {
                    case 'lbp_att_punc_history':
                        $field = 'timestamp';
                    break;
                }

                if ($field !== false)
                {

                    // Delete the old records
                    $DB->delete_records_select($settings['purge_table'], "{$field} < ?", array($unix));
                    $MSGS['success'] = get_string('purgedbtables:complete', 'block_elbp');
                    $MSGS['success'] = str_replace('%t%', $settings['purge_table'], $MSGS['success']);
                    return true;

                }

            }

        }

    }

    /**
     * Save User Actions configuration and run POST scripts
     * @global \ELBP\type $MSGS
     * @global \ELBP\type $DB
     * @return boolean
     */
    private function saveConfigActions()
    {

        global $MSGS, $DB, $DBC;

        $settings = $_POST;

        // Reset colours
        if (isset($settings['submit_reset_colours']) && isset($settings['reset_colours_for']))
        {

            // Reset colours for everyone
            if ($settings['reset_colours_for'] == 'ALL')
            {

                $DB->delete_records_select("lbp_settings", "pluginid IS NOT NULL and userid IS NOT NULL and (setting = 'header_bg_col' OR setting = 'header_font_col')");
                $DB->delete_records_select("lbp_custom_plugin_settings", "pluginid IS NOT NULL and userid IS NOT NULL and (setting = 'header_bg_col' OR setting = 'header_font_col')");
                $MSGS['success'] = get_string('execute:coloursreset', 'block_elbp');

            }
            elseif ($settings['reset_colours_for'] == 'USER' && isset($settings['for_user']))
            {

                // Reset for a specific user
                $username = $settings['for_user'];
                $user = $DB->get_record("user", array("username" => $username));

                if (!$user){
                    $MSGS['errors'] = get_string('invaliduser', 'block_elbp') . " : " . $username;
                    return false;
                }

                $DB->delete_records_select("lbp_settings", "pluginid IS NOT NULL and userid = ? and (setting = 'header_bg_col' OR setting = 'header_font_col')", array($user->id));
                $DB->delete_records_select("lbp_custom_plugin_settings", "pluginid IS NOT NULL and userid = ? and (setting = 'header_bg_col' OR setting = 'header_font_col')", array($user->id));
                $MSGS['success'] = get_string('execute:coloursresetforuser', 'block_elbp') . " " . fullname($user) . " ({$user->username})";

            }

            return true;

        }

        // Clear personal tutor links
        elseif (isset($settings['submit_clear_personal_tutors']) && isset($settings['clear_mentees_for']))
        {
            if($settings['clear_mentees_for'] == 'ALL')
            {
                // first clear the records from the role assignments table
                $DB->delete_records_select("role_assignments","roleid = ?",array(getRole(\ELBP\PersonalTutor::getPersonalTutorRole())));

                // next clear the lbp_tutor_assignments table
                $DB->delete_records("lbp_tutor_assignments");

                $MSGS['success'] = get_string('execute:clearmentee', 'block_elbp');

            }
            elseif ($settings['clear_mentees_for'] == 'USER' && isset($settings['for_pt']))
            {
                // Reset for a specific user
                $username = $settings['for_pt'];
                $user = $DB->get_record("user", array("username" => $username));

                if (!$user){
                    $MSGS['errors'] = get_string('invaliduser', 'block_elbp') . " : " . $username;
                    return false;
                }else{
                    $tutorID = $user->id;
                }
                #use getMenteeonTutor to get mentees of stated tutor
                $DBC = new \ELBP\DB();
                $mentees = $DBC->getMenteesOnTutor($tutorID);

                if($mentees){

                    $errors = 0;
                    foreach($mentees as $mentee){
                        $PT = new \ELBP\PersonalTutor();
                        $PT->loadTutorID($user->id);
                        if(!$PT->removeMentee($mentee->id)){
                            $errors ++;
                        }
                    }
                    if($errors == 0){
                        $MSGS['success'] = get_string('execute:clearmenteeforuser', 'block_elbp') . " " . fullname($user) . " ({$user->username})";
                    }else{
                        $MSGS['errors'] = get_string('execute:notclearmenteeforuser', 'block_elbp') . " " . fullname($user) . " ({$user->username})";
                    }
                }else{
                    $MSGS['errors'] = get_string('execute:nomenteesforuser', 'block_elbp') . " " . fullname($user) . " ({$user->username})";
                }

            }

        }

        // Capabilities
        elseif (isset($settings['submit_user_capability']) && !empty($settings['user']) && !empty($settings['capability']) && in_array($settings['value'], array(0, 1))){

            // Reset for a specific user
            $username = $settings['user'];
            $user = $DB->get_record("user", array("username" => $username));

            if (!$user){
                $MSGS['errors'] = get_string('invaliduser', 'block_elbp') . " : " . $username;
                return false;
            }

            // Check to see if they already have a record
            $check = $DB->get_record("lbp_user_capabilities", array("userid" => $user->id, "capabilityid" => $settings['capability']));
            if ($check)
            {
                $check->value = $settings['value'];
                $DB->update_record("lbp_user_capabilities", $check);
            }
            else
            {
                $ins = new \stdClass();
                $ins->userid = $user->id;
                $ins->capabilityid = $settings['capability'];
                $ins->value = $settings['value'];
                $DB->insert_record("lbp_user_capabilities", $ins);
            }

            $MSGS['success'] = get_string('usercapabilityupdated', 'block_elbp');
            return true;

        }

        // Delete user capability record
        elseif (isset($settings['submit_delete_user_capability_x'], $settings['submit_delete_user_capability_y']) && ctype_digit($settings['id'])){

            $id = $settings['id'];
            $DB->delete_records("lbp_user_capabilities", array("id" => $id));

            $MSGS['success'] = get_string('usercapabilityupdated', 'block_elbp');
            return true;

        }

    }

    /**
     * Create directory in Moodledata to store files
     * Will create the directory in: /moodledata/ELBP/%pluginname%/$dir
     * Will attempt to create the parent directories if they don't exist yet
     * Uses chmod of 0764:
     *      Owner: rwx,
     *      Group: rw,
     *      Public: r
     *  @param type $dir
     */
    public function createDataDirectory($dir)
    {

        global $CFG;

        // First check if a directory for this plugin exists - Should do as they should be created on install

        // Now try and make the actual dir we want
        if (!is_dir( $CFG->dataroot . '/ELBP/' . $dir )){
            if (is_writeable($CFG->dataroot . '/ELBP/')){
                if (!mkdir($CFG->dataroot . '/ELBP/'. $dir, 0764, true)){
                    return false;
                }
            } else {
                return false;
            }
        }

        // If we got this far must be ok
        return true;

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
     * Get required headers for csv import
     * @return string
     */
    private function getImportCsvHeaders(){
        $headers = array();
        $headers[] = 'Student_ID';
        $headers[] = 'Tutor_ID';
        $headers[] = 'Tutor_Name';
        return $headers;
    }

    /**
     * Create a unique code for the path to a file in the dataroot so that we can easily send that file
     * to the browser without exposing the path
     * @param string $path
     */
    protected function createDataPathCode($path){

        return \elbp_create_data_path_code($path);

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

        $file = $CFG->dataroot . '/ELBP/templates/template.csv';
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

        $file = $CFG->dataroot . '/ELBP/templates/example.csv';
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


        //get all of the students
        $students = $DB->get_records("user", array("deleted" => 0, "institution" => 'student'),'','*',0,50);

        foreach ($students as $student){

            $data = array();
            $stuUSN = $student->username;

         #  Not sure if this is required
         #    if(ctype_alpha($stuUSN)){ //check for id without letters
         #       $data[] = '876543'; //some random number to fill an empty space
         #   }else{
                $data[] = $stuUSN;
         #   }


            //for each student we need to pick a random tutor to place in data[]
            $staff = $DB->get_records("user", array("institution" => 'staff'),'rand()','*',0,1);
            foreach($staff as $tutor){
                $data[] = $tutor->id;
                $usn = $tutor->username;
                if(strpos($usn,'@') !== FALSE){
                    $usnArr = explode('@',$usn);
                    $usn = $usnArr[0];
                }
                $data[] = $usn;
            }

            fputcsv($fh,$data);
        }

        fclose($fh);
        return $code;
    }





    /**
     * Save the course config settings
     * @global \ELBP\type $MSGS
     */
    private function saveConfigCourse()
    {

        global $MSGS;

        $id = required_param('id', PARAM_INT);

        $settings = $_POST;

        // Default layout
        if (isset($settings['plugins_layout']))
        {

            // If it's blank, we want to use the site default, so delete any setting we already saved for it
            if ($settings['plugins_layout'] == '')
            {
                $this->deleteSetting('course_' . $id . '_plugins_layout');
            }
            // Otherwise, save the setting
            else
            {
                $this->updateSetting('course_' . $id . '_plugins_layout', $settings['plugins_layout']);
            }

            $MSGS['success'] = get_string('settingsupdated', 'block_elbp');

        }

        return true;

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

        $output = '';

        // Check file exists
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
            //return array('success' => false,'error' => $str);
        }

        $i = 1;
        $record = new \stdClass();

        while( ($row = fgetcsv($fh)) !== false ){
            $num = count($row);
            $i++;
            $errorCount = 0;
            for($c = 0 ; $c < $num; $c++){
                if($c == 0){// student user name
                    $stuUSN = $row[$c];
                    //check student id exists in db
                    $stuCheck = $DB->get_record("user",array("username"=>$stuUSN),'id');
                    if($stuCheck){
                        //if it does then add to the $record array
                        $record->studentid  = $stuCheck->id;
                    }else{
                        //otherwise increase error count ($errorCount)
                        $errorCount ++;
                    }

                }elseif($c == 2){// tutor id
                    $tutUSN = $row[$c];
                    //check tutor id exists in db
                    $tutCheck = $DB->get_record("user",array("username"=>$tutUSN),'id,username');
                    if($tutCheck){
                        //if it does then add to the $record array
                        $record->tutorid  = $tutCheck->id;
                    }else{
                        //otherwise increase error count ($errorCount)
                        $errorCount ++;
                    }

                }

            }

            if ($errorCount == 0){
                $tutor = new \ELBP\PersonalTutor;
                $tutor->loadTutorID($record->tutorid);
                $tutor->assignMentee($record->studentid);
            }

        }

        fclose($fh);

        return array('success' => true, 'output' => get_string('import:stututSuccess', 'block_elbp'));

    }

    /**
     * Save main ELBP configuration data.
     * This is stuff like the name.
     * @global \ELBP\type $MSGS
     * @return boolean
     */
    private function saveConfigMain(){}

    /**
     * Save the Settings configuration data
     * @global \ELBP\type $MSGS
     * @return boolean
     */
    private function saveConfigSettings()
    {

        global $MSGS, $CFG;

        $settings = $_POST;


        if (isset($settings['submitconfig']))
        {

            // Remove so doesn't get put into lbp_settings
            unset($settings['submitconfig']);

            // Checkboxes need int values
            $settings['elbp_use_gradients'] = (isset($settings['elbp_use_gradients'])) ? '1' : '0';
            $settings['enable_email_alerts'] = (isset($settings['enable_email_alerts'])) ? '1' : '0';
            $settings['academic_year_enabled'] = (isset($settings['academic_year_enabled'])) ? '1' : '0';


            // Progress colours & descs will be arrays
            $manualProgressColours = array();
            $manualProgressColours['ranks'] = (isset($settings['progress_ranks'])) ? array_values($settings['progress_ranks']) : false;
            $manualProgressColours['titles'] = (isset($settings['progress_titles'])) ? array_values($settings['progress_titles']) : false;
            $manualProgressColours['colours'] = (isset($settings['progress_colours'])) ? array_values($settings['progress_colours']) : false;
            $manualProgressColours['desc'] = (isset($settings['progress_desc'])) ? array_values($settings['progress_desc']) : false;

            // Make sure they have a rank, otherwise remove it
            if ($manualProgressColours['ranks'])
            {
                foreach($manualProgressColours['ranks'] as $key => $rank)
                {
                    if (!ctype_digit($rank))
                    {
                        unset($manualProgressColours['ranks'][$key]);
                        unset($manualProgressColours['titles'][$key]);
                        unset($manualProgressColours['colours'][$key]);
                        unset($manualProgressColours['desc'][$key]);
                    }
                }
            }

            // Resort array keys
            $manualProgressColours['ranks'] = ($manualProgressColours['ranks']) ? array_values($manualProgressColours['ranks']) : null;
            $manualProgressColours['titles'] = ($manualProgressColours['titles']) ? array_values($manualProgressColours['titles']) : null;
            $manualProgressColours['colours'] = ($manualProgressColours['colours']) ? array_values($manualProgressColours['colours']) : null;
            $manualProgressColours['desc'] = ($manualProgressColours['desc']) ? array_values($manualProgressColours['desc']) : null;

            $settings['manual_student_progress'] = serialize($manualProgressColours);

            unset($settings['progress_ranks']);
            unset($settings['progress_titles']);
            unset($settings['progress_colours']);
            unset($settings['progress_desc']);

            // Specific course categories
            $settings['specific_course_cats'] = @implode(",", $settings['specific_course_cats']);

            // Checkboxes need int values
            if (isset($settings['logo_delete_current'])){

                $currentLogo = $this->getSetting('print_logo');

                if ($currentLogo){

                    if (file_exists($this->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $currentLogo)){
                        unlink($this->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $currentLogo);
                    }

                    $this->deleteSetting('print_logo');
                }

                unset($settings['logo_delete_current']);

            }

            if (!empty($settings['academic_year_start_date'])){
                $settings['academic_year_start_date'] = strtotime($settings['academic_year_start_date']);
            }


            foreach( (array)$settings as $setting => $value ){
                $this->updateSetting($setting, $value);
            }

            // FILES for logo img
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0){

                // Make sure data directory exists
                $this->createDataDirectory('uploads');

                 $fInfo = finfo_open(FILEINFO_MIME_TYPE);
                 $mime = finfo_file($fInfo, $_FILES['logo']['tmp_name']);
                 finfo_close($fInfo);

                 $array = array('image/bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/tiff', 'image/pjpeg');
                 if (in_array($mime, $array))
                 {
                      $result = move_uploaded_file($_FILES['logo']['tmp_name'], $this->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR  . $_FILES['logo']['name']);
                      if ($result)
                      {
                          $this->updateSetting('print_logo', $_FILES['logo']['name']);
                          \elbp_create_data_path_code($this->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR  . $_FILES['logo']['name']);
                      }
                      else
                      {
                          $MSGS['errors'] = get_string('uploads:unknownerror', 'block_elbp') . ' - ' . $_FILES['logo']['name'];
                      }
                 }
                 else
                 {
                     $MSGS['errors'] = get_string('uploads:invalidmimetype', 'block_elbp') . ' - ' . $_FILES['logo']['name'];
                 }


            }

            // User Guides

            // Student user guide
            if (isset($_FILES['student_user_guide']) && $_FILES['student_user_guide']['error'] == 0){

                 $fInfo = finfo_open(FILEINFO_MIME_TYPE);
                 $mime = finfo_file($fInfo, $_FILES['student_user_guide']['tmp_name']);
                 finfo_close($fInfo);

                 $array = array('application/pdf');
                 if (in_array($mime, $array))
                 {
                      $result = move_uploaded_file($_FILES['student_user_guide']['tmp_name'], $CFG->dataroot . '/ELBP/' . $_FILES['student_user_guide']['name']);
                      if ($result)
                      {
                          $this->updateSetting('student_user_guide', $_FILES['student_user_guide']['name']);
                          // Create download code
                          \elbp_create_data_path_code($CFG->dataroot . '/ELBP/' . $_FILES['student_user_guide']['name']);
                      }
                      else
                      {
                          $MSGS['errors'] = get_string('uploads:unknownerror', 'block_elbp') . ' - ' . $_FILES['student_user_guide']['name'];
                      }
                 }
                 else
                 {
                     $MSGS['errors'] = get_string('uploads:invalidmimetype', 'block_elbp') . ' - ' . $_FILES['student_user_guide']['name'];
                 }

            }

            // Staff user guide
            if (isset($_FILES['staff_user_guide']) && $_FILES['staff_user_guide']['error'] == 0){

                 $fInfo = finfo_open(FILEINFO_MIME_TYPE);
                 $mime = finfo_file($fInfo, $_FILES['staff_user_guide']['tmp_name']);
                 finfo_close($fInfo);

                 $array = array('application/pdf');
                 if (in_array($mime, $array))
                 {
                      $result = move_uploaded_file($_FILES['staff_user_guide']['tmp_name'], $CFG->dataroot . '/ELBP/' . $_FILES['staff_user_guide']['name']);
                      if ($result)
                      {
                          $this->updateSetting('staff_user_guide', $_FILES['staff_user_guide']['name']);
                          // Create download code
                          \elbp_create_data_path_code($CFG->dataroot . '/ELBP/' . $_FILES['staff_user_guide']['name']);
                      }
                      else
                      {
                          $MSGS['errors'] = get_string('uploads:unknownerror', 'block_elbp') . ' - ' . $_FILES['staff_user_guide']['name'];
                      }
                 }
                 else
                 {
                     $MSGS['errors'] = get_string('uploads:invalidmimetype', 'block_elbp') . ' - ' . $_FILES['staff_user_guide']['name'];
                 }

            }

            $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
            return true;

        }

    }

    /**
     * Save plugins configuration / run POST scripts
     * This is stuff like installing new plugins, uninstalling, enabling, etc...
     * @global \ELBP\type $DB
     * @global \ELBP\type $MSGS
     * @return boolean
     */
    private function saveConfigPlugins()
    {

        global $DB, $MSGS;

        // Install
        if (isset($_POST['install_new_plugin']))
        {

            if ( ( empty($_POST['plugin_name']) && empty($_POST['plugin_name_2']) ) || empty($_POST['plugin_path'])) return false;

            if (!empty($_POST['plugin_name'])){
                $name = $_POST['plugin_name'];
            } elseif (!empty($_POST['plugin_name_2'])){
                $name = $_POST['plugin_name_2'];
            }

            try
            {
                $plugin = \ELBP\Plugins\Plugin::instaniate($name, $_POST['plugin_path']);
                $MSGS['success'] = get_string('installed', 'block_elbp') . ' ' . $plugin->getName() . ' ' . get_string('plugin', 'block_elbp');
            }
            catch (\ELBP\ELBPException $e){
                $MSGS['errors'] = $e->getException();
            }

            return;

        }

        // Create new custom plugin
        if (isset($_POST['add_new_custom_plugin']) && !empty($_POST['title']))
        {

            $title = $_POST['title'];

            $title= trim($title);
            $title = preg_replace("/[^a-z0-9 ]/i", "", $title);

            $check = $this->DB->get_record("lbp_plugins", array("name" => $title));
            $check2 = $this->DB->get_record("lbp_custom_plugins", array("name" => $title));
            // Name must be unique
            if ($check || $check2){
                $MSGS['errors'] = get_string('pluginnameexists', 'block_elbp');
                return false;
            }

            // Create the plugin record
            $plugin = new \ELBP\Plugins\CustomPlugin();
            $plugin->setName($title);
            $plugin->createPlugin();
            $MSGS['success'] = get_string('created', 'block_elbp') . ' ' . $plugin->getTitle() . ' ' . get_string('plugin', 'block_elbp');
            return true;

        }

        // Enable/Disable
        if (isset($_POST['enable_disable_plugin_y'], $_POST['enable_disable_plugin_x']))
        {

            $plugin = $this->DB->get_record("lbp_plugins", array("id"=>$_POST['plugin_id']));
            if (!$plugin) return false;

            $plugin->enabled = ($plugin->enabled == 1) ? 0 : 1;
            if ($plugin->enabled == 1) $msg = get_string('pluginenabled', 'block_elbp');
            else $msg = get_string('plugindisabled', 'block_elbp');

            $this->DB->update_record("lbp_plugins", $plugin);
            $MSGS['success'] = $msg;
            return true;

        }

        // Enable/Disable Custom
        if (isset($_POST['enable_disable_custom_plugin_y'], $_POST['enable_disable_custom_plugin_x']))
        {
            $plugin = $this->DB->get_record("lbp_custom_plugins", array("id" => $_POST['plugin_id']));
            if (!$plugin) return false;

            $plugin->enabled = ($plugin->enabled == 1) ? 0 : 1;
            if ($plugin->enabled == 1) $msg = get_string('pluginenabled', 'block_elbp');
            else $msg = get_string('plugindisabled', 'block_elbp');

            $this->DB->update_record("lbp_custom_plugins", $plugin);
            $MSGS['success'] = $msg;
            return true;

        }

        // Uninstall plugin
        if (isset($_POST['uninstall_plugin']))
        {

            $check = $this->DB->get_record("lbp_plugins", array("id"=>$_POST['plugin_id']));
            if (!$check) return false;

            try
            {
                $plugin = \ELBP\Plugins\Plugin::instaniate($check->name);
                $plugin->uninstall();
                $MSGS['success'] = get_string('pluginuninstalled', 'block_elbp');
                return true;
            }
            catch (\ELBP\ELBPException $e){
                $MSGS['errors'] = $e->getException();
                return false;
            }

        }

        // Uninstall custom plugin
        if (isset($_POST['uninstall_custom_plugin']))
        {

            $check = $this->DB->get_record("lbp_custom_plugins", array("id" => $_POST['plugin_id']));
            if (!$check) return false;

            $plugin = new \ELBP\Plugins\CustomPlugin($check->id);
            if ($plugin)
            {
                $plugin->delete();
                $MSGS['success'] = get_string('pluginuninstalled', 'block_elbp');
            }

            return true;

        }

        // Enable/Disable plugin group
        if (isset($_POST['enable_disable_group_y'], $_POST['enable_disable_group_x']))
        {

            $group = $this->DB->get_record("lbp_plugin_groups", array("id"=>$_POST['group_id']));
            if (!$group) return false;

            $group->enabled = ($group->enabled == 1) ? 0 : 1;
            if ($group->enabled == 1) $msg = get_string('groupenabled', 'block_elbp');
            else $msg = get_string('groupdisabled', 'block_elbp');

            $this->DB->update_record("lbp_plugin_groups", $group);
            $MSGS['success'] = $msg;
            return true;
        }

        // Upgrade plugin to latest version
        if (isset($_POST['upgrade_plugin_y'], $_POST['upgrade_plugin_x']))
        {

            // Are we trying to update a plugin?
            $upgradePluginID = $_POST['plugin_id'];
            $getPlugin = $DB->get_record("lbp_plugins", array("id" => $upgradePluginID));
            if ($getPlugin){
                try {

                    $upgradePlugin = \ELBP\Plugins\Plugin::instaniate($getPlugin->name);
                    $blockVersion = $this->getBlockVersion();
                    $pluginVersion = $upgradePlugin->getVersion();

                    if ($pluginVersion < $blockVersion)
                    {
                        // Upgrade the plugin
                        $upgradePlugin->upgrade();
                        // Set the version to same as block
                        $upgradePlugin->setVersion($blockVersion);
                        $upgradePlugin->updatePlugin();
                    }

                    $MSGS['success'] = get_string('pluginupdated', 'block_elbp');
                    return true;

                } catch (\ELBP\ELBPException $e){
                    $MSGS['errors'] = $e->getMessage();
                    return false;
                }
            }

            return false;

        }

        // Export custom plugin to XML
        if (isset($_POST['export_custom_plugin_y'], $_POST['export_custom_plugin_x']))
        {

            $pluginID = $_POST['custom_plugin_id'];
            $check = $DB->get_record("lbp_custom_plugins", array("id" => $pluginID));
            if (!$check) return false;

            $plugin = new \ELBP\Plugins\CustomPlugin($pluginID);
            if (!$plugin->isValid()) return false;

            $XML = $plugin->exportXML();

            $name = preg_replace("/[^a-z0-9]/i", "", $plugin->getTitle());
            $name = str_replace(" ", "_", $name);

            header('Content-disposition: attachment; filename=custom_plugin_'.$name.'.xml');
            header('Content-type: text/xml');
            echo $XML->asXML();
            exit;

        }

        // Import a custom plugin from XML
        if (isset($_POST['import_custom_plugin']) && isset($_FILES['plugin_xml']) && $_FILES['plugin_xml']['error'] == 0)
        {

            $file = $_FILES['plugin_xml'];
            $result = \ELBP\Plugins\CustomPlugin::createFromXML($file['tmp_name']);

            if ($result['success'] == true){
                $MSGS['success'] = $result['output'];
                return true;
            } else {
                $MSGS['errors'] = $result['error'];
                return false;
            }

        }



        if (isset($_POST['submit_plugin_layouts']))
        {

            $layoutIDsSubmitted = array();
            $groupIDsSubmitted = array();

            $layoutIDs = (isset($_POST['plugin_layouts_id'])) ? $_POST['plugin_layouts_id'] : false;
            $layoutNames = (isset($_POST['plugin_layouts_name'])) ? $_POST['plugin_layouts_name'] : false;
            $layoutDefaults = (isset($_POST['plugin_layouts_default'])) ? $_POST['plugin_layouts_default'] : false;
            $layoutEnableds = (isset($_POST['plugin_layouts_enabled'])) ? $_POST['plugin_layouts_enabled'] : false;

            // There can only be one default, so find the first default and remove all the rest
            $thereCanBeOnlyOne = array();
            if ($layoutDefaults)
            {
                reset($layoutDefaults);
                $key = key($layoutDefaults);
                $val = current($layoutDefaults);
                $thereCanBeOnlyOne[$key] = $val;
                unset($layoutDefaults);
                $layoutDefaults = $thereCanBeOnlyOne;
            }

            if (count($layoutIDs) <> count($layoutNames)) return false;

            // Loop through
            if ($layoutIDs)
            {
                foreach($layoutIDs as $layoutNum => $layoutID)
                {

                    $layout = new \ELBP\PluginLayout($layoutID);
                    $layout->setName($layoutNames[$layoutNum]);
                    $layout->setDefault( (isset($layoutDefaults[$layoutNum])) ? $layoutDefaults[$layoutNum] : 0 );
                    $layout->setEnabled( (isset($layoutEnableds[$layoutNum])) ? $layoutEnableds[$layoutNum] : 0 );

                    $groupIDs = @$_POST['plugin_layouts_groups_id'][$layoutNum];
                    $groupNames = $_POST['plugin_layouts_groups_name'][$layoutNum];
                    $groupEnableds = (isset($_POST['plugin_layouts_groups_enabled'][$layoutNum])) ? $_POST['plugin_layouts_groups_enabled'][$layoutNum] : array();

                    if (!empty($groupIDs))
                    {
                        foreach($groupIDs as $groupNum => $groupID)
                        {
                            $groupPlugins = (isset($_POST['layout_group_plugins'][$layoutNum][$groupNum])) ? $_POST['layout_group_plugins'][$layoutNum][$groupNum] : array();
                            $layout->addGroup($groupID, $groupNames[$groupNum], ((isset($groupEnableds[$groupNum])) ? $groupEnableds[$groupNum] : 0), $groupPlugins );
                        }
                    }

                    $layout->save();

                    // Append to submitted ids
                    $layoutIDsSubmitted[] = $layout->getID();


                    // Find the ids of all the groups on it now and append to submitted groups
                    if ($layout->getGroups())
                    {
                        foreach($layout->getGroups() as $group)
                        {
                            $groupIDsSubmitted[] = $group->id;
                        }
                    }

                }
            }

            // Delete layouts that were not submitted this time
            $placeholders = \elbp_implode_placeholders($layoutIDsSubmitted);
            if ($placeholders){
                $DB->execute("DELETE FROM {lbp_plugin_layouts} WHERE id NOT IN ({$placeholders})", $layoutIDsSubmitted);
            } elseif (!$layoutIDs){
                // None submitted, delete them all
                $DB->execute("DELETE FROM {lbp_plugin_layouts}");
            }


            // Delete groups that were not submitted this time
            $placeholders = \elbp_implode_placeholders($groupIDsSubmitted);
            if ($placeholders){
                $DB->execute("DELETE FROM {lbp_plugin_groups} WHERE id NOT IN ({$placeholders})", $groupIDsSubmitted);
            } elseif (!isset($_POST['plugin_layouts_groups_id']) || !$_POST['plugin_layouts_groups_id']){
                // None submitted, delete them all
                $DB->execute("DELETE FROM {lbp_plugin_groups}");
            }


        }



        // Save a plugin group with the plugins linked to it
//        if (isset($_POST['submit_group']))
//        {
//
//            $ids = $_POST['group_id'];
//            $names = $_POST['group_name'];
//            $plugins = isset($_POST['group_plugins']) ? $_POST['group_plugins'] : false;
//            $order = $_POST['group_order'];
//
//            if (empty($ids) || empty($names)) return false;
//            if ( count($ids) <> count($names) ) return false;
//
//            // Any in group 0 we'll update as well
//            if (isset($plugins[0]) && !empty($plugins[0]))
//            {
//                foreach($plugins[0] as $pluginID)
//                {
//
//                    $obj = new \stdClass();
//
//                    if (strpos($pluginID, ":custom") !== false)
//                    {
//                        $pluginID = str_replace(":custom", "", $pluginID);
//                        $table = "lbp_custom_plugins";
//                    }
//                    else
//                    {
//                        $table = "lbp_plugins";
//                    }
//
//                    $obj->id = $pluginID;
//                    $obj->groupid = null;
//                    $obj->ordernum = 0;
//                    $this->DB->update_record($table, $obj);
//
//                }
//            }
//
//            for ($i = 1; $i <= count($ids); $i++)
//            {
//
//                $id = $ids[$i];
//                $name = trim($names[$i]);
//                $pluginList = isset($plugins[$i]) ? $plugins[$i] : false;
//                $orderNum = (int)$order[$i]; # If not an int - it is now
//                $enabled = isset($_POST['enable_disable_group'][$i]) ? 1 : 0;
//
//                // Insert new
//                if ($id == -1)
//                {
//
//                    if (empty($name)) continue;
//
//                    $obj = new \stdClass();
//                    $obj->name = $name;
//                    $obj->ordernum = $orderNum;
//                    $obj->enabled = 1;
//                    $id = $this->DB->insert_record("lbp_plugin_groups", $obj);
//
//                }
//                else
//                {
//
//                    // Update
//                    $group = $this->DB->get_record("lbp_plugin_groups", array("id"=>$id));
//                    if (!$group) continue;
//
//                    if (empty($name)){
//                        $this->DB->delete_records("lbp_plugin_groups", array("id"=>$id));
//                        continue;
//                    }
//
//                    $group->name = $name;
//                    $group->ordernum = $orderNum;
//                    $group->enabled = $enabled;
//                    $this->DB->update_record("lbp_plugin_groups", $group);
//
//
//                }
//
//                // Assign plugins to it
//                if ($pluginList)
//                {
//
//                    $ordernum = 1;
//                    foreach($pluginList as $plugin)
//                    {
//
//                        if (strpos($plugin, ":custom") !== false)
//                        {
//                            $plugin = str_replace(":custom", "", $plugin);
//                            $table = "lbp_custom_plugins";
//                        }
//                        else
//                        {
//                            $table = "lbp_plugins";
//                        }
//
//                        $obj = new \stdClass();
//                        $obj->id = $plugin;
//                        $obj->groupid = $id;
//                        $obj->ordernum = $ordernum;
//                        $this->DB->update_record($table, $obj);
//                        $ordernum++;
//                    }
//                }
//
//
//            }
//
//            $MSGS['success'] = get_string('groupsupdated', 'block_elbp');
//            return true;
//        }


    }

    /**
     * Save MIS configuration.
     * This is stuff like new MIS connections. Linking MIS connections to Plugins. etc...
     * @global \ELBP\type $MSGS
     * @global \ELBP\type $FORMVALS
     * @return boolean
     */
    private function saveConfigMIS()
    {

        global $MSGS, $FORMVALS;

        // Creating a new MIS connection
        if (isset($_POST['submit_new_mis']))
        {

            $name = $_POST['new_mis_name'];
            $type = $_POST['new_mis_type'];
            $host = $_POST['new_mis_host'];
            $user = $_POST['new_mis_user'];
            $pass = $_POST['new_mis_pass'];
            $dbname = $_POST['new_mis_dbname'];

            // Set vals for sticky form
            $FORMVALS['new_mis_name'] = elbp_html($name);
            $FORMVALS['new_mis_type'] = elbp_html($type);
            $FORMVALS['new_mis_host'] = elbp_html($host);
            $FORMVALS['new_mis_user'] = elbp_html($user);
            $FORMVALS['new_mis_pass'] = elbp_html($pass);
            $FORMVALS['new_mis_dbname'] = elbp_html($dbname);

            // If something not filled out
            if (empty($name) || empty($type) || empty($host)){
                $MSGS['errors'] = get_string('fieldsnotfilledin', 'block_elbp');
                return false;
            }

            // If name already in use
            $checkName = $this->DB->get_record("lbp_mis_connections", array("name"=>$name));
            if ($checkName)
            {
                $MSGS['errors'] = get_string('nameinuse', 'block_elbp');
                return false;
            }

            // Check type if valid
            $typefilename = $this->CFG->dirroot . '/blocks/elbp/classes/db/'.$type.'.class.php';
            if (!file_exists($typefilename)){
                $MSGS['errors'] = get_string('nosuchmistype', 'block_elbp');
                return false;
            }

            // Otherwise its up to them to make sure the details are correct and test the connection
            $data = new \stdClass();
            $data->name = $name;
            $data->type = $type;
            $data->host = $host;
            $data->un = $user;
            $data->pw = $pass;
            $data->db = $dbname;

            // Insert the connection
            $this->DB->insert_record("lbp_mis_connections", $data);

            // Clear form
            $FORMVALS['new_mis_name'] = '';
            $FORMVALS['new_mis_type'] = '';
            $FORMVALS['new_mis_host'] = '';
            $FORMVALS['new_mis_user'] = '';
            $FORMVALS['new_mis_pass'] = '';
            $FORMVALS['new_mis_dbname'] = '';

            // Success msg
            $MSGS['success'] = get_string('misconnectioncreated', 'block_elbp');
            return true;

        }

        // Edit an existing MIS connection
        if (isset($_POST['edit_mis_connection']))
        {

            $id =   $_POST['mis_connection_id'];
            $name = $_POST['mis_name'];
            $type = $_POST['mis_type'];
            $host = $_POST['mis_host'];
            $user = $_POST['mis_user'];
            $pass = $_POST['mis_pass'];
            $dbname = $_POST['mis_dbname'];

            // If something not filled out
            if (!ctype_digit($id) || empty($name) || empty($type) || empty($host) || empty($user) || empty($pass)){
                $MSGS['errors'] = get_string('fieldsnotfilledin', 'block_elbp');
                return false;
            }

            // If name already in use
            $checkName = $this->DB->get_record("lbp_mis_connections", array("name"=>$name));
            if ($checkName && $checkName->id <> $id)
            {
                $MSGS['errors'] = get_string('nameinuse', 'block_elbp');
                return false;
            }

            // Check type if valid
            $typefilename = $this->CFG->dirroot . '/blocks/elbp/classes/db/'.$type.'.class.php';
            if (!file_exists($typefilename)){
                $MSGS['errors'] = get_string('nosuchmistype', 'block_elbp');
                return false;
            }

            // Update it
            $data = new \stdClass();
            $data->id = $id;
            $data->name = $name;
            $data->type = $type;
            $data->host = $host;
            $data->un = $user;
            $data->pw = $pass;
            $data->db = $dbname;

            $this->DB->update_record("lbp_mis_connections", $data);

            // Success msg
            $MSGS['success'] = get_string('misconnectionupdated', 'block_elbp');
            return true;

        }

        // Delete an MIS connection
        if (isset($_POST['delete_mis_connection_y'], $_POST['delete_mis_connection_x']))
        {

            $misID = $_POST['mis_connection_id'];

            // Delete from lbp_mis_connections
            $this->DB->delete_records("lbp_mis_connections", array("id"=>$misID));

            // Delete any mis_plugin records for that ID
            $this->DB->delete_records("lbp_plugin_mis", array("misid"=>$misID));

            // Success msg
            $MSGS['success'] = get_string('misconnectiondeleted', 'block_elbp');
            return true;


        }

        // Assign an MIS connection to a plugin
        if (isset($_POST['submit_assign_plugin_mis']))
        {

            if (isset($_POST['plugin_id'])){
                $pluginID = $_POST['plugin_id'];
                $table = "lbp_plugin_mis";
            }

            if (isset($_POST['custom_plugin_id'])){
                $pluginID = $_POST['custom_plugin_id'];
                $table = "lbp_custom_plugin_mis";
            }

            if (!isset($pluginID)){
                return false;
            }

            $misID = $_POST['mis_connection_id'];

            if (!ctype_digit($pluginID)){
                $MSGS['errors'] = get_string('fieldsnotfilledin', 'block_elbp');
                return false;
            }

            // We're not going to bother checkiung if the plugin & connection exist with that ID, since we have to be admin to do this
            // going to assume not going to mess with it

            // Check if there is already a connection for this plugin called "main" (We use "main" as the main connection for the plugin)
            $check = $this->DB->get_record($table, array("pluginid"=>$pluginID, "name"=>"core"));

            // If mis id is empty (not a digit), delete the record
            if ($check && !ctype_digit($misID))
            {
                $this->DB->delete_records($table, array("id"=>$check->id));
                $MSGS['success'] = get_string('misplugindeleted', 'block_elbp');
                return true;
            }

            // No record and no MIS sent- do nothing
            if (!$check && !ctype_digit($misID)){
                return;
            }

            // Otherwise, misID wasn't empty

            if ($check)
            {
                // Update
                $check->misid = $misID;
                $this->DB->update_record($table, $check);

            }
            else
            {
                // Insert
                $data = new \stdClass();
                $data->name = "core";
                $data->pluginid = $pluginID;
                $data->misid = $misID;
                $this->DB->insert_record($table, $data);
            }

            // Success msg
            $MSGS['success'] = get_string('mispluginassigned', 'block_elbp');
            return true;

        }



    }

    /**
     * Save the user's settings
     * @global \ELBP\type $USER
     * @global \ELBP\type $MSGS
     * @return boolean
     */
    public function saveUserSettings(){

        global $USER, $MSGS, $DB;

        $DBC = new \ELBP\DB();


        // Resetting alerts?
        if (isset($_POST['clear_alerts'])){
            $DB->delete_records("lbp_alerts", array("userid" => $USER->id));
            $MSGS['success'] = get_string('alertsdeleted', 'block_elbp');
            return true;
        }

        // Unset the save button
        unset($_POST['save_settings']);

        // Standard settings
        $settings = array('tutorial_autosave', 'addsup_autosave');

        // Loop through the settings supplied
        foreach($settings as $setting)
        {
            if (isset($_POST[$setting]))
            {
                \ELBP\Setting::setSetting($setting, $_POST[$setting], $USER->id);
            }
        }


        // Now alerts
        $type = (isset($_POST['type'])) ? $_POST['type'] : false;
        $id = (isset($_POST['id'])) ? $_POST['id'] : false;

        if (in_array($type, array('course', 'student', 'mentees', 'addsup')))
        {

            // First delete any existing alerts for this thing
            \ELBP\Alert::deleteUserAlerts($USER->id, $type, $id);

            // Loop through alerts
            if (isset($_POST['alerts']))
            {
                foreach($_POST['alerts'] as $eventID)
                {

                    $attributes = null;

                    // See if we have any alert attributes for this event
                    if (isset($_POST['alert_attributes'][$eventID])){
                        $attributes = $_POST['alert_attributes'][$eventID];
                    }

                    \ELBP\Alert::updateUserAlert($USER->id, $eventID, $type, $id, 1, $attributes);

                }
            }

        }

        // Not a message, but can't be arsed to make another global variable
        $MSGS['returntype'] = $type;
        $MSGS['returnid'] = $id;

        $title = '';

        if ($type == 'course'){
            $course = $DBC->getCourse( array('type' => 'id', 'val' => $id) );
            $title = $course->fullname;
        } elseif ($type == 'student'){
            $student = $DBC->getUser( array('type' => 'id', 'val' => $id) );
            $title = \fullname($student) . " ({$student->username})";
        } elseif ($type == 'mentees'){
            $title = get_string('allmentees', 'block_elbp');
        } elseif ($type == 'addsup'){
            $title = get_string('alladdsup', 'block_elbp');
        }

        $MSGS['returntitle'] = $title;
        $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
        return true;

    }



    /**
     * Build the filter options for when viewing a list of students.
     * E.g. Choose letter of First/Last name, etc... As well as a search box
     */
    public function buildStudentListFilter()
    {

        $filterFirst = optional_param('filterFirst', false, PARAM_ALPHA);
        $filterLast = optional_param('filterLast', false, PARAM_ALPHA);
        $filterSearch = optional_param('filterSearch', false, PARAM_TEXT);


        $pageURL = $_SERVER['REQUEST_URI'];
        $letters = range('A', 'Z');

        // If the admin has set a config option to use a range of specific characters (e.g. non-english alphabet) use that instead
        $defaultRange = Setting::getSetting("list_filter_letters");
        if ($defaultRange && !is_null($defaultRange) ){
            $letters = str_split($defaultRange);
        }

        $output = "";
        $output .= "<div class='elbp_centre' id='elbp_filter_block'>";

        // First name A-Z

        // If there's already a "filterFirst=" in the URL, strip it out for these links
        $url = strip_from_query_string("filterFirst", $pageURL);
        $url = strip_from_query_string("page", $url);

        $output .= "First Name: ";

        // If no first name selected, display this as bold non-link
        if (!$filterFirst) $output .= "<strong>All</strong> ";
        // Else as a link
        else $output .= "<a href='{$url}'>All</a> ";

        foreach($letters as $letter)
        {
            // If letter is selected, display as bold non-link
            if ($filterFirst && $filterFirst == $letter) $output .= "<strong>{$letter}</strong> ";
            // Else display as link
            else $output .= "<a href='".append_query_string($url, "filterFirst={$letter}")."'>{$letter}</a> ";
        }



        $output .= "<br>";



        // Last name A-Z

        // If there's already a filterLast in the URL, strip it out for these links
        $url = strip_from_query_string("filterLast", $pageURL);
        $url = strip_from_query_string("page", $url);

        $output .= "Last Name: ";
        // If no first name selected, display this as bold non-link
        if (!$filterLast) $output .= "<strong>All</strong> ";
        // Else as a link
        else $output .= "<a href='{$url}'>All</a> ";

        foreach($letters as $letter)
        {
            if ($filterLast && $filterLast == $letter) $output .= "<strong>{$letter}</strong> ";
            else $output .= "<a href='".append_query_string($url, "filterLast={$letter}")."'>{$letter}</a> ";
        }



        $output .= "<br>";


        // Search box

        // If we try to send this form via GET to a url with a query string already present (e.g. ?course=view) it'll not work
        // So we have to send any elements we already ahve as hidden fields in this form

        $url = strip_from_query_string("filterSearch", $pageURL);
        $url = strip_from_query_string("page", $url);
        $fields = query_string_to_array($url);


        $output .= "<form action='{$url}' method='get'>";
            $output .= "<input type='text' name='filterSearch' value='".  htmlspecialchars($filterSearch, ENT_QUOTES)."' class='elbp_text' /> ";
            if ($fields)
            {
                foreach($fields as $field => $value)
                {
                    $output .= "<input type='hidden' name='{$field}' value='{$value}' />";
                }
            }
            $output .= " <input type='submit' value='".get_string('search', 'block_elbp')."' class='elbp_button' />";
        $output .= "</form>";


        // Strip every filter
        $url = strip_from_query_string("filterSearch", $pageURL);
        $url = strip_from_query_string("page", $url);
        $url = strip_from_query_string("filterFirst", $url);
        $url = strip_from_query_string("filterLast", $url);

        $output .= "<p class='elbp_centre'><span class='elbp_small'><a href='{$url}'>[".get_string('resetsearch', 'block_elbp')."]</a></span></p>";

        $output .= "</div>";

        return $output;

    }

    /**
     * Build a list of students to display in a table - for the view students by course/mentees/lbpadmin stuff, etc...
     * By default this is a bog standard list with: Img, Full name, ELBP Link
     * You can add more columns to it using arrays of ELBPCol objects
     * @param array $records Recordset of students to display
     * @param array $params
     * @param array $additional - Any extra columns we want
     */
    public function buildListOfStudents($records, $params=null, $additional=null)
    {

        global $OUTPUT, $USER;

        $page = optional_param('page', 1, PARAM_INT);
        $courseid = optional_param('courseid', false, PARAM_INT);
        $groupid = optional_param('groupid', false, PARAM_INT);

        if (!isset($params['viewtext'])) $params['viewtext'] = get_string('view', 'block_elbp') . ' ' . $this->getELBPShortName();
        if (!isset($params['viewfile'])) $params['viewfile'] = 'view.php';

        if ($page)
        {
            $nextPage = $page + 1;
            $lastPage = $page - 1;
        }

        $pageURL = $_SERVER['REQUEST_URI'];
        $url = strip_from_query_string("page", $pageURL);
        $count = 1;

        $output = "";

        // Display pages
        $perpage = Setting::getSetting('list_stud_per_page', $USER->id);
        if (!$perpage) $perpage = 15;

        if ($params && isset($params['course']) && isset($params['courseID'])){
            // First count the records that an unrestricted query would return
            $count = $this->ELBPDB->countStudentsOnCourse($params['courseID']);
        }
        elseif($params && isset($params['mentees'])){
            $extra = array();
            if ($courseid) $extra['course'] = $courseid;
            if ($groupid) $extra['group'] = $groupid;
            $count = $this->ELBPDB->countTutorsMentees($USER->id, $extra);
        }

        $numPages = ceil( $count / $perpage );

        // If only 1 page, no point doing anything
        $pagination = "";

        if ($numPages > 1)
        {

            $pagination .= "<div class='elbp_pages'>";

            $pagination .= "<ul class='elbp_pagination'>";

                if ($page > 1) $pagination .= "<li class='prev'><a href='".append_query_string($url, "page={$lastPage}")."'>".get_string('previous', 'block_elbp')."</a></li>";


                    // If there are tonnes of pages we don't want to show them all
                    // E.g. 1 ... 10 11 12 [13] 14 15 16 ... 21
                    if ($numPages > 50)
                    {

                        $countAround = 5; # The number of elements to display on either side of current

                        for ($i = 1; $i <= $numPages; $i++)
                        {

                            $gap = false;
                            $out = $i;

                            // Make sure not first or last element
                            if ($i > 1 && $i < $numPages && ($i < ($page - $countAround) || ($i - $page) > $countAround ))
                            {
                                $gap = true;
                                $out = "...";
                                $i = ($i < $page) ? ($page - $countAround - 1) : ($numPages - 1);
                            }

                            if ($gap) $pagination .= "<li class='gap'>{$out}</li>";
                            elseif ($i == $page) $pagination .= "<li class='active'>{$out}</li>";
                            else $pagination .= "<li><a href='".append_query_string($url, "page={$i}")."'>{$out}</a></li>";

                        }

                    }
                    else
                    {

                        for ($i = 1; $i <= $numPages; $i++)
                        {
                            if ($i == $page) $pagination .= "<li class='active'>{$i}</li>";
                            else $pagination .= "<li><a href='".append_query_string($url, "page={$i}")."'>{$i}</a></li>";
                        }

                    }

               if ($page < $numPages) $pagination .= "<li class='next'><a href='".append_query_string($url, "page={$nextPage}")."'>".get_string('next', 'block_elbp')."</a></li>";

            $pagination .= "</ul>";

            $pagination .= "</div>";

            $pagination .= "<br class='elbp_cl'>";

        }

        $output .= $pagination;

        $output .= "<table class='elbp_student_list'>\n";
        $output .= "<tr>";
            $output .= "<th></th>"; # IMG
            $output .= "<th>".get_string('fullname', 'block_elbp')."</th>"; # NAME

            // Hook in additional columns from plugins, e.g. "Avg Att", "Avg Punc", "Target grade", etc...
            if (is_array($additional))
            {
                $headers = $additional[0];
                foreach((array)$headers as $header)
                {
                    $output .= "<th>{$header}</th>";
                }
            }

            // View link
            $output .= "<th></th>";

        $output .= "</tr>\n";

        if ($records)
        {
            foreach($records as $record)
            {
                $output .= "<tr>";

                    $output .= "<td>". $OUTPUT->user_picture($record) ."</td>";
                    $output .= "<td><a href='{$this->CFG->wwwroot}/user/profile.php?id={$record->id}' target='_blank'>". fullname($record) ."</a></td>";

                    // Hook in additional values from plugins, to match up with column headers
                    if (is_array($additional))
                    {
                        $cols = $additional[1][$record->id];
                        foreach((array)$additional[0] as $header)
                        {
                            $output .= "<td>{$cols[$header]}</td>";
                        }
                    }

                    $output .= "<td><a href='{$params['viewfile']}?id={$record->id}' target='_blank'>{$params['viewtext']}</a></td>";

                $output .= "</tr>\n";
            }
        }

        $output .= "</table><br>\n";

        if (!$records) $output .= "<p>".get_string('nousersfound', 'block_elbp')."</p>\n";

        $output .= $pagination;

        return $output;

    }


    /**
     * Given an array of access permissions, check if any of them are true, we don't care which one
     * @param type $access
     * @return boolean
     */
    public function anyPermissionsTrue($access)
    {
        if ($access)
        {
            foreach($access as $acc)
            {
                if ($acc == true)
                {
                    return true;
                }
            }
        }

        return false;

    }


    /**
     * Check if logged in user has the permission to view ELBP info about a given student
     * @global type $USER
     * @param type $userID
     * @return boolean
     */
    public function getUserPermissions($userID, $user = null)
    {

        global $USER, $DB;

        if (is_null($user)) $user = $USER->id;

        $access = array();
        $access['god'] = false;
        $access['elbpadmin'] = false;
        $access['teacher'] = false;
        $access['tutor'] = false;
        $access['user'] = false;
        $access['parent'] = false;
        $access['other'] = false; // This might be Additional Support Tutors, or any other role they've given the view_elbp capability to
        $access['context'] = array();

        // To be granted access to a user, you must meet any of these criteria:
        // Be an Admin
        // Be an ELBP_Administrator (role)
        // Be a teacher/non-editing teacher on any course which the user is a student on
        // Be a personal tutor assigned to the user

        $siteContext = \context_system::instance();
        $userContext = \context_user::instance($userID);
        $frontPageContext = \context_course::instance(SITEID);

        if (!$userContext){
            $this->errorMsg = get_string('user', 'block_elbp');
            return false;
        }

        // Are we a site admin?
        if (is_siteadmin($USER->id)){
            $access['god'] = true;
            $access['context'][] = $siteContext;
        }

        // Are we an ELBP Administrator?
        // This checks if they are an elbp_admin on the front page course (SITEID)
        if (has_capability('block/elbp:elbp_admin', $frontPageContext, $user, false)) {
            $access['elbpadmin'] = true ;
            $access['context'][] = $frontPageContext;
        }

        // Are we a teacher on any of the student's courses?
        // Loop through all the student's courses and see if we have the view_elbp capability in that context
        $studentsCourses = $this->ELBPDB->getStudentsCourses($userID);
        if ($studentsCourses)
        {
            foreach($studentsCourses as $studentsCourse)
            {
                $courseContext = \context_course::instance($studentsCourse->id);
                if (has_capability('block/elbp:view_elbp', $courseContext, $user, false)) {
                    $access['teacher'] = true;
                    $access['context'][] = $courseContext;
                    break; // Stop the loop, one is enough if it's successful
                }
            }
        }


        // Are they a personal tutor of the student?
        if ($this->ELBPDB->hasTutorSpecificMentee($userID, $user))
        {
            $access['tutor'] = true;
            $access['context'][] = $userContext;
        }

        // Other tutors or roles
        if (has_capability('block/elbp:view_elbp', $userContext, $user, false)) {
            $access['other'] = true;
            $access['context'][] = $userContext;
        }

        // Are we a parent, using the Parent Portal?
        if (isset($_SESSION['pp_user'])){
            $check = $DB->get_record("portal_requests", array("portaluserid" => $_SESSION['pp_user']->id, "userid" => $userID, "status" => 1));
            if ($check){
                $access['parent'] = true;
                $access['context'] = false;
            }
        }

        // Finally, are the the user ourselves?
        if ($userID == $user){
            $access['user'] = true;
            $access['context'][] = $frontPageContext;
        }

        $this->access = $access;

        $this->setAccessPlugins();

        return $access;

    }

    /**
     * Set the permissions array into all the installed plugins
     */
    private function setAccessPlugins(){
        if ($this->plugins){
            foreach($this->plugins as $plugin){
                $plugin->setAccess($this->access);
            }
        }
    }



    /**
     * Check whether logged in user has the permissions to access this course's information
     * @global type $USER
     * @param type $courseID
     * @param type $userID
     * @return boolean
     */
    public function getCoursePermissions($courseID, $userID = null)
    {

        global $USER;

        if (is_null($userID)) $userID = $USER->id;

        // If userID is still null, return false
        if (is_null($userID) || $userID <= 0){
            $this->errorMsg = get_string('user', 'block_elbp');
            return false;
        }

        $siteContext = \context_system::instance();
        $userContext = \context_user::instance($userID);
        $frontPageContext = \context_course::instance(SITEID);

        if (!$userContext){
            $this->errorMsg = get_string('user', 'block_elbp');
            return false;
        }

        // Check course is valid
        $course = $this->ELBPDB->getCourse( array("type" => "id", "val" => $courseID) );
        if (!$course){
            $this->errorMsg = get_string('course', 'block_elbp');;
            return false;
        }

        // Check course context is valid
        $courseContext = \context_course::instance($course->id);
        if (!$courseContext){
            $this->errorMsg = get_string('coursecontext', 'block_elbp');;
            return false;
        }

        $access = array();
        $access['god'] = false;
        $access['elbpadmin'] = false;
        $access['teacher'] = false;
        $access['tutor'] = false;
        $access['user'] = false;

        // CHeck if admin
        if (is_siteadmin($USER->id)){
            $access['god'] = true;
            $access['context'] = $siteContext;
        }

        // Check if we are the user ourselves - Why is this here? This is to do with courses, not users....
        if ($userID == $USER->id){
            $access['user'] = true;
        }

        // Check if we're a teacher on the course (editing or non-editing)
        if(isset($courseContext)){

            if (has_capability('block/elbp:view_elbp', $courseContext, $USER, false)) {
                $access['teacher'] = true;
                if (!isset($access['context'])) $access['context'] = $courseContext;
            }

            if (has_capability('block/elbp:elbp_admin', $courseContext, $USER, false) || has_capability('block/elbp:elbp_admin', $frontPageContext, $USER, false)) {
                $access['elbpadmin'] = true;
                if (!isset($access['context'])) $access['context'] = $courseContext;
            }


        }

        return $access;


    }

    /**
     * Get a specific plugin object
     * @param string $pluginName
     * @return Plugin
     */
    public function getPlugin($pluginName, $loadDisabled = false)
    {
        if ($this->plugins)
        {
            foreach($this->plugins as $plugin)
            {
                if ($plugin)
                {
                    if ($plugin->getName() == $pluginName)
                    {
                        return $plugin;
                    }
                }
            }
        }

        return false;

    }

    /**
     * Get a specific plugin object by its ID
     * @param int $pluginID
     * @return type
     */
    public function getPluginByID($pluginID, $custom = false, $loadDisabled = false)
    {

        if ($this->plugins)
        {
            foreach($this->plugins as $plugin)
            {
                if ($plugin)
                {
                    if ($custom && $plugin->isCustom() && ($plugin->getID() == $pluginID))
                    {
                        return $plugin;
                    }
                    elseif (!$custom && !$plugin->isCustom() && ($plugin->getID() == $pluginID))
                    {
                        return $plugin;
                    }
                }
            }
        }

        // If we got this far we havne't found it yet, so if we want to check disabled plugins, let's just try and
        // get it straight out of the db
        if ($loadDisabled)
        {

            global $DB;

            if ($custom){

                $record = $DB->get_record("lbp_custom_plugins", array("id" => $pluginID));
                if ($record){
                    $plugin = new Plugins\CustomPlugin($record->id);
                }

            } else {
                $record = $DB->get_record("lbp_plugins", array("id" => $pluginID));
                if ($record){
                    $plugin = Plugins\Plugin::instaniate($record->name, $record->path);
                }
            }

            if ($plugin)
            {
                return $plugin;
            }

        }


        return false;

    }

    /**
     * Load the required javascript
     * @global \ELBP\type $CFG
     * @global type $PAGE
     * @param type $simple If true will be returned in <script> tags. Else will be put into $PAGE object
     * @return type
     */
    public function loadJavascript()
    {

        global $CFG, $PAGE;

        $studID = ($this->student) ? $this->student->id : -1;
        $courseID = ($this->courseID) ? $this->courseID : -1;

        $PAGE->requires->js_call_amd('block_elbp/scripts', 'init');

        // Loop through plugins & load javascript for them as well
        if ($this->plugins){
            foreach ( $this->plugins as $plugin){
                 $plugin->loadJavascript();
            }
        }

    }

    /**
     * Load required css
     * @global \ELBP\type $CFG
     * @global \ELBP\type $PAGE
     * @param type $simple If true will be returned in <link> tags. Else put into $PAGE object.
     * @return type
     */
    public function loadCSS($simple = false)
    {
        global $CFG, $PAGE;

        if ($simple)
        {

            $output = "";
            $output .= "<link rel='stylesheet' type='text/css' href='{$CFG->wwwroot}/blocks/elbp/js/jquery/css/start/jquery-ui.min.css' />";
            $output .= "<link rel='stylesheet' type='text/css' href='{$CFG->wwwroot}/blocks/elbp/js/jquery/plugins/minicolors/jquery.minicolors.css' />";
            $output .= "<link rel='stylesheet' type='text/css' href='{$CFG->wwwroot}/blocks/elbp/js/jquery/plugins/tinytbl/jquery.tinytbl.css' />";
            $output .= "<link rel='stylesheet' type='text/css' href='{$CFG->wwwroot}/blocks/elbp/js/jquery/plugins/raty/jquery.raty.css' />";
            $output .= "<link rel='stylesheet' type='text/css' href='{$CFG->wwwroot}/blocks/elbp/css/application.css' />";
            return $output;

        }
        else
        {

            $PAGE->requires->css( '/blocks/elbp/js/jquery/css/start/jquery-ui.min.css' );
            $PAGE->requires->css( '/blocks/elbp/js/jquery/plugins/minicolors/jquery.minicolors.css' );
            $PAGE->requires->css( '/blocks/elbp/js/jquery/plugins/tinytbl/jquery.tinytbl.css' );
            $PAGE->requires->css( '/blocks/elbp/js/jquery/plugins/raty/jquery.raty.css' );
            $PAGE->requires->css( '/blocks/elbp/js/jquery/plugins/fileupload/jquery.fileupload.css' );
            $PAGE->requires->css( '/blocks/elbp/css/application.css' );

        }


    }

    /**
     * Similar to the Moodle OUTPUT->footer() method, this prints anything that is required in the footer of the page,
     * such as the html for the popup dialogue box which can be re-used across pages
     */
    public function footer()
    {

    }

    /**
     * Handle an AJAX request and send all the data to the relevant plugin
     * @param type $plugin
     * @param type $action
     * @param type $params
     * @return type
     */
    public function handleAjaxRequest($plugin, $action, $params)
    {

        $plugin = $this->getPlugin($plugin);

        // First check permissions
        if (isset($params['student']) && $params['student'] > 0){
            $access = $this->getUserPermissions($params['student']);
            if (!$this->anyPermissionsTrue($access)) exit;
        }

        return ($plugin) ? $plugin->ajax($action, $params, $this) : false;

    }


    /**
     * Get the current error message and then set it to blank so the same one isn't displayed later
     * @return type
     */
    public function getErrorMsg()
    {
        $msg = $this->errorMsg;
        $this->errorMsg = '';
        return $msg;
    }

    /**
     * Get all the hooks that it is possible to enable
     */
    public function getAllPossibleHooks()
    {

        global $DB;

        $hooks = array();
        $records = $DB->get_records("lbp_hooks");

        if($records)
        {
            foreach($records as $record)
            {

                // Get the plugin's name
                $name = \ELBP\Plugins\Plugin::getPluginName($record->pluginid);

                if ($name)
                {

                    if (!isset($hooks[$record->pluginid])) $hooks[$record->pluginid] = array();

                    $hooks[$record->pluginid]['name'] = $name;
                    if (!isset($hooks[$record->pluginid]['hooks'])) $hooks[$record->pluginid]['hooks'] = array();
                    $hooks[$record->pluginid]['hooks'][] = array("id" => $record->id, "name" => $record->name);

                }

            }
        }

       return $hooks;

    }


    /**
     * Calculate the student's progress for all the plugins
     * @return type
     */
    public function calculateStudentProgress()
    {

        $max = 0;
        $num = 0;
        $info = array();

        if ($this->plugins)
        {

            foreach($this->plugins as $plugin)
            {

                $plugin->loadStudent( $this->student->id );
                $calc = $plugin->calculateStudentProgress();
                $max += $calc['max'];
                $num += $calc['num'];

                $info[$plugin->getTitle()] = @$calc['info'];

            }

        }

        $percent = ($max > 0) ? round( ($num / $max) * 100 ) : 100;
        return array(
            'percent' => $percent,
            'info' => $info
        );

    }

    /**
     * Get the background & text colours for progress bars, based on %
     * @param type $percent
     */
    public function getProgressColours($percent)
    {

        $return = array();
        $return['text'] = 'black';

        if ($percent < 33){
            $return['background'] = "red";
        }
        elseif ($percent < 66){
            $return['background'] = "orange";
        }
        elseif ($percent < 100){
            $return['background'] = "blue";
        }
        else {
            $return['background'] = "green";
            $return['text'] = 'white';
        }

        return $return;

    }


    /**
     * Print out object
     */
    public function toString()
    {
        print_object($this);
    }

    /**
     * Upgrade the block and then all the plugins
     * @param type $oldversion
     * @param type $custom
     * @return boolean
     */
    public function upgrade($oldversion, $custom = false)
    {

        global $DB;

        $result = true;
        $dbman = $this->DB->get_manager();

        // Create MoodleData directory if it doesn't exist
        \elbp_create_data_directory('install');


        // Main ELBP updates
        if ($oldversion < 2013080600) {

            // Define table lbp_plugin_report_elements to be created
            $table = new \xmldb_table('lbp_plugin_report_elements');

            // Adding fields to table lbp_plugin_report_elements
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('getstringname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('getstringcomponent', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_plugin_report_elements
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('pid_fk', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_plugins', array('id'));

            // Conditionally launch create table for lbp_plugin_report_elements
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created 'lbp_plugin_report_elements' table");

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2013080600, 'elbp');

        }


        if ($oldversion < 2013082100) {

            // Define field castas to be added to lbp_mis_mappings
            $table = new \xmldb_table('lbp_mis_mappings');
            $field = new \xmldb_field('castas', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'alias');

            // Conditionally launch add field castas
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            \mtrace("~~ Created added field 'castas' to 'lbp_mis_mappings' table");

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2013082100, 'elbp');

        }

        if ($oldversion < 2013091000) {

            // Changing the size of the ordernum field in lbp_plugins as it was too small
            $table = new \xmldb_table('lbp_plugins');
            $field = new \xmldb_field('ordernum', XMLDB_TYPE_NUMBER, '4, 1', null, XMLDB_NOTNULL, null, null, 'groupid');

            // Launch change of precision for field ordernum
            $dbman->change_field_precision($table, $field);

            \mtrace("~~ Changed precision on field 'ordernum' of table 'lbp_plugins' to: '4,1'");

            \upgrade_block_savepoint(true, 2013091000, 'elbp');

        }


        if ($oldversion < 2013100100) {

            // Define field fieldfunc to be added to lbp_mis_mappings
            $table = new \xmldb_table('lbp_mis_mappings');
            $field = new \xmldb_field('fieldfunc', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'castas');

            // Conditionally launch add field fieldfunc
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // elbp savepoint reached
            \mtrace("~~ Added field 'fieldfunc' onto table 'lbp_mis_mappings'");
            \upgrade_block_savepoint(true, 2013100100, 'elbp');

        }


        if ($oldversion < 2013100200) {

            // Define field castas to be dropped from lbp_mis_mappings
            $table = new \xmldb_table('lbp_mis_mappings');
            $field = new \xmldb_field('castas');

            // Conditionally launch drop field castas
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            // elbp savepoint reached
            \mtrace("~~ Removed field 'castas' from table 'lbp_mis_mappings'");
            \upgrade_block_savepoint(true, 2013100200, 'elbp');

        }

        if ($oldversion < 2013100900) {

             // Define key primary (primary) to be dropped form lbp_plugin_report_elements
            $table = new \xmldb_table('lbp_plugin_report_elements');
            $key = new \xmldb_key('plu_fk', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_plugins', array('id'));

            // Launch drop key primary
            $dbman->drop_key($table, $key);


             // Changing nullability of field pluginid on table lbp_plugin_report_elements to null
            $table = new \xmldb_table('lbp_plugin_report_elements');
            $field = new \xmldb_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');

            // Launch change of nullability for field pluginid
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
                \mtrace("~~Changed field 'pluginid' in table 'lbp_plugin_report_elements' to allow NULL");
            }

            $this->DB->insert_record("lbp_plugin_report_elements", array("pluginid" => null, "getstringname" => "reports:elbp:personaltutors", "getstringcomponent" => "block_elbp"));
            \mtrace("~~ Inserted reporting element record for ELBP block ~~");
            \upgrade_block_savepoint(true, 2013100900, 'elbp');

        }


        if ($oldversion < 2013110400) {


            // Define table lbp_register_events to be created
            $table = new \xmldb_table('lbp_register_events');

            // Adding fields to table lbp_register_events
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('day', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('starttime', XMLDB_TYPE_NUMBER, '4, 2', null, XMLDB_NOTNULL, null, null);
            $table->add_field('endtime', XMLDB_TYPE_NUMBER, '4, 2', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_register_events
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('fk_cid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));

            // Conditionally launch create table for lbp_register_events
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_register_events' ~~");





            // Define table lbp_register to be created
            $table = new \xmldb_table('lbp_register');

            // Adding fields to table lbp_register
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('week', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_CHAR, '255', null, null, null, null);

            // Adding keys to table lbp_register
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('fk_sid', XMLDB_KEY_FOREIGN, array('studentid'), 'user', array('id'));
            $table->add_key('fk_eid', XMLDB_KEY_FOREIGN, array('eventid'), 'lbp_register_events', array('id'));

            // Adding indexes to table lbp_register
            $table->add_index('ix_sid_eid', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'eventid'));
            $table->add_index('ix_sid_eid_wk', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'eventid', 'week'));

            // Conditionally launch create table for lbp_register
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_register' ~~");


            upgrade_block_savepoint(true, 2013110400, 'elbp');

        }

        if ($oldversion < 2013111200){

            // Define table lbp_termly_creport_atts to be created
            $table = new \xmldb_table('lbp_termly_creport_atts');

            // Adding fields to table lbp_termly_creport_atts
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('termlyreportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('attribute', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

            // Adding keys to table lbp_termly_creport_atts
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('trp_id', XMLDB_KEY_FOREIGN, array('termlyreportid'), 'lbp_termly_reports', array('id'));

            // Adding indexes to table lbp_termly_creport_atts
            $table->add_index('rpid_att_indx', XMLDB_INDEX_NOTUNIQUE, array('termlyreportid', 'attribute'));

            // Conditionally launch create table for lbp_termly_creport_atts
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_termly_creport_atts' ~~");

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2013111200, 'elbp');

        }


        if ($oldversion < 2014010700) {

            // Define table lbp_custom_plugins to be created
            $table = new \xmldb_table('lbp_custom_plugins');

            // Adding fields to table lbp_custom_plugins
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ordernum', XMLDB_TYPE_NUMBER, '4, 1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Adding keys to table lbp_custom_plugins
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Conditionally launch create table for lbp_custom_plugins
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_custom_plugins' ~~");


            // Define table lbp_custom_plugin_attributes to be created
            $table = new \xmldb_table('lbp_custom_plugin_attributes');

            // Adding fields to table lbp_custom_plugin_attributes
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('field', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

            // Adding keys to table lbp_custom_plugin_attributes
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('fk_cpid', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_custom_plugins', array('id'));
            $table->add_key('uid_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

            // Adding indexes to table lbp_custom_plugin_attributes
            $table->add_index('upf_indx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'pluginid', 'field'));
            $table->add_index('pf_indx', XMLDB_INDEX_NOTUNIQUE, array('pluginid', 'field'));

            // Conditionally launch create table for lbp_custom_plugin_attributes
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_custom_plugin_attributes' ~~");


            // Define table lbp_custom_plugin_settings to be created
            $table = new \xmldb_table('lbp_custom_plugin_settings');

            // Adding fields to table lbp_custom_plugin_settings
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setting', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

            // Adding keys to table lbp_custom_plugin_settings
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('uid_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
            $table->add_key('cpid_fk', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_custom_plugins', array('id'));

            // Adding indexes to table lbp_custom_plugin_settings
            $table->add_index('ups_indx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'pluginid', 'setting'));
            $table->add_index('ps_indx', XMLDB_INDEX_NOTUNIQUE, array('pluginid', 'setting'));

            // Conditionally launch create table for lbp_custom_plugin_settings
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_custom_plugin_settings' ~~");


            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014010700, 'elbp');

        }


        if ($oldversion < 2014010900) {

            // Define table lbp_custom_plugin_items to be created
            $table = new \xmldb_table('lbp_custom_plugin_items');

            // Adding fields to table lbp_custom_plugin_items
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setbyuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('settime', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('del', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

            // Adding keys to table lbp_custom_plugin_items
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('p_fk', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_custom_plugins', array('id'));
            $table->add_key('sid_fk', XMLDB_KEY_FOREIGN, array('studentid'), 'user', array('id'));
            $table->add_key('sbuid_fk', XMLDB_KEY_FOREIGN, array('setbyuserid'), 'user', array('id'));

            // Adding indexes to table lbp_custom_plugin_items
            $table->add_index('pstd_indx', XMLDB_INDEX_NOTUNIQUE, array('pluginid', 'studentid', 'settime', 'del'));

            // Conditionally launch create table for lbp_custom_plugin_items
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_custom_plugin_items' ~~");

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014010900, 'elbp');

        }


        if ($oldversion < 2014011000) {

            // Define table lbp_custom_plugin_permission to be created
            $table = new \xmldb_table('lbp_custom_plugin_permission');

            // Adding fields to table lbp_custom_plugin_permission
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_custom_plugin_permission
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('pid_fk', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_custom_plugins', array('id'));
            $table->add_key('rid_fk', XMLDB_KEY_FOREIGN, array('roleid'), 'role', array('id'));

            // Adding indexes to table lbp_custom_plugin_permission
            $table->add_index('pr_indx', XMLDB_INDEX_NOTUNIQUE, array('pluginid', 'roleid'));

            // Conditionally launch create table for lbp_custom_plugin_permission
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_custom_plugin_permission' ~~");

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014011000, 'elbp');
        }


        if ($oldversion < 2014011400) {

            // Define table lbp_custom_plugin_mis to be created
            $table = new \xmldb_table('lbp_custom_plugin_mis');

            // Adding fields to table lbp_custom_plugin_mis
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('misid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_custom_plugin_mis
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('pid_fk', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_custom_plugins', array('id'));
            $table->add_key('mid_fk', XMLDB_KEY_FOREIGN, array('misid'), 'lbp_mis_connections', array('id'));

            // Conditionally launch create table for lbp_custom_plugin_mis
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            \mtrace("~~ Created table 'lbp_custom_plugin_mis' ~~");

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014011400, 'elbp');

        }


        if ($oldversion < 2014011500) {

            // Define field itemid to be added to lbp_custom_plugin_attributes
            $table = new \xmldb_table('lbp_custom_plugin_attributes');
            $field = new \xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'pluginid');

            // Conditionally launch add field itemid
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }



            // Define key iid_fk (foreign) to be added to lbp_custom_plugin_attributes
            $table = new \xmldb_table('lbp_custom_plugin_attributes');
            $key = new \xmldb_key('iid_fk', XMLDB_KEY_FOREIGN, array('itemid'), 'lbp_custom_plugin_items', array('id'));

            // Launch add key iid_fk
            $dbman->add_key($table, $key);



            // Define index upfi_indx (not unique) to be added to lbp_custom_plugin_attributes
            $table = new \xmldb_table('lbp_custom_plugin_attributes');
            $index = new \xmldb_index('upfi_indx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'pluginid', 'itemid', 'field'));

            // Conditionally launch add index upfi_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014011500, 'elbp');
        }

        if ($oldversion < 2014011501) {

            // Changing nullability of field value on table lbp_custom_plugin_attributes to null
            $table = new \xmldb_table('lbp_custom_plugin_attributes');
            $field = new \xmldb_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null, 'field');

            // Launch change of nullability for field value
            $dbman->change_field_notnull($table, $field);

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014011501, 'elbp');
        }

        if ($oldversion < 2014011502) {

            // Changing nullability of field value on table lbp_custom_plugin_settings to null
            $table = new \xmldb_table('lbp_custom_plugin_settings');
            $field = new \xmldb_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null, 'setting');

            // Launch change of nullability for field value
            $dbman->change_field_notnull($table, $field);

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014011502, 'elbp');
        }


        if ($oldversion < 2014011503) {

            // Changing type of field value on table lbp_custom_plugin_permission to char
            $table = new \xmldb_table('lbp_custom_plugin_permission');
            $field = new \xmldb_field('value', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'roleid');

            // Launch change of type for field value
            $dbman->change_field_type($table, $field);

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014011503, 'elbp');
        }



        if ($oldversion < 2014022600) {

            // Define table lbp_file_path_codes to be created
            $table = new \xmldb_table('lbp_file_path_codes');

            // Adding fields to table lbp_file_path_codes
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_file_path_codes
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Adding indexes to table lbp_file_path_codes
            $table->add_index('p_indx', XMLDB_INDEX_UNIQUE, array('path'));

            // Conditionally launch create table for lbp_file_path_codes
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014022600, 'elbp');
        }


        if ($oldversion < 2014022700) {


            // Define key cfk (foreign) to be dropped form lbp_register_events
            $table = new \xmldb_table('lbp_register_events');
            $key = new \xmldb_key('fk_cid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));

            // Launch drop key cfk
            $dbman->drop_key($table, $key);




            // Define field eventcode to be added to lbp_register_events
            $field = new \xmldb_field('eventcode', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'id');

            // Conditionally launch add field eventcode
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }




            // Changing nullability of field courseid on table lbp_register_events to null
            $field = new \xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'eventcode');

            // Launch change of nullability for field courseid
            $dbman->change_field_notnull($table, $field);



            // Define key cfk (foreign) to be added to lbp_register_events
            $key = new \xmldb_key('fk_cid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));

            // Launch add key cfk
            $dbman->add_key($table, $key);



            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014022700, 'elbp');
        }





        // Add missing indexes/keys
        if ($oldversion < 2014022701)
        {


            // Define index nm_indx (not unique) to be added to lbp_plugin_groups
            $table = new \xmldb_table('lbp_plugin_groups');
            $index = new \xmldb_index('nm_indx', XMLDB_INDEX_NOTUNIQUE, array('name'));

            // Conditionally launch add index nm_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('nmen_indx', XMLDB_INDEX_NOTUNIQUE, array('name', 'enabled'));

            // Conditionally launch add index nmen_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('nmodren_indx', XMLDB_INDEX_NOTUNIQUE, array('name', 'ordernum', 'enabled'));

            // Conditionally launch add index nmodren_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }




            // Define key pid_fk (foreign) to be added to lbp_plugin_report_elements
            $table = new \xmldb_table('lbp_plugin_report_elements');
            $key = new \xmldb_key('pid_fk', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_plugins', array('id'));

            // Launch add key pid_fk
            $dbman->add_key($table, $key);


            $index = new \xmldb_index('pidnm_indx', XMLDB_INDEX_NOTUNIQUE, array('pluginid', 'getstringname'));

            // Conditionally launch add index pidnm_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }





             // Define field daynum to be added to lbp_register_events
            $table = new \xmldb_table('lbp_register_events');
            $field = new \xmldb_field('daynum', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'day');

            // Conditionally launch add field daynum
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }


            $index = new \xmldb_index('ecode_indx', XMLDB_INDEX_NOTUNIQUE, array('eventcode'));

            // Conditionally launch add index ecode_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('daynum_indx', XMLDB_INDEX_NOTUNIQUE, array('daynum'));

            // Conditionally launch add index daynum_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('dn_st_indx', XMLDB_INDEX_NOTUNIQUE, array('daynum', 'starttime'));

            // Conditionally launch add index dn_st_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }



            $table = new \xmldb_table('lbp_review_question_values');
            $index = new \xmldb_index('valdel_indx', XMLDB_INDEX_NOTUNIQUE, array('value', 'del'));

            // Conditionally launch add index valdel_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('valnumdel_indx', XMLDB_INDEX_NOTUNIQUE, array('value', 'numericvalue', 'del'));

            // Conditionally launch add index valnumdel_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }




            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014022701, 'elbp');

        }


        if ($oldversion < 2014022707)
        {


            $table = new \xmldb_table('lbp_confidentiality');
            $index = new \xmldb_index('nm_index', XMLDB_INDEX_NOTUNIQUE, array('name'));

            // Conditionally launch add index nm_index
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }


            // Define key gid_fk (foreign) to be added to lbp_custom_plugins
            $table = new \xmldb_table('lbp_custom_plugins');
            $key = new \xmldb_key('gid_fk', XMLDB_KEY_FOREIGN, array('groupid'), 'lbp_plugin_groups', array('id'));

            // Launch add key gid_fk
            $dbman->add_key($table, $key);

            $index = new \xmldb_index('ttl_indx', XMLDB_INDEX_NOTUNIQUE, array('title'));

            // Conditionally launch add index ttl_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('ttl_en_indx', XMLDB_INDEX_NOTUNIQUE, array('title', 'enabled'));

            // Conditionally launch add index ttl_en_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('ttl_en_ord_indx', XMLDB_INDEX_NOTUNIQUE, array('title', 'enabled', 'ordernum'));

            // Conditionally launch add index ttl_en_ord_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('gid_ord_indx', XMLDB_INDEX_NOTUNIQUE, array('groupid', 'ordernum'));

            // Conditionally launch add index gid_ord_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new \xmldb_index('gid_ord_en_indx', XMLDB_INDEX_NOTUNIQUE, array('groupid', 'ordernum', 'enabled'));

            // Conditionally launch add index gid_ord_en_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }


            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014022707, 'elbp');

        }

        if ($oldversion < 2014030402)
        {

            // Define field img to be added to lbp_challenges
            $table = new \xmldb_table('lbp_challenges');

            if ($dbman->table_exists($table))
            {

                $field = new \xmldb_field('img', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'parent');

                // Conditionally launch add field img
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }

            }

            // elbp savepoint reached
            \upgrade_block_savepoint(true, 2014030402, 'elbp');

        }


        if ($oldversion < 2014061700)
        {

            // Define table lbp_user_capabilities to be created.
            $table = new \xmldb_table('lbp_user_capabilities');

            // Adding fields to table lbp_user_capabilities.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('capabilityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('value', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_user_capabilities.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('uid_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
            $table->add_key('cpid_fk', XMLDB_KEY_FOREIGN, array('capabilityid'), 'capabilities', array('id'));

            // Conditionally launch create table for lbp_user_capabilities.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2014061700, 'elbp');

        }


        if ($oldversion < 2014102901)
        {

            $this->DB->insert_record("lbp_confidentiality", array("id" => 4, "name" => "PERSONAL"));

            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2014102901, 'elbp');

        }

        // Inserting traffic light report element
        if ($oldversion < 2015102700)
        {

            $this->DB->insert_record("lbp_plugin_report_elements", array("pluginid" => null, "getstringname" => "reports:elbp:trafficlightstatus", "getstringcomponent" => "block_elbp"));

            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2015102700, 'elbp');

        }

        // lbp_target_sets
        if ($oldversion < 2016011900) {

            // Define table lbp_target_sets to be created.
            $table = new \xmldb_table('lbp_target_sets');

            // Adding fields to table lbp_target_sets.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('deleted', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');

            // Adding keys to table lbp_target_sets.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Conditionally launch create table for lbp_target_sets.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }


            // Define table lbp_target_set_attributes to be created.
            $table = new \xmldb_table('lbp_target_set_attributes');

            // Adding fields to table lbp_target_set_attributes.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('targetsetid', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
            $table->add_field('field', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

            // Adding keys to table lbp_target_set_attributes.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Conditionally launch create table for lbp_target_set_attributes.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }

            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2016011900, 'elbp');

        }



        // Plugin layouts
        if ($oldversion < 2016040401) {

            // Define table lbp_plugin_layouts to be created.
            $table = new \xmldb_table('lbp_plugin_layouts');

            // Adding fields to table lbp_plugin_layouts.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('isdefault', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

            // Adding keys to table lbp_plugin_layouts.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Conditionally launch create table for lbp_plugin_layouts.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }





            // Define field layoutid to be added to lbp_plugin_groups.
            $table = new \xmldb_table('lbp_plugin_groups');
            $field = new \xmldb_field('layoutid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'enabled');

            // Conditionally launch add field layoutid.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }





            // Define table lbp_plugin_group_plugins to be created.
            $table = new \xmldb_table('lbp_plugin_group_plugins');

            // Adding fields to table lbp_plugin_group_plugins.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('ordernum', XMLDB_TYPE_NUMBER, '4, 1', null, XMLDB_NOTNULL, null, '0');

            // Adding keys to table lbp_plugin_group_plugins.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('fk_pid', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_plugins', array('id'));
            $table->add_key('fk_pgid', XMLDB_KEY_FOREIGN, array('groupid'), 'lbp_plugin_groups', array('id'));

            // Conditionally launch create table for lbp_plugin_group_plugins.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }





            // Fix old groups
            //
            // Create default layout
            $ins = new \stdClass();
            $ins->name = \get_string('default', 'block_elbp');
            $ins->enabled = 1;
            $ins->isdefault = 1;
            $id = $DB->insert_record("lbp_plugin_layouts", $ins);

            // Change groups to have that layout id
            $DB->execute("UPDATE {lbp_plugin_groups} SET layoutid = ?", array($id));

            // FInd plugins and add their ordernums to new table
            $plugins = $DB->get_records("lbp_plugins");
            if ($plugins)
            {
                foreach($plugins as $plugin)
                {

                    $ins = new \stdClass();
                    $ins->pluginid = $plugin->id;
                    $ins->groupid = $plugin->groupid;
                    $ins->ordernum = $plugin->ordernum;
                    $DB->insert_record("lbp_plugin_group_plugins", $ins);

                }
            }





            // Define field groupid to be dropped from lbp_plugins.
            $table = new \xmldb_table('lbp_plugins');

            // Launch drop key gid_fk.
            $key = new \xmldb_key('gid_fk', XMLDB_KEY_FOREIGN, array('groupid'), 'lbp_plugin_groups', array('id'));
            $dbman->drop_key($table, $key);

            // Conditionally launch drop field groupid.
            $field = new \xmldb_field('groupid');
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            // Conditionally launch drop field ordernum.
            $field = new \xmldb_field('ordernum');
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }





            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2016040401, 'elbp');

            \mtrace("~~ Created new tables: `lbp_plugin_layouts`, `lbp_plugin_group_plugins` and moved plugin group information into new tables ~~");

        }



        if ($oldversion < 2016040500)
        {

            // Define table lbp_custom_plugin_grp_plugin to be created.
            $table = new \xmldb_table('lbp_custom_plugin_grp_plugin');

            // Adding fields to table lbp_custom_plugin_grp_plugin.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('pluginid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('ordernum', XMLDB_TYPE_NUMBER, '4, 1', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table lbp_custom_plugin_grp_plugin.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('fk_pid', XMLDB_KEY_FOREIGN, array('pluginid'), 'lbp_custom_plugins', array('id'));
            $table->add_key('fk_gid', XMLDB_KEY_FOREIGN, array('groupid'), 'lbp_plugin_groups', array('id'));

            // Conditionally launch create table for lbp_custom_plugin_grp_plugin.
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }




            // Find custom plugins and add records to new table
            $plugins = $DB->get_records("lbp_custom_plugins");
            if ($plugins)
            {
                foreach($plugins as $plugin)
                {

                    $ins = new \stdClass();
                    $ins->pluginid = $plugin->id;
                    $ins->groupid = $plugin->groupid;
                    $ins->ordernum = $plugin->ordernum;
                    $DB->insert_record("lbp_custom_plugin_grp_plugin", $ins);

                }
            }




            // Define field groupid to be dropped from lbp_custom_plugins.
            $table = new \xmldb_table('lbp_custom_plugins');

            // Launch drop key gid_fk.
            $key = new \xmldb_key('gid_fk', XMLDB_KEY_FOREIGN, array('groupid'), 'lbp_plugin_groups', array('id'));
            $dbman->drop_key($table, $key);

            // Conditionally launch drop index gid_ord_indx.
            $index = new \xmldb_index('gid_ord_indx', XMLDB_INDEX_NOTUNIQUE, array('groupid', 'ordernum'));
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            // Conditionally launch drop index gid_ord_en_indx.
            $index = new \xmldb_index('gid_ord_en_indx', XMLDB_INDEX_NOTUNIQUE, array('groupid', 'ordernum', 'enabled'));
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }


            // Conditionally launch drop index ttl_en_ord_indx.
            $index = new \xmldb_index('ttl_en_ord_indx', XMLDB_INDEX_NOTUNIQUE, array('title', 'enabled', 'ordernum'));
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }



            $field = new \xmldb_field('groupid');
            // Conditionally launch drop field groupid.
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }


            $field = new \xmldb_field('ordernum');
            // Conditionally launch drop field ordernum.
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }


            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2016040500, 'elbp');

            \mtrace("~~ Created new table: `lbp_custom_plugin_grp_plugin` and moved custom plugin group information into new table ~~");


        }



        if ($oldversion < 2016040501)
        {

            // Define index ttl_indx (not unique) to be dropped form lbp_custom_plugins.
            $table = new \xmldb_table('lbp_custom_plugins');

            // Conditionally launch drop index ttl_indx.
            $index = new \xmldb_index('ttl_indx', XMLDB_INDEX_NOTUNIQUE, array('title'));
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }


            // Conditionally launch drop index ttl_en_indx.
            $index = new \xmldb_index('ttl_en_indx', XMLDB_INDEX_NOTUNIQUE, array('title', 'enabled'));
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }


            // Launch rename field title.
            $field = new \xmldb_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id');
            $dbman->rename_field($table, $field, 'name');

            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2016040501, 'elbp');

            \mtrace("~~ Changed field name from `title` to `name` on table lbp_custom_plugins` ~~");


        }


        if ($oldversion < 2017052400)
        {

            // Clear any alerts for groupid
            $DB->delete_records_select("lbp_alerts", "groupid is not null", array());

            // Table changes
            $table = new \xmldb_table('lbp_alerts');

            // Define key gid_fk (foreign) to be dropped form lbp_alerts.
            $key = new \xmldb_key('gid_fk', XMLDB_KEY_FOREIGN, array('groupid'), 'groups', array('id'));
            $dbman->drop_key($table, $key);

            $field = new \xmldb_field('groupid');
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            $field = new \xmldb_field('mass', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'studentid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Elbp savepoint reached.
            \upgrade_block_savepoint(true, 2017052400, 'elbp');


        }






        $newBlockVersion = $this->getNewBlockVersion();

        // Plugin upgrades - THis could be a problem... what if they haven't installed a plugin? Then the upgrade won't work
        //
        if ($this->plugins)
        {
            foreach($this->plugins as $plugin)
            {
                // We don't send the oldversion number here, because this is the version number of the elbp block
                // Plugins have their own version number in the plugins table which we will use
                $plugin->upgrade();
                $plugin->setVersion($newBlockVersion);
                $plugin->updatePlugin();
            }
        }

        return $result;

    }

    /**
     * Get the new version from the version.php file
     * @global \ELBP\type $CFG
     * @return type
     */
    public function getNewBlockVersion(){

        global $CFG;
        $plugin = new \stdClass();
        include $CFG->dirroot . '/blocks/elbp/version.php';
        return $plugin->version;

    }

    /**
     * Execute a command from the ELBP command line tool
     * I will delete this soon, it's pretty pointless.
     * @global \ELBP\type $CFG
     * @global \ELBP\type $DB
     * @param type $action
     * @return string
     */
    public function executeAjaxCommand($action)
    {

        global $CFG, $DB;

        $explode = explode(" ", $action);
        $act = $explode[0];

        $supportedCommands = array('help', 'search', 'load', 'pt');

        $output = "";

        switch($act)
        {

            // List available commands
            case 'help':

                $output .= get_string('execute:help:supported', 'block_elbp') . ":\n";
                foreach($supportedCommands as $command)
                {
                    $output .= $command . " - " . get_string('execute:help:'.$command, 'block_elbp') . "\n";
                }

                return $output;

            break;

            // Search for a user
            case 'search':

                array_shift($explode);
                $search = ($explode) ? implode(" ", $explode) : false;

                if (!$search) return get_string('execute:search:search', 'block_elbp');

                $users = $DB->get_records_select("user", "( username LIKE ? OR lastname LIKE ? OR firstname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ? ) AND deleted = 0 AND confirmed = 1",
                                                 array('%'.$search.'%', '%'.$search.'%', '%'.$search.'%', '%'.$search.'%'),
                                                 "lastname ASC, firstname ASC, username ASC");

                if ($users)
                {

                    foreach($users as $user)
                    {

                        $output .= "<a href='{$CFG->wwwroot}/blocks/elbp/view.php?id={$user->id}' target='_blank'>".fullname($user) . " ({$user->username})</a>\n";

                    }

                    return $output;

                }
                else
                {
                    return get_string('noresults', 'block_elbp') . " - " . elbp_html($search);
                }


            break;

            // Load up a user's ELBP
            case 'load':

                $username = (isset($explode[1])) ? $explode[1] : false;

                if (!$username) return get_string('execute:load:username', 'block_elbp');

                $user = $DB->get_record("user", array("username" => $username));

                if ($user)
                {
                    $output .= get_string('loading', 'block_elbp') . ' ' . fullname($user) . '...';
                    $output .= "<script>window.location.href = '{$CFG->wwwroot}/blocks/elbp/view.php?id={$user->id}';</script>";
                    return $output;
                }
                else
                {
                    return get_string('execute:load:invalidusername', 'block_elbp') . " - " . elbp_html($username);
                }


            break;

            // Display the Personal Tutors assigned to a user
            case 'pt':

                $username = (isset($explode[1])) ? $explode[1] : false;

                if (!$username) return get_string('execute:load:username', 'block_elbp');

                $user = $DB->get_record("user", array("username" => $username));

                if ($user)
                {

                    $ELBPDB = new \ELBP\DB();
                    $tutors = $ELBPDB->getTutorsOnStudent($user->id);
                    $output = "";

                    if ($tutors)
                    {

                        foreach($tutors as $tutor)
                        {
                            $output .= fullname($tutor) . "\n";
                        }
                    }
                    else
                    {
                        $output .= get_string('noresults', 'block_elbp');
                    }

                    return $output;


                }
                else
                {
                    return get_string('execute:load:invalidusername', 'block_elbp') . " - " . elbp_html($username);
                }


            break;

            default:

                return get_string('unknowncommand', 'block_elbp') . ": {$action}";

            break;

        }

    }

    /**
     * Get the student's overall progress bar
     * @param bool $info Show the info box as well
     * @return string
     */
    public function getStudentProgressBar($showInfo = true)
    {

        if (!$this->student) return '';

        $access = $this->getUserPermissions($this->student->id);

        $setting = $this->getSetting('enable_student_progress_bar');
        if ($setting != 'calculated' && $setting != 'manual') return '';

        $output = "";

        // Calculated progress
        if ($setting == 'calculated')
        {

            $progress = $this->calculateStudentProgress();
            $width = $progress['percent'];
            $info = $progress['info'];
            $colour = $this->getProgressColours($width);

            $output .= "<div class='elbp_target_bar elbp_student_progress_bar'>";
                $output .= "<div class='progress-bar {$colour['background']} stripes' title='{$width}%' onclick='$(\"div#student-progress-info\").slideToggle();return false;'>";
                    $output .= "<div style='width:{$width}%;'></div>";
                $output .= "</div>";

                if ($showInfo)
                {

                    $output .= "<div id='student-progress-info' onclick='$(this).slideToggle();return false;'>";
                        $output .= "<table>";
                            $output .= "<tr>";
                                $output .= "<th>".get_string('plugin', 'block_elbp')."</th>";
                                $output .= "<th>".get_string('requirement', 'block_elbp')."</th>";
                                $output .= "<th>".get_string('progress', 'block_elbp')."</th>";
                                $output .= "<th>".get_string('value', 'block_elbp')."</th>";
                            $output .= "</tr>";

                            if ($info)
                            {
                                foreach($info as $plugin => $i)
                                {
                                    if ($i)
                                    {
                                        foreach($i as $key => $val)
                                        {
                                            $output .= "<tr>";
                                                $output .= "<td>{$plugin}</td>";
                                                $output .= "<td>{$key}</td>";
                                                $output .= "<td>{$val['percent']}%</td>";
                                                $output .= "<td>{$val['value']}</td>";
                                            $output .= "</tr>";
                                            $plugin = '';
                                        }
                                    }
                                }
                            }

                            $output .= "<tr>";
                                $output .= "<td></td>";
                                $output .= "<td><b>".get_string('total', 'block_elbp')."</b></td>";
                                $output .= "<td><b>{$width}%</b></td>";
                                $output .= "<td></td>";
                            $output .= "</tr>";

                        $output .= "</table>";
                    $output .= "</div>";

                }

            $output .= "</div>";

        }

        // Manual
        elseif ($setting == 'manual')
        {

            // Count the number of options
            $options = $this->getSetting('manual_student_progress');
            $options = unserialize($options);

            // If there are options defined
            if ($options)
            {

                // Count number of options
                $cnt = count($options['ranks']);

                // Current rank for this student
                $currentRank = \ELBP\Setting::getSetting('student_progress_rank', $this->student->id);

                $descriptions = "";

                $output .= "<div class='elbp_progress_traffic_lights'>";

                    for($i = 0; $i < $cnt; $i++)
                    {

                        $rank = \elbp_html($options['ranks'][$i]);
                        $title = \elbp_html($options['titles'][$i]);
                        $colour = \elbp_html($options['colours'][$i]);
                        $desc = \elbp_html($options['desc'][$i]);
                        $trans = ($currentRank != $rank) ? 'elbp_progress_traffic_light_trans' : '';
                        $display = ($currentRank == $rank) ? 'inline-block' : 'none';

                        // Don't show the other ones if they can't edit it
                        if ($currentRank != $rank && !\elbp_has_capability('block/elbp:update_student_manual_progress', $access)){
                            continue;
                        }

                        // Get slightly darker/lighter colour, based on this colour
                        $colour2 = \elbp_get_gradient_colour($colour);

                        // Div output
                        $output .= "<div id='elbp_progress_traffic_light_{$rank}' class='elbp_progress_traffic_light {$trans}' rankNum='{$rank}' title='{$title}' style='background-color:{$colour};background: linear-gradient(to bottom, {$colour} 40%, {$colour2} 100%);'></div>";

                        // Description spans
                        $descriptions .= "<span id='elbp_progress_traffic_light_desc_{$rank}' class='elbp_progress_traffic_light_desc' style='display:{$display};'>";

                            if (\elbp_has_capability('block/elbp:update_student_manual_progress', $access)){
                                $descriptions .= "<input type='button' value='{$title}' rankNum='{$rank}' class='elbp_set_student_manual_progress' /><br>";
                            } else {
                                $descriptions .= "<b>{$title}</b><br>";
                            }

                            $descriptions .= "<small>";
                                if ($currentRank == $rank){
                                    $descriptions .= "<b>{$desc}</b>";
                                } else {
                                    $descriptions .= $desc;
                                }
                            $descriptions .= "</small>";

                        $descriptions .= "</span>";

                    }

                    $output .= "<div>";
                        $output .= $descriptions;
                    $output .= "</div>";

                    $output .= "<div id='elbp_progress_traffic_loading'></div>";

                $output .= "</div>";

            }


        }

        return $output;

    }

    /**
     * Get the current block version in the DB
     * @global \ELBP\type $DB
     * @return type
     */
    public static function getBlockVersionStatic(){

        global $DB;

        // Get block version
        $record = $DB->get_record("block", array("name" => "elbp"));
        if (isset($record->version)){
            $version = $record->version;
        } else {
            $version = get_config("block_elbp", "version");
        }

        return $version;

    }

    /**
     * Get current block version in DB
     * @return type
     */
    public function getBlockVersion(){

        return self::getBlockVersionStatic();

    }

    /**
     * An array of the tables that support being purged in the Environment config
     * @return type
     */
    public static function getSupportDBTablesForPurging(){

        return array(
            'lbp_att_punc_history'
        );

    }

    /**
     * Check if the Academic Year is enabled
     * @return type
     */
    public static function isAcademicYearEnabled()
    {

        $enabled = \ELBP\Setting::getSetting('academic_year_enabled');
        $year = \ELBP\Setting::getSetting('academic_year_start_date');

        return ($enabled == 1 && $year);

    }

    /**
     * Get the d-m-Y date of the start of the academic year
     * @return boolean
     */
    public function getAcademicYearStartDate(){

        $year = \ELBP\Setting::getSetting('academic_year_start_date');
        if ($year > 0){
            return date('d-m-Y', $year);
        } else {
            return false;
        }

    }

    /**
     * Instantiate self
     * @param type $options
     * @return \ELBP\ELBP
     */
    public static function instantiate( $options = null )
    {
        return new ELBP($options);
    }




}
