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
