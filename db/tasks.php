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
 * *
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

$tasks = array(

    // Run the plugin crons every minute.
    // The actual times for the plugin crons themselves are defined in the plugin, so it should be safe
    // to run this overall cron every minute.
    array(
        'classname' => 'block_elbp\task\plugin_crons',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

    // Run the garbage clean-up at midnight.
    array(
        'classname' => 'block_elbp\task\clean_up',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '0',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

    // Process the automated notifications (e.g. from things like Attendance dropping below a set value) every 5 minutes,
    // but only between midnight and 6 am, as it could be quite a lot of notifications.
    array(
        'classname' => 'block_elbp\task\process_automated_notifications',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '0-6',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),

    // Process the standard queued alerts every 15 minutes.
    array(
        'classname' => 'block_elbp\task\process_notifications',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    )
);