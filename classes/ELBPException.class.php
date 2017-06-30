<?php

/**
 * ELBP Exception class for handling errors
 * 
 * This should be used a lot more and exceptions dealt with properly, at the moment it's hit and miss
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
class ELBPException extends \Exception {
    
    protected $context; # This is not a Moodle context, it's an english word/phrase to define the context in which the error occured, e.g "Plugin"
    protected $expected; # This is used to print out what was expected to occur/be passed in, which would not have led to the exception
    protected $recommended; # The recommended response to seeing this exception. E.g. "Programming error - contact site developer." Or something along those lines.
    
    /**
     * 
     * @param type $context
     * @param type $message
     * @param type $expected
     * @param type $recommended
     */
    public function __construct($context, $message, $expected = null, $recommended = null) {
        $this->context = $context;
        $this->expected = $expected;
        $this->recommended = $recommended;
        parent::__construct($message);
    }
    
    public function getContext(){
        return $this->context;
    }
    
    public function getExpected(){
        return $this->expected;
    }
    
    public function getRecommended(){
        return $this->recommended;
    }


    /**
     * Get the full exception message in the format we want
     * @return string
     */
    public function getException(){
        
        global $CFG;
        
        $output = "";
        $output .= "<div class='elbp_err_box'>";
        $output .= "<h1>" . get_string('elbpexception', 'block_elbp') . "</h1>";
        $output .= "<h2>[" . $this->getContext() . "]</h2><br>";
        $output .= "<em>".$this->getMessage()."</em><br>";
        
        if (!is_null($this->getExpected())){
            $output .= "<br>";
            $output .=  "<strong>".get_string('expected', 'block_elbp') . "</strong> - " . $this->getExpected();
        }
        
        if (!is_null($this->getRecommended())){
            $output .= "<br>";
            $output .= "<strong>".get_string('recommended', 'block_elbp')."</strong> - " . $this->getRecommended();
        }
        
        $output .= "<br><br>";
        
        // If in max debug mode, show backtrace
        if ($CFG->debug >= 32767)
        {
            $debugtrace = debug_backtrace();
            if ($debugtrace)
            {
                foreach($debugtrace as $trace)
                {
                    $file = (isset($trace['file'])) ? $trace['file'] : '?';
                    $line = (isset($trace['line'])) ? $trace['line'] : '?';
                    $output .= "<div class='notifytiny' style='text-align:center !important;'>{$file}:{$line}</div>";
                }
            }
        }
                
        $output .= "</div>";
        return $output;
    }
    
}