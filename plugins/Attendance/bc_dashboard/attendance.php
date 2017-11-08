<?php

namespace ELBP\bc_dashboard\Attendance;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class attendance extends \BCDB\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'sql';

    public function __construct($params = null) {
        
        $this->object = \ELBP\Plugins\Plugin::instaniate("Attendance");
        
        // Get types and periods for options
        $types = $this->object->getTypes();
        if ($types)
        {
            foreach($types as $key => $val)
            {
                unset($types[$key]);
                $types[$val] = $val;
            }
        }
        
        $periods = $this->object->getPeriods();
        if ($periods)
        {
            foreach($periods as $key => $val)
            {
                unset($periods[$key]);
                $periods[$val] = $val;
            }
        }
        
        // Set them into the options
        $this->options = array(
            array('select', get_string('reportoption:type', 'block_elbp'), $types),
            array('select', get_string('reportoption:period', 'block_elbp'), $periods)
        );
        
        parent::__construct($params);
        
    }
    
    public function get() {
        
        $type = $this->getParam(0);
        $period = $this->getParam(1);
        
        if ($type === false || $period === false){
            return false;
        }
        
        $this->sql['select'] = $this->alias.'.value';
        $this->sql['join'][] = "left join {lbp_att_punc} {$this->alias} on ({$this->alias}.studentid = u.id and {$this->alias}.type = ? and {$this->alias}.period = ? AND {$this->alias}.courseid IS NULL)";
        $this->sql['params'][] = $type;
        $this->sql['params'][] = $period;
                        
    }
    
    /**
     * Aggregate the attendance/punctuality values into an average
     * @param type $results
     */
    public function aggregate($results) {
        
        $field = $this->getAliasName();
        $ttl = 0;
        $cnt = count($results);

        // Loop through the users
        foreach($results as $row)
        {

            $ttl += $row[$field];
        }

        // Average
        $ttl = ($cnt > 0) ? round($ttl / $cnt, 2) : 0;

        return array($field => $ttl);
        
    }

    public function call(&$results) {}
    
}
