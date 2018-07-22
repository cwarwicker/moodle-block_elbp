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
class MISConnection {
    
    private $id = false;
    private $name;
    private $pluginID;
    private $misID;
    
    private $custom = false;
    
    private $mapping = array();
    private $mapping_alias = array();
    private $mapping_func = array();
    
    
    /**
     * Instantiate object using id from record in lbp_plugin_mis
     * @param type $id
     */
    public function __construct($id, $custom = false) {
        
        global $DB;
        
        if ($custom){
            $table = 'lbp_custom_plugin_mis';
            $this->custom = true;
        } else {
            $table = 'lbp_plugin_mis';
        }
        
        $check = $DB->get_record($table, array("id" => $id));
        if ($check)
        {
            
            $this->id = $check->id;
            $this->name = $check->name;
            $this->pluginID = $check->pluginid;
            $this->misID = $check->misid;
            
            $this->loadMapping();
            
        }
        
    }
    
    public function isValid(){
        return ($this->id !== false) ? true : false;
    }
    
    public function getID(){
        return $this->id;
    }
    
    public function getName(){
        return $this->name;
    }
    
    public function getPluginID(){
        return $this->pluginID;
    }
    
    public function getMisID(){
        return $this->misID;
    }
    
    public function getMappings(){
        return $this->mapping;
    }
    
    /**
     * Load the mappings, so we know what fields we are using, and if there are any functions to run, or
     * aliases to use, etc...
     * @global type $DB
     */
    public function loadMapping(){
        
        global $DB;
                
        $mappings = $DB->get_records("lbp_mis_mappings", array("pluginmisid" => $this->id));
        $mapping = array();
        $alias = array();
        $func = array();
        
        if ($mappings){
            foreach($mappings as $map){
                $mapping[$map->name] = $map->field;
                $alias[$map->name] = $map->alias;
                $func[$map->name] = $map->fieldfunc;
            }
        }
        
        $this->mapping = $mapping;
        $this->mapping_alias = $alias;
        $this->mapping_func = $func;
        
    }
    
    /**
     * Get the value to put into the SELECT part of the sql query
     * E.g. SELECT
     *          START_DATE
     *          CONVERT(char(10), START_DATE, 112) as startdate
     * @param string $name
     * @return string
     */
    public function getFieldMapQuerySelect($name){
        
        $val = "";
        
        // If we have defined a function for this, use that
        if (isset($this->mapping_func[$name])){
            $val .= "{$this->mapping_func[$name]}";
        } else {
            $val .= "{$this->mapping[$name]}";
        }
        
        // If there is an alias, apply that
        if (isset($this->mapping_alias[$name])){
            $val .= " as {$this->mapping_alias[$name]}";
        }
        
        return $val;
        
        
    }
    
    /**
     * Get the value to put into the WHERE, ORDER BY, GROUP BY, etc... clauses of the sql query
     * E.g. WHERE
     *          START_DATE = :START_DATE
     *          CONVERT(char(10), START_DATE, 105) = :START_DATE
     * @param string $name
     */
    public function getFieldMapQueryClause($name){
        
        $val = "";
        
        // If we have defined a function for this, use that
        if (isset($this->mapping_func[$name])){
            $val .= "{$this->mapping_func[$name]}";
        } else {
            $val .= "{$this->mapping[$name]}";
        }
        
        return $val;
        
    }
    
    /**
     * Get an array of all fields in a format which will work in the SELECT part of the sql query
     * @param type $implode
     * @return type
     */
    public function getAllMappingsForSelect($implode = false){
        
        $fields = array();
        if ($this->mapping){
            foreach($this->mapping as $map){
                if ($map && !empty($map)){
                    $fields[] = $map;
                }
            }
        }
        
        // Loop again for functions/aliases, etc...
        if ($this->mapping){
            foreach($this->mapping as $name => $map){
                
                $field = $this->getFieldMapQuerySelect($name);
                if ($field){
                    $field = trim($field);
                    if (!in_array($field, $fields) && !empty($field)){
                        $fields[] = $field;
                    }
                }
                
            }
        }
        
        $fields = array_unique($fields);
                          
        return ($implode) ? implode(', ', $fields) : $fields;
        
    }
    
    /**
     * If the field has an alias return that, otherwise return it's standard mapping
     * This is for example in timetable when we CONVERT() the dates, in the fieldmap we want the CONVERT as it
     * goes into the query, but when building the lesson object, we need the alias to get it frmo the results
     * @param type $name
     * @param type $convertHTML
     */
    public function getFieldAliasOrMap($name, $convertHTML = false){
        
        if ($this->getFieldAlias($name, $convertHTML) !== false){
            return $this->getFieldAlias($name, $convertHTML);
        }
        
        else return $this->getFieldMap($name, $convertHTML);
        
    }
    
    /**
     * Get the field mapping for a particular field
     * @param type $name
     * @param type $convertHTML
     * @return type
     */
    public function getFieldMap($name, $convertHTML = false){
        $value = (isset($this->mapping[$name]) && !empty($this->mapping[$name])) ? $this->mapping[$name] : false;
        if ($value && $convertHTML) $value = elbp_html($value);
        return $value;
    }
    
    /**
     * Get the field function for a particular field
     * @param type $name
     * @param type $convertHTML
     * @return type
     */
    public function getFieldFunc($name, $convertHTML = false){
        $value = (isset($this->mapping_func[$name]) && !empty($this->mapping_func[$name])) ? $this->mapping_func[$name] : false;
        if ($value && $convertHTML) $value = elbp_html($value);
        return $value;
    }
    
    /**
     * Get the field alias for a particular field
     * @param type $name
     * @param type $convertHTML
     * @return type
     */
    public function getFieldAlias($name, $convertHTML = false){
        $value = (isset($this->mapping_alias[$name]) && !empty($this->mapping_alias[$name])) ? $this->mapping_alias[$name] : false;
        if ($value && $convertHTML) $value = elbp_html($value);
        return $value;
    }
 
    /**
     * Set field mapping information for a particular field
     * @global \ELBP\type $DB
     * @param type $name
     * @param type $field
     * @param type $alias
     * @param type $func
     * @return type
     */
    public function setFieldMap($name, $field, $alias = null, $func = null){
        
        global $DB;
        
        $check = $DB->get_record("lbp_mis_mappings", array("pluginmisid" => $this->id, "name" => $name));
        if ($check){
            $check->field = $field;
            $check->alias = $alias;
            $check->fieldfunc = $func;
            return $DB->update_record("lbp_mis_mappings", $check);
        } else {
            $data = new \stdClass();
            $data->pluginmisid = $this->id;
            $data->name = $name;
            $data->field = $field;
            $data->alias = $alias;
            $data->fieldfunc = $func;
            return $DB->insert_record("lbp_mis_mappings", $data);
        }
        
    }
    
    
    
    public function __toString(){
        $output = "ID: {$this->id}\n";
        $output .= "Name: {$this->name}\n";
        $output .= "PluginID: {$this->pluginID}\n";
        $output .= "MisID: {$this->misID}\n";
        $output .= "Field Mappings: " . print_r($this->mapping, true);
        return nl2br($output);
    }
    
}