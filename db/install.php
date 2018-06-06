<?php

/**
 * Install default settings that aren't really relevant to one specific plugin
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
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