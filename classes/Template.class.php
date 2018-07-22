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

namespace ELBP;

/**
 * 
 */
class Template {
    
    private $variables;
    private $output;
    
    /**
     * Construct the template and set the global variables all templates will need access to
     * @global type $CFG
     * @global type $OUTPUT
     * @global type $USER
     */
    public function __construct() {
        
        global $CFG, $OUTPUT, $USER;
        $this->variables = array();
        $this->output = '';
        $this->set("string", get_string_manager()->load_component_strings('block_elbp', $CFG->lang, true));
        $this->set("CFG", $CFG);
        $this->set("OUTPUT", $OUTPUT);
        $this->set("USER", $USER);
        
    }
    
    
    /**
     * Set a variable to be used in the template
     * @param type $var
     * @param type $val
     * @return \ELBP\Template
     */
    public function set($var, $val)
    {
        $this->variables[$var] = $val;
        return $this;
    }
    
    /**
     * Get all variables set in the template
     * @return type
     */
    public function getVars()
    {
        return $this->variables;
    }
    
    /**
     * Load a template file
     * @param type $template
     * @return type
     * @throws \ELBP\ELBPException
     */
    public function load($template)
    {
                
        $this->output = ''; # Reset output
                        
        if (!file_exists($template)) throw new \ELBP\ELBPException( get_string('template', 'block_elbp'), get_string('filenotfound', 'block_elbp'), $template, get_string('programming:createfileorchangepath', 'block_elbp'));
        if (!empty($this->variables)) extract($this->variables);
                
        flush();
        ob_start();
            include $template;
        $output = ob_get_clean();
        
        $this->output = $output;
        return $this->output;        
        
    }
    
    /**
     * Echo the template file
     */
    public function display()
    {
        echo $this->output;
    }
    
}