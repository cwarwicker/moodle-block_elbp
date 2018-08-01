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

class PluginLayout
{

    private $id = false;
    private $name;
    private $enabled;
    private $isDefault;
    private $groups = array();
    
    public function __construct($id = false){
        
        global $DB;
        
        if ($id){
            
            $record = $DB->get_record("lbp_plugin_layouts", array("id" => $id));
            if ($record){
                
                $this->id = $record->id;
                $this->name = $record->name;
                $this->enabled = $record->enabled;
                $this->isDefault = $record->isdefault;
                
            }
            
        }
        
    }
    
    public function isValid(){
        return ($this->id !== false);
    }
    
    public function getID(){
        return $this->id;
    }
    
    public function getName(){
        return $this->name;
    }
    
    public function isEnabled(){
        return ($this->enabled == 1);
    }
    
    public function isDefault(){
        return ($this->isDefault == 1);
    }
    
    public function setName($name){
        $this->name = $name;
        return $this;
    }
    
    public function setEnabled($val){
        $this->enabled = $val;
        return $this;
    }
    
    public function setDefault($val){
        $this->isDefault = $val;
        return $this;
    }
    
    /**
     * Save layout record
     * @global \ELBP\type $DB
     * @return type
     */
    public function save(){
        
        global $DB;
        
        if ($this->isValid()){
            
            $obj = new \stdClass();
            $obj->id = $this->id;
            $obj->name = $this->name;
            $obj->enabled = $this->enabled;
            $obj->isdefault = $this->isDefault;
            $DB->update_record("lbp_plugin_layouts", $obj);
            
        } else {
            
            $obj = new \stdClass();
            $obj->name = $this->name;
            $obj->enabled = $this->enabled;
            $obj->isdefault = $this->isDefault;
            $this->id = $DB->insert_record("lbp_plugin_layouts", $obj);
            
        }
        
        // Now the groups
        if ($this->groups)
        {
            
            foreach($this->groups as $orderNum => $group)
            {
                
                // Update
                if ($group->id > 0)
                {
                    $obj = new \stdClass();
                    $obj->id = $group->id;
                    $obj->name = $group->name;
                    $obj->enabled = $group->enabled;
                    $obj->layoutid = $this->id;
                    $obj->ordernum = $orderNum;
                    $DB->update_record("lbp_plugin_groups", $obj);
                }
                
                // Insert
                else
                {
                    $obj = new \stdClass();
                    $obj->name = $group->name;
                    $obj->enabled = $group->enabled;
                    $obj->layoutid = $this->id;
                    $obj->ordernum = $orderNum;
                    $group->id = $DB->insert_record("lbp_plugin_groups", $obj);
                }
                
                
                // Group Plugins
                
                // Wipe plugins from this group first, then add the ones we selected
                $DB->delete_records("lbp_plugin_group_plugins", array("groupid" => $group->id));
                $DB->delete_records("lbp_custom_plugin_grp_plugin", array("groupid" => $group->id));
                        
                if ($group->pluginIDs)
                {
                    foreach($group->pluginIDs as $key => $pluginID)
                    {
                                                
                        // Normal Plugin
                        if (strpos($pluginID, 'c') === false)
                        {
                            $obj = new \stdClass();
                            $obj->pluginid = $pluginID;
                            $obj->groupid = $group->id;
                            $obj->ordernum = $key;
                            $DB->insert_record("lbp_plugin_group_plugins", $obj);
                        }
                        
                        // Custom Plugin
                        else
                        {
                            
                            
                            $obj = new \stdClass();
                            $obj->pluginid = ltrim($pluginID, 'c');
                            $obj->groupid = $group->id;
                            $obj->ordernum = $key;
                            $DB->insert_record("lbp_custom_plugin_grp_plugin", $obj);
                            
                        }
                        
                    }
                    
                }     
                
            }
            
        }

        return $this->id;
        
    }
    
    /**
     * Add a group to the layout
     * @param type $id
     * @param type $name
     * @param type $enabled
     */
    public function addGroup($id, $name, $enabled, $plugins){
        
        $obj = new \stdClass();
        $obj->id = $id;
        $obj->name = $name;
        $obj->enabled = $enabled;
        $obj->layoutid = $this->id;
        $obj->pluginIDs = $plugins;
        
        $this->groups[] = $obj;
        
    }
    
    /**
     * Get groups
     * @param type $onlyEnabled
     * @return type
     */
    public function getGroups($onlyEnabled = false){
        
        // Load groups if not loaded yet
        if (!$this->groups){
            $this->loadGroups();
        }
        
        $return = array();
        
        if ($this->groups)
        {
            foreach($this->groups as $group)
            {
                
                if ( ($onlyEnabled && $group->enabled == 1) || !$onlyEnabled )
                {
                    $return[] = $group;
                }
                               
            }
        }
        
        return $return;
        
    }
    
    /**
     * Get a particular group from the layout
     * @param type $id
     * @return boolean
     */
    public function getGroup($id){
        
        $groups = $this->getGroups(true);
        if ($groups)
        {
            foreach($groups as $group)
            {
                if ($group->id == $id)
                {
                    return $group;
                }
            }
        }
        
        return false;
        
    }
    
