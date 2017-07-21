<?php

/**
 * Handle Moodle events, such as course enrolments/unenrolments and what that means for the elbp data
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

//
//$handlers = array (
//    'user_enrolled' => array (
//        'handlerfile'      => '/blocks/elbp/lib.php',
//        'handlerfunction'  => 'event_course_user_enrolled',
//        'schedule'         => 'cron',
//        'internal'         => 1,
//    ),
// 
//    'user_unenrolled' => array (
//        'handlerfile'      => '/blocks/elbp/lib.php',
//        'handlerfunction'  => 'event_course_user_unenrolled',
//        'schedule'         => 'cron',
//        'internal'         => 1,
//    ),
//    
//    'groups_member_added' => array (
//        'handlerfile'      => '/blocks/elbp/lib.php',
//        'handlerfunction'  => 'event_group_user_added',
//        'schedule'         => 'instant',
//        'internal'         => 1,
//    ),
//    
//    'groups_member_removed' => array (
//        'handlerfile'      => '/blocks/elbp/lib.php',
//        'handlerfunction'  => 'event_group_user_removed',
//        'schedule'         => 'instant',
//        'internal'         => 1,
//    ),
//    
//);


$observers = array(
    
    array(
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'block_elbp_observer::eblp_user_enrolment',
        'internal'    => false,
    ),
    
    array(
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'block_elbp_observer::eblp_user_unenrolment',
        'internal'    => false,
    ),
    
    array(
        'eventname'   => '\core\event\group_member_added',
        'callback'    => 'block_elbp_observer::elbp_group_member_added',
        'internal'    => false,
    ),
    
    array(
        'eventname'   => '\core\event\group_member_removed',
        'callback'    => 'block_elbp_observer::elbp_group_member_removed',
        'internal'    => false,
    ),
);