<?php

/**
 * Class for simple HTML templating
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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