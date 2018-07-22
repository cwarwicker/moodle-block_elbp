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
