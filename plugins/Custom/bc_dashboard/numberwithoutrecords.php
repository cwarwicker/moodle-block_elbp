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
namespace block_elbp\df_dashboard\Custom;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

/**
 * Description of avggcse
 *
 * @author cwarwicker
 */
class numberwithoutrecords extends \block_df_dashboard\Report\Element {
    
    protected $level = 'aggregate';
    protected $type = 'function';
    
    public function __construct($params = null) {
        
        $this->options = array(
            array('select', get_string('reportoption:plugin', 'block_df_dashboard'), \block_elbp\Plugins\CustomPlugin::all()) # Plugin
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
        $this->object = new \block_elbp\Plugins\CustomPlugin($pluginID);
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
