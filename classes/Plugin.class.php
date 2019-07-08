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
 abstract class Plugin {


    protected $CFG;
    protected $DB;
    protected $ELBPDB;
    protected $access;

    protected $id;
    protected $name;
    protected $title;
    protected $path;
    protected $version;
    protected $enabled;
    protected $useMIS = false;
    protected $mis_connection = false; # This is a lbp_pLugin_mis record for this plugin and "core" as the name
    protected $connection = false; # This is the actual MIS connection that is being used
    protected $plugin_connection = false;
    protected $mis_settings = array();

    protected $hooks = array();

    protected $js;
    protected $student;
    protected $course;

    protected $attributes;
    protected $requiredExtensions;

    public $www;

    protected $tables = array();

    public $quickID;

    /**
     * Construct a plugin object
     * @global array $CFG
     * @global type $DB
     * @param mixed $data If it's an int, we're constructing by ID. Most of the time it'll be a string name though.
     * It could also be an array however, if so it means we want to load up a class even if it doesn't exist in the DB
     * (installation of plugin), in which case we pass the name, title and version to it
     * @throws ELBPException
     */
    public function __construct($data) {

        global $CFG, $DB, $access;

        // If someone tries to call $this->connection->connect() or any other method will fail
        // Also too lazy to put in a try catch for every situtation
        // Therefore will use an anonymous class so that if there's no valid connection, they can still call connect on it
        $this->connection = new \Anon;
        $this->connection->connect = function() {
            try {
                throw new \ELBP\ELBPException( get_string('plugin', 'block_elbp') . ' - ' . $this->name, get_string('nomisconnection', 'block_elbp'), false, get_string('admin:setupmisconnectionplugin', 'block_elbp'));
            } catch (\ELBP\ELBPException $e){
                echo $e->getException();
            }
        };


        $this->CFG = $CFG;
        $this->DB = $DB;
        $this->ELBPDB = new \ELBP\DB();

        // Building up the object for installation, without checking the DB
        if (is_array($data))
        {

            // Data must contain: name, title, version, path. Anything else is optional
            if (!isset($data['name']) || !isset($data['title']) || !isset($data['version'])){
                throw new \ELBP\ELBPException( get_string('plugin', 'block_elbp'). ' - ' . $this->name, get_string('invaliddataarray', 'block_elbp') . '<br>' . print_r($data, true), get_string('programming:nametitleversionpath', 'block_elbp') );
                return;
            }

            foreach($data as $var => $val)
            {
                $this->$var = $val;
            }

            return;

        }



        // If the data is an integer, we are looking up the plugin based on its ID
        if (is_int($data)){
            $check = $this->DB->get_record("lbp_plugins", array("id" => $data));
        }

        // Otherwise we are looking it up based on its name
        else
        {
            $check = $this->DB->get_record("lbp_plugins", array("name" => $data));
        }

        if (!$check){
            throw new \ELBP\ELBPException(get_string('plugin', 'block_elbp'). ' - ' . $this->name, get_string('noplugininstalled', 'block_elbp') . ":<br>" . elbp_html($data), get_string('pluginidorname', 'block_elbp'));
            return;
        }

        // More stuff here
        $this->id = $check->id;
        $this->name = $check->name;
        $this->title = $check->title;
        $this->path = $check->path;
        $this->version = $check->version;
        $this->enabled = $check->enabled;
        $this->mis_connection = $this->getMISConnection("core");

        $this->js = false;
        $this->student = false;

        if (is_null($this->path)){
            $this->www = $CFG->wwwroot . '/blocks/elbp/plugins/' . $this->name . '/';
        } else {
            $this->www = $CFG->wwwroot . '/' . $this->path;
        }

        $this->quickID = $this->id;

    }

    /**
     * This is required for checking if a plugin is custom or not
     * @return boolean
     */
    public function isCustom(){
        return false;
    }

    public function getID(){
        return $this->id;
    }

    public function getName(){
        return $this->name;
    }

    /**
     * Get name with spaces changed to underscores
     * @return type
     */
    public function getNameString(){
        return str_replace(" ", "_", $this->name);
    }

    /**
     * Get the defined title of the plugin
     * @return type
     */
    public function getTitle(){
        return clean_text($this->title, FORMAT_PLAIN);
    }

    /**
     * Get the version number of the plugin
     * @return type
     */
    public function getVersion(){
        return $this->version;
    }

    /**
     * Get the path to the plugin, if it's an external plugin which is located in a different directory
     * @return type
     */
    public function getPath(){
        return $this->path;
    }


    /**
     * Get a string of the version number, displaying as an actual date
     * @return type
     */
    public function getVersionDateString(){
        $parts = elbp_convert_version($this->version);
        return "v{$parts['version']} {$parts['day']}/{$parts['month']}/{$parts['year']}";
    }

    protected function setTitle($title){
        $this->title = $title;
        return $this;
    }

    protected function setEnabled($value){
        $this->enabled = $value;
        return $this;
    }

    public function setVersion($version){
        $this->version = $version;
        return $this;
    }

    /**
     * Set the permissions array for this context as calculated
     * @param type $access
     */
    public function setAccess($access){
        $this->access = $access;
    }

    /**
     * Get the permissions array as calculated
     * @return type
     */
    public function getAccess(){
        return $this->access;
    }

    /**
     * Get a list of all the database tables related to this plugin, so we can wipe them on uninstall
     * @return type
     */
    public function getDBTables(){
        return $this->tables;
    }

    /**
     * Get the path to the plugin's config file
     * @return type
     */
    public function getConfigPath()
    {
        $path = "";
        $path .= (!is_null($this->getPath())) ? $this->getPath() : 'blocks/elbp/plugins/'.$this->getName().'/';
        $path .= ($this->hasDuplicatePath()) ? "config_" . $this->getName() . ".php" : "config.php";
        return $path;
    }

    /**
     * Load required javascript
     * @global array $CFG
     * @global type $PAGE
     * @param bool $simple If true, will be returned in <script> tags. Otherwise will be added to the $PAGE global variable
     * @return type
     */
    public function loadJavascript()
    {
        global $CFG, $PAGE;

        if ($this->js)
        {
            foreach($this->js as $js => $method)
            {
                $PAGE->requires->js_call_amd($js, $method);
            }
        }

    }

    /**
     * Get the MIS connection linked to this plugin
     * @return type
     */
    public function getMainMIS(){
        return $this->mis_connection;
    }

    /**
     * Load all the possible hooks related to this plugin
     * @global \ELBP\Plugins\type $DB
     */
    public function loadHooks(){

        global $DB;

        $records = $DB->get_records_sql("SELECT ph.*, h.name, p.name as hookPluginName
                                         FROM {lbp_plugin_hooks} ph
                                         INNER JOIN {lbp_hooks} h ON h.id = ph.hookid
                                         INNER JOIN {lbp_plugins} p ON p.id = h.pluginid
                                         WHERE ph.pluginid = ?", array($this->id));

        $this->hooks = $records;

    }

    /**
     * Get the hooks
     * @return type
     */
    public function getHooks(){
        return $this->hooks;
    }

    /**
     * Get a list of any PHP extensions which are required in order for this plugin to work
     * @return type
     */
    public function getRequiredExtensions(){
        return $this->requiredExtensions;
    }


    /**
     * Some plugins have extra bits that Bedford College want, but which I don't think should be released
     * in any open source release of the block, as they are fairly custom to us and wouldn't really make
     * all that much sense in the release.
     * This is where they are loaded
     * @param type $extra
     */
    public function loadExtra($extra){

        global $CFG;

        if (is_null($this->path))
        {
            $file = $CFG->dirroot . '/blocks/elbp/plugins/' . $this->name . '/extras/' . $extra . '/' . $extra . '.php';
        }
        else
        {
            $file = $CFG->dirroot . '/' . $this->path . '/extras/' . $extra . '/' . $extra . '.php';
        }

        if (file_exists($file))
        {
            require_once $file;
            $className = "\\ELBP\\Plugins\\{$this->name}\\Extras\\{$extra}";
            return new $className;
        }

        return false;

    }


    /**
     * Get all the possible events for this plugin
     */
    public function getEvents(){

        global $DB;
        return $DB->get_records("lbp_alert_events", array("pluginid" => $this->id, "enabled" => 1));

    }

    /**
     * Set a user's alert preferences for a given event
     * @param int $eventID
     * @param int $value
     * @param array $params
     * @param array $attributes
     */
    public function setUserEventPreference($eventID, $value, $params, $attributes = false, $userID = null)
    {

        global $DB, $USER;

        if (is_null($userID)) $userID = $USER->id;

        // See if there is already a record for this user and this event

        // Student-based
        if (isset($params['studentID'])){
            $field = 'studentid';
            $fieldValue = $params['studentID'];
        }

        // Group-based
        elseif (isset($params['groupID'])){
            $field = 'groupid';
            $fieldValue = $params['groupID'];
        }

        // Course-based
        elseif (isset($params['courseID'])){
            $field = 'courseid';
            $fieldValue = $params['courseID'];
        }

        else {
            return false;
        }

        $check = $DB->get_record("lbp_alerts", array("eventid" => $eventID, "userid" => $userID, $field => $fieldValue));

        if (!$check)
        {
            // Insert new record

            $data = new \stdClass();
            $data->userid = $userID;
            $data->eventid = $eventID;
            $data->$field = $fieldValue;
            $data->value = $value;

            $id = $DB->insert_record("lbp_alerts", $data);

            // If attributes are defined, add them as well
            if ($attributes)
            {
                foreach($attributes as $attribute => $attributeValue)
                {
                    $data = new \stdClass();
                    $data->useralertid = $id;
                    $data->$field = $attribute;
                    $data->value = $attributeValue;
                    $DB->insert_record("lbp_alert_attributes", $data);
                }
            }

        }
        else
        {
            // Update existing record

            $check->value = $value;
            $DB->update_record("lbp_alerts", $check);

            // Just delete the attributes, too lazy to check them all
            $DB->delete_records("lbp_alert_attributes", array("useralertid" => $check->id));

            // If attributes are defined, add them as well
            if ($attributes)
            {
                foreach($attributes as $attribute => $attributeValue)
                {
                    $data = new \stdClass();
                    $data->useralertid = $check->id;
                    $data->$field = $attribute;
                    $data->value = $attributeValue;
                    $DB->insert_record("lbp_alert_attributes", $data);
                }
            }

        }


    }

    /**
     * Get any mass actions this plugin has
     * @return boolean
     */
    public function getMassActions(){
        return false;
    }


    /**
     * Are there any other plugins with the same path as this?
     */
    public function hasDuplicatePath(){

        global $DB;

        $record = $DB->get_record_select("lbp_plugins", "path = ? AND id <> ?", array($this->path, $this->id), "id", IGNORE_MULTIPLE);

        return ($record) ? true : false;

    }


    /**
     * Check if plugin has a given hook enabled
     * @param mixed $hookID Can be int - ID, or string - name
     * @return boolean
     */
    public function hasHookEnabled($hookID){

        if (!$this->hooks) $this->loadHooks();

        if ($this->hooks)
        {
            foreach($this->hooks as $hook)
            {

                // If digit, check based on ID
                if (ctype_digit($hookID) || is_numeric($hookID))
                {
                    if ($hook->hookid == $hookID)
                    {
                        return true;
                    }
                }
                // Else it'll have to be in the format PluginName/HookName and we'll check that
                else
                {
                    if (preg_match("/[a-z]+\/[a-z]+/i", $hookID))
                    {
                        $split = explode("/", $hookID);
                        $pluginName = $split[0];

                        $cntSplit = count($split);
                        $hookName = "";
                        for($i = 1; $i < $cntSplit; $i++)
                        {
                            $hookName .= $split[$i] . "/";
                        }

                        $hookName = rtrim($hookName, "/");

                        if ($hook->hookpluginname == $pluginName && $hook->name == $hookName)
                        {
                            return true;
                        }
                    }
                }
            }
        }

        return false;

    }

    /**
     * Should it have a plugin box and popup?
     * @return boolean
     */
    public function hasPluginBox(){
        return true;
    }




//    /**
//     * Call a hook (if we have this hook enabled)
//     * This is for inserting form elements into things to submit extra data into tables (generall attribute tables)
//     * @param string $pluginName
//     * @param string $hookName
//     */
//    public function callHook($pluginName, $hookName){
//
//        global $DB, $ELBP;
//
//        $plugin = $ELBP->getPlugin($pluginName);
//        if (!$plugin) return false;
//
//        $hook = $DB->get_record("lbp_hooks", array("name" => $hookName, "pluginid" => $plugin->getID()));
//        if (!$hook) return false;
//
//        if (!$this->hasHookEnabled($hook->id)) return false;
//
//        $method = "_hookIn_{$hookName}";
//
//        if (!method_exists($plugin, $method)) return false;
//
//        return $plugin->$method();
//
//    }





    /**
     * Save the settings just sent in the plugin configuration form
     * @param type $settings
     */
    public function saveConfig($settings)
    {

        if (isset($settings['submitconfig']))
        {

            // Remove so doesn't get put into lbp_settings
            unset($settings['submitconfig']);

            // Enabled is stored in the plugins table, not the settings table, so do that differently
            if (isset($settings['enabled']))
            {
                $this->setEnabled($settings['enabled']);
                $this->updatePlugin();
                unset($settings['enabled']);
            }

            // Title is stored in the plugins table, not the settings table
            if (isset($settings['plugin_title']))
            {
                $title = trim($settings['plugin_title']);
                if (!empty($title)){
                    $this->setTitle($settings['plugin_title']);
                    $this->updatePlugin();
                }
                unset($settings['plugin_title']);
            }

            if (!isset($settings['override_academic_year'])){
                $settings['override_academic_year'] = 0;
            }

            foreach( (array)$settings as $setting => $value ){
                $this->updateSetting($setting, $value);
            }
        }
    }

    /**
     * Update a setting for this plugin
     * @param type $setting The setting name
     * @param type $value The value
     * @param type $userID (optional) The user ID
     * @return type
     */
    public function updateSetting($setting, $value, $userID = null)
    {
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_SETTINGS, LOG_ACTION_ELBP_SETTINGS_UPDATED_SETTING, $userID, array(
            "setting" => $setting,
            "value" => $value
        ));
        return \ELBP\Setting::setSetting($setting, $value, $userID, $this->id);
    }

    /**
     * Get a setting for this plugin
     * @param type $setting Setting name
     * @param type $userID (optional) the user id
     * @return type
     */
    public function getSetting($setting, $userID = null)
    {
        return \ELBP\Setting::getSetting($setting, $userID, $this->id);
    }

    /**
     * Get the ID of the student loaded into this plugin
     * @return boolean
     */
    public function getStudentID()
    {
        if ($this->student){
            return $this->student->id;
        }

        return false;
    }

    /**
     * Get the student loaded into this plugin
     * @return type
     */
    public function getStudent(){
        return $this->student;
    }

    /**
     * Get the background colour to use for this plugin's header
     * @return type
     */
    public function getHeaderBackgroundColour(){

        // Check if this user has set their own colours
        if ($this->student){
            $col = \ELBP\Setting::getSetting("header_bg_col", $this->student->id, $this->id);
            if ($col){
                return $col;
            }
        }

        $col = \ELBP\Setting::getSetting("header_bg_col", null, $this->id);

        return ($col) ? $col : '#ffffff';

    }

    /**
     * Get the font colour to use for this plugin's header
     * @return type
     */
    public function getHeaderFontColour(){

        // Check if this user has set their own colours
        if ($this->student){
            $col = \ELBP\Setting::getSetting("header_font_col", $this->student->id, $this->id);
            if ($col){
                return $col;
            }
        }

        $col = \ELBP\Setting::getSetting("header_font_col", null, $this->id);

        return ($col) ? $col : '#000000';

    }

    /**
     * Get a CSS string to apply the bg and font colour to the html element
     * @return type
     */
    public function getHeaderStyle()
    {
        $style = "";
        $bg = $this->getHeaderBackgroundColour();
        $font = $this->getHeaderFontColour();

        // Make sure they are valid - could type anything into input box and display it on anyone who views
        if (!preg_match("/^#[a-z0-9]{3,6}$/i", $bg)){
            $bg = '#ffffff';
        }

        if (!preg_match("/^#[a-z0-9]{3,6}$/i", $font)){
            $font = '#000000';
        }


        // if using gradients:
        $gradients = \ELBP\Setting::getSetting('elbp_use_gradients');

        if ($bg){

            if ($gradients == 1){

                $gr = elbp_get_gradient_colour($bg);
                $rgbBG = elbp_hex_to_rgb($bg);
                $rgbGR = elbp_hex_to_rgb($gr);
                $hslBG = elbp_convert_rgb_hsl($rgbBG['red'], $rgbBG['green'], $rgbBG['blue']);
                $hslGR = elbp_convert_rgb_hsl($rgbGR['red'], $rgbGR['green'], $rgbGR['blue']);

                // If gradient is lighter than background, reverse them
                if ($hslGR[2] > $hslBG[2]){
                    $tmpBG = $bg;
                    $bg = $gr;
                    $gr = $tmpBG;
                }

                $style .= "background: {$bg};";
                $style .= "background: -ms-linear-gradient(top, {$bg} 0%, {$bg} 50%, {$gr} 51%, {$gr} 100%);";
                $style .= "background: linear-gradient(to bottom, {$bg} 0%, {$bg} 50%, {$gr} 51%, {$gr} 100%);";
                $style .= "filter: progid:DXImageTransform.Microsoft.gradient( startColorstr=\"{$bg}\", endColorstr=\"{$gr}\",GradientType=0 );";

            } else {

                $style .= "background: {$bg};";

            }

        }

        // Else:
        if ($font) $style .= "color: {$font};";

        return $style;

    }

    /**
     * Get the path to the icon to put into an img tag
     */
    public function getDockIconPath(){

        global $CFG;

        $path = $this->getPath();

        if (is_null($path)){
            $icon = $CFG->wwwroot . '/blocks/elbp/plugins/'.$this->getName().'/pix/dock.png';
        } else {
            $icon = $CFG->wwwroot . $path . 'pix/'.$this->getName().'/dock.png';
            if (!file_exists( str_replace($CFG->wwwroot, $CFG->dirroot, $icon) )){
                $icon = $CFG->wwwroot . $path . 'pix/dock.png';
            }
        }

        return $icon;

    }

    /**
     * Get the JS string to be applied to the close icon element, in order to change the colour on hover
     * to something that will still be visible against the chosen background
     * @return type
     */
    public function getIconHover(){

        $font = $this->getHeaderFontColour();

        if (!preg_match("/^#[a-z0-9]{3,6}$/i", $font)){
            $font = '#000000';
        }

        $hover = elbp_convert_hex_opposite($font);

        $output = " onmouseover='$(this).css(\"color\", \"{$hover}\");' onmouseout='$(this).css(\"color\", \"{$font}\");' ";

        return $output;

    }

    /**
     * Get the current MIS connection loaded
     * @return type
     */
    public function getLiveConnection(){
        return $this->connection;
    }

    /**
     * Get an plugin-MIS connection link record by name
     * @param type $name
     * @return type
     */
    protected function getMISConnection($name)
    {
        $record = $this->DB->get_record("lbp_plugin_mis", array("pluginid"=>$this->id, "name"=>$name));
        return $record;
    }

    /**
     * Get an MIS connection record by id
     * @param type $id
     * @return type
     */
    protected function getMISConnectionByID($id)
    {
        return $this->DB->get_record("lbp_mis_connections", array("id"=>$id));
    }


    /**
     * Load the core MIS connection for this plugin (or any mis connection if name specified)
     * @param type $name
     */
    public function loadMISConnection($name = false)
    {

        if (!$name)
        {
            // Get the name of the core one
            if (!$this->mis_connection) return false;
            $conn = $this->getMISConnectionByID($this->mis_connection->misid);
            if (!$conn) return false;
            $name = $conn->name;
        }

        try {
            $MIS = \ELBP\MIS\Manager::instantiate( $name );
            $this->connection = $MIS;
        }
        catch(ELBPException $e){
            echo $e->getException();
        }
    }

    /**
     * Disable the plugin dynamically
     * Won't disable it in the DB, but it will now fail isEnabled() check for the current running script
     */
    public function disable(){
        $this->enabled = false;
    }

    /**
     * Check if plugin is enabled
     * @return type
     */
    public function isEnabled()
    {
        return (bool) $this->enabled;
    }

    /**
    * Check if the plugin is installed in the DB
    * @return type
    */
    public function isInstalled()
    {
        $check = $this->DB->get_record("lbp_plugins", array("name" => $this->name));
        return ($check) ? true : false;
    }

    /**
     * Has it got a cron enabled?
     * @return type
     */
    public function isCronEnabled()
    {
        $setting = $this->getSetting('cron_enabled');
        return ($setting == 1);
    }

    /**
     * To be overriden by any plugin's which can use a cron
     * @return boolean
     */
    public function cron(){
        return true;
    }


    /**
     * Check if the plugin uses headers. This will be overwritten by StudentProfile plugin to false, as it doesn;t
     * @return boolean
     */
    public function isUsingHeaders(){
        return true;
    }

    /**
     * Check if the plguin uses progress bars. Will be overwritten by any that do use them.
     * @return boolean
     */
    public function isUsingBlockProgress(){
        return false;
    }

    /**
     * Check if the plugin is using an MIS connection
     * @return type
     */
    public function isUsingMIS(){
        return $this->useMIS;
    }

    /**
     * Get an MIS setting from those loaded
     * @param type $setting
     * @return type
     */
    public function getMisSetting($setting){
        return (isset($this->mis_settings[$setting])) ? $this->mis_settings[$setting] : false;
    }


    /**
     * Is the Academic Year setting enabled in the ELBP and not overriden by this plugin?
     * @return type
     */
    public function isAcademicYearEnabled(){
        return (\ELBP\ELBP::isAcademicYearEnabled() && $this->getSetting('override_academic_year') != 1);
    }

    /**
     * Get the unix timestamp of the start of the Academic Year
     * @return boolean
     */
    public function getAcademicYearUnix(){
        if (!$this->isAcademicYearEnabled()) return false;
        return \ELBP\Setting::getSetting('academic_year_start_date');
    }

    /**
     * Check if this plugin is in a particular plugin group
     * @param type $groupID
     * @return boolean
     */
    public function isInPluginGroup($groupID){

        $check = $this->DB->get_record("lbp_plugin_group_plugins", array("pluginid" => $this->id, "groupid" => $groupID));
        return ($check) ? true : false;

    }



    /**
     * Get the plugin connection loaded
     * @return type
     */
    public function getPluginConnection(){
        return $this->plugin_connection;
    }

    /**
     * This simply creates a record in lbp_plugins with the current data in the object - name, title
     * @param int $version This is the version number specified by the plugin's install method, so it can be upgraded later on if necessary
     */
    protected function createPlugin()
    {
        // Make sure it's not already installed
        if (!$this->isInstalled())
        {

            // This is the most basic install of a plugin, just creates that 1 record in lbp_plugins
            // Plugins can of course overwrite this method to do extra things if they want to

            $record = new \stdClass();
            $record->name = $this->name;
            $record->title = $this->title;
            $record->path = $this->path;
            $record->version = $this->version;
            $record->enabled = 0; # Disable by default so we can do the config settings for it first
            return $this->DB->insert_record("lbp_plugins", $record);
        }

        return $this->id;

    }

    /**
     * Update the plugin info
     * @return type
     */
    public function updatePlugin()
    {
        if ($this->isInstalled())
        {
            $record = new \stdClass();
            $record->id = $this->id;
            $record->name = $this->name;
            $record->title = $this->title;
            $record->version = $this->version;
            $record->enabled = $this->enabled;
            return $this->DB->update_record("lbp_plugins", $record);
        }
    }

    /**
     * In case the next upgrade section fails, save the version number to the DB now so it doesn't cock up if have to run it again
     * @param int $version
     */
    protected function upgradeSavePoint($version)
    {
        $current = $this->version;
        $this->setVersion($version)->updatePlugin();
        elbp_print_success_msg( get_string('upgraded', 'block_elbp') . ' ' . $this->name . ' ' .
                                get_string('from', 'block_elbp') . ' ' . get_string('version', 'block_elbp') . ' ' . $current . ' ' .
                                get_string('to', 'block_elbp') . ' ' . $version );
    }

    /**
     * Call all hooks enabled on this plugin and return the data
     * @global type $ELBP
     * @param type $params
     * @return boolean
     */
    protected function callAllHooks($params)
    {

        global $ELBP,$courses;
        if (!$this->student) return false;
        if (!$this->hooks) $this->loadHooks();
        $return = array();

        if ($this->hooks)
        {
            foreach($this->hooks as $hook)
            {
                $method = "_callHook_".$hook->name;

                $method = str_replace("/", "_", $method);
                $method = str_replace(" ", "_", $method);

                $plugin = $ELBP->getPlugin($hook->hookpluginname);
                if (method_exists($plugin, $method)){
                    $return[$hook->hookpluginname . '/' . $hook->name] = $plugin->$method($this, $params);
                }
            }
        }

        return $return;

    }



    /**
     * Uninstall the plugin
     * By default this will just delete the plugin record and any records relating to it in tables like plugin_groups, plugin_mis, etc...
     * If a plugin needs to do more, it can have its own uninstlal method and call this parent one first, then do..whatever
     */
    public function uninstall()
    {

        // Delete self
        $this->DB->delete_records("lbp_plugins", array("id"=>$this->id));

        // Delete plugin mis
        $this->DB->delete_records("lbp_plugin_mis", array("pluginid"=>$this->id));

        // Delete settings
        $this->DB->delete_records("lbp_settings", array("pluginid"=>$this->id));

        // Delete alert events
        $this->DB->delete_records("lbp_alert_events", array("pluginid"=>$this->id));

        // Delete report elements
        $this->DB->delete_records("lbp_plugin_report_elements", array("pluginid"=>$this->id));

        // Delete hooks
        $this->DB->delete_records("lbp_hooks", array("pluginid" => $this->id));

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
        $TPL->set("params", $params);

        try {
            $output .= $TPL->load($this->CFG->dirroot . '/blocks/elbp/plugins/'.$this->name.'/tpl/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;

    }




    /**
     * Display the summary box of the plugin
     * @return type
     */
    public function displaySummaryBox()
    {

        if (!$this->isEnabled()) return;

        $output = "";

        $output .= $this->getSummaryBox();

        return $output;
    }

    /**
     * Get the content of the expanded view
     * @global type $OUTPUT
     * @param type $params
     * @return type
     */
    public function display($params = array())
    {

        global $OUTPUT;

        if (!$this->isEnabled()) return;
        if (!$this->student) return;

        $output = "";
        $output .= "<div id='elbp_popup_header_plugin_{$this->name}' class='elbp_popup_header' title='".get_string('closepopup', 'block_elbp')."' style='{$this->getHeaderStyle()}'>";
            $output .= "<table class='elbp_popup_header_table'>";
                $output .= "<tr>";
                    $output .= "<td>{$this->getTitle()}</td>";
                    $output .= "<td class='elbp_popup_close'><a href='#' id='close_expanded_view' onclick='ELBP.unpop(\"{$this->name}\", \"".elbp_html($this->title)."\");return false;'><i class='icon-remove-sign icon-medium' style='{$this->getHeaderStyle()}' {$this->getIconHover()} ></i></a></td>";
                $output .= "</tr>";
            $output .= "</table>";
        $output .= "</div>";

        $output .= "<div id='elbp_popup_content'>";
            $output .= "<div class='elbp_centre'>".$OUTPUT->user_picture($this->student, array("courseid"=>1, "size"=>50, "link" => false))."</div><br>";
            $output .= "<h1 class='elbp_centre'>".fullname($this->student)." ({$this->student->username})</h1>";
            $output .= $this->getDisplay($params);
        $output .= "</div>";

        echo $output;

    }


    /**
     * Load a given student into the plugin
     * @param type $studentID
     * @return boolean
     */
    public function loadStudent($studentID, $fromBlock = false)
    {

        // We might be using the block itself, but not want it enabled on the ELBP
        if (!$fromBlock && !$this->isEnabled()) return false;

        $user = $this->ELBPDB->getUser( array("type"=>"id", "val"=>$studentID) );
        if ($user){
            $this->student = $user;
            return true;
        }
        return false;
    }

    /**
     * Load a given course into the plugin
     * @param type $courseID
     * @return boolean
     */
    public function loadCourse($courseID)
    {
        $course = $this->ELBPDB->getCourse( array("type"=>"id", "val"=>$courseID) );
        if ($course){
            $this->course = $course;
            return true;
        }
        return false;
    }

    /**
     * Load all the default attributes for the plugin, as defined in the plugin's settings
     * @return type
     */
    public function loadDefaultAttributes(){

        $this->attributes = "";

        $setting = \ELBP\Setting::getSetting("attributes", null, $this->id);

        $this->attributes = $setting;

        return $this->attributes;

    }

    /**
     * Return array of elements to be displayed in the output of the as session
     * @return type
     */
    public function getAttributesForDisplay( $pluginObj = false )
    {
        return  $this->getElementsFromAttributeString($pluginObj);
    }

    /**
     * Get a specific type of attribute, e.g. "main" or "side"
     * @param type $type
     * @param type $useThese
     * @return type
     */
    public function getAttributesForDisplayDisplayType($type, $useThese = null)
    {

        $elements = (is_null($useThese)) ? $this->getAttributesForDisplay() : $useThese;

        $return = array();

        if ($elements)
        {
            foreach($elements as $element)
            {
                $element->display = trim($element->display); # Random spaces have been found at the end for some reason
                if ($element->display == $type)
                {
                    $return[] = $element;
                }
            }
        }

        return $return;

    }

    /**
     * Get HTML elements from the saved string of attributes
     * @return type
     */
    public function getElementsFromAttributeString($pluginObj = false)
    {

        $FORM = new \ELBP\ELBPForm();

        // Load student
        if ($this->student){
            $FORM->loadStudentID($this->student->id);
        }

        // Load object
        if ($pluginObj){
            $FORM->loadObject($pluginObj);
        }

        return $FORM->load( $this->getDefaultAttributes() );

    }

    /**
     * Get the default attributes
     * @return type
     */
    public function getDefaultAttributes(){

        return ($this->attributes) ? $this->attributes : "";

    }

    /**
     * Count the number of attributes
     * @return type
     */
    public function countAttributes()
    {

        $FORM = new \ELBP\ELBPForm();
        $FORM->load( $this->getDefaultAttributes() );
        $elements = $FORM->getElements();
        return count($elements);

    }

    /**
     * Set the global ELBP object into the plugin
     * @param type $obj
     * @return \ELBP\Plugins\Plugin
     */
    public function setELBPObject($obj){
        $this->ELBP = $obj;
        return $this;
    }

    /**
     * Display the configuration settings for this plugin
     */
    public function displayConfig()
    {

        global $ELBP;

        $output = "";

        $enable = ($this->isEnabled()) ? 'checked' : '';
        $disable = (!$this->isEnabled()) ? 'checked' : '';

        $output .= "<small><strong>".get_string('blockconfig:enable', 'block_elbp')."</strong> - ".get_string('blockconfig:enable:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='radio' name='enabled' value='1' {$enable} /> <label>".get_string('enable')."</label> &nbsp;";
        $output .= "&nbsp; <input type='radio' name='enabled' value='0' {$disable} /> <label>".get_string('disable')."</label>";

        $output .= "<br><br>";

        $output .= "<small><strong>".get_string('blockconfig:plugintitle', 'block_elbp')."</strong> - ".get_string('blockconfig:plugintitle:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='text' name='plugin_title' value='{$this->title}' />";


        $output .= "<br><br>";

        if ($this->isUsingHeaders())
        {

            $bg = $this->getSetting('header_bg_col');
            if ($bg === false) $bg = '#ffffff';

            $output .= "<small><strong>".get_string('blockconfig:headerbg', 'block_elbp')."</strong> - ".get_string('blockconfig:headerbg:desc', 'block_elbp')."</small><br>";
            $output .= "<input type='color' name='header_bg_col' value='{$bg}' />";

            $output .= "<br><br>";

            $output .= "<small><strong>".get_string('blockconfig:headerfont', 'block_elbp')."</strong> - ".get_string('blockconfig:headerfont:desc', 'block_elbp')."</small><br>";
            $output .= "<input type='color' name='header_font_col' value='{$this->getSetting('header_font_col')}' />";

            $output .= "<br><br>";

        }

        // Academic Year
        if ($ELBP->isAcademicYearEnabled())
        {

            $override = ($this->getSetting('override_academic_year') == 1) ? 'checked' : '';

            $output .= "<small><strong>".get_string('academicyear', 'block_elbp')." (".$ELBP->getAcademicYearStartDate().")</strong> - ".get_string('academicyearoverride:desc', 'block_elbp')."</small><br>";
            $output .= "<input type='checkbox' name='override_academic_year' value='1' {$override} /> <label>".get_string('ignore', 'block_elbp')."</label>  &nbsp;";
            $output .= "<br><br>";

        }

        if ($this->isUsingBlockProgress())
        {

            $enable = ($this->getSetting('block_progress_enabled') == 1) ? 'checked' : '';
            $disable = ($this->getSetting('block_progress_enabled') <> 1) ? 'checked' : '';
            $output .= "<small><strong>".get_string('blockconfig:blockprogress', 'block_elbp')."</strong> - ".get_string('blockconfig:blockprogress:desc', 'block_elbp')."</small><br>";
            $output .= "<input type='radio' name='block_progress_enabled' value='1' {$enable} /> <label>".get_string('enable')."</label>  &nbsp;";
            $output .= "&nbsp; <input type='radio' name='block_progress_enabled' value='0' {$disable} /> <label>".get_string('disable')."</label>";
            $output .= "<br><br>";

        }

        // EMail alerts for the plugin

        // Students
        $enable = ( $this->getSetting('plugin_stud_alerts_enabled') !== 0) ? 'checked' : '';
        $disable = ( $this->getSetting('plugin_stud_alerts_enabled') === 0) ? 'checked' : '';
        $output .= "<small><strong>".get_string('blockconfig:alerts:stud', 'block_elbp')."</strong> - ".get_string('blockconfig:alerts:stud:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='radio' name='plugin_stud_alerts_enabled' value='1' {$enable} /> <label>".get_string('enable')."</label>  &nbsp;";
        $output .= "&nbsp; <input type='radio' name='plugin_stud_alerts_enabled' value='0' {$disable} /> <label>".get_string('disable')."</label>";

        $output .= "<br><br>";


        // Overall student progress definitions
        $setting = \ELBP\Setting::getSetting('enable_student_progress_bar');
        if ($setting == 'calculated' && $this->supportsStudentProgress())
        {

            $output .= "<br><br>";
            $output .= "<h2>".get_string('studentprogressconfig', 'block_elbp')."</h2>";

            $output .= $this->getStudentProgressDefinitionForm();

        }


        // Summary elements
        if (method_exists($this, 'getSummaryElements'))
        {
            $elements = $this->getSummaryElements();
            if ($elements)
            {
                $output .= "<br>";
                $output .= "<h2>".get_string('profilesummary', 'block_elbp')."</h2>";
                foreach($elements as $element)
                {
                    $enable = ($this->isSummaryElementEnabled($element['name'], $element['component'])) ? 'checked' : '';
                    $disable = (!$this->isSummaryElementEnabled($element['name'], $element['component'])) ? 'checked' : '';
                    $output .= "<small><strong>".get_string($element['name'], $element['component'])."</strong><br>";
                    $output .= "<input type='radio' name='plugin_summary_element_enabled_{$element['component']}/{$element['name']}' value='1' {$enable} /> <label>".get_string('enable')."</label>  &nbsp;";
                    $output .= "&nbsp; <input type='radio' name='plugin_summary_element_enabled_{$element['component']}/{$element['name']}' value='0' {$disable} /> <label>".get_string('disable')."</label>";
                    $output .= "<br><br>";
                }
            }
        }


        $output .= "<script>$(document).ready( function(){ ELBP.apply_colour_picker(); } );</script>";

        echo $output;

    }

    public function isSummaryElementEnabled($element, $component){

        $setting = $this->getSetting('plugin_summary_element_enabled_'.$component.'/'.$element);
        return ($setting !== '0' && $setting !== 0);

    }

    /**
     * Does this plugin support the Student Progress bar?
     * Will be overwritten by those that do
     * @return boolean
     */
    protected function supportsStudentProgress()
    {
        return false;
    }

    /**
     * Will be overwritten by those plugins which support the Student Progress bar to display a config form
     * @return string
     */
    protected function getStudentProgressDefinitionForm()
    {
        return '';
    }

    /**
     * Get the path to the root of this plugin in the data directory
     * @global array $CFG
     * @return type
     */
    public function getDataRoot()
    {
        global $CFG;
        return $CFG->dataroot . '/ELBP/' . $this->name;
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

        global $CFG, $failMkDir;

        // First check if a directory for this plugin exists - Should do as they should be created on install

        // Check for ELBP directory
        if (!is_dir( $CFG->dataroot . '/ELBP' )){
            if (is_writeable($CFG->dataroot)){
                if (!mkdir($CFG->dataroot . '/ELBP', 0770, true)){
                    $failMkDir = 'mkdir:'.$CFG->dataroot . '/ELBP';
                    return false;
                }
            } else {
                $failMkDir = 'write:' . $CFG->dataroot . '/ELBP';
                return false;
            }
        }

        // Check for plugin directory
        if (!is_dir( $this->getDataRoot() )){
            if (is_writeable($CFG->dataroot . '/ELBP')){
                if (!mkdir($this->getDataRoot(), 0770, true)){
                    $failMkDir = 'mkdir:'.$this->getDataRoot();
                    return false;
                }
            } else {
                $failMkDir = 'write:'.$this->getDataRoot();
                return false;
            }
        }

        // Now try and make the actual dir we want
        if (!is_dir( $this->getDataRoot() . '/' . $dir )){
            if (is_writeable($this->getDataRoot())){
                if (!mkdir($this->getDataRoot() . '/' . $dir, 0770, true)){
                    $failMkDir = 'mkdir:'.$this->getDataRoot() . '/' . $dir;
                    return false;
                }
            } else {
                $failMkDir = 'write:'. $this->getDataRoot() . '/' . $dir;
                return false;
            }
        }

        // If we got this far must be ok
        return true;


    }

    /**
     * Get all reporting elements for this plugin
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getReportingElements(){

        global $DB;
        return $DB->get_records("lbp_plugin_report_elements", array("pluginid" => $this->id), "id ASC");

    }

    /**
     * Get the stringname of a specific reporting element
     * @global \ELBP\Plugins\type $DB
     * @param type $id
     * @return type
     */
    protected function getReportingElementName($id){
        global $DB;
        $record = $DB->get_record("lbp_plugin_report_elements", array("id" => $id));
        return ($record) ? $record->getstringname : false;
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
     * Default calculation to be overriden by plugins which use the Student Progress bar
     * @return type
     */
    public function calculateStudentProgress(){

        return array(
            'max' => 0,
            'num' => 0
        );

    }

    /**
     * Update attribute names in the actual user attributes data table
     * @param type $newNames
     * @param type $oldNames
     * @return false
     */
    public function updateChangedAttributeNames($newNames, $oldNames)
    {
        return false;
    }

    /**
     * Get the alert events on this plugin
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getAlertEvents(){

        global $DB;

        $alerts = $DB->get_records("lbp_alert_events", array("pluginid" => $this->id, "enabled" => 1));
        return $alerts;

    }


    /**
     * Instantiate a plugin object by name
     * @param type $pluginName The name of the plugin, e.g. "StudentProfile" or "GradeTracker"
     * @param type $pluginPath The path to the plugin, e.g. null or "mod/qualification" or "block/timetable", etc...
     * @param bool $install Default is true, which means that if you try to instaniate a plugin which isn't installed it will try to install it
     */
    static public function instaniate($pluginName, $pluginPath = null, $install = true)
    {

        global $CFG, $DB, $_SUPPRESS_ELBP_ERR;

        // Might still be a different path, but only calling it using the name, so check DB
        if (is_null($pluginPath)){
            $check = $DB->get_record("lbp_plugins", array("name"=>$pluginName));
            if ($check && !is_null($check->path)){
                $pluginPath = $check->path;
            }
        }

        // If the path sent is null, check if it's a core plugin
        if (is_null($pluginPath))
        {
            $file = $CFG->dirroot . '/blocks/elbp/plugins/' . $pluginName . '/'. $pluginName . '.class.php';
        }
        // Else if the path sent is valid and exists, look for the class file there
        elseif ( is_file($CFG->dirroot . $pluginPath) )
        {
            $file = $CFG->dirroot . $pluginPath;
            // Find the new plugin name to use, based on the file we specified
            $pat = "/\/([a-z _\.-]+)\.class\.php$/i";
            preg_match($pat, $pluginPath, $matches);
            if (isset($matches[1])){
                $pluginName = $matches[1];
            }
        }
        // Else if the path itself contains ".php" then ignore the pluginName and just use the direct path
        elseif ( preg_match("/\.php/", $pluginPath) )
        {
            $file = $CFG->dirroot . $pluginPath;
        }
        // Else just look for pluginName.class.php in the pluginPath
        else
        {
            $file = $CFG->dirroot .  $pluginPath . $pluginName . '.class.php';
        }

        // File doesn't exist, so throw exception
        if (!file_exists($file)){
            if ($_SUPPRESS_ELBP_ERR !== true){
                throw new \ELBP\ELBPException( get_string('plugin', 'block_elbp'). ' - ' . $pluginName, get_string('filenotfound', 'block_elbp'), $file, sprintf( get_string('programming:createfileorchangepathoruninstall', 'block_elbp'), $pluginName) );
            }
            return false;
        }

        // Include the file
        include_once $file;


        // Attempt to create new instance of object & make sure it's an extension of ELBPPLugin
        try
        {
            $namespace = '\ELBP\\Plugins\\'.$pluginName;
            if (class_exists($namespace)){
                $obj = new $namespace();
            } else {
                if ($_SUPPRESS_ELBP_ERR !== true){
                    throw new \ELBP\ELBPException( get_string('plugin', 'block_elbp'). ' - ' . $pluginName, get_string('exception:noclass', 'block_elbp'), $namespace );
                }
                return false;
            }

        }

        // Catch the exception and try to install the plugin
        catch (\ELBP\ELBPException $e)
        {

            if (class_exists($namespace)){

                // if we've got this far than the class file exists, but the record in the DB doesn't, so let's try and install it
                $namespace = '\ELBP\\Plugins\\'.$pluginName;
                $obj = new $namespace(true);

                // If we can't install it, then something is just wrong
                if ($install){
                    if (!$obj->install()){
                        echo $e->getException();
                        return false;
                    }
                } else {
                    return false;
                }
                // Install was okay, so now just create the obj again in the normal way
                // Tell admin/developer the install was okay
                //elbp_print_success_msg( get_string('installed', 'block_elbp') . ' ' . $pluginName . ' ' . get_string('plugin', 'block_elbp') );
                $obj = new $namespace();

            } else {
                if ($_SUPPRESS_ELBP_ERR !== true){
                    throw new \ELBP\ELBPException( get_string('plugin', 'block_elbp'). ' - ' . $pluginName, get_string('exception:noclass', 'block_elbp'), $namespace );
                }
                return false;
            }

        }

        return $obj;



    }

    /**
     * Force a plugin uninstall when the object can't load, e.g. source code was removed
     * @global \ELBP\Plugins\type $DB
     * @param type $pluginName
     * @return type
     */
    public static function forceUninstall($pluginName){

        global $DB;
        return $DB->delete_records("lbp_plugins", array("name" => $pluginName));

    }

    /**
     * Get just the name of a plugin based on its ID
     * @global type $DB
     * @param type $id
     * @return type
     */
    public static function getPluginName($id){

        global $DB;

        $record = $DB->get_record("lbp_plugins", array("id" => $id));
        return ($record) ? $record->name : false;

    }

    public static function anyPluginsAvailable(){

        global $DB;

        $count = $DB->count_records("lbp_plugins", array("enabled" => 1));
        return ($count > 0) ? true : false;

    }





    /*
     * This method should be used for anything else the plugin needs to do
     * It should call createPlugin() and then do anything else you want it to
     */
    abstract function install();


     /*
     *  This method should run any upgrades required of the plugin
     *      E.g. DB might be version 10
     *      Months later they might git hub over the latest version of the plugin, which is now version 15
     *      "10" passed into upgrade() and it would run all the upgrades up to the latest version and then set DB version to 15
     *      Basically the same as the standard Moodle upgrade procedure, but will need to handle the updating of the DB version ourselves
     *      as obviously Moodle won't know about our table with version numbers in
     */
    abstract function upgrade();

    /**
     * Get the content of the overview summary box for this plugin
     */
    abstract function getSummaryBox();

    /**
     * Run an AJAX call
     */
    abstract function ajax($action, $params, $ELBP);


}