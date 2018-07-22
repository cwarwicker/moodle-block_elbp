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

/**
 * 
 */
class Setting
{
    
    
    /**
     * Get a setting
     * @global type $DB
     * @param type $setting The setting name
     * @param type $userID (optional) If sent, will look for a user's setting
     * @param type $pluginID (optional) If sent, will look for a setting related to that plugin
     * @param bool $custom (optional) If true, will look in the custom_plugin settings instead
     * @return type
     */
    public static function getSetting($setting, $userID=null, $pluginID=null, $custom = false)
    {
        
        global $DB;
        
        $params = array();
        $sql = array();
        
        $table = ($custom) ? "lbp_custom_plugin_settings" : "lbp_settings";
        
        $sql['main'] = "SELECT value FROM {".$table."} WHERE setting = ?";
        $params[]= $setting;
        
        // If userid is defined
        if (!is_null($userID)){
            $sql['user'] = " AND userid = ? ";
            $params[] = $userID;
        }
        else
        {
            $sql['user'] = " AND userid IS NULL ";
        }
        
        // If pluginid is defined
        if (!is_null($pluginID)){
            $sql['plugin'] = " AND pluginid = ? ";
            $params[] = $pluginID;
        }
        else
        {
            $sql['plugin'] = " AND pluginid IS NULL ";
        }
       
        
        $record = $DB->get_record_sql( implode(" ", $sql) , $params);
        
        // If userID is specified but no record is found, try again using null and see if there'a a default value
        if (!$record && !is_null($userID))
        {
            $sql['user'] = " AND userid IS NULL ";
            // Unset user param
            unset($params[1]);
            $record = $DB->get_record_sql( implode(" ", $sql) , $params);
        }
        
        // Either return the value, or false if we still can't find it
        return ($record) ? $record->value : false;
                
    }
    
    
    /**
     * Set a setting
     * @global \ELBP\type $DB
     * @param type $setting Setting name
     * @param null $value Value
     * @param type $userID (optional)
     * @param type $pluginID (optional)
     * @param bool $custom (optional)
     * @return type
     */
    static function setSetting($setting, $value, $userID = null, $pluginID = null, $custom = false)
    {
        
        global $DB;
        
        $params = array();
        
        if ($value == '') $value = null;
        
        // Check if setting already exists given these parameters
        $sql = " setting = ? ";
        $params[] = $setting;
        
        if (!is_null($userID)){
            $sql .= " AND userid = ? ";
            $params[] = $userID;
        }
        else
        {
            $sql .= " AND userid IS NULL ";
        }
        
        if (!is_null($pluginID)){
            $sql .= " AND pluginid = ? ";
            $params[] = $pluginID;
        }
        else
        {
            $sql .= " AND pluginid IS NULL ";
        }
        
        $table = ($custom) ? "lbp_custom_plugin_settings" : "lbp_settings";
        
        
        $check = $DB->get_record_select($table, $sql, $params);
        
        // If one already exists, update the value
        if ($check)
        {
            $check->value = $value;
            return $DB->update_record($table, $check);
        }
        
        // Doesn't exist, so create one
        $obj = new \stdClass();
        $obj->setting = $setting;
        $obj->value = $value;
        $obj->userid = $userID;
        $obj->pluginid = $pluginID;
                
        return $DB->insert_record($table, $obj);
        
        
    }
    
    
    
    /**
     * Delete a setting completely
     * @global \ELBP\type $DB
     * @param type $setting
     * @param null $value
     * @param type $userID
     * @param type $pluginID
     * @return type
     */
    static function deleteSetting($setting, $userID = null, $pluginID = null)
    {
        
        global $DB;
        return $DB->delete_records("lbp_settings", array("setting" => $setting, "userid" => $userID, "pluginid" => $pluginID));
                
    }
    
    
    
    
    
}