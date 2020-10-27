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

class Anon {
    
    /**
     * Call dynamically created methods
     * @param type $name
     * @param type $arguments
     * @return type
     */
    public function __call($name, $arguments) {
        if (isset($this->$name) && $this->$name instanceof Closure){
            return call_user_func_array($this->$name, $arguments);
        }
    }
    
    /**
     * Print out info on object
     * @return string
     */
    public function __toString() {
        
        $properties = get_class_vars( get_class($this) );
        $output = "";
        
        if ($properties)
        {
            foreach($properties as $prop => $val)
            {
                $output .= $prop . ": " . $val . "<br>";
            }
        }
        
        return $output;
        
    }
    
}