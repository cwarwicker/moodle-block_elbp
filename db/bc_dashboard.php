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

defined('MOODLE_INTERNAL') || die;

$elements = array(
    
    'Attendance:attendance' => array(
        'sub' => 'Attendance',
        'file' => '/blocks/elbp/plugins/Attendance/bc_dashboard/attendance.php',
        'class' => '\ELBP\bc_dashboard\Attendance\attendance',
    ),
    
    'Comments:numberofcomments' => array(
        'sub' => 'Comments',
        'file' => '/blocks/elbp/plugins/Comments/bc_dashboard/numberofcomments.php',
        'class' => '\ELBP\bc_dashboard\Comments\numberofcomments',
    ),
    
    'Custom:lastupdate' => array(
        'sub' => 'Custom',
        'file' => '/blocks/elbp/plugins/Custom/bc_dashboard/lastupdate.php',
        'class' => '\ELBP\bc_dashboard\Custom\lastupdate',
    ),
    
    'Custom:multifield' => array(
        'sub' => 'Custom',
        'file' => '/blocks/elbp/plugins/Custom/bc_dashboard/multifield.php',
        'class' => '\ELBP\bc_dashboard\Custom\multifield',
    ),
    
    'Custom:numberofrecords' => array(
        'sub' => 'Custom',
        'file' => '/blocks/elbp/plugins/Custom/bc_dashboard/numberofrecords.php',
        'class' => '\ELBP\bc_dashboard\Custom\numberofrecords',
    ),
    
    'Custom:numberwithoutrecords' => array(
        'sub' => 'Custom',
        'file' => '/blocks/elbp/plugins/Custom/bc_dashboard/numberwithoutrecords.php',
        'class' => '\ELBP\bc_dashboard\Custom\numberwithoutrecords',
    ),
    
    'Custom:numberwithrecords' => array(
        'sub' => 'Custom',
        'file' => '/blocks/elbp/plugins/Custom/bc_dashboard/numberwithrecords.php',
        'class' => '\ELBP\bc_dashboard\Custom\numberwithrecords',
    ),
    
    'Custom:singlefield' => array(
        'sub' => 'Custom',
        'file' => '/blocks/elbp/plugins/Custom/bc_dashboard/singlefield.php',
        'class' => '\ELBP\bc_dashboard\Custom\singlefield',
    ),
    
    'Targets:numberoftargets' => array(
        'sub' => 'Targets',
        'file' => '/blocks/elbp/plugins/Targets/bc_dashboard/numberoftargets.php',
        'class' => '\ELBP\bc_dashboard\Targets\numberoftargets',
    ),
    
    'Tutorials:numberoftutorials' => array(
        'sub' => 'Tutorials',
        'file' => '/blocks/elbp/plugins/Tutorials/bc_dashboard/numberoftutorials.php',
        'class' => '\ELBP\bc_dashboard\Tutorials\numberoftutorials',
    ),
    
    'Tutorials:lasttutorial' => array(
        'sub' => 'Tutorials',
        'file' => '/blocks/elbp/plugins/Tutorials/bc_dashboard/lasttutorial.php',
        'class' => '\ELBP\bc_dashboard\Tutorials\lasttutorial',
    )
    
    
);