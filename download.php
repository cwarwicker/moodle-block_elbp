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
 * @copyright   2011-2017 Bedford College, 2017 onwards Conn Warwicker
 * @author      Conn Warwicker <conn@cmrwarwicker.com>
 * @link        https://github.com/cwarwicker/moodle-block_elbp
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Originally developed at Bedford College, now maintained by Conn Warwicker
 *
 */

require_once '../../config.php';
require_once $CFG->dirroot . '/lib/filelib.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';
require_login();

$f = required_param('f', PARAM_TEXT);

$record = $DB->get_record("lbp_file_path_codes", array("code" => $f));
if ($record) {
    $record->path = $CFG->dataroot . DIRECTORY_SEPARATOR . 'ELBP' . DIRECTORY_SEPARATOR . $record->path;
}

if (!$record || !file_exists($record->path)){
    print_error( get_string('filenotfound', 'block_elbp') );
    exit;
}

$niceName = \elbp_get_stripped_uploaded_file_name( basename($record->path) );
\send_file($record->path, $niceName);
exit;