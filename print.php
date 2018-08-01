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

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

if (!isset($_SESSION['pp_user'])){
    require_login();
}

$PAGE->set_context( context_course::instance(SITEID) );

$pluginID = required_param('plugin', PARAM_INT);
$objectID = required_param('object', PARAM_TEXT);
$studentID = optional_param('student', null, PARAM_INT);
$type = optional_param('type', null, PARAM_TEXT);
$custom = optional_param('custom', false, PARAM_INT);

$ELBP = ELBP\ELBP::instantiate();
$DBC = new ELBP\DB();

$string = $ELBP->getString();

$plugin = $ELBP->getPluginByID($pluginID, $custom);

if ($plugin)
{
    $plugin->printOut($objectID, $studentID, $type);
}
else
{
    echo $string['invalidplugin'];
}