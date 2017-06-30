<?php
/**
 * Generic file to use when printing off anything in the ELBP
 * 
 * Won't actually print it, it'll generate a simple HTML page which you can then print.
 * 
 * Might look into PDF again in the future, but it was too erratic when I tried before.
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