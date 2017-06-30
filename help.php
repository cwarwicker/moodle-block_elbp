<?php
/**
 * Display a help file
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 */

require_once '../../config.php';
$f = required_param('f', PARAM_TEXT);
$file = $CFG->dirroot . '/blocks/elbp/man/' . $f . '.html';
if (file_exists($file)){
    define('ELBP', true);
    include $CFG->dirroot . '/blocks/elbp/man/_head.html';
    include $file;
    include $CFG->dirroot . '/blocks/elbp/man/_foot.html';
} else {
    print_error( get_string('filenotfound', 'block_elbp') );
}

exit;