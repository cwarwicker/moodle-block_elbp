<?php

namespace ELBP\bc_dashboard\Tutorials;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class numberoftutorials extends \BCDB\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'sql';

    public function __construct($params = null) {
        
        $this->object = \ELBP\Plugins\Plugin::instaniate("Tutorials");
        
        // What options can they choose?
        $this->options = array(
            array('select', get_string('reportoption:count', 'block_bc_dashboard'), array('total' => get_string('total', 'block_bc_dashboard'), 'average' => get_string('average', 'block_bc_dashboard')))
        ); 
        
        parent::__construct($params);
        
    }
    
    public function get() {
                        
        $this->sql['select'] = "count(distinct {$this->alias}.id)";
        $this->sql['join'][] = "left join {lbp_tutorials} {$this->alias} on ({$this->alias}.studentid = user.id and {$this->alias}.del = 0)";
                        
    }
    
    /**
     * Aggregate the attendance/punctuality values into an average
     * @param type $results
     */
    public function aggregate($results) {
        
        $field = $this->getAliasName();
        $type = $this->getParam(0);
        $ttl = 0;
        $cnt = count($results);

        // Loop through the users
        foreach($results as $row)
        {

            $ttl += $row[$field];
        }
        
        if ($type == 'average'){
            $ttl = ($cnt > 0) ? round( ($ttl / $cnt), 2 ) : 0;
        }

        return array($field => $ttl);
        
    }

    public function call(&$results) {}

}
