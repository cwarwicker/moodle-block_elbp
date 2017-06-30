<?php

/**
 * Anonymous class for on-the-fly Lambda magic
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