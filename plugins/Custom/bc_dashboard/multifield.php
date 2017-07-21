<?php

namespace ELBP\bc_dashboard\Custom;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class multifield extends \BCDB\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'function';
    
    public function __construct($params = null) {
       
        $this->options = array(
            array('select', get_string('reportoption:plugin', 'block_bc_dashboard'), \ELBP\Plugins\CustomPlugin::all()), # Plugin
            array('multiselect', get_string('reportoption:fields', 'block_bc_dashboard'), array()), # Fields to combine
            array('select', get_string('reportoption:groupby', 'block_bc_dashboard'), array()), # Field to group by
            array('text', get_string('reportoption:groupbyvalue', 'block_bc_dashboard'), ''), # Value to group by
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
        $return[1] = $fieldsArray; # Fields to combine
        $return[2] = $fieldsArray; # Field to group by
        
        // Also set into the current object for when we have done this through the edit page
        $this->options[1][2] = $fieldsArray;
        $this->options[2][2] = $fieldsArray;
        
        return $return;
        
    }
    
    public function get(){}
    
    /**
     * Get the latest update of all the users' latests updates
     * @param type $results
     * @return type
     */
    public function aggregate($results) {
                
        $type = $this->getParam(4);
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
                
        // Load object
        $pluginID = $this->getParam(0);
        $this->object = new \ELBP\Plugins\CustomPlugin($pluginID);
        if (!$this->object) return false;
        
        $alias = $this->getAliasName();
               
        $fieldParams = $this->getParam(1);
        $groupByParam = $this->getParam(2);
        $groupByValue = $this->getParam(3);
        $type = $this->getParam(4);
                
        
        $fields = array();
              
        // Get the names of the fields, since we would have passed in ids
        if ($fieldParams)
        {
            foreach($fieldParams as $field)
            {
                $fields[$field] = $this->object->getAttributeNameFromID($field);
            }
        }
        
        
        // Then make sure the gropuby field exists as well
        $groupBy = $this->object->getAttributeNameFromID($groupByParam);
        
                
        // Now loop the users
        if ($results['users'])
        {
            foreach($results['users'] as $key => $row)
            {
                
                $this->object->loadStudent($row['id']);
                                
                $ttl = 0;
                $fieldCnt = 0;
                
                // Using the groupby filter, find the specific item we want
                $items = $this->object->getMultiItemsByAttribute($groupBy, $groupByValue);
                
                if ($items)
                {
                    
                    foreach($items as $item)
                    {

                        $this->object->loadAttributes($item->id);
                        $attributes = $this->object->getStudentAttributes();

                        // Now if we've found the item, loop through the fields and add them up for this item
                        if ($fields)
                        {
                            foreach($fields as $field)
                            {
                                
                                if (array_key_exists($field, $attributes)){
                                    $ttl += trim($attributes[$field]);
                                    $fieldCnt++;
                                }
                                
                            }
                        }
                    }
                
                }
                
                // Average
                if ($type == 'average')
                {
                    $results['users'][$key][$alias] = ($fieldCnt > 0) ? round( $ttl / $fieldCnt, 2 ) : 0;
                }
                // Total
                else
                {
                    $results['users'][$key][$alias] = $ttl;
                }
                
                
                
            }
        }
                
    }

}
