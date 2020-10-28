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
 * This scheduled task runs the cron for each installed plugin.
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

defined('MOODLE_INTERNAL') or die();

require_once($CFG->dirroot . '/blocks/elbp/lib.php');

class plugin_crons extends \core\task\scheduled_task
{

    /**
     * Get the name of the task
     * @return type
     */
    public function get_name() {
        return get_string('task:plugin_crons', 'block_elbp');
    }

    /**
     * Execute the clean up
     * @global type $DB
     */
    public function execute() {

        $ELBP = \block_elbp\ELBP::instantiate();

        $plugins = $ELBP->getPlugins();
        if ($plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->isCronEnabled()) {
                    mtrace("Processing cron for plugin ({$plugin->getTitle()})");
                    $plugin->cron();
                }
            }
        }

    }

}