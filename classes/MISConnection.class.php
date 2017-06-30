<?php

/**
 * This class is for actual MIS connections in plugins, so we can do things like get/set field mappings, etc...
 * 
 * This isn't created using an lbp_mis_connection record id, it is created using an lbp_plugin_mis record id
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
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