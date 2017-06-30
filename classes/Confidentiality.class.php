<?php

/**
 * Confidentiality class
 * 
 * This allows us to restrict access to certain things, such as tutorials, notes/concerns, etc... to only certain people
 * By default there are 3 levels in the DB:
 * 
 * 1) GLOBAL - Anyone who has access to that student's ELBP can see [x] (This includes elbpadmins and any similar roles (also guardians))
 * 2) RESTRICTED - Only Course tutors or Personal tutors of the student can see [x]. (So e.g. it would be hidden from elbpadmins)
 * 3) PRIVATE - Only the student and the staff member who set [x] can see it
 * 
 * In theory insitituions can add their own levels as well, but they would have to be defined in the code here.
 * If I am storing them in the DB, why can't they just define them in the DB instead of the code? Seems stupid..
 * Suppose it makes sense, as they would still have to be defined in the code for the permissions.
 * Stop talking to yourself.
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

define("ELBP_CONFIDENTIALITY_GLOBAL", 1);
define("ELBP_CONFIDENTIALITY_RESTRICTED", 2);
define("ELBP_CONFIDENTIALITY_PRIVATE", 3);
define("ELBP_CONFIDENTIALITY_PERSONAL", 4);

class Confidentiality {
        
    private $levels = array();
    
    /**
     * Construct the object and get all the confidentiality levels from the DB
     * @global \ELBP\type $DB
     */
    public function __construct() {
        
        global $DB;
        
        $records = $DB->get_records("lbp_confidentiality", null, "id ASC");
        $return = array();
        
        if ($records)
        {
 
            foreach($records as $record)
            {
                $return[$record->id] = $record->name;
            }

        }
        
        $this->levels = $return;
                    
    }
    
    /**
     * Get the defined levels
     * @return type
     */
    public function getLevels(){
        return $this->levels;
    }
    
    
    /**
     * Does the logged in user meet a given confidentiality requirement?
     * @global type $ELBP
     * @global type $USER
     * @param type $access
     * @param type $level
     * @param type $setByUserID
     * @return boolean
     */
    public function meetsConfidentialityRequirement($access, $level, $setByUserID = null){
        
        global $ELBP, $USER;
        
        // If level is supplied as an int (which it probably will be in a lot of cases actually) convert to string
        if (is_number($level)){
            $level = $this->levels[$level];
        }
        
        // If access not properly defined or level is not one of the ones in the DB, then we will say "NO" to be safe
        if (!$access || !in_array($level, $this->levels)){
            return false;
        }
        
                        
        switch($level)
        {
            
            // GLOBAL - Anyone with access to the ELBP 
            case "GLOBAL":
                if ($ELBP->anyPermissionsTrue($access)){
                    return true;
                }
            break;
            
            // RESTRICTED - Only course tutors & personal tutors. Not anyone who has an overall view, like managers
            case "RESTRICTED":
                if ($access['tutor'] == true || $access['teacher'] == true || $access['user'] == true || $access['parent'] == true){
                    return true;
                }
            break;
            
            // PRIVATE - Only the student in question and the person who set whatever it is, e.g. Tutorial, Concern, etc...
            case "PRIVATE":
                if ($access['user'] == true || $access['parent'] == true) return true;
                if (!is_null($setByUserID) && $setByUserID == $USER->id) return true;
            break;
            
            // Personal - Only the student in question and their personal tutors. NOT PARENTS/GUARDIANS
            case "PERSONAL":
                if ($access['user'] == true || $access['tutor'] == true) return true;
            break;
            
            // If you add any custom ones in, you can define them here
            
            
        }
        
        return false;
        
        
    }
    
    /**
     * Get all levels defined in DB
     * @global type $DB
     * @return type
     */
    public function getAllLevels(){
        
        global $DB;
        return $DB->get_records("lbp_confidentiality", null, "id ASC");
        
    }
    
    
    public static function getHelpString($level = false){
        
        global $ELBP;
        
        $strings = array();
        
        // Is Parent Portal installed? - Not a block so can only base this on if ELBP plugin is installed
        if ($ELBP->getPlugin("elbp_portal"))
        {
         
            $strings[ELBP_CONFIDENTIALITY_GLOBAL] = get_string('confidentiality:global:help:pp', 'block_elbp');
            $strings[ELBP_CONFIDENTIALITY_RESTRICTED] = get_string('confidentiality:restricted:help:pp', 'block_elbp');
            $strings[ELBP_CONFIDENTIALITY_PRIVATE] = get_string('confidentiality:private:help:pp', 'block_elbp');
            $strings[ELBP_CONFIDENTIALITY_PERSONAL] = get_string('confidentiality:personal:help:pp', 'block_elbp');

            }
        else
        {
            
            $strings[ELBP_CONFIDENTIALITY_GLOBAL] = get_string('confidentiality:global:help', 'block_elbp');
            $strings[ELBP_CONFIDENTIALITY_RESTRICTED] = get_string('confidentiality:restricted:help', 'block_elbp');
            $strings[ELBP_CONFIDENTIALITY_PRIVATE] = get_string('confidentiality:private:help', 'block_elbp');
            $strings[ELBP_CONFIDENTIALITY_PERSONAL] = get_string('confidentiality:personal:help', 'block_elbp');
            
        }
        
        if ($level)
        {
            if (isset($strings[$level]))
            {
                return $strings[$level];
            }
            else
            {
                return '';
            }
        }
        else
        {
            return implode("\n\n<br><br>", $strings);
        }
        
    }
    
    
}