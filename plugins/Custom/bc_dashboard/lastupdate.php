<?php

namespace ELBP\bc_dashboard\Custom;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class lastupdate extends \BCDB\Report\Element {
    
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
        $max = 0;
        
        // Loop through the users
        foreach($results as $row)
        {
            $unix = ($row[$field] > 0) ? strtotime($row[$field]) : 0;
            if ($unix > $max){
                $max = $unix;
            }
        }

        $date = ($max > 0) ? date(self::DATE_FORMAT, $max) : null;
        
        return array($field => $date);
        
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

        if ($results['users'])
        {
            foreach($results['users'] as $key => $row)
            {
                $this->object->loadStudent($row['id']);
                $date = $this->object->getLastUpdated();
                $date = ($date > 0) ? date(self::DATE_FORMAT, $date) : null;
                $results['users'][$key][$alias] = $date;
            }
        }
         
        
    }

}
