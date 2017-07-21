<?php

defined('MOODLE_INTERNAL') || die;

/**
 * This file contains the list of all the elements which can be used in bc_dashboard reporting, and their definitions
 * 
 * @copyright 2017 Bedford College
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