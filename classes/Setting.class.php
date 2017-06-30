<?php

/**
 * Class for the Settings
 * 
 * This could be global settings set by the admin, or individual settings for particular users and/or plugins
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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