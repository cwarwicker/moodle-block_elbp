<?php
/**
 * Preview an attributes form before saving it
 *
 * Based on the attributes submitted for a plugin, produce a preview of what that form will look like.
 * 
 * Note: This isn't  used any more, not with the new attributes system, so can be deleted at some point
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
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

$s = required_param('s', PARAM_TEXT);
$t = optional_param('t', get_string('blockconfig:plugintitle', 'block_elbp'), PARAM_TEXT);

$attributes = unserialize(urldecode($s));
$t = urldecode($t);

$ELBP = \ELBP\ELBP::instantiate( array("load_plugins" => false) );
$FORM = new \ELBP\ELBPForm();
$FORM->loadStudentID($USER->id); # As an example, load ourselves as the student

$PAGE->set_context( context_course::instance(SITEID) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp/preview.php?s='.$s);
$PAGE->set_title( get_string('formpreview', 'block_elbp') );
$PAGE->set_heading( $ELBP->getELBPMyName() );
$PAGE->set_cacheable(true);
$ELBP->loadCSS();
$ELBP->loadJavascript();

$PAGE->navbar->add( get_string('formpreview', 'block_elbp') , null, navigation_node::TYPE_COURSE);

echo $OUTPUT->header();

echo $OUTPUT->heading( get_string('formpreview', 'block_elbp') );

echo "<p>".get_string('formpreview:desc', 'block_elbp')."</p>";
echo "<br>";

if ($attributes)
{

    echo "<table>";
   
    echo "<tr><th style='border:1px solid #000;' class='elbp_centre' colspan='2'>{$t}</th></tr>";
    
    foreach($attributes as $attribute)
    {
        
        if (!isset($attribute['options'])){
            $attribute['options'] = false;
        }

        echo "<tr>";
            echo "<td style='border:1px solid #000;'>{$attribute['name']}</td>";
            echo "<td style='border:1px solid #000;'>".$FORM->convertDataIntoFormElement($attribute['name'], $attribute['type'], $attribute['options'], "", $attribute['validation'], $attribute['default'])."</td>";
        echo "</tr>";
        
    }
    
    echo "</table>";

}

echo $OUTPUT->footer();
