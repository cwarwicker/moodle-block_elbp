<?php

namespace ELBP\bc_dashboard\Custom;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class numberwithoutrecords extends \BCDB\Report\Element {
    
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
     * Add up the users who do have records
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
     * Check if the user has any records for this plugin
     * @param type $results
     */
    public function call(&$results) {
        
        // Load object
        $pluginID = $this->getParam(0);
        $this->object = new \ELBP\Plugins\CustomPlugin($pluginID);
        if (!$this->object) return false;
        
        $alias = $this->getAliasName();

        if ($results['users'])
        {
            foreach($results['users'] as $key => $row)
            {
                $this->object->loadStudent($row['id']);
                $results['users'][$key][$alias] = (!$this->object->hasRecords()) ? 1 : 0;
            }
        }
         
        
    }

}
