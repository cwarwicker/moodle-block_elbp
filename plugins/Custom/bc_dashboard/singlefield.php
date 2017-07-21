<?php

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
