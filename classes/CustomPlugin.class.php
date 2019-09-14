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
class CustomPlugin {

    protected $id = false;
    protected $name;
    protected $enabled;

    protected $attributes; // These are the blank, default attributes on this plugin
    protected $studentattributes; // These are the attributes with actual values for this instance
    protected $access;
    protected $js;
    protected $student = false;
    protected $course;

    public $custom = true;
    private $errors = array();
    private $permissions = array();

    private $connection;
    private $mis_connection;
    private $plugin_connection;

    public $quickID;

    const PERMISSION_VIEW = 'view';
    const PERMISSION_ADD = 'add';
    const PERMISSION_EDIT_ANY = 'edit_any';
    const PERMISSION_EDIT_OWN = 'edit_own';
    const PERMISSION_DEL_ANY = 'del_any';
    const PERMISSION_DEL_OWN = 'del_own';
    const PERMISSION_PRINT = 'print';

    /**
     * Construct the plugin object
     * @global \ELBP\Plugins\type $DB
     * @param type $id
     */
    public function __construct($id = false) {

        global $DB;

        $check = $DB->get_record("lbp_custom_plugins", array("id" => $id));
        if ($check)
        {

            $this->ELBPDB = new \ELBP\DB();

            $this->id = $check->id;
            $this->name = $check->name;
            $this->enabled = $check->enabled;

            $this->quickID = 'c' . $this->id;

            // Set connection object to default and method so we can just call it later regardless of
            // whether we've got a connection object or not. Basically it's all of this because I
            // can't be arsed to do a check to see if the connection property is there or not
            $this->connection = new \Anon;
            $this->connection->connect = function() {
                try {
                    throw new \ELBP\ELBPException( get_string('plugin', 'block_elbp'), get_string('nomisconnection', 'block_elbp'), false, get_string('admin:setupmisconnectionplugin', 'block_elbp'));
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
            };

            $this->loadDefaultAttributes();
            $this->loadPermissions();

        }

    }

    /**
     * Connect.
     * This is only used for external database reports, when we need to connect using an
     * MIS connection to another db
     */
    public function connect(){

        if ($this->getStructure() == 'ext_db'){

            $this->mis_connection = $this->getMISConnection("core");
            $this->loadMISConnection();
            if ($this->connection && $this->connection->connect()){
                $core = $this->getMainMIS();
                if ($core){
                    $pluginConn = new \ELBP\MISConnection($core->id, true);
                    if ($pluginConn->isValid()){
                        $this->useMIS = true;
                        $this->plugin_connection = $pluginConn;
                        #$this->setupMisRequirements();
                    }
                }
            }

        }

    }

    /**
     * Is the plugin valid?
     * @return type
     */
    public function isValid(){
        return ($this->id !== false);
    }

    /**
     * Yes
     * @return boolean
     */
    public function isCustom(){
        return true;
    }

    /**
     * Just too complicated at the moment, maybe in the future
     * @return type
     */
    public function isCronEnabled()
    {
        return false;
    }

    public function getID(){
        return $this->id;
    }

    public function setID($id){
        $this->id = $id;
    }


    public function setName($name){
        $this->name = $name;
        return $this;
    }



    public function getDBTables(){
        return false;
    }

    /**
     * Check if the plugin is enabled
     * Also use this check to just say it's disabled if the user doesn't have permission to view it
     * @return boolean
     */
    public function isEnabled(){

        if ($this->student){

            $permissions = $this->getUserPermissions();
            if (!$this->havePermission(self::PERMISSION_VIEW, $permissions)) return false;

        }

        return ($this->enabled == 1);
    }

    public function setEnabled($val){
        $this->enabled = $val;
        return $this;
    }


    /**
     * Doesn't have a path as it's a custom plugin
     * @return string
     */
    public function getPath(){
        return "";
    }

    public function getTitle(){
        return $this->getName();
    }

    /**
     * Get the title/name. Same thing in custom plugins.
     * @return type
     */
    public function getName(){
        return $this->name;
    }

    /**
     * Get the name with all spaces removed
     * @return type
     */
    public function getNameString(){
        return str_replace(" ", "_", $this->name);
    }

    /**
     * Doesn't have one
     * @return string
     */
    public function getVersionDateString(){
        return "";
    }

    /**
     * Doesn't have one
     * @return string
     */
    public function getVersion(){
        return "";
    }

    /**
     * Doesn't have one
     * @return string
     */
    public function hasDuplicatePath(){
        return false;
    }

    public function hasPluginBox(){
        return true;
    }

    /**
     * Set the access levels which were calculated for this context
     * @param type $access
     */
    public function setAccess($access){
        $this->access = $access;
    }

    public function getAccess(){
        return $this->access;
    }

    public function getErrors(){
        return $this->errors;
    }

    public function getStudentID(){
        if ($this->student){
            return $this->student->id;
        }
        return false;
    }

    public function getStudent(){
        return $this->student;
    }

    public function getStudentAttributes(){
        return $this->studentattributes;
    }

    public function getConfigPath(){
        return 'blocks/elbp/plugins/Custom/config.php?id=' . $this->id;
    }

    /**
     * Get which structure we are using, e.g. Simple Report, Multi Report, Incremental Report, etc...
     * @return boolean
     */
    public function getStructure(){

        $structure = $this->getSetting("plugin_structure");

        if (!in_array($structure, array('single', 'multi', 'incremental', 'int_db', 'ext_db'))){
            return false;
        }

        return $structure;

    }

    /**
     * Get the main MIS connection linked to this plugin
     * @return type
     */
    public function getMainMIS(){
        $this->mis_connection = $this->getMISConnection("core"); // Do this again here as we don't want to connect properly
        return $this->mis_connection;
    }

    /**
     * Get a given MIS connection by name, linked to this plugin
     * @global \ELBP\Plugins\type $DB
     * @param type $name
     * @return type
     */
    protected function getMISConnection($name)
    {
        global $DB;
        $record = $DB->get_record("lbp_custom_plugin_mis", array("pluginid"=>$this->id, "name"=>$name));
        return $record;
    }

    /**
     * Get a given MIS connection by ID, linked to this plugin
     * @global \ELBP\Plugins\type $DB
     * @param type $id
     * @return type
     */
    protected function getMISConnectionByID($id)
    {
        global $DB;
        return $DB->get_record("lbp_mis_connections", array("id"=>$id));
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
     * Check if this plugin is in a particular plugin group
     * @param type $groupID
     * @return boolean
     */
    public function isInPluginGroup($groupID){

        $check = $this->DB->get_record("lbp_custom_plugin_grp_plugin", array("pluginid" => $this->id, "groupid" => $groupID));
        return ($check) ? true : false;

    }

    public function getPermissions(){
        return $this->permissions;
    }

    /**
     * Does a given role have a particular permission on this plugin?
     * @param type $roleID
     * @param type $permission
     * @return type
     */
    public function roleHasPermission($roleID, $permission){

        return (isset($this->permissions[$roleID]) && in_array($permission, $this->permissions[$roleID]));

    }

    /**
     * Load up the permissions for this plugin, so we can work out who can do what
     * @global type $DB
     */
    private function loadPermissions(){

        global $DB;

        // If no permissions defined at all, load defaults, otherwise use ones we setup
        $check = $DB->get_records("lbp_custom_plugin_permission", array("pluginid" => $this->id));
        if ($check)
        {

            // Reset to blank array
            $this->permissions = array();
            foreach($check as $permission)
            {

                if (!isset($this->permissions[$permission->roleid]) || !is_array($this->permissions[$permission->roleid]))
                {
                    $this->permissions[$permission->roleid] = array();
                }

                $this->permissions[$permission->roleid][] = $permission->value;

            }

        }
        else
        {
            $this->loadDefaultPermissions();
        }

    }

    /**
     * Get the default permissions to use if we haven't saved any in the plugin configuration
     * 1 = Manager, 2 = Course Creator, 3 = Teacher, 4 = Non-Editing teacher, 5 = Student, 7 = Authenticated User
     */
    private function loadDefaultPermissions(){

        $this->permissions = array(

            1 => array(
                self::PERMISSION_VIEW, self::PERMISSION_ADD, self::PERMISSION_EDIT_ANY, self::PERMISSION_EDIT_OWN, self::PERMISSION_DEL_ANY, self::PERMISSION_DEL_OWN, self::PERMISSION_PRINT
            ),
            2 => array(
                self::PERMISSION_VIEW, self::PERMISSION_ADD, self::PERMISSION_EDIT_ANY, self::PERMISSION_EDIT_OWN, self::PERMISSION_DEL_ANY, self::PERMISSION_DEL_OWN, self::PERMISSION_PRINT
            ),
            3 => array(
                self::PERMISSION_VIEW, self::PERMISSION_ADD, self::PERMISSION_EDIT_ANY, self::PERMISSION_EDIT_OWN, self::PERMISSION_DEL_ANY, self::PERMISSION_DEL_OWN, self::PERMISSION_PRINT
            ),
            4 => array(
                self::PERMISSION_VIEW, self::PERMISSION_ADD, self::PERMISSION_EDIT_ANY, self::PERMISSION_EDIT_OWN, self::PERMISSION_DEL_ANY, self::PERMISSION_DEL_OWN, self::PERMISSION_PRINT
            ),
            5 => array(
                self::PERMISSION_VIEW, self::PERMISSION_EDIT_OWN, self::PERMISSION_DEL_OWN, self::PERMISSION_PRINT
            ),
            7 => array(
                self::PERMISSION_VIEW, self::PERMISSION_EDIT_OWN, self::PERMISSION_DEL_OWN, self::PERMISSION_PRINT
            )

        );

    }

    /**
     * Clear the permissions from this plugin
     * @global \ELBP\Plugins\type $DB
     */
    private function clearPermissions(){

        global $DB;

        $DB->delete_records("lbp_custom_plugin_permission", array("pluginid" => $this->id));
        $this->permissions = array();

    }

    /**
     * Add a role's permission to the permissions array to save later
     * @param type $roleID
     * @param type $permission
     * @return \ELBP\Plugins\CustomPlugin
     */
    private function addPermission($roleID, $permission){

        if (!isset($this->permissions[$roleID]) || !is_array($this->permissions[$roleID])){
            $this->permissions[$roleID] = array();
        }

        $this->permissions[$roleID][] = $permission;

        return $this;

    }

    /**
     * Save the permissions in our permissions array to the database
     * @global \ELBP\Plugins\type $DB
     */
    private function savePermissions(){

        global $DB;

        if ($this->permissions)
        {
            foreach($this->permissions as $roleID => $permissions)
            {
                if ($permissions)
                {
                    foreach($permissions as $permission)
                    {
                        $ins = new \stdClass();
                        $ins->pluginid = $this->id;
                        $ins->roleid = $roleID;
                        $ins->value = $permission;
                        $DB->insert_record("lbp_custom_plugin_permission", $ins);
                    }
                }
            }
        }

    }

    public function getPrintLogo($type){

        global $CFG;
        $logo = $this->getSetting($type);
        return ($logo) ? $CFG->wwwroot . '/blocks/elbp/download.php?f=' . \elbp_get_data_path_code($CFG->dataroot . DIRECTORY_SEPARATOR . 'ELBP' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $logo) : false;


    }

    public function getDockIconPath(){
        return $this->getPrintLogo('plugin_icon_dock');
    }

    /**
     * Don't need any specific PHP extensions for custom plugins
     * @return boolean
     */
    public function getRequiredExtensions(){
        return false;
    }

    /**
     * Given a particular user and a particular student they are looking at, find out what permissions they have on this plugin
     * @global \ELBP\Plugins\type $DB
     * @global type $USER
     * @global type $ELBP
     * @param type $studentID
     * @param type $userID
     * @return boolean
     */
    public function getUserPermissions($studentID = false, $userID = false){

        global $DB, $USER, $ELBP;

        if (!$studentID && !$this->student) return false;
        if (!$studentID) $studentID = $this->student->id;
        if (!$userID) $userID = $USER->id;

        $ELBPDB = new \ELBP\DB();

        $roles = array();
        $return = array();

        // Firstly build up an array of roles that this user has

        // Start with system roles
        $systemContext = \context_system::instance();
        $check = $DB->get_records("role_assignments", array("contextid" => $systemContext->id, "userid" => $userID), null, "DISTINCT(roleid)");
        if ($check){
            foreach($check as $chk){
                $roles[] = $chk->roleid;
            }
        }

        // Next any roles that are assigned to the student in the user context, e.g. Personal Tutor
        $userContext = \context_user::instance($studentID);
        if ($userContext){
            $check = $DB->get_records("role_assignments", array("contextid" => $userContext->id, "userid" => $userID), null, "DISTINCT(roleid)");
            if ($check){
                foreach($check as $chk){
                    $roles[] = $chk->roleid;
                }
            }
        }

        // Next find any courses the student is enrolled on and get the roles the user has on those courses
        $courses = $ELBPDB->getStudentsCourses($studentID);
        if ($courses){
            foreach($courses as $course){
                $courseContext = \context_course::instance($course->id);
                if ($courseContext){
                    $check = $DB->get_records("role_assignments", array("contextid" => $courseContext->id, "userid" => $userID), null, "DISTINCT(roleid)");
                    if ($check){
                        foreach($check as $chk){
                            $roles[] = $chk->roleid;
                        }
                    }
                }
            }
        }

        // Lastly get any roles they have on the front page
        $frontPageContext = \context_course::instance(SITEID);
        $check = $DB->get_records("role_assignments", array("contextid" => $frontPageContext->id, "userid" => $userID), null, "DISTINCT(roleid)");
        if ($check){
            foreach($check as $chk){
                $roles[] = $chk->roleid;
            }
        }

        // If it is the same user viewing their own ELBP, add the role of "authenticated user" and we will use that as default for
        // the same user
        if ($studentID === $userID){
            $authenticatedUserRole = $DB->get_record("role", array("shortname" => "user"));
            if ($authenticatedUserRole){
                $roles[] = $authenticatedUserRole->id;
            }
        }



        // Remove duplicates
        $roles = array_unique($roles);

        $this->userrolepermissions = array();

        // Find all permissions these roles have
        if ($roles)
        {
            foreach($roles as $role)
            {

                $this->userrolepermissions[$role] = array();

                if (isset($this->permissions[$role]))
                {
                    if ($this->permissions[$role])
                    {
                        foreach($this->permissions[$role] as $permission)
                        {
                            $return[] = $permission;
                            $this->userrolepermissions[$role][] = $permission;
                        }
                    }
                }
            }
        }

        // Remove duplicates
        $return = array_unique($return);

        $this->userpermissions = $return;

        return $return;

    }

    /**
     * Do we have a specific permission loaded into our permission array for this user?
     * @global \ELBP\Plugins\type $USER
     * @param type $permission
     * @param array $permissions
     * @return type
     */
    public function havePermission($permission, array $permissions){

        global $USER;
        return (in_array($permission, $permissions) || is_siteadmin($USER->id));

    }


    /**
     * Load the attributes for this plugin
     * @return type
     */
    public function loadDefaultAttributes(){

        $this->attributes = "";

        $setting = \ELBP\Setting::getSetting("attributes", null, $this->id, true);

        $this->attributes = $setting;

        return $this->attributes;

    }

    /**
     * Get the loaded attributes
     * @return type
     */
    public function getDefaultAttributes(){

        return ($this->attributes) ? $this->attributes : "";

    }

    /**
     * Count the attribute elements
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
     * Return array of elements to be displayed in the output of the as session
     * @return type
     */
    public function getAttributesForDisplay()
    {
        return $this->getElementsFromAttributeString();
    }

    /**
     * What am I doing with this method name?
     * Anyway, get particular attributes based on their display type, e.g. Main, Side
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
     * Given an attribute string, get the elements from it
     * @return type
     */
    public function getElementsFromAttributeString()
    {

        $FORM = new \ELBP\ELBPForm();

        // Load student
        if ($this->student){
            $FORM->loadStudentID($this->student->id);
        }

        // Load object
        $FORM->loadObject($this);

        $FORM->load( $this->getDefaultAttributes() );

        return $FORM->getElements();

    }

    /**
     * If we are using Multi Report, get a specific item
     * @global \ELBP\Plugins\type $DB
     * @param type $itemID
     * @return boolean
     */
    private function getMultiItem($itemID){

        global $DB;

        if (!$this->student) return false;

        return $DB->get_record("lbp_custom_plugin_items", array("id" => $itemID));

    }

    /**
     * If we are using Multi report, get all items for this student
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    public function getMultiItems()
    {

        global $DB;

        if (!$this->student) return false;

        return $DB->get_records("lbp_custom_plugin_items", array("studentid" => $this->student->id, "pluginid" => $this->id, "del" => 0), "settime DESC");

    }


    public function getMultiItemsByAttribute($att, $value){

        global $DB;

        if (!$this->student) return false;

        return $DB->get_records_sql("select i.*
                                    from {lbp_custom_plugin_items} i
                                    inner join {lbp_custom_plugin_attributes} a on (a.itemid = i.id)
                                    where i.pluginid = ? and i.studentid = ? and a.field = ? and a.value = ?", array($this->id, $this->student->id, $att, $value));

    }

    /**
     * Load JS that we need
     * @global type $CFG
     * @global type $PAGE
     * @param type $simple
     * @return type
     */
    public function loadJavascript($simple = false)
    {
        global $CFG, $PAGE;

        $output = "";

        if ($this->js)
        {
            foreach($this->js as $js)
            {
                if ($simple)
                {
                    $output .= "<script type='text/javascript' src='{$CFG->wwwroot}/{$js}'></script>";
                }
                else
                {
                    $PAGE->requires->js( $js );
                }
            }
        }

        return $output;
    }

    /**
     * Load object into all attributes
     * @param type $attributes
     */
    public function loadObjectIntoAttributes($attributes){

        if ($attributes){
            foreach($attributes as $attribute){
                $attribute->loadObject($this);
            }
        }

    }

    /**
     * Load attributes set for this student/item
     * @global \ELBP\Plugins\type $DB
     * @param type $itemID
     * @return type
     */
    public function loadAttributes($itemID = null, &$attributes = false){

        global $DB;

        $check = $DB->get_records("lbp_custom_plugin_attributes", array("pluginid" => $this->id, "userid" => $this->student->id, "itemid" => $itemID), "id ASC");

        $this->studentattributes = $this->_loadAttributes($check);

        // If we've sent an array of attribute formelements, we want to apply the values to these
        if ($attributes)
        {

            // Generate new IDs
            \ELBP\ELBPFORM::generateNewIDs($attributes);

            // Wipe all values
            foreach($attributes as $attribute)
            {
                $attribute->setValue(false);
            }

            foreach($attributes as $attribute)
            {

                // If the attribute name exists in the defined attributes (ones linked to this target)
                // Simply add it to the data array
                if (array_key_exists($attribute->name, $this->studentattributes))
                {
                    $attribute->setValue($this->studentattributes[$attribute->name]);
                }
                else
                {

                    // Otherwise
                    // Loop through defined attributes (linked to target) and see if there are any LIKE
                    // this attribute, e.g. for Matrices they will be Name_Row => Col rather than Name => Col
                    $valueArray = array();
                    $like = false;

                    if ($this->studentattributes)
                    {
                        foreach($this->studentattributes as $key => $d)
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
                    }

                }

            }
        }

        return $this->studentattributes;

    }

    /**
     * Load attributes into an array
     * @param type $check
     * @return type
     */
    protected function _loadAttributes($check){

        $results = array();

        if ($check)
        {
            foreach($check as $att)
            {
                // If something already set for this, turn it into an array
                if ( isset($results[$att->field]) && !is_array($results[$att->field]) )
                {
                    $tmpArray = array();
                    $tmpArray[] = $results[$att->field];
                    $tmpArray[] = $att->value;
                    $results[$att->field] = $tmpArray;
                }
                // If it's already set but it's already been converted to an array, just append new element
                elseif ( isset($results[$att->field]) && is_array($results[$att->field]) )
                {
                    $results[$att->field][] = $att->value;
                }
                else
                {
                    $results[$att->field] = $att->value;
                }
            }
        }

        return $results;

    }


    /**
     * Load a given student into the plugin
     * @param type $studentID
     * @return boolean
     */
    public function loadStudent($studentID, $itemID = null, &$attributes = false)
    {
        global $USER;

        if (!$this->isEnabled()) return false;

        $user = $this->ELBPDB->getUser( array("type"=>"id", "val"=>$studentID) );
        if ($user){
            $this->student = $user;
            $this->loadAttributes($itemID, $attributes);
            $this->loadPermissions($this->student->id, $USER->id);
            return true;
        }
        return false;
    }

    /**
     * Load a course
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
     * Update the plugin info
     * @return type
     */
    protected function updatePlugin()
    {
        global $DB;

        if ($this->isValid())
        {
            $record = new \stdClass();
            $record->id = $this->id;
            $record->name = $this->name;
            $record->enabled = $this->enabled;
            return $DB->update_record("lbp_custom_plugins", $record);
        }
    }

    /**
     * Create a new custom plugin
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function createPlugin(){

        global $DB;

        $record = new \stdClass();
        $record->name = $this->name;
        return $DB->insert_record("lbp_custom_plugins", $record);

    }

     /**
     * Upgrade the plugin from an older version to newer
     */
    public function upgrade(){

        return false;

    }

    /**
     * Delete all data related to a custom plugin
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    public function delete(){

        global $DB;

        $DB->delete_records("lbp_custom_plugins", array("id" => $this->id));
        $DB->delete_records("lbp_custom_plugin_attributes", array("pluginid" => $this->id));
        $DB->delete_records("lbp_custom_plugin_items", array("pluginid" => $this->id));
        $DB->delete_records("lbp_custom_plugin_mis", array("pluginid" => $this->id));
        $DB->delete_records("lbp_custom_plugin_permission", array("pluginid" => $this->id));
        $DB->delete_records("lbp_custom_plugin_settings", array("pluginid" => $this->id));

        return true;

    }

    /**
     * Update lbp_setting record
     * @param type $setting
     * @param type $value
     * @param type $userID
     * @return type
     */
    public function updateSetting($setting, $value, $userID = null)
    {
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_SETTINGS, LOG_ACTION_ELBP_SETTINGS_UPDATED_SETTING, $userID, array(
            "setting" => $setting,
            "value" => $value
        ));
        return \ELBP\Setting::setSetting($setting, $value, $userID, $this->id, true);
    }

    /**
     * Get lbp_setting record
     * @param type $setting
     * @param type $userID
     * @return type
     */
    public function getSetting($setting, $userID = null)
    {
        return \ELBP\Setting::getSetting($setting, $userID, $this->id, true);
    }

    /**
     * Get the background colour to use on the plugin box header
     * @return type
     */
    public function getHeaderBackgroundColour(){

        // Check if this user has set their own colours
        if ($this->student){
            $col = \ELBP\Setting::getSetting("header_bg_col", $this->student->id, $this->id, true);
            if ($col){
                return $col;
            }
        }

        $col = \ELBP\Setting::getSetting("header_bg_col", null, $this->id, true);

        return ($col) ? $col : '#ffffff';

    }

    /**
     * Get the font colour to use on the plugin box header
     * @return type
     */
    public function getHeaderFontColour(){

        // Check if this user has set their own colours
        if ($this->student){
            $col = \ELBP\Setting::getSetting("header_font_col", $this->student->id, $this->id, true);
            if ($col){
                return $col;
            }
        }

        $col = \ELBP\Setting::getSetting("header_font_col", null, $this->id, true);

        return ($col) ? $col : '#000000';

    }

    /**
     * Get the full style to apply to the plugin box header
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
     * Change the colour of the close icon based on colours selected
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
     * Save the settings just sent in the plugin configuration form
     * @param type $settings
     */
    public function saveConfig($settings)
    {

        global $ELBP, $DB, $MSGS;

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

                // Since we have to pass the title in various js calls (may have to change that), let's just strip
                // out anything that isn't a-z or space
                $title = preg_replace("/[^a-z0-9 ]/i", "", $title);

                if (!empty($title)){
                    $this->setName($title);
                    $this->updatePlugin();
                }

                unset($settings['plugin_title']);
            }

            foreach( (array)$settings as $setting => $value ){
                $this->updateSetting($setting, $value);
            }


            // FILES for icon img
            if (isset($_FILES['plugin_icon']) && $_FILES['plugin_icon']['error'] == 0){

                 $fInfo = \finfo_open(FILEINFO_MIME_TYPE);
                 $mime = \finfo_file($fInfo, $_FILES['plugin_icon']['tmp_name']);
                 \finfo_close($fInfo);

                 $explode = explode(".", $_FILES['plugin_icon']['name']);
                 $ext = $explode[1];
                 $name = 'custom_plugin_icon-' . $this->id . '.' . $ext;

                 $array = array('image/bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/tiff', 'image/pjpeg');
                 if (in_array($mime, $array))
                 {
                      $result = move_uploaded_file($_FILES['plugin_icon']['tmp_name'], $ELBP->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $name);
                      if ($result)
                      {
                          $this->updateSetting('plugin_icon', $name);
                          \elbp_create_data_path_code($ELBP->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $name);
                      }
                      else
                      {
                          $MSGS['errors'] = '<p>'.get_string('uploads:unknownerror', 'block_elbp').'</p>';
                      }
                 }
                 else
                 {
                     $MSGS['errors'] = '<p>'.get_string('uploads:invalidmimetype', 'block_elbp').'</p>';
                 }


            }



            // FILES for icon dock img
            if (isset($_FILES['plugin_icon_dock']) && $_FILES['plugin_icon_dock']['error'] == 0){

                 $fInfo = \finfo_open(FILEINFO_MIME_TYPE);
                 $mime = \finfo_file($fInfo, $_FILES['plugin_icon_dock']['tmp_name']);
                 \finfo_close($fInfo);

                 $explode = explode(".", $_FILES['plugin_icon_dock']['name']);
                 $ext = $explode[1];
                 $name = 'custom_plugin_icon_dock-' . $this->id . '.' . $ext;

                 $array = array('image/bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/tiff', 'image/pjpeg');
                 if (in_array($mime, $array))
                 {
                      $result = move_uploaded_file($_FILES['plugin_icon_dock']['tmp_name'], $ELBP->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $name);
                      if ($result)
                      {
                            $this->updateSetting('plugin_icon_dock', $name);
                            \elbp_create_data_path_code($ELBP->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $name);
                      }
                      else
                      {
                          $MSGS['errors'] = '<p>'.get_string('uploads:unknownerror', 'block_elbp').'</p>';
                      }
                 }
                 else
                 {
                     $MSGS['errors'] = '<p>'.get_string('uploads:invalidmimetype', 'block_elbp').'</p>';
                 }


            }



        }

        // Save the attributes for the plugin
        elseif(isset($_POST['submit_attributes']))
        {
            \elbp_save_attribute_script($this);
            return true;
        }

        // Save the title attribute to use in item headings if applicable
        elseif(isset($_POST['submit_title_attribute']))
        {
            if (!empty($settings['title_attribute'])){
                $this->updateSetting('title_attribute', $settings['title_attribute']);
                $MSGS['success'] = get_string('attributesupdated', 'block_elbp');
            }
            return true;
        }

        // Save the plugin permissions
        elseif (isset($_POST['submit_permissions'])){

            // Clear permissions
            $this->clearPermissions();

            // Insert new permissions (if we tick nothing, it will load default permissions)
            if ($settings['permissions'])
            {
                foreach($settings['permissions'] as $roleID => $permissions)
                {
                    foreach($permissions as $permission)
                    {
                        $this->addPermission($roleID, $permission);
                    }
                }

                $this->savePermissions();

            }

            $MSGS['success'] = get_string('permissionsupdated', 'block_elbp');
            return true;

        }

        // Run a permission check on a given user and student
        elseif (isset($_POST['submit_check_permissions']) && !empty($_POST['check_student']) && !empty($_POST['check_staff']))
        {

            $studentUsername = trim($_POST['check_student']);
            $staffUsername = trim($_POST['check_staff']);

            $student = $DB->get_record("user", array("username" => $studentUsername));
            $staff = $DB->get_record("user", array("username" => $staffUsername));

            if (!$student){
                $MSGS['errors'] = get_string('nosuchuser', 'block_elbp') . ": " . $studentUsername;
                return false;
            }
            if (!$staff){
                $MSGS['errors'] = get_string('nosuchuser', 'block_elbp') . ": " . $staffUsername;
                return false;
            }

            $this->getUserPermissions($student->id, $staff->id);

            return true;

        }

        // Save SQL field mappings and query, if we are using a DB report
        elseif (isset($_POST['submit_definitions'])){

            unset($settings['submit_definitions']);

            $this->updateSetting("sql_query", $settings['sql_query']);
            $this->updateSetting("row_return_type", $settings['row_return_type']);

            $settings['query_map_field'] = array_filter($settings['query_map_field']);
            $settings['query_map_name'] = array_filter($settings['query_map_name']);

            // If we have set field mappings
            if ($settings['query_map_field'] && $settings['query_map_name'])
            {
                $cnt = count($settings['query_map_field']);
                $cnt2 = count($settings['query_map_name']);

                // If the mappings and fields are equal
                if ($cnt == $cnt2)
                {

                    for ($i = 0; $i < $cnt; $i++)
                    {

                        $field = trim($settings['query_map_field'][$i]);
                        $name = trim($settings['query_map_name'][$i]);

                        if ($field == '' || $name == ''){
                            unset($settings['query_map_field'][$i]);
                            unset($settings['query_map_name'][$i]);
                        }

                    }

                    $this->updateSetting("query_map_field", implode(",", $settings['query_map_field']));
                    $this->updateSetting("query_map_name", implode(",", $settings['query_map_name']));
                }
            }

            $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
            return true;

        }

        // Run a test query, if we are using a DB report, to see what data it returns
        elseif (isset($_POST['submit_test_query']))
        {

            $sql = $settings['sql_query'];
            $rowType = $settings['row_return_type'];


            // Internal SQL Query
            if ($this->getStructure() == 'int_db')
            {

                if ($rowType == 'single'){

                    $result = $DB->get_record_sql($sql);
                    if ($result)
                    {
                        $result = $this->replaceMappedFieldsWithNames($result, $settings);
                    }

                } elseif ($rowType == 'multiple'){

                    $result = $DB->get_records_sql($sql);
                    if ($result)
                    {
                        $result = $this->replaceMappedFieldsWithNames($result, $settings);
                    }

                }

            }

            // External SQL query
            elseif ($this->getStructure() == 'ext_db' && $this->getMainMIS() !== false)
            {

                $this->connect();

                if (!$this->mis_connection){
                    $MSGS['errors'][] = get_string('nocoremis', 'block_elbp');
                    return false;
                }

                if (!$this->plugin_connection || !$this->plugin_connection->isValid()){
                    $MSGS['errors'][] = get_string('mis:connectioninvalid', 'block_elbp');
                    return false;
                }

                $query = $this->connection->query($sql, null);
                $results = $this->connection->getRecords($query);

                if ($rowType == 'single'){

                    $result = (isset($results[0])) ? $results[0] : false;
                    if ($result)
                    {
                        $result = $this->replaceMappedFieldsWithNames($result, $settings);
                    }

                } elseif ($rowType == 'multiple'){

                    $result = $results;
                    if ($result)
                    {
                        $result = $this->replaceMappedFieldsWithNames($result, $settings);
                    }

                }



            }

            $this->queryTestResult = $result;
            $this->queryTestNames = $settings['query_map_name'];

        }

    }

    /**
     * Get an array of the fields we have mapped to our SQL query
     * @return type
     */
    public function getMappedFields(){
        return explode(",", $this->getSetting('query_map_field'));
    }

    /**
     * Get an array of the names of these mappings
     * @return type
     */
    public function getMappedNames(){
        return explode(",", $this->getSetting('query_map_name'));
    }

    /**
     * Convert placeholders to variables so that we can run the SQL properly
     * @param type $sql
     * @param array $vars
     * @return type
     */
    private function prepareSQL($sql, array $vars){

        $params = array();

        // User: id
        if (isset($vars['uid']) && strpos($sql, "%uid%") !== false){
            $sql = str_replace("%uid%", "?", $sql);
            $params[] = $vars['uid'];
        }

        // User: username
        if (isset($vars['uname']) && strpos($sql, "%uname%") !== false){
            $sql = str_replace("%uname%", "?", $sql);
            $params[] = $vars['uname'];
        }

        // User: idnumber
        if (isset($vars['uidnum']) && strpos($sql, "%uidnum%") !== false){
            $sql = str_replace("%uidnum%", "?", $sql);
            $params[] = $vars['uidnum'];
        }

        // Course: id
        if (isset($vars['cid']) && strpos($sql, "%cid%") !== false){
            $sql = str_replace("%cid%", "?", $sql);
            $params[] = $vars['cid'];
        }

        // Course: shortname
        if (isset($vars['cshort']) && strpos($sql, "%cshort%") !== false){
            $sql = str_replace("%cshort%", "?", $sql);
            $params[] = $vars['cshort'];
        }

        // Course: idnumber
        if (isset($vars['cidnum']) && strpos($sql, "%cidnum%") !== false){
            $sql = str_replace("%cidnum%", "?", $sql);
            $params[] = $vars['cidnum'];
        }

        return array(
            'sql' => $sql,
            'params' => $params
        );

    }

    /**
     * Replace field names with their mapped names, so that we bring back the correct
     * data with the correct key, in our SQL query
     *
     * E.g. Our SQL query might be: SELECT username, firstname, lastname FROM {user}
     * And we might have mapped:
     *  username = "User Name"
     *  firstname = "First Name"
     *  lastname = "Surname"
     *
     * The query itself would return an array of:
     * [username="whatever",firstname="test",lastname="person"]
     *
     * But we want to then convert those fields to the actual field names we want to call them, so that it would be:
     * [User Name="whatever",First Name="test",Surname="person"]
     *
     * @param type $data
     * @param type $settings
     * @return type
     */
    private function replaceMappedFieldsWithNames($data, $settings = null){

        if (!is_null($settings)){
            $fields = $settings['query_map_field'];
            $names = $settings['query_map_name'];
            $rowType = $settings['row_return_type'];
        } else {
            $fields = $this->getMappedFields();
            $names = $this->getMappedNames();
            $rowType = $this->getSetting('row_return_type');
        }

        $cnt = count($fields);

        if (is_array($data) && $rowType == 'multiple')
        {

            foreach($data as &$row)
            {

                $row = (array)$row;

                for ($i = 0; $i < $cnt; $i++)
                {
                    if (isset($row[$fields[$i]])){
                        $row[$names[$i]] = $row[$fields[$i]];
                    }
                }
            }

        }
        else
        {

            if (!is_array($data)){
                $data = (array)$data;
            }

            for ($i = 0; $i < $cnt; $i++)
            {
                if (isset($data[$fields[$i]])){
                    $data[$names[$i]] = $data[$fields[$i]];
                }
            }

        }

        return $data;

    }

    /**
     * Display the main configuration form
     * @global \ELBP\Plugins\type $CFG
     * @global \ELBP\Plugins\type $ELBP
     */
    public function displayConfig(){

        global $CFG, $ELBP;

        $output = "";

        $enable = ($this->isEnabled()) ? 'checked' : '';
        $disable = (!$this->isEnabled()) ? 'checked' : '';

        $output .= "<small><strong>".get_string('blockconfig:enable', 'block_elbp')."</strong> - ".get_string('blockconfig:enable:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='radio' name='enabled' value='1' {$enable} /> <label>".get_string('enable')."</label>  &nbsp;";
        $output .= "&nbsp; <input type='radio' name='enabled' value='0' {$disable} /> <label>".get_string('disable')."</label> ";

        $output .= "<br><br>";

        $output .= "<small><strong>".get_string('blockconfig:plugintitle', 'block_elbp')."</strong> - ".get_string('blockconfig:plugintitle:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='text' name='plugin_title' value='{$this->name}' />";


        $output .= "<br><br>";

        $output .= "<small><strong>".get_string('blockconfig:headerbg', 'block_elbp')."</strong> - ".get_string('blockconfig:headerbg:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='color' name='header_bg_col' value='{$this->getSetting('header_bg_col')}' />";

        $output .= "<br><br>";

        $output .= "<small><strong>".get_string('blockconfig:headerfont', 'block_elbp')."</strong> - ".get_string('blockconfig:headerfont:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='color' name='header_font_col' value='{$this->getSetting('header_font_col')}' />";


        $output .= "<br><br>";

        $output .= "<h2>".get_string('pluginstructure', 'block_elbp')."</h2>";
        $output .= "<p>".get_string('pluginstructure:desc', 'block_elbp')."</p>";

        $output .= "<table id='custom_plugin_structures'>";

        $structure = $this->getStructure();

            $output .= "<tr>";
                $output .= "<td><input type='radio' name='plugin_structure' value='single' ". ( ($structure == 'single') ? 'checked' : '' ) ." /></td>";
                $output .= "<td><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/report.png' alt='".get_string('singlereport', 'block_elbp')."' /></td>";
                $output .= "<td>".get_string('singlereport', 'block_elbp')."</td>";
                $output .= "<td class='report_type_desc'>".get_string('singlereport:desc', 'block_elbp')."</td>";
            $output .= "</tr>";

            $output .= "<tr>";
                $output .= "<td><input type='radio' name='plugin_structure' value='multi' ". ( ($structure == 'multi') ? 'checked' : '' ) ." /></td>";
                $output .= "<td><img src='{$CFG->wwwroot}/blocks/elbp/pix/multi_report.png' alt='".get_string('multireport', 'block_elbp')."' /></td>";
                $output .= "<td>".get_string('multireport', 'block_elbp')."</td>";
                $output .= "<td class='report_type_desc'>".get_string('multireport:desc', 'block_elbp')."</td>";
            $output .= "</tr>";

            $output .= "<tr>";
                $output .= "<td><input type='radio' name='plugin_structure' value='incremental' ". ( ($structure == 'incremental') ? 'checked' : '' ) ." /></td>";
                $output .= "<td><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/data_sort.png' alt='".get_string('incrementalreport', 'block_elbp')."' /></td>";
                $output .= "<td>".get_string('incrementalreport', 'block_elbp')."</td>";
                $output .= "<td class='report_type_desc'>".get_string('incrementalreport:desc', 'block_elbp')."</td>";
            $output .= "</tr>";

            $output .= "<tr>";
                $output .= "<td><input type='radio' name='plugin_structure' value='int_db' ". ( ($structure == 'int_db') ? 'checked' : '' ) ." /></td>";
                $output .= "<td><img src='{$CFG->wwwroot}/blocks/elbp/pix/int_db_report.png' alt='".get_string('internaldbreport', 'block_elbp')."' /></td>";
                $output .= "<td>".get_string('internaldbreport', 'block_elbp')."</td>";
                $output .= "<td class='report_type_desc'>".get_string('internaldbreport:desc', 'block_elbp')."</td>";
            $output .= "</tr>";

            $output .= "<tr>";
                $output .= "<td><input type='radio' name='plugin_structure' value='ext_db' ". ( ($structure == 'ext_db') ? 'checked' : '' ) ." /></td>";
                $output .= "<td><img src='{$CFG->wwwroot}/blocks/elbp/pix/ext_db_report.png' alt='".get_string('externaldbreport', 'block_elbp')."' /></td>";
                $output .= "<td>".get_string('externaldbreport', 'block_elbp')."</td>";
                $output .= "<td class='report_type_desc'>".get_string('externaldbreport:desc', 'block_elbp')."</td>";
            $output .= "</tr>";

        $output .= "</table>";


        $output .= "<br><br>";

        $output .= "<h2>".get_string('summaryconfig', 'block_elbp')."</h2>";

        $output .= "<small><strong>".get_string('summaryconfig:title', 'block_elbp')."</strong> - ".get_string('summaryconfig:title:desc', 'block_elbp')."</small><br>";
        $output .= "<input type='text' name='plugin_summary_title' value='{$this->getSetting('plugin_summary_title')}' />";

        $output .= "<br><br>";

        $output .= "<small><strong>".get_string('summaryconfig:icon', 'block_elbp')."</strong> - ".get_string('summaryconfig:icon:desc', 'block_elbp')."</small><br>";
        $output .= "<small>";
            if (is_writable($ELBP->dir . DIRECTORY_SEPARATOR . 'uploads')){
                $output .= "<b class='elbp_good'>".get_string('dirwritable', 'block_elbp')." : ".$ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'."</b>";
            } else {
                $output .= "<b class='elbp_error'>".get_string('dirnotwritable', 'block_elbp')." : ".$ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'."</b>";
            }

        $output .= "</small><br>";

        if ($this->getSetting('plugin_icon') !== false){
            $output .= "<img src='".$this->getPrintLogo('plugin_icon')."' alt='' style='width:64px;height:64px;' /><br>";
        }

        $output .= "<input type='file' name='plugin_icon' value='' />";


        $output .= "<br><br>";

        $output .= "<small><strong>".get_string('summaryconfig:dockicon', 'block_elbp')."</strong> - ".get_string('summaryconfig:dockicon:desc', 'block_elbp')."</small><br>";
        $output .= "<small>";
            if (is_writable($ELBP->dir . DIRECTORY_SEPARATOR . 'uploads')){
                $output .= "<b class='elbp_good'>".get_string('dirwritable', 'block_elbp')." : ".$ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'."</b>";
            } else {
                $output .= "<b class='elbp_error'>".get_string('dirnotwritable', 'block_elbp')." : ".$ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'."</b>";
            }

        $output .= "</small><br>";

        if ($this->getSetting('plugin_icon_dock') !== false){
            $output .= "<img src='". $CFG->wwwroot . "/blocks/elbp/download.php?f=".\elbp_get_data_path_code($ELBP->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $this->getSetting('plugin_icon_dock'))."' alt='' style='width:64px;height:64px;' /><br>";
        }

        $output .= "<input type='file' name='plugin_icon_dock' value='' />";

        $output .= "<br><br>";
        $output .= "<h2>".get_string('notificationconfig', 'block_elbp')."</h2>";
        $output .= "<small>".get_string('notificationconfig:desc', 'block_elbp')."</small><br><br>";

        if ($structure == 'single' || $structure == 'multi' || $structure == 'incremental')
        {

            $enable = ($this->isNotifyEnabled()) ? 'checked' : '';
            $disable = (!$this->isNotifyEnabled()) ? 'checked' : '';

            $output .= "<small><strong>".get_string('enabledisable', 'block_elbp')."</strong><br>";
            $output .= "<input type='radio' name='notify_enabled' value='1' {$enable} /> <label>".get_string('enable')."</label>  &nbsp;";
            $output .= "&nbsp; <input type='radio' name='notify_enabled' value='0' {$disable} /> <label>".get_string('disable')."</label><br><br>";

            $output .= "<small><strong>".get_string('notificationconfig:users', 'block_elbp')."</strong> - ".get_string('notificationconfig:users:desc', 'block_elbp')."</small><br>";
            $output .= "<input type='text' name='notify_users' value='{$this->getSetting('notify_users')}' class='elbp_max' /><br><br>";

            $output .= "<small><strong>".get_string('notificationconfig:emailcontent', 'block_elbp')."</strong> - ".get_string('notificationconfig:emailcontent:desc', 'block_elbp')."</small><br>";
            $output .= "<textarea name='notify_message'>{$this->getSetting('notify_message')}</textarea><br><br>";

        }
        else
        {
            $output .= "<small>".get_string('notavailable')."</small>";
        }

        echo $output;

    }


    public function getAttributeNameFromID($id){

        $atts = $this->getAttributesForDisplay();

        if ($atts){
            foreach($atts as $att){
                if ($att->id == $id){
                    return $att->name;
                }
            }
        }

        return false;

    }

    /**
     * Return an attribute from the $this->attributes property, which were loaded earlier
     * @param type $name
     * @param type $na
     * @return string
     */
    public function getAttribute($name, $na = true){

        // First check to do is see if it's an array. If it is, we will implode it to a string
        if (isset($this->studentattributes[$name]) && is_array($this->studentattributes[$name]) && !empty($this->studentattributes[$name])) return implode(", ", $this->studentattributes[$name]);
        if (isset($this->studentattributes[$name]) && is_array($this->studentattributes[$name]) && empty($this->studentattributes[$name]) && $na) return get_string('na', 'block_elbp');

        // Not an array - string/int (well...string)
        if (isset($this->studentattributes[$name]) && $this->studentattributes[$name] == '' && $na) return get_string('na', 'block_elbp');
        if (isset($this->studentattributes[$name])) return $this->studentattributes[$name];
        if ($na) return get_string('na', 'block_elbp');
        return "";
    }

    /**
     * Return an attribute without doing anything to it.
     * SO for eaxmple if it's an array, it'll return an array instead of imploding it
     * @param type $name
     * @return type
     */
    public function getAttributeAsIs($name){
        return (isset($this->studentattributes[$name])) ? $this->studentattributes[$name] : false;
    }


    public function hasRecords(){

        $structure = $this->getStructure();

        if ($structure == 'single'){
            return (!empty($this->studentattributes));
        }

        elseif ($structure == 'multi' || $structure == 'incremental'){
            return ($this->getMultiItems());
        }

        return false;

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
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){

        global $CFG, $ELBP;

        $structure = $this->getStructure();

        if ($structure === false){
            return get_string('custompluginnostructure', 'block_elbp');
        }

        $this->connect();

        $TPL = new \ELBP\Template();

        $TPL->set("obj", $this);
        $TPL->set("ELBP", $ELBP);

        $method = "setDisplayVars".$structure;
        if (method_exists($this, $method)){
            $this->$method($TPL);
        }

        try {
            return $TPL->load($CFG->dirroot . '/blocks/elbp/plugins/Custom/tpl/'.$structure.'/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }

    }


    /**
     * Display the full plugin
     */
    public function display($params = array())
    {

        global $CFG, $OUTPUT;

        if (!$this->isEnabled()) return;
        if (!$this->student) return;

        $title = str_replace(" ", "_", $this->name);

        $output = "";
        $output .= "<div id='elbp_popup_header_plugin_{$this->name}' class='elbp_popup_header' title='".get_string('closepopup', 'block_elbp')."' style='{$this->getHeaderStyle()}'>";
            $output .= "<table class='elbp_popup_header_table'>";
                $output .= "<tr>";
                    $output .= "<td>{$this->getName()}</td>";
                    $output .= "<td class='elbp_popup_close'><a href='#' id='close_expanded_view' onclick='ELBP.unpop(\"{$title}\", \"".elbp_html($this->name)."\");return false;'><i class='icon-remove-sign icon-medium' style='{$this->getHeaderStyle()}' {$this->getIconHover()} ></i></a></td>";
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
     * Get the data to be put into the display() method
     * @global \ELBP\Plugins\type $CFG
     * @global \ELBP\Plugins\type $ELBP
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()){

        global $CFG, $ELBP;

        $structure = $this->getStructure();

        if ($structure === false){
            return get_string('custompluginnostructure', 'block_elbp');
        }

        $this->connect();

        $output = "";

        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);
        $TPL->set("params", $params);
        $TPL->set("ELBP", $ELBP);

        $structure = ucfirst($structure);

        $method = "setDisplayVars".$structure;
        if (method_exists($this, $method)){
            $this->$method($TPL);
        }

        $structure = strtolower($structure);

        try {
            $output .= $TPL->load($CFG->dirroot . '/blocks/elbp/plugins/Custom/tpl/'.$structure.'/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;

    }

    /**
     * Set specific variables to be used in the display for the Single Report
     * @param type $TPL
     */
    private function setDisplayVarsSingle(&$TPL){

        $attributes = $this->getAttributesForDisplay();

        $this->loadObjectIntoAttributes($attributes);

        $TPL->set("attributes", $attributes);
        $TPL->set("mainAttributes", $this->getAttributesForDisplayDisplayType("main", $attributes));
        $TPL->set("sideAttributes", $this->getAttributesForDisplayDisplayType("side", $attributes));
        $TPL->set("permissions", $this->getUserPermissions());

        $FORM = new \ELBP\ELBPForm();
        $FORM->loadStudentID($this->student->id);
        $TPL->set("FORM", $FORM);

    }

    /**
     * Set specific variables to be used in the display for the Multi Report
     * @param type $TPL
     */
    private function setDisplayVarsMulti(&$TPL){

        $items = $this->getMultiItems();
        $TPL->set("items", $items);
        $TPL->set("permissions", $this->getUserPermissions());

    }

    /**
     * Set specific variables to be used in the display for the Incremental Report
     * @param type $TPL
     */
    private function setDisplayVarsIncremental(&$TPL){

        $FORM = new \ELBP\ELBPForm();
        $FORM->loadStudentID($this->student->id);

        $TPL->set("attributes", $this->getAttributesForDisplay());
        $TPL->set("FORM", $FORM);
        $TPL->set("permissions", $this->getUserPermissions());

        $items = $this->getMultiItems();
        // Here we want to order in the other direction, with newest appended to bottom of table
        usort($items, function($a, $b){
            return ($a->settime > $b->settime);
        });

        $TPL->set("items", $items);
        $TPL->set("num", 0);

    }

    /**
     * Given the student and course loaded into the plugin, get the values to be used in placeholders
     * @return type
     */
    private function getSQLVars(){

        $vars = array();

        if ($this->student){
            $vars['uid'] = $this->student->id;
            $vars['uname'] = $this->student->username;
            $vars['uidnum'] = $this->student->idnumber;
        }
        if ($this->course){
            $vars['cid'] = $this->course->id;
            $vars['cshort'] = $this->course->shortname;
            $vars['cidnum'] = $this->course->idnumber;
        }

        return $vars;

    }

    /**
     * Set specific variables to be used in the display for the Internal DB Report
     * @param type $TPL
     */
    private function setDisplayVarsInt_db(&$TPL){

        $vars = $this->getSQLVars();
        $data = $this->prepareSQL($this->getSetting('sql_query'), $vars);

        $result = $this->runIntDBQuery($data['sql'], $data['params']);
        $result = $this->replaceMappedFieldsWithNames($result);

        $TPL->set("names", $this->getMappedNames());
        $TPL->set("result", $result);
        $TPL->set("permissions", $this->getUserPermissions());

    }

    /**
     * Set specific variables to be used in the display for the External DB Report
     * @param type $TPL
     */
    private function setDisplayVarsExt_db(&$TPL){

        $vars = array();

        if ($this->student){
            $vars['uid'] = $this->student->id;
            $vars['uname'] = $this->student->username;
            $vars['uidnum'] = $this->student->idnumber;
        }

        if ($this->course){
            $vars['cid'] = $this->course->id;
            $vars['cshort'] = $this->course->shortname;
            $vars['cidnum'] = $this->course->idnumber;
        }

        $data = $this->prepareSQL($this->getSetting('sql_query'), $vars);

        $result = $this->runExtDBQuery($data['sql'], $data['params']);
        $result = $this->replaceMappedFieldsWithNames($result);

        $TPL->set("names", $this->getMappedNames());
        $TPL->set("result", $result);
        $TPL->set("permissions", $this->getUserPermissions());

    }

    /**
     * Run the external DB query and return the results
     * @global \ELBP\Plugins\type $DB
     * @param type $sql
     * @param type $vars
     * @return boolean
     */
    private function runExtDBQuery($sql, $vars = null){

        global $DB;

        if (!$this->connection) return false;

        $rowType = $this->getSetting('row_return_type');

        $query = $this->connection->query($sql, $vars);
        $results = $this->connection->getRecords($query);

        if ($rowType == 'multiple'){
            return $results;
        }
        else
        {
            return (isset($results[0])) ? $results[0] : false;
        }

    }

    /**
     * Run the internal db query and return the results
     * @global \ELBP\Plugins\type $DB
     * @param type $sql
     * @param type $vars
     * @return type
     */
    private function runIntDBQuery($sql, $vars = null){

        global $DB;

        $rowType = $this->getSetting('row_return_type');

        if ($rowType == 'multiple'){
            return $DB->get_records_sql($sql, $vars);
        }
        else
        {
            return $DB->get_record_sql($sql, $vars);
        }

    }

    /**
     * Given an array of attributes on this plugin, set the student values for all of them for the
     * currently loaded student
     * @param type $data
     */
    private function setSubmittedAttributes($data){

        if (isset($data['studentID'])) unset($data['studentID']);
        if (isset($data['courseID'])) unset($data['courseID']);

        $possibleAttributes = $this->getElementsFromAttributeString();

        $this->studentattributes = array();

        if ($possibleAttributes)
        {
            foreach($possibleAttributes as $attribute)
            {

                // If we submitted something for that attribute, add it to the target object
                if (isset($data[$attribute->name])){
                    if (isset($attribute->options) && $attribute->options && !is_array($data[$attribute->name])){
                        $this->studentattributes[$attribute->name] = array($data[$attribute->name]);
                    } else {
                        $this->studentattributes[$attribute->name] = $data[$attribute->name];
                    }
                }

                // Matrix elements can't have the exact name, as they need the row in their name
                // So it won't be found by doing the above, we need to check if there are any
                // that start with that name
                foreach($data as $key => $d)
                {
                    $explode = explode($attribute->name . "_", $key);
                    if ($explode && count($explode) > 1)
                    {
                        $this->studentattributes[$key] = $d;
                    }
                }

            }
        }

    }

    /**
     * Save a single report
     * @global \ELBP\Plugins\type $DB
     * @return boolean
     */
    private function saveSingle(){

        global $DB;

        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->getElementsFromAttributeString();

        if ($allAttributes)
        {

            $FORM = new \ELBP\ELBPForm();

            foreach($allAttributes as $definedAttribute)
            {

                $value = (isset($this->studentattributes[$definedAttribute->name])) ? $this->studentattributes[$definedAttribute->name] : '';

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


        // Move any tmp files
        if (!$this->moveTmpUploadedFiles($allAttributes)){
            $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }


        // Update attributes for target
        if ($this->studentattributes)
        {

            foreach($this->studentattributes as $field => $value)
            {


                // If array, do each of them
                if (is_array($value))
                {

                    // If it's an array then we're going to have to delete all records of this att first
                    // Otherwise, say we saved 4 values: one, two, three, four oringally, then we update to: one, four
                    // The two & thre would still be in there
                    $DB->delete_records("lbp_custom_plugin_attributes", array("userid" => $this->student->id, "pluginid" => $this->id, "field" => $field));

                    foreach($value as $val)
                    {

                        if ($val == '') $val = null;

                        $ins = new \stdClass();
                        $ins->pluginid = $this->id;
                        $ins->userid = $this->student->id;
                        $ins->field = $field;
                        $ins->value = $val;
                        if (!$DB->insert_record("lbp_custom_plugin_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }

                    }

                }
                else
                {

                    if ($value == '') $value = null;

                    // Get att from DB
                    $attribute = $DB->get_record("lbp_custom_plugin_attributes", array("pluginid" => $this->id, "userid" => $this->student->id, "itemid" => null, "field" => $field));

                    // if it exists, update it
                    if ($attribute)
                    {
                        $ins = new \stdClass();
                        $ins->id = $attribute->id;
                        $ins->value = $value;
                        if (!$DB->update_record("lbp_custom_plugin_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                    }

                    // Else, insert it
                    else
                    {
                        $ins = new \stdClass();
                        $ins->pluginid = $this->id;
                        $ins->userid = $this->student->id;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_custom_plugin_attributes", $ins)){
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

                    if (!isset($this->studentattributes[$allAttribute->name]))
                    {
                        $DB->delete_records("lbp_custom_plugin_attributes", array("pluginid" => $this->id, "userid" => $this->student->id, "field" => $allAttribute->name));
                    }

                }
            }


        }

        // Notify
        if ($this->isNotifyEnabled())
        {
            $usernames = $this->getNotifyUsers();
            if ($usernames)
            {
                foreach($usernames as $username)
                {
                    $user = \elbp_get_user($username);
                    if ($user)
                    {
                        $this->notifyUser($user);
                    }
                }
            }
        }

        return true;

    }


    /**
     * Save a multiple report
     * @global \ELBP\Plugins\type $DB
     * @global \ELBP\Plugins\type $USER
     * @param int $itemID (Default: false)
     * @return boolean
     */
    private function saveMulti($itemID = false){

        global $DB, $USER;

        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->getElementsFromAttributeString();

        if ($allAttributes)
        {

            foreach($allAttributes as $definedAttribute)
            {

                $value = (isset($this->studentattributes[$definedAttribute->name])) ? $this->studentattributes[$definedAttribute->name] : '';

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


        // First save the item itself

        // Existing item - updating it
        if ($itemID)
        {

            // Don't think we actually need to do anything here, as settime, setbyuserid, etc... would always be the same
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_CUSTOM, LOG_ACTION_ELBP_CUSTOM_UPDATED_ITEM, $this->student->id, array(
                "itemID" => $itemID
            ));

        }
        else
        {

            // New item
            $obj = new \stdClass();
            $obj->pluginid = $this->id;
            $obj->studentid = $this->student->id;
            $obj->setbyuserid = $USER->id;
            $obj->settime = time();
            $itemID = $DB->insert_record("lbp_custom_plugin_items", $obj);

            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_CUSTOM, LOG_ACTION_ELBP_CUSTOM_ADDED_ITEM, $this->student->id, array(
                "itemID" => $itemID
            ));

        }


        // Move any tmp files
        if (!$this->moveTmpUploadedFiles($allAttributes, $itemID)){
            $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }


        // Update attributes for target
        if ($this->studentattributes)
        {

            foreach($this->studentattributes as $field => $value)
            {


                // If array, do each of them
                if (is_array($value))
                {

                    // If it's an array then we're going to have to delete all records of this att first
                    // Otherwise, say we saved 4 values: one, two, three, four oringally, then we update to: one, four
                    // The two & thre would still be in there
                    $DB->delete_records("lbp_custom_plugin_attributes", array("userid" => $this->student->id, "pluginid" => $this->id, "itemid" => $itemID, "field" => $field));

                    foreach($value as $val)
                    {

                        if ($val == '') $val = null;

                        $ins = new \stdClass();
                        $ins->pluginid = $this->id;
                        $ins->userid = $this->student->id;
                        $ins->itemid = $itemID;
                        $ins->field = $field;
                        $ins->value = $val;
                        if (!$DB->insert_record("lbp_custom_plugin_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }

                    }

                }
                else
                {

                    if ($value == '') $value = null;

                    // Get att from DB
                    $attribute = $DB->get_record("lbp_custom_plugin_attributes", array("pluginid" => $this->id, "userid" => $this->student->id, "itemid" => $itemID, "field" => $field));

                    // if it exists, update it
                    if ($attribute)
                    {
                        $ins = new \stdClass();
                        $ins->id = $attribute->id;
                        $ins->value = $value;
                        if (!$DB->update_record("lbp_custom_plugin_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                    }

                    // Else, insert it
                    else
                    {
                        $ins = new \stdClass();
                        $ins->pluginid = $this->id;
                        $ins->userid = $this->student->id;
                        $ins->itemid = $itemID;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_custom_plugin_attributes", $ins)){
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

                    if (!isset($this->studentattributes[$allAttribute->name]) || $this->studentattributes[$allAttribute->name] == "")
                    {
                        $DB->delete_records("lbp_custom_plugin_attributes", array("pluginid" => $this->id, "userid" => $this->student->id, "itemid" => $itemID, "field" => $allAttribute->name));
                    }

                }
            }


        }

        // Notify
        if ($this->isNotifyEnabled())
        {
            $usernames = $this->getNotifyUsers();
            if ($usernames)
            {
                foreach($usernames as $username)
                {
                    $user = \elbp_get_user($username);
                    if ($user)
                    {
                        $this->notifyUser($user);
                    }
                }
            }
        }

        return true;

    }

    /**
     * Delete a given item from a multiple report
     * @global \ELBP\Plugins\type $DB
     * @param type $itemID
     * @return boolean
     */
    private function deleteMultiItem($itemID){

        global $DB;

        $data = new \stdClass();
        $data->id = $itemID;
        $data->del = 1;

        if (!$DB->update_record("lbp_custom_plugin_items", $data)){
            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }

        // Log Action
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_CUSTOM, LOG_ACTION_ELBP_CUSTOM_DELETED_ITEM, $this->student->id, array(
            "itemID" => $itemID
        ));

        return true;

    }

    /**
     * Load the student's attribute data for a given item on a multiple or inremental report
     * @param type $itemID
     * @return type
     */
    private function loadMultiItemAttributeData($itemID = false){

        $attributes = $this->getAttributesForDisplay();

        if ($itemID){

            $this->loadStudent($this->student->id, $itemID);

            if ($attributes){

                foreach($attributes as &$attribute){

                    $attribute->loadObject($this);

                    if (isset($this->studentattributes[$attribute->name])){

                        $attribute->setValue($this->studentattributes[$attribute->name]);

                    } else {

                        // Matrix elements can't have the exact name, as they need the row in their name
                        // So it won't be found by doing the above, we need to check if there are any
                        // that start with that name
                        $valueArray = array();

                        foreach($this->studentattributes as $key => $d)
                        {
                            $explode = explode($attribute->name . "_", $key);
                            if ($explode && count($explode) > 1)
                            {
                                $valueArray[$explode[1]] = $d;
                            }
                        }

                        if (count($valueArray) == 1){
                            $valueArray = reset($valueArray);
                        }

                        $attribute->setValue($valueArray);

                    }

                }

            }

        } else {

            if ($attributes){

                foreach($attributes as &$attribute){

                    $attribute->loadObject($this);

                }

            }

        }

        return $attributes;


    }

    /**
     * Save an inremental report
     * @global \ELBP\Plugins\type $DB
     * @global \ELBP\Plugins\type $USER
     * @param type $itemID
     * @return boolean
     */
    private function saveIncremental($itemID = false){

        global $DB, $USER;

        // Loop through defined attributes and check if we have that submitted. Then validate it if needed
        $allAttributes = $this->getElementsFromAttributeString();

        if ($allAttributes)
        {

            foreach($allAttributes as $definedAttribute)
            {

                $value = (isset($this->studentattributes[$definedAttribute->name])) ? $this->studentattributes[$definedAttribute->name] : '';

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


        // First save the item itself

        // Existing item - updating it
        if ($itemID)
        {

            // Don't think we actually need to do anything here, as settime, setbyuserid, etc... would always be the same
            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_CUSTOM, LOG_ACTION_ELBP_CUSTOM_UPDATED_ITEM, $this->student->id, array(
                "itemID" => $itemID
            ));

        }
        else
        {

            // New item
            $obj = new \stdClass();
            $obj->pluginid = $this->id;
            $obj->studentid = $this->student->id;
            $obj->setbyuserid = $USER->id;
            $obj->settime = time();
            $itemID = $DB->insert_record("lbp_custom_plugin_items", $obj);

            // Log Action
            elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_CUSTOM, LOG_ACTION_ELBP_CUSTOM_ADDED_ITEM, $this->student->id, array(
                "itemID" => $itemID
            ));

        }

        // Move any tmp files
        if (!$this->moveTmpUploadedFiles($allAttributes, $itemID)){
            $this->errors[] = get_string('uploads:movingfiles', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }

        // Update attributes for target
        if ($this->studentattributes)
        {

            foreach($this->studentattributes as $field => $value)
            {


                // If array, do each of them
                if (is_array($value))
                {

                    // If it's an array then we're going to have to delete all records of this att first
                    // Otherwise, say we saved 4 values: one, two, three, four oringally, then we update to: one, four
                    // The two & thre would still be in there
                    $DB->delete_records("lbp_custom_plugin_attributes", array("userid" => $this->student->id, "pluginid" => $this->id, "itemid" => $itemID, "field" => $field));

                    foreach($value as $val)
                    {

                        if ($val == '') $val = null;

                        $ins = new \stdClass();
                        $ins->pluginid = $this->id;
                        $ins->userid = $this->student->id;
                        $ins->itemid = $itemID;
                        $ins->field = $field;
                        $ins->value = $val;
                        if (!$DB->insert_record("lbp_custom_plugin_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotinsertrecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }

                    }

                }
                else
                {

                    if ($value == '') $value = null;

                    // Get att from DB
                    $attribute = $DB->get_record("lbp_custom_plugin_attributes", array("pluginid" => $this->id, "userid" => $this->student->id, "itemid" => $itemID, "field" => $field));

                    // if it exists, update it
                    if ($attribute)
                    {
                        $ins = new \stdClass();
                        $ins->id = $attribute->id;
                        $ins->value = $value;
                        if (!$DB->update_record("lbp_custom_plugin_attributes", $ins)){
                            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
                            return false;
                        }
                    }

                    // Else, insert it
                    else
                    {
                        $ins = new \stdClass();
                        $ins->pluginid = $this->id;
                        $ins->userid = $this->student->id;
                        $ins->itemid = $itemID;
                        $ins->field = $field;
                        $ins->value = $value;
                        if (!$DB->insert_record("lbp_custom_plugin_attributes", $ins)){
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

                    if (!isset($this->studentattributes[$allAttribute->name]) || $this->studentattributes[$allAttribute->name] == "")
                    {
                        $DB->delete_records("lbp_custom_plugin_attributes", array("pluginid" => $this->id, "userid" => $this->student->id, "itemid" => $itemID, "field" => $allAttribute->name));
                    }

                }
            }


        }

        $this->newItemID = $itemID;

        // Notify
        if ($this->isNotifyEnabled())
        {
            $usernames = $this->getNotifyUsers();
            if ($usernames)
            {
                foreach($usernames as $username)
                {
                    $user = \elbp_get_user($username);
                    if ($user)
                    {
                        $this->notifyUser($user);
                    }
                }
            }
        }

        return true;

    }

    /**
     * Delete an item from an incremental report
     * @global \ELBP\Plugins\type $DB
     * @param type $itemID
     * @return boolean
     */
    private function deleteIncrementalItem($itemID){

        global $DB;

        $data = new \stdClass();
        $data->id = $itemID;
        $data->del = 1;

        if (!$DB->update_record("lbp_custom_plugin_items", $data)){
            $this->errors[] = get_string('errors:couldnotupdaterecord', 'block_elbp') . "[".__FILE__.":".__LINE__."]";
            return false;
        }

        // Log Action
        elbp_log(LOG_MODULE_ELBP, LOG_ELEMENT_ELBP_CUSTOM, LOG_ACTION_ELBP_CUSTOM_DELETED_ITEM, $this->student->id, array(
            "itemID" => $itemID
        ));

        return true;

    }


    /**
     * The method called from the main ELBP ajax handler script
     * @global \ELBP\Plugins\type $CFG
     * @global \ELBP\Plugins\type $DB
     * @global \ELBP\Plugins\type $USER
     * @param type $action
     * @param type $params
     * @param \ELBP\Plugins\type $ELBP
     * @return boolean
     */
    public function ajax($action, $params, $ELBP){

        global $CFG, $DB, $USER;

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

                $permissions = $this->getUserPermissions();

                if (!$this->havePermission(self::PERMISSION_VIEW, $permissions)) return false;

                $FORM = new \ELBP\ELBPForm();
                $FORM->loadStudentID($this->student->id);

                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this);
                $TPL->set("FORM", $FORM);
                $TPL->set("access", $this->access);
                $TPL->set("ELBP", $ELBP);
                $TPL->set("permissions", $permissions);

                $page = $params['type'];

                $TPL->set("page", $page);

                $itemID = (isset($params['itemID'])) ? $params['itemID'] : false;
                if ($itemID){
                    $item = $this->getMultiItem($itemID);
                    if (!$item || $item->studentid <> $this->student->id){
                        $itemID = false;
                    }
                }

                // If we are trying to add/edit but we don't have the permission, then stop
                if ($page == 'new' && !$this->havePermission( self::PERMISSION_ADD, $permissions )){
                    $page = 'all';
                }

                // If we are trying to add/edit but we don't have the permission, then stop
                if ($page == 'edit' && $item && ( ($USER->id <> $item->setbyuserid && !$this->havePermission( self::PERMISSION_EDIT_ANY, $permissions )) || ($USER->id == $item->setbyuserid && !$this->havePermission( self::PERMISSION_EDIT_OWN, $permissions )) ) ){
                    $page = 'all';
                }


                if ($page == 'new' || $page == 'edit'){
                    $attributes = $this->getAttributesForDisplay();
                    $TPL->set("attributes", $attributes);
                }

                if ($page == 'edit'){
                    $page = 'new'; # Use the same form, just check for different capabilities
                }





                if ($page == 'all'){
                    $this->setDisplayVarsMulti($TPL);
                }

                $TPL->set("itemID", $itemID);
                $TPL->set("data", $this->loadMultiItemAttributeData($itemID));

                try {
                    $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/Custom/tpl/'.$this->getStructure().'/'.$page.'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;

           break;



           case 'save_single':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $permissions = $this->getUserPermissions();
                if (!$this->havePermission(self::PERMISSION_EDIT_ANY, $permissions)) return false;

                $this->setSubmittedAttributes($params);

                if (!$this->saveSingle()){

                    echo "$('#custom_output').html('<div class=\"elbp_err_box\" id=\"custom_errors\"></div>');";

                    foreach($this->getErrors() as $error){

                        echo "$('#custom_errors').append('<span>{$error}</span><br>');";

                    }

                    exit;

                }


                // SUccess message at top
                echo "$('#custom_output').html('<div class=\"elbp_success_box\" id=\"custom_success\"></div>');";
                echo "$('#custom_success').append('<span>".get_string('saved', 'block_elbp')."</span><br>');";

                exit;

            break;



            case 'save_multi':

                if (!$params || !isset($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $itemID = (isset($params['itemID']) && $params['itemID'] > 0) ? $params['itemID'] : false;

                if (!$this->loadStudent($params['studentID'], $itemID)) return false;

                $item = $this->getMultiItem($itemID);

                $permissions = $this->getUserPermissions();

                // New item
                if (!$itemID && !$this->havePermission( self::PERMISSION_ADD , $permissions)){
                    return false;
                }

                // Existing item
                if ( ($item && $USER->id <> $item->setbyuserid && !$this->havePermission( self::PERMISSION_EDIT_ANY, $permissions )) || ($item && $USER->id == $item->setbyuserid && !$this->havePermission( self::PERMISSION_EDIT_OWN, $permissions )) ){
                    return false;
                }


                $this->setSubmittedAttributes($params);

                // [Permissions] - If itemid false, check add permission, else check edit_any or edit_own

                if (!$this->saveMulti($itemID)){

                    echo "$('#custom_output').html('<div class=\"elbp_err_box\" id=\"custom_errors\"></div>');";

                    foreach($this->getErrors() as $error){

                        echo "$('#custom_errors').append('<span>{$error}</span><br>');";

                    }

                    exit;

                }


                // SUccess message at top
                echo "$('#custom_output').html('<div class=\"elbp_success_box\" id=\"custom_success\"></div>');";
                echo "$('#custom_success').append('<span>".get_string('saved', 'block_elbp')."</span><br>');";

                if (!$itemID){
                    echo "$('#elbp_custom_plugin_form')[0].reset();";
                }

                exit;

            break;



           case 'delete_item':

                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['itemID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $permissions = $this->getUserPermissions();

                $item = $this->getMultiItem($params['itemID']);

                if ( ($USER->id <> $item->setbyuserid && !$this->havePermission( self::PERMISSION_DEL_ANY, $permissions )) || ($USER->id == $item->setbyuserid && !$this->havePermission( self::PERMISSION_DEL_OWN, $permissions )) ){
                    return false;
                }


                // If the record exists, check to make sure the student ID on it is the same as the one we specified
                if (!$item || $item->studentid <> $params['studentID']) return false;

                if (!$this->deleteMultiItem($item->id)){
                    echo "$('#custom_output').html('<div class=\"elbp_err_box\" id=\"generic_err_box\"></div>');";
                    echo "$('#generic_err_box').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                echo "$('#custom_output').html('<div class=\"elbp_success_box\" id=\"generic_success_box\"></div>');";
                echo "$('#generic_success_box').append('<span>".get_string('itemdeleted', 'block_elbp')."</span><br>');";
                echo "$('#elbp_custom_item_{$item->id}').remove();";

                exit;

           break;

           case 'save_incremental':

                if (!$params || !isset($params['studentID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $itemID = (isset($params['itemID']) && $params['itemID'] > 0) ? $params['itemID'] : false;

                if (!$this->loadStudent($params['studentID'], $itemID)) return false;

                $item = $this->getMultiItem($itemID);
                if ($item && $item->studentid <> $params['studentID']) return false;

                $permissions = $this->getUserPermissions();

                // New item
                if (!$itemID && !$this->havePermission( self::PERMISSION_ADD , $permissions)){
                    return false;
                }

                // Existing item
                if ( $item && (($USER->id <> $item->setbyuserid && !$this->havePermission( self::PERMISSION_EDIT_ANY, $permissions )) || ($USER->id == $item->setbyuserid && !$this->havePermission( self::PERMISSION_EDIT_OWN, $permissions ))) ){
                    return false;
                }


                $this->setSubmittedAttributes($params);

                if (!$this->saveIncremental($itemID)){

                    echo "$('#custom_output').html('<div class=\"elbp_err_box\" id=\"custom_errors\"></div>');";

                    foreach($this->getErrors() as $error){

                        echo "$('#custom_errors').append('<span>{$error}</span><br>');";

                    }

                    exit;

                }


                // SUccess message at top
                echo "$('#custom_output').html('<div class=\"elbp_success_box\" id=\"custom_success\"></div>');";
                echo "$('#custom_success').append('<span>".get_string('saved', 'block_elbp')."</span><br>');";

                exit;

            break;

           case 'delete_incremental_item':

               if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['itemID'])) return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $permissions = $this->getUserPermissions();

                $item = $this->getMultiItem($params['itemID']);

                if ( ($USER->id <> $item->setbyuserid && !$this->havePermission( self::PERMISSION_DEL_ANY, $permissions )) || ($USER->id == $item->setbyuserid && !$this->havePermission( self::PERMISSION_DEL_OWN, $permissions )) ){
                    return false;
                }

                // If the record exists, check to make sure the student ID on it is the same as the one we specified
                if (!$item || $item->studentid <> $params['studentID']) return false;

                if (!$this->deleteIncrementalItem($item->id)){
                    echo "$('#custom_output').html('<div class=\"elbp_err_box\" id=\"generic_err_box\"></div>');";
                    echo "$('#generic_err_box').append('<span>".get_string('errors:couldnotupdaterecord', 'block_elbp')."</span><br>');";
                    exit;
                }

                echo "$('#custom_output').html('<div class=\"elbp_success_box\" id=\"generic_success_box\"></div>');";
                echo "$('#generic_success_box').append('<span>".get_string('itemdeleted', 'block_elbp')."</span><br>');";
                echo "$('.incremental_item_{$item->id}').remove();";
                echo "$('.incremental_item_{$item->id}_edit').remove();";
                exit;

           break;

           case 'refresh_incremental':

               if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;

               if ($this->getStructure() != 'incremental') return false;

                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;

                $permissions = $this->getUserPermissions();
                if (!$this->havePermission(self::PERMISSION_VIEW, $permissions)) return false;

                $FORM = new \ELBP\ELBPForm();
                $FORM->loadStudentID($this->student->id);

                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this);
                $TPL->set("FORM", $FORM);
                $TPL->set("access", $this->access);
                $TPL->set("ELBP", $ELBP);
                $TPL->set("permissions", $permissions);

                $this->setDisplayVarsIncremental($TPL);

                try {
                    $TPL->load( $CFG->dirroot . '/blocks/elbp/plugins/Custom/tpl/incremental/items.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;

           break;


        }

    }


    /**
     * Display a particular item from a multiple report
     * @global \ELBP\Plugins\type $CFG
     * @global \ELBP\Plugins\type $ELBP
     * @global type $OUTPUT
     * @global \ELBP\Plugins\type $USER
     * @param type $item
     * @return boolean
     */
    public function displayMultiItem($item){

        global $CFG, $ELBP, $OUTPUT, $USER;

        if (!$this->student) return false;

        // Reload student with item ID
        $this->loadStudent($this->student->id, $item->id);
        $attributes = $this->getAttributesForDisplay();

        if (!$attributes) return get_string('noattributesdefined', 'block_elbp');

        $output = "";

        $output .= "<table class='elbp_custom_item_header_table' onclick='$(\"#custom_item_content_{$item->id}\").slideToggle();return false;'>";
            $output .= "<tr>";
                $output .= "<td class='elbp_object_icon'>";
                if ($this->getSetting('plugin_icon') !== false){
                    $output .= "<img src='". $CFG->wwwroot . "/blocks/elbp/download.php?f=".\elbp_get_data_path_code($ELBP->dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $this->getSetting('plugin_icon'))."' alt='{$this->getName()}'>";
                }

                $output .= "<td class='elbp_object_name'>";
                    $title = date('D jS F Y', $item->settime);
                    $titleAtt = $this->getSetting('title_attribute');
                    if ( $titleAtt && $this->getAttribute($titleAtt, false) !== false && $this->getAttribute($titleAtt, false) != "" ){
                        $title = $this->getAttribute($titleAtt, false);
                    }
                    $output .= "<span class='title'>{$title}</span><br><br>";
                    $output .= get_string('setby', 'block_elbp') . ": " . \elbp_get_fullname($item->setbyuserid).", ". date('D jS F Y, H:i', $item->settime);
               $output .= " </td>";
            $output .= "</tr>";
        $output .= "</table>";

        $output .= "<div id='custom_item_content_{$item->id}' class='elbp_custom_item_hidden'>";

            $output .= "<div class='elbp_centre'>";
                $output .= "<small>";
                if ( ($USER->id <> $item->setbyuserid && $this->havePermission( self::PERMISSION_EDIT_ANY, $this->userpermissions )) || ($USER->id == $item->setbyuserid && $this->havePermission( self::PERMISSION_EDIT_OWN, $this->userpermissions ))){
                    $output .= "<a href='#' onclick='ELBP.Custom.edit_item(\"{$this->getName()}\", {$item->id});return false;'><img src='".elbp_image_url('t/editstring', 'core')."' alt='' /> ".get_string('edit', 'block_elbp')."</a> &nbsp; &nbsp; &nbsp;";
                }
                if ( ($USER->id <> $item->setbyuserid && $this->havePermission( self::PERMISSION_DEL_ANY, $this->userpermissions )) || ($USER->id == $item->setbyuserid && $this->havePermission( self::PERMISSION_DEL_OWN, $this->userpermissions ))){
                    $output .= "<a href='#' onclick='ELBP.Custom.delete_item(\"{$this->getName()}\", {$item->id});return false;'><img src='".elbp_image_url('t/delete', 'core')."' alt='' /> ".get_string('delete', 'block_elbp')."</a> &nbsp; &nbsp; &nbsp;";
                }
                if ( ($this->havePermission( self::PERMISSION_PRINT, $this->userpermissions )) ){
                    $output .= "<a href='{$CFG->wwwroot}/blocks/elbp/print.php?plugin={$this->id}&object={$item->id}&student={$item->studentid}&custom=1' target='_blank' ><img src='".elbp_image_url('t/print', 'core')."' alt='' /> ".get_string('print', 'block_elbp')."</a> &nbsp; &nbsp; &nbsp;";
                }
                $output .= "</small><br><br>";
            $output .= "</div>";

            $output .= "<div>";

            // Main central elements
            $output .= "<div class='elbp_custom_item_main_elements'>";
                $mainAttributes = $this->getAttributesForDisplayDisplayType("main", $attributes);

                if ($mainAttributes)
                {
                    foreach($mainAttributes as $attribute)
                    {
                        $output .= "<h2>{$attribute->name}</h2>";
                        $output .= "<div class='elbp_custom_item_attribute_content'>";
                            $output .= $attribute->displayValue();
                        $output .= "</div>";
                        $output .= "<br>";
                    }
                }
            $output .= "</div>";


            // Summary
            $output .= "<div class='elbp_custom_item_summary_elements'>";

                $sideAttributes = $this->getAttributesForDisplayDisplayType("side", $attributes);

                if ($sideAttributes)
                {
                    $output .= "<b>".get_string('otherattributes', 'block_elbp')."</b><br><br>";
                    $output .= "<table class='custom_item_summary_table'>";
                    foreach($sideAttributes as $attribute)
                    {
                        //".elbp_html( $this->getAttribute($attribute->name, true) ) . "
                         $output .= "<tr><td>{$attribute->name}:</td><td>{$attribute->displayValue()}</td></tr>";
                    }
                    $output .= "</table>";
                }

            $output .= "</div>";

            $output .= "<br class='elbp_cl'>";

            $output .= "</div>";

        $output .= "</div>";

        echo $output;

    }

    public function getReportingElements(){
        return false;
    }

    public function getMassActions(){
        return false;
    }

    /**
     * Print the report
     * @global \ELBP\Plugins\type $CFG
     * @global \ELBP\Plugins\type $DB
     * @param type $objectID
     * @param type $studentID
     * @param type $type
     */
    public function printOut($objectID, $studentID, $type){

        global $CFG, $DB;

        $structure = $this->getStructure();
        if ($structure === false){
             echo get_string('custompluginnostructure', 'block_elbp');
             exit;
        }

        $this->loadStudent($studentID);

        $permissions = $this->getUserPermissions();
        if (!$this->havePermission( self::PERMISSION_PRINT, $permissions )){
            echo get_string('noaccess', 'block_elbp');
            exit;
        }

        $attributes = $this->getAttributesForDisplay();


        $txt = "";

        switch($structure)
        {

            case 'single':

                $mainAttributes = $this->getAttributesForDisplayDisplayType("main");
                $sideAttributes = $this->getAttributesForDisplayDisplayType("side");

                if ($mainAttributes)
                {
                    foreach($mainAttributes as $att)
                    {
                        $txt .= "<h2 class='custom_attribute_title'>{$att->name}</h2>";
                        $txt .= "<div class='elbp_custom_attribute_content'>";
                            $txt .= $att->displayValue(true);
                        $txt .= "</div>";
                    }
                }


                if ($sideAttributes)
                {

                    $txt .= "<br><hr><br>";

                    foreach($sideAttributes as $att)
                    {
                        $txt .= "<p>{$att->name}: ".$att->displayValue(true)."</p>";
                    }
                }

            break;

            case 'multi':
            case 'incremental':


                // ID of a specific item
                if (is_numeric($objectID))
                {

                    $item = $this->getMultiItem($objectID);
                    if ($item)
                    {

                        if ($item->studentid <> $this->student->id){
                            echo get_string('invaliduser', 'block_elbp');
                            exit;
                        }

                        $this->loadStudent($this->student->id, $item->id);

                        $txt .= "<div>";

                            // Main central elements
                            $txt .= "<div style='width:80%;float:left;'>";

                                $mainAttributes = $this->getAttributesForDisplayDisplayType("main", $attributes);

                                if ($mainAttributes)
                                {
                                    foreach($mainAttributes as $attribute)
                                    {
                                        $txt .= "<h2>{$attribute->name}</h2>";
                                        $txt .= "<div class='elbp_custom_item_attribute_content'>";
                                            $txt .= $attribute->displayValue(true);
                                        $txt .= "</div>";
                                        $txt .= "<br>";
                                    }
                                }

                            $txt .= "</div>";


                            // Summary
                            $txt .= "<div style='width:20%;float:left;padding-top:20px;'>";

                                $sideAttributes = $this->getAttributesForDisplayDisplayType("side", $attributes);

                                if ($sideAttributes)
                                {
                                    $txt .= "<b>".get_string('otherattributes', 'block_elbp')."</b><br><br>";
                                    $txt .= "<table class='custom_item_summary_table'>";
                                    foreach($sideAttributes as $attribute)
                                    {
                                         $txt .= "<tr><td>{$attribute->name}:</td><td>{$attribute->displayValue(true)}</td></tr>";
                                    }
                                    $txt .= "</table>";
                                }

                            $txt .= "</div>";

                        $txt .= "</div>";

                        $txt .= "<br style='clear:both;' />";

                    }

                }
                else // All of them
                {

                    $items = $this->getMultiItems();

                    if ($items)
                    {

                        foreach($items as $item)
                        {

                            $this->loadStudent($this->student->id, $item->id);

                            $txt .= "<div>";

                                // Main central elements
                                $txt .= "<div style='width:80%;float:left;'>";

                                    $mainAttributes = $this->getAttributesForDisplayDisplayType("main", $attributes);

                                    if ($mainAttributes)
                                    {
                                        foreach($mainAttributes as $attribute)
                                        {
                                            $txt .= "<h2>{$attribute->name}</h2>";
                                            $txt .= "<div class='elbp_custom_item_attribute_content'>";
                                                $txt .= $attribute->displayValue(true);
                                            $txt .= "</div>";
                                            $txt .= "<br>";
                                        }
                                    }

                                $txt .= "</div>";


                                // Summary
                                $txt .= "<div style='width:20%;float:left;padding-top:20px;'>";

                                    $sideAttributes = $this->getAttributesForDisplayDisplayType("side", $attributes);

                                    if ($sideAttributes)
                                    {
                                        $txt .= "<b>".get_string('otherattributes', 'block_elbp')."</b><br><br>";
                                        $txt .= "<table class='custom_item_summary_table'>";
                                        foreach($sideAttributes as $attribute)
                                        {
                                             $txt .= "<tr><td>{$attribute->name}:</td><td>".$attribute->displayValue(true). "</td></tr>";
                                        }
                                        $txt .= "</table>";
                                    }

                                $txt .= "</div>";

                            $txt .= "</div>";

                            $txt .= "<br style='clear:both;' />";

                            $txt .= "<br><hr><br>";

                        }

                    }

                }


            break;

            case 'int_db':

                $vars = $this->getSQLVars();
                $data = $this->prepareSQL($this->getSetting('sql_query'), $vars);

                $result = $this->replaceMappedFieldsWithNames( $this->runIntDBQuery($data['sql'], $data['params']) );
                $names = $this->getMappedNames();

                // Rows - Single
                if ($this->getSetting('row_return_type') == 'single')
                {

                    if ($result && $names)
                    {

                        foreach($names as $name)
                        {

                            $txt .= "<h2 class='custom_attribute_title'>{$name}</h2>";
                            $txt .= "<div class='elbp_custom_attribute_content'>";
                                $txt .= (isset($result[$name])) ? \elbp_html($result[$name], true) : '-';
                            $txt .= "</div>";

                        }

                    }


                }
                elseif ($this->getSetting('row_return_type') == 'multiple')
                {

                    $txt .= "<table style='min-width:50%;margin:auto;'>";

                        $txt .= "<tr>";
                            foreach($names as $name)
                            {
                                $txt .= "<th style='text-align:left;'>{$name}</th>";
                            }
                        $txt .= "</tr>";

                        foreach($result as $row)
                        {

                            $txt .= "<tr>";
                                foreach($names as $name)
                                {
                                    $txt .= "<td>".( (isset($row[$name])) ? $row[$name] : '-' )."</td>";
                                }
                            $txt .= "</tr>";

                        }

                    $txt .= "</table>";

                }

            break;



            case 'ext_db':

                $this->connect();
                $vars = $this->getSQLVars();
                $data = $this->prepareSQL($this->getSetting('sql_query'), $vars);
                $result = $this->replaceMappedFieldsWithNames( $this->runExtDBQuery($data['sql'], $data['params']) );
                $names = $this->getMappedNames();

                // Rows - Single
                if ($this->getSetting('row_return_type') == 'single')
                {

                    if ($result && $names)
                    {

                        foreach($names as $name)
                        {

                            $txt .= "<h2 class='custom_attribute_title'>{$name}</h2>";
                            $txt .= "<div class='elbp_custom_attribute_content'>";
                                $txt .= (isset($result[$name])) ? \elbp_html($result[$name], true) : '-';
                            $txt .= "</div>";

                        }

                    }


                }
                elseif ($this->getSetting('row_return_type') == 'multiple')
                {

                    $txt .= "<table style='min-width:50%;margin:auto;'>";

                        $txt .= "<tr>";
                            foreach($names as $name)
                            {
                                $txt .= "<th style='text-align:left;'>{$name}</th>";
                            }
                        $txt .= "</tr>";

                        foreach($result as $row)
                        {

                            $txt .= "<tr>";
                                foreach($names as $name)
                                {
                                    $txt .= "<td>".( (isset($row[$name])) ? $row[$name] : '-' )."</td>";
                                }
                            $txt .= "</tr>";

                        }

                    $txt .= "</table>";

                }

            break;


        }



        // Print the custom plugin
        $pageTitle = fullname($this->getStudent()) . ' (' . $this->student->username . ') - ' . $this->getName();
        $title = $this->getName();
        $heading = fullname($this->getStudent()) . ' (' . $this->student->username . ')';
        $logo = \ELBP\ELBP::getPrintLogo();

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
     * Export the plugin to XML, so that someone else can easily import it
     * (Does not export the data, only the structure of it)
     * @global \ELBP\Plugins\type $CFG
     * @global \ELBP\Plugins\type $DB
     * @return \SimpleXMLElement
     */
    public function exportXML(){

        global $ELBP, $DB;

        $settings = $DB->get_records("lbp_custom_plugin_settings", array("pluginid" => $this->id, "userid" => null));
        $permissions = $this->getPermissions();

        $xml = new \SimpleXMLElement('<xml/>');
        $xml->addChild('name', $this->name);

        $s = $xml->addChild('settings');
        if ($settings)
        {
            foreach($settings as $setting)
            {
                $el = $s->addChild('setting', $setting->value);
                $el->addAttribute('name', $setting->setting);
            }
        }

        $p = $xml->addChild('permissions');
        if ($permissions)
        {
            foreach($permissions as $roleID => $perms)
            {
                if ($perms)
                {
                    foreach($perms as $perm)
                    {
                        $el = $p->addChild('permission', $perm);
                        $el->addAttribute('roleid', $roleID);
                    }
                }

            }
        }


        // Icons
        $array = array('bmp', 'gif', 'jpeg', 'jpg', 'png', 'tiff');
        $icon = false;

        foreach($array as $ext){

            // Plugin icon
            $path = $ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'.DIRECTORY_SEPARATOR.'custom_plugin_icon-' . $this->id . '.' . $ext;
            if (file_exists($path)){

                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $icon = 'data:image/' . $type . ';base64,' . base64_encode($data);
                $xml->addChild('icon', $icon);

            }

            // Dock icon
            $path = $ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'.DIRECTORY_SEPARATOR.'custom_plugin_icon_dock-' . $this->id . '.' . $ext;
            if (file_exists($path)){

                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $dock = 'data:image/' . $type . ';base64,' . base64_encode($data);
                $xml->addChild('dockIcon', $dock);


            }

        }

        return $xml;

    }


    /**
     * Import the custom plugin from an XML exported file
     * @param type $file
     */
    public static function createFromXML($file){

        global $ELBP;

        // CHeck file exists
        if (!file_exists($file)){
            return array('success' => false, 'error' => get_string('filenotfound', 'block_elbp') . " ( {$file} )");
        }

        // Check mime type of file to make sure it is csv
        $fInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fInfo, $file);
        finfo_close($fInfo);

        // Has to be csv file, otherwise error and return
        if ($mime != 'application/xml' && $mime != 'text/plain' && $mime != 'text/xml'){
            return array('success' => false, 'error' => get_string('uploads:invalidmimetype', 'block_elbp') . " ( {$mime} )");
        }

        // Open file
        $xml = \simplexml_load_file($file);

        if (!isset($xml->name) || !isset($xml->settings)){
            return array('success' => false, 'error' => get_string('importcustomplugin:missingnodes', 'block_elbp'));
        }


        $title = (string)$xml->name;
        $settings = array();
        $permissions = array();

        $i = 0;

        foreach($xml->settings->setting as $setting)
        {
            $name = (string)$xml->settings->setting[$i]->attributes()->name;
            $setting = (string)$setting;
            $settings[$name] = $setting;
            $i++;
        }

        $i = 0;

        foreach($xml->permissions->permission as $permission)
        {
            $roleID = (string)$xml->permissions->permission[$i]->attributes()->roleid;
            $permission = (string)$permission;
            if (!isset($permissions[$roleID])){
                $permissions[$roleID] = array();
            }
            $permissions[$roleID][] = $permission;
            $i++;
        }


        // Create the plugin
        $plugin = new \ELBP\Plugins\CustomPlugin();
        $plugin->setName($title);
        $pluginID = $plugin->createPlugin();
        $plugin->setID($pluginID);

        if (!$plugin->getID()){
            return array('success' => false, 'error' => get_string('errors:couldnotinsertrecord', 'block_elbp'));
        }

        // Settings
        foreach($settings as $name => $value)
        {
            $plugin->updateSetting($name, $value);
        }

        // Permissions
        if ($permissions){
            foreach($permissions as $roleID => $perms){
                if ($perms){
                    foreach($perms as $perm){
                        $plugin->addPermission($roleID, $perm);
                    }
                }
            }
        }

        $plugin->savePermissions();

        // Icon
        if (isset($xml->icon)){

            $icon = (string)$xml->icon;
            $ext = \elbp_get_image_ext_from_base64($icon);
            $path = $ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'.DIRECTORY_SEPARATOR.'custom_plugin_icon-' . $plugin->getID() . '.' . $ext;
            \elbp_save_base64_image($icon, $path);
            $plugin->updateSetting('plugin_icon', 'custom_plugin_icon-' . $plugin->getID() . '.' . $ext);
            \elbp_create_data_path_code($path);

        }

        // Dock Icon
        if (isset($xml->dockIcon)){

            $icon = (string)$xml->dockIcon;
            $ext = \elbp_get_image_ext_from_base64($icon);
            $path = $ELBP->dir . DIRECTORY_SEPARATOR . 'uploads'.DIRECTORY_SEPARATOR.'custom_plugin_icon_dock-' . $plugin->getID() . '.' . $ext;
            \elbp_save_base64_image($icon, $path);
            $plugin->updateSetting('plugin_icon_dock', 'custom_plugin_icon_dock-' . $plugin->getID() . '.' . $ext);
            \elbp_create_data_path_code($path);

        }

        return array('success' => true, 'output' => get_string('created', 'block_elbp') . ' ' . get_string('created', 'block_elbp') . ': ' . $plugin->getName());


    }

    public function calculateStudentProgress(){

        return array(
            'max' => 0,
            'num' => 0
        );

    }



    /**
     * Having uploaded any file attributes to the /tmp/ directory, we now want to move them
     * to a proper directory so they don't get deleted
     * @param type $defaultAttributes
     */
    protected function moveTmpUploadedFiles($defaultAttributes, $itemID = 0){

        global $CFG, $USER;

        $result = true;

        if ($defaultAttributes)
        {
            foreach($defaultAttributes as $attribute)
            {
                if ($attribute->type == 'File')
                {

                    // Sanitize the path
                    $this->studentattributes[$attribute->name] = \elbp_sanitize_path($this->studentattributes[$attribute->name]);

                    $value = (isset($this->studentattributes[$attribute->name])) ? $this->studentattributes[$attribute->name] : false;
                    if ($value)
                    {

                        // Is it a tmp file?
                        if (strpos($value, "tmp:") === 0){

                            $value = \elbp_sanitize_path( substr($value, (4 - strlen($value))) );
                            $tmpFile = $CFG->dataroot . '/ELBP/tmp/' . $value;

                            // Create directory
                            $create = \elbp_create_data_directory( 'Custom/' . $this->getID() . '/' . $itemID );
                            if ($create){

                                $explode = explode("/", $value);
                                $value = end($explode);

                                $newFile = $CFG->dataroot . '/ELBP/Custom/' . $this->getID() . '/' . $itemID . '/' . $value;

                                if (\rename($tmpFile, $newFile)){
                                    $this->studentattributes[$attribute->name] = 'Custom/' . $this->getID() . '/' . $itemID . '/' . $value;
                                } else {
                                    $result = false;
                                }

                            } else {
                                $result = false;
                            }

                        }

                    }
                }
            }
        }

        return $result;

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

                // New one, no old name
                if (!isset($oldNames[$i])) continue;

                $newName = $newNames[$i];
                $oldName = $oldNames[$i];

                // Name has changed
                if ($newName !== $oldName)
                {

                    // Update all references to the old name to the new name
                    $DB->execute("UPDATE {lbp_custom_plugin_attributes} SET field = ? WHERE field = ? AND pluginid = ?", array($newName, $oldName, $this->id));

                }

            }
        }

        return true;

    }

    public function isNotifyEnabled(){
        $setting = $this->getSetting('notify_enabled');
        return ($setting == 1);
    }

    public function getNotifyUsers(){

        $return = array();

        $setting = $this->getSetting('notify_users');
        if ($setting)
        {
            $emails = explode(",", $setting);
            foreach($emails as $email)
            {
                $return[] = trim($email);
            }
        }

        return $return;

    }

    private function notifyUser($emailToUser){

        $content = $this->getSetting('notify_message') . "\n\n" . $this->getInfoForEvent();

        $Alert = new \ELBP\EmailAlert();
        return $Alert->queue("email", $emailToUser, $this->getTitle() . ' :: ' . get_string('notification', 'block_elbp'), $content, nl2br($content));

    }

    /**
     * Get the content for the triggered alert emails
     * @global \ELBP\Plugins\Comments\type $CFG
     * @global \ELBP\Plugins\Comments\type $USER
     * @param type $useHtml
     * @param type $tmp
     * @return string
     */
    private function getInfoForEvent()
    {
        global $CFG, $USER;

        $output = "";

        $output .= "\n----------\n";
        $output .= get_string('student', 'block_elbp') . ": " . fullname($this->getStudent()) . " ({$this->getStudent()->username})\n";

        // Attributes
        if ($this->studentattributes)
        {

            foreach($this->studentattributes as $field => $value)
            {
                if (is_array($value)){
                    $value = implode(",", $value);
                }
                $value = preg_replace("/\n/", " ", $value);
                $output .= $field . ": <b>" . $value . "</b>\n\n";
            }

        }

        $output .= "----------\n";
        $output .= get_string('updatedby', 'block_elbp') . ": " . fullname($USER) . "\n";
        $output .= get_string('link', 'block_elbp') . ": " . "{$CFG->wwwroot}/blocks/elbp/view.php?id={$this->student->id}\n";

        return $output;

    }


    public function getLastUpdated(){

        global $DB;

        $structure = $this->getStructure();
        if ($structure == 'multi' || $structure == 'incremental'){

            $record = $DB->get_records("lbp_custom_plugin_items", array("studentid" => $this->student->id, "pluginid" => $this->id, "del" => 0), "settime DESC", "settime", 0, 1);
            $record = reset($record);
            return ($record) ? $record->settime : false;

        } else {
            return false;
        }

    }


     /**
     * Get the alert events on this plugin
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public function getAlertEvents(){
        return false;
    }




    /**
     * Get all custom plugins for dashboard
     * @global \ELBP\Plugins\type $DB
     * @return type
     */
    public static function all(){

        global $DB;

        $return = array();
        $records = $DB->get_records("lbp_custom_plugins", array("enabled" => 1), "name ASC");

        if ($records)
        {
            foreach($records as $record)
            {
                $return[$record->id] = $record->name;
            }
        }

        return $return;

    }


}