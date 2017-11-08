<?php

namespace ELBP\bc_dashboard\Tutorials;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class lasttutorial extends \BCDB\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'sql';

    public function __construct($params = null) {
        
        $this->object = \ELBP\Plugins\Plugin::instaniate("Tutorials");
        parent::__construct($params);
        
    }
    
    public function get() {
        $this->sql['select'] = "max({$this->alias}.tutorialdate)";
        $this->sql['join'][] = "left join {lbp_tutorials} {$this->alias} on ({$this->alias}.studentid = u.id and {$this->alias}.del = 0)";
    }
    
    /**
     * Convert the unix timestamp to a date string when we get the value
     * @param type $obj
     * @return type
     */
    public function val($obj){
        
        $value = parent::val($obj);
        return ($value > 0) ? date(self::DATE_FORMAT, $value) : null;
        
    }
    
    /**
     * Aggregate the attendance/punctuality values into an average
     * @param type $results
     */
    public function aggregate($results) {
        
        $field = $this->getAliasName();
        $max = 0;
        
        // Loop through the users
        foreach($results as $row)
        {

            if ($row->$field > $max){
                $max = $row[$field];
            }
        }
        
        $val = ($max > 0) ? date(self::DATE_FORMAT, $max) : null;
        return array($field => $val);
        
    }

    public function call(&$results) {}

}
