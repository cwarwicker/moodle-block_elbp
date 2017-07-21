<?php

namespace ELBP\bc_dashboard\Custom;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class numberofrecords extends \BCDB\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'function';
    
    public function __construct($params = null) {
        
        $this->options = array(
            array('select', get_string('reportoption:plugin', 'block_bc_dashboard'), \ELBP\Plugins\CustomPlugin::all()) # Plugin
        );
        
        parent::__construct($params);
        
    }
    
    public function get(){}
    
    /**
     * Get the latest update of all the users' latests updates
     * @param type $results
     * @return type
     */
    public function aggregate($results) {
        
        $field = $this->getAliasName();
        $ttl = 0;

        // Loop through the users
        foreach($results as $row)
        {
            $ttl += $row[$field];
        }

        return array($field => $ttl);
        
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
        
        $structure = $this->object->getStructure();
        $alias = $this->getAliasName();

        if ($results['users'])
        {
            foreach($results['users'] as $key => $row)
            {
                $this->object->loadStudent($row['id']);
                $results['users'][$key][$alias] = ($structure == 'multi' || $structure == 'incremental') ? count($this->object->getMultiItems()) : null;
            }
        }
        
    }

}
