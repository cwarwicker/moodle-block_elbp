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
 * @copyright   2011-2017 Bedford College, 2017 onwards Conn Warwicker
 * @author      Conn Warwicker <conn@cmrwarwicker.com>
 * @link        https://github.com/cwarwicker/moodle-block_elbp
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Originally developed at Bedford College, now maintained by Conn Warwicker
 * 
 */
namespace block_elbp\df_dashboard\Comments;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class numberofcomments extends \block_df_dashboard\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'sql';

    public function __construct($params = null) {
        
        $this->object = \block_elbp\Plugins\Plugin::instaniate("Comments");
                
        // What options can they choose?
        $this->options = array(
            array('select', get_string('reportoption:type', 'block_elbp'), array('all' => get_string('all', 'block_elbp'), 'positive' => get_string('positive', 'block_elbp'), 'negative' => get_string('negative', 'block_elbp'))),
            array('select', get_string('reportoption:count', 'block_df_dashboard'), array('total' => get_string('total', 'block_df_dashboard'), 'average' => get_string('average', 'block_df_dashboard'), 'percent' => get_string('percent', 'block_df_dashboard')))
        );            
        
        parent::__construct($params);
        
    }
    
    public function get() {
        
        $status = $this->getParam(0);
        $type = $this->getParam(1);
        
        // Select will be different if we use percent
        if ($type == 'total' || $type == 'average'){        
            $this->sql['select'] = "count(distinct {$this->alias}.id)";
        } elseif ($type == 'percent'){
            $this->sql['select'] = "count(distinct {$this->alias}_2.id) as {$this->alias}_value_all, count(distinct {$this->alias}.id) as {$this->alias}_value_status, round( ( count(distinct {$this->alias}.id) / count(distinct {$this->alias}_2.id) ) * 100, 2 ) ";
        }
        
        // What are the joins we need?
        if ($status == 'positive' || $status == 'negative'){
            $positive = ($status == 'positive') ? 1 : -1;
            $this->sql['join'][] = "left join {lbp_comments} {$this->alias} on ({$this->alias}.studentid = u.id and {$this->alias}.del = 0 and {$this->alias}.positive = ?)";
            $this->sql['params'][] = $positive;
        } else {
            $this->sql['join'][] = "left join {lbp_comments} {$this->alias} on ({$this->alias}.studentid = u.id and {$this->alias}.del = 0)";
        }
        
        // If percent we need to join again to get ALL, regardless of status
        if ($type == 'percent'){
            $this->sql['join'][] = "left join {lbp_comments} {$this->alias}_2 on ({$this->alias}_2.studentid = u.id and {$this->alias}_2.del = 0)";
        }
                        
    }
    
    /**
     * Aggregate the attendance/punctuality values into an average
     * @param type $results
     */
    public function aggregate($results) {
        
        $type = $this->getParam(1);
        $field = $this->getAliasName();
        
        $ttl = 0;
        $cnt = count($results);
        
        // For percentages only
        $ttl_all = 0;
        $ttl_status = 0;
        $field_all = $field . '_all';
        $field_status = $field . '_status';

        // Loop through the users
        foreach($results as $row)
        {
            
            // Percentage is done differently
            if ($type == 'percent'){
                $ttl_all += $row[$field_all];
                $ttl_status += $row[$field_status];
            } else {            
                $ttl += $row[$field];
            }
            
        }
                
        // Average
        if ($type == 'average'){
            $ttl = ($cnt > 0) ? round( ($ttl / $cnt), 2 ) : 0;
        } elseif ($type == 'percent'){
            $ttl = ($ttl_all > 0) ? round( ($ttl_status / $ttl_all) * 100, 2 ) : 0;
        }
        
        return array($field => $ttl);
        
    }

//    public function val($obj) {
//        
//        $type = $this->getParam(1);
//        $val = parent::val($obj);
//        if ($type == 'percent'){
//            return round($val) . '%';
//        } else {
//            return $val;
//        }
//        
//    }
    
    public function call(&$results) {}

}
