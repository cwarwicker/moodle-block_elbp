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
            return implode("\n\n", $strings);
        }
        
    }
    
    
}