    /**
     * Get all the groups on this layout
     * @global \ELBP\type $DB
     * @param type $onlyEnabled
     * @return type
     */
    public function loadGroups(){
        
        global $DB;
        
        $this->groups = $DB->get_records("lbp_plugin_groups", array("layoutid" => $this->id), "ordernum ASC");
        
        if ($this->groups)
        {
            foreach($this->groups as $group)
            {
                
                $group->plugins = array();
                
                // Find plugins in this group
                $plugins = $DB->get_records_sql("SELECT p.*, pgp.ordernum
                                                 FROM {lbp_plugins} p
                                                 INNER JOIN {lbp_plugin_group_plugins} pgp ON pgp.pluginid = p.id
                                                 WHERE pgp.groupid = ?
                                                 ORDER BY pgp.ordernum ASC", array($group->id));
                if ($plugins)
                {
                    foreach($plugins as $plugin)
                    {
                        try {
                            $obj = \ELBP\Plugins\Plugin::instaniate($plugin->name, $plugin->path);
                            $obj->ordernum = $plugin->ordernum;
                            $group->plugins[$plugin->id] = $obj;
                        } catch(\ELBP\ELBPException $e){
                            
                        }                        
                    }
                }
                
                // Find custom plugins in this group
                $plugins = $DB->get_records_sql("SELECT cp.id, cpgp.ordernum
                                                 FROM {lbp_custom_plugins} cp
                                                 INNER JOIN {lbp_custom_plugin_grp_plugin} cpgp ON cpgp.pluginid = cp.id
                                                 WHERE cpgp.groupid = ?
                                                 ORDER BY cpgp.ordernum ASC", array($group->id));
                if ($plugins)
                {
                    foreach($plugins as $plugin)
                    {
                        $obj = new Plugins\CustomPlugin($plugin->id);
                        if ($obj->isValid())
                        {
                            $obj->ordernum = $plugin->ordernum;
                            $group->plugins['c' . $plugin->id] = $obj;
                        }
                    }
                }
                
                // Order the plugins by ordernum
                uasort($group->plugins, function($a, $b){
                    return ($b->ordernum < $a->ordernum);
                });
                
            }
        }
        
       
        
        return $this->groups;
        
    }
    
    /**
     * Check to see if a plugin is on any of the groups in this layout
     * @param type $pluginID
     * @return boolean
     */
    public function isPluginInLayoutGroups($pluginID){
                
        if (!$this->groups){
            $this->loadGroups();
        }
        
        $return = false;
        
        if ($this->groups)
        {
            foreach($this->groups as $group)
            {
                if ($group->plugins)
                {
                    foreach($group->plugins as $pID => $plugin)
                    {
                        if ($pluginID == $pID)
                        {
                            $return = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        return $return;
        
    }
    
    
    /**
     * Get all the layouts
     * @global type $DB
     * @param type $onlyEnabled
     * @return type
     */
    public static function getAllPluginLayouts($onlyEnabled = false)
    {
        
        global $DB;
        
        if ($onlyEnabled){
            $layouts = $DB->get_records("lbp_plugin_layouts", array("enabled" => 1), "name ASC", "id");
        } else {
            $layouts = $DB->get_records("lbp_plugin_layouts", null, "name ASC", "id");
        }
        
        $return = array();
        
        if ($layouts)
        {
            foreach($layouts as $layout)
            {
                $return[] = new \ELBP\PluginLayout($layout->id);
            }
        }
        
        return $return;
        
    }
    
    /**
     * Get the default layout
     * @global \ELBP\type $DB
     * @return type
     */
    public static function getDefaultPluginLayout(){
        
        global $DB;
        
        $record = $DB->get_record("lbp_plugin_layouts", array("isdefault" => 1, "enabled" => 1), "id", IGNORE_MULTIPLE);
        return ($record) ? new \ELBP\PluginLayout($record->id) : false;
        
    }
    
    /**
     * Get a user's layout or the default one
     * @global \ELBP\type $DB
     * @param type $userID
     * @return \ELBP\PluginLayout
     */
    public static function getUsersLayout($userID, $courseID = false){
                
        // First check if this indivdual user has an overriden layout        
        $layoutID = \ELBP\Setting::getSetting('plugins_layout', $userID);
        $layout = new \ELBP\PluginLayout($layoutID);
        if ($layout->isValid() && $layout->isEnabled())
        {
            return $layout;
        }
        
        // Then check if the course has a layout
        if ($courseID && $courseID > 0 && $courseID <> SITEID)
        {
            $layoutID = \ELBP\Setting::getSetting('course_'.$courseID.'_plugins_layout');
            $layout = new \ELBP\PluginLayout($layoutID);
            if ($layout->isValid() && $layout->isEnabled())
            {
                return $layout;
            }
        }
        else
        {
            
            // If we didn't pass a courseID through in the URL, check all the user's courses and see if any
            // have an overriden layout. If they have more than 1 course with a layout, we will just use the
            // first one we come across, as there is no real way to make a choice between them
            $ELBPDB = new \ELBP\DB();
            $courses = $ELBPDB->getStudentsCourses($userID);
            if ($courses)
            {
                foreach($courses as $course)
                {
                    $layoutID = \ELBP\Setting::getSetting('course_'.$course->id.'_plugins_layout');
                    $layout = new \ELBP\PluginLayout($layoutID);
                    if ($layout->isValid() && $layout->isEnabled())
                    {
                        return $layout;
                    }
                }
            }
            
        }
        
        // Otherwise, just get the site default
        return \ELBP\PluginLayout::getDefaultPluginLayout();
        
    }
    
}
