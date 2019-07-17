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
namespace ELBP\bc_dashboard\Tutorials;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

class lasttutorial extends \BCDB\Report\Element {

    protected $level = 'aggregate';
    protected $type = 'hybrid'; // This means it uses SQL to get the data, but for each indivudla user row, we want to run a function on that data
    protected $datatype = 'string';

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

        // If it's already in date format, just return it
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)){
          return $value;
        }

        return ($value > 0) ? date(self::DATE_FORMAT, $value) : '-';

    }

    /**
     * Aggregate the values into an average
     * @param type $results
     */
    public function aggregate($results) {

        $field = $this->getAliasName();
        $max = 0;

        // Loop through the users
        foreach($results as $row)
        {

            if ($row[$field] > $max){
                $max = $row[$field];
            }
        }

        $val = ($max > 0) ? date(self::DATE_FORMAT, $max) : null;
        return array($field => $val);

    }


     public function call(&$results){

       $alias = $this->getAliasName('user');

       if ($results['users']){

         foreach($results['users'] as $key => $row)
         {

           // Convert unix timestamp to date format
           $results['users'][$key][$alias] = $this->val($row);

         }

       }


     }

}
