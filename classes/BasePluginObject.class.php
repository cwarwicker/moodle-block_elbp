<?php

/**
 * Abstract class to be extended by objects created by plugins
 * 
 * By which I mean, for instance, if the plugin is Targets, when looping through a user's targets it 
 * creates objects of the class "Target". That is the class what would extend this.
 * 
 * It defines some methods which we want to re-use across all of them, rather than duplicating code and 
 * getting in a mess by doing it differently in different places, mainly involving their attributes.
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
abstract class BasePluginObject {
        
    
    /**
     * Return an attribute from the $this->attributes property, which were loaded earlier
     * @param type $name
     * @param type $na
     * @return string
     */
    public function getAttribute($name, $na = false){
        
        // First check to do is see if it's an array. If it is, we will implode it to a string
        if (isset($this->attributes[$name]) && is_array($this->attributes[$name]) && !empty($this->attributes[$name])) return implode(", ", $this->attributes[$name]);
        if (isset($this->attributes[$name]) && is_array($this->attributes[$name]) && empty($this->attributes[$name]) && $na) return get_string('na', 'block_elbp');
        
        // Not an array - string/int (well...string)
        if (isset($this->attributes[$name]) && $this->attributes[$name] == '' && $na) return get_string('na', 'block_elbp');
        if (isset($this->attributes[$name])) return $this->attributes[$name];
        if ($na) return get_string('na', 'block_elbp');
        return "";
    }
        
    /**
     * Return an attribute without doing anything to it. 
     * So for eaxmple if it's an array, it'll return an array instead of imploding it
     * @param type $name
     * @return type
     */
    public function getAttributeAsIs($name){
        return (isset($this->attributes[$name])) ? $this->attributes[$name] : false;
    }
    
    /**
     * Return all the attributes
     * @return type
     */
    public function getAttributes(){
        return $this->attributes;
    }
    
    public function getStudentAttributes(){
        return $this->getAttributes();
    }
    
    
    /**
     * Load all the attributes into an array
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
     * Given an array of attributes on this plugin, set the student values for all of them for the
     * currently loaded student
     * @param type $data
     */
    protected function setSubmittedAttributes(&$data, $obj){
                
        $possibleAttributes = $obj->getElementsFromAttributeString();
                        
        $this->attributes = array();
        
        if ($possibleAttributes)
        {
            foreach($possibleAttributes as $attribute)
            {
                                
                // If we submitted something for that attribute, add it to the target object
                if (isset($data[$attribute->name])){
                    
                    if (isset($attribute->options) && $attribute->options && !is_array($data[$attribute->name])){
                        $this->attributes[$attribute->name] = array($data[$attribute->name]);
                    } else {
                        $this->attributes[$attribute->name] = $data[$attribute->name];
                    }
                    
                    unset($data[$attribute->name]);
                    
                } else {
                
                    // Matrix elements can't have the exact name, as they need the row in their name
                    // So it won't be found by doing the above, we need to check if there are any
                    // that start with that name
                    $like = false;
                    
                    foreach($data as $key => $d)
                    {
                        $explode = explode($attribute->name . "_", $key);
                        if ($explode && count($explode) > 1)
                        {
                            $this->attributes[$key] = $d;
                            $like = true;
                        }
                    }
                    
                    if ($like){
                        unset($data[$attribute->name]);
                    }
                
                }
                
            }
        }
        
        // ANything left over must be attributes from hooks
        foreach($data as $att => $val)
        {
            $this->attributes[$att] = $val;
        }        
                
    }
    
    /**
     * Having uploaded any file attributes to the /tmp/ directory, we now want to move them
     * to a proper directory so they don't get deleted
     * @param type $defaultAttributes
     */
    protected function moveTmpUploadedFiles($defaultAttributes, $obj){
                
        global $CFG;
        
        $result = true;
        
        if ($defaultAttributes)
        {
            foreach($defaultAttributes as $attribute)
            {
                if ($attribute->type == 'File')
                {
                    $value = (isset($this->attributes[$attribute->name])) ? $this->attributes[$attribute->name] : false;
                    if ($value)
                    {
                        
                        // Current tmp file
                        $tmpFile = $CFG->dataroot . '/ELBP/' . $value;
                       
                        // Create directory
                        if ($obj->createDataDirectory( $this->getID() )){
                        
                            $explode = explode("/", $value);
                            $value = end($explode);
                            
                            $newFile = $CFG->dataroot . '/ELBP/' . $obj->getName() . '/' . $this->getID() . '/' . $value;
                            
                            if (\rename($tmpFile, $newFile)){
                                
                                $this->attributes[$attribute->name] = $obj->getName() . '/' . $this->getID() . '/' . $value;
                                
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
                
        return $result;
        
    }
    
    
}