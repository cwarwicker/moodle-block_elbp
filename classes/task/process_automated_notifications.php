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
 * This scheduled task processes automated notifications, such as where a staff member has asked for any notifications
 * when one of their students Total Attendance drops below 75%, etc...
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

namespace block_elbp\task;

require_once $CFG->dirroot . '/blocks/elbp/lib.php';

class process_automated_notifications extends \core\task\scheduled_task
{

    /**
     * Get the name of the task
     * @return type
     */
    public function get_name(){
        return get_string('task:process_automated_notifications', 'block_elbp');
    }

    /**
     * Execute the clean up
     * @global type $DB
     */
    public function execute() {

        // Process automatic events, such as checking that attendance has not dropped below a given percentage or that a target hasn't passewd its deadline, etc...
        // This could potentially take a while, as people who have the alert enabled for a course means we will have to loop through all users in that course and then potentially loop through all their targets or similar.
        $processed = \block_elbp\Alert::processAuto();
        mtrace("Processed {$processed} automated alerts");

    }

}