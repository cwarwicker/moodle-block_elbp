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
namespace ELBP\bc_dashboard\Custom;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class singlefield extends \BCDB\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'function';
    
    public function __construct($params = null) {
        
        $this->options = array(
            array('select', get_string('reportoption:plugin', 'block_bc_dashboard'), \ELBP\Plugins\CustomPlugin::all()), # Plugin
            array('select', get_string('reportoption:fields', 'block_bc_dashboard'), array()), # Fields to choose from
            array('select', get_string('reportoption:count', 'block_bc_dashboard'), array('total' => get_string('total', 'block_bc_dashboard'), 'average' => get_string('average', 'block_bc_dashboard')))
        );
        
        parent::__construct($params);
        
    }
    
    public function refreshOptions(){
        
        // Plugin
        $pluginID = $this->getParam(0);
        $plugin = new \ELBP\Plugins\CustomPlugin($pluginID);
        if (!$plugin->isValid()){
            return false;
        }
        
        // Possible fields
        $fieldsArray = array();
        $fields = $plugin->getAttributesForDisplay();
        if ($fields){
            foreach($fields as $field){
                if ($field->type !== 'Description'){
                    $fieldsArray[$field->id] = $field->name;
                }
            }
        }
        
        // Set the array of field IDs and names into the options
        $return = array();
        $return[1] = $fieldsArray; # Fields to choose from
        
        $this->options[1][2] = $fieldsArray;
        
        return $return;
        
    }
    
    public function get(){}
    
    /**
     * Get the latest update of all the users' latests updates
     * @param type $results
     * @return type
     */
    public function aggregate($results) {
        
        $type = $this->getParam(2);
        $field = $this->getAliasName();
        
        $ttl = 0;
        $cnt = count($results);

        // Loop through the users
        foreach($results as $row)
        {
            $ttl += $row[$field];
        }

        // Total
        if ($type == 'total'){
            $value = $ttl;
        }
        // Average
        elseif ($type == 'average'){
            $value = ($cnt > 0) ? round($ttl / $cnt, 2) : 0;
        } else {
            $value = null;
        }

        return array($field => $value);
        
    }

    /**
     * Get the latest update for each user
     * @param type $results
     */
    public function call(&$results) {                
        
        $pluginID = $this->getParam(0);
        $this->object = new \ELBP\Plugins\CustomPlugin($pluginID);
        if (!$this->object) return false;
        
        $structure = $this->object->getStructure();
        $alias = $this->getAliasName();
               
        $fieldParam = $this->getParam(1);
        $type = $this->getParam(2);
        
        $field = $this->object->getAttributeNameFromID($fieldParam);
        
        if ($results['users'])
        {
            foreach($results['users'] as $key => $row)
            {
                                
                $ttl = 0;
                $this->object->loadStudent($row['id']);
                
                if ($structure == 'multi' || $structure == 'incremental')
                {
                    $items = $this->object->getMultiItems();
                    $cnt = count($items);
                    
                    if ($items)
                    {
                        foreach($items as $item)
                        {
                            
                            $this->object->loadAttributes($item->id);
                            $attributes = $this->object->getStudentAttributes();
                            
                            if (isset($attributes[$field])){
                                $ttl += trim($attributes[$field]);
                            }
                            
                        }
                    }
                    
                    if ($type == 'total'){
                        $results['users'][$key][$alias] = $ttl;
                    } elseif ($type == 'average'){
                        $results['users'][$key][$alias] = ($cnt > 0) ? round( $ttl / $cnt, 2 ) : 0;
                    }
                    
                    
                }
                else
                {
                    $results['users'][$key][$alias] = null;
                }
                
            }
        }
        
    }

}
