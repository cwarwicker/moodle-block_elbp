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

namespace ELBP\Plugins\Targets;
require_once $CFG->dirroot . '/blocks/elbp/plugins/Targets/Target.class.php';

class TargetSets {
    
    private $id = false;
    private $userid;
    private $name;
    private $deleted;
    protected $attributes = array();
    
    /**
     * Construct Target object based on ID
     * @param mixed $id
     */
    public function __construct($id = false) {
        
        global $DB;
        
        if ($id)
        {             
            $record = $DB->get_record("lbp_target_sets", array("id"=>$id));

            if ($record)
            {

                $this->id = $record->id;
                $this->userid = $record->userid;
                $this->name = $record->name;
                $this->deleted = $record->deleted;
                
                $this->loadAttributes();

            }

        }             
    }
    
    public function loadAttributes(){
        
        global $DB;
                
        $check = $DB->get_records("lbp_target_set_attributes", array("targetsetid" => $this->id));
        
        $this->attributes = $this->_loadAttributes($check);
                
        return $this->attributes;
        
    }
    
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
    
    public function Save()
    {
        global $CFG, $USER, $DB;
              
        $data = new \stdClass();
        $data->id = $this->id;
        $data->userid = $this->userid;
        $data->name = $this->name;
        $data->deleted = $this->deleted;
        
        if ($data->id > 0)
        {
            $DB->update_record("lbp_target_sets", $data);
            return $this->id;
        }
        else
        {
            return $DB->insert_record("lbp_target_sets", $data);
        }
    }
    
    public function SaveAttributes()
    {
        global $CFG, $USER, $DB;
        
        $data = new \stdClass();
        $data->id = $this->id;
        $data->targetsetid = $this->targetsetid;
        $data->field = $this->field;
        $data->value = $this->value;
        
        if ($this->id > 0)
        {
            $DB->update_record("lbp_target_set_attributes", $data);
            return $this->id;
        }
        else
        {
            return $DB->insert_record("lbp_target_set_attributes", $data);
        }
    }
    
    public function ClearAttributes($targetsetid)
    {
        global $DB;
        return $DB->delete_records('lbp_target_set_attributes', array('targetsetid' => $targetsetid));   
    }
    
    public function isValid(){
        return ($this->id) ? true : false;
    }
    
    public function getID(){
        return $this->id;
    }
    
    public function getAttributes(){
        return $this->attributes;
    }
    
    public function getSetTime(){
    }
    
    public function getStaffID(){
    }
    
    public function getDueDate(){
    }
    
    public function getProgress(){
    }
    
    public function getStatus(){
    }
    
    public function getName(){
        return $this->name;
    }
    
    public function getUserID(){
        return $this->name;
    }
    
    public function getDeleted(){
        return $this->userid;
    }
    
    public function getField(){
        return $this->field;
    }
    
    public function getValue(){
        return $this->value;
    }

    public function setName($name){
        $this->name = $name;
    }
    
    public function setUserID($userid){
        $this->userid = $userid;
    }
    
    public function setDeleted($deleted){
        $this->deleted = $deleted;
    }
    
    public function setTargetsetid($targetsetid){
        $this->targetsetid = $targetsetid;
    }
    
    public function setField($field){
        $this->field = $field;
    }
    
    public function setValue($value){
        $this->value = $value;
    }
    
}
