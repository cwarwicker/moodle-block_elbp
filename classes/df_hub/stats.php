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
 * This file contains stats to be included in the df_hub statistics display.
 *
 * @package     block_elbp
 * @copyright   2011-2017 Bedford College, 2017 onwards Conn Warwicker
 * @author      Conn Warwicker <conn@cmrwarwicker.com>
 * @link        https://github.com/cwarwicker/moodle-block_elbp
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Originally developed at Bedford College, now maintained by Conn Warwicker
 *
 */
defined('MOODLE_INTERNAL') or die();

require_once($CFG->dirroot . '/blocks/elbp/lib.php');

// Count Additional Support Sessions
$stats['addsup'] = $DB->count_records("lbp_add_sup_sessions", array("del" => 0));

// Count Attachments
$stats['attachments'] = $DB->count_records("lbp_attachments", array("del" => 0));

// COunt Challenges
$stats['challenges'] = $DB->count_records("lbp_user_challenges", array("del" => 0));

// Count Comments
$stats['comments'] = $DB->count_records("lbp_comments", array("del" => 0));

// Count Course Reports
$stats['coursereports'] = $DB->count_records("lbp_course_reports", array("del" => 0));

// Count Periodical Course Reports
$stats['periodicalcoursereports'] = $DB->count_records("lbp_termly_creports", array("del" => 0));

// COunt Targets
$stats['targets'] = $DB->count_records("lbp_targets", array("del" => 0));

// Count Tutorials
$stats['tutorials'] = $DB->count_records("lbp_tutorials", array("del" => 0));

// Count Custom Plugins
$stats['customplugins'] = $DB->count_records("lbp_custom_plugins");