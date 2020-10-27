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

function xmldb_block_elbp_install()
{
    
    global $DB;
     
    
    // Confidentiality levels
    $DB->insert_record("lbp_confidentiality", array("id" => 1, "name" => "GLOBAL"));
    $DB->insert_record("lbp_confidentiality", array("id" => 2, "name" => "RESTRICTED"));
    $DB->insert_record("lbp_confidentiality", array("id" => 3, "name" => "PRIVATE"));
    $DB->insert_record("lbp_confidentiality", array("id" => 4, "name" => "PERSONAL"));
    
    // Reporting elements
    $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => null, "getstringname" => "reports:elbp:personaltutors", "getstringcomponent" => "block_elbp"));
    $DB->insert_record("lbp_plugin_report_elements", array("pluginid" => null, "getstringname" => "reports:elbp:trafficlightstatus", "getstringcomponent" => "block_elbp"));
    
    // Create default plugin layout and group
    $layoutID = $DB->insert_record("lbp_plugin_layouts", array("name" => "Default Layout", "enabled" => 1, "isdefault" => 1));
    $DB->insert_record("lbp_plugin_groups", array("name" => "ELBP", "enabled" => 1, "ordernum" => 0, "layoutid" => $layoutID));
    
    // Create MoodleData directory
    \elbp_create_data_directory('install');
    \elbp_create_data_directory('uploads');
    
    return true;
    
}