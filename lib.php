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

require_once $CFG->dirroot . '/blocks/elbp/ELBP.class.php';

// Find all classes in /classes and include them
foreach( glob("{$CFG->dirroot}/blocks/elbp/classes/*.class.php") as $file ){
    require_once $file;
}


/**
 * Append an element to the query string, taking into account URLs could already contain QS and/or could be in various formats
 * @param type $url
 * @param type $qs
 */
function append_query_string($url, $qs)
{

    $seperator = ( parse_url($url, PHP_URL_QUERY) == null ) ? '?' : '&amp;' ;
    return $url . $seperator . $qs;

}

/**
 * Convert a query string to an array of elements
 * @param type $qs
 */
function query_string_to_array($qs)
{
    $parse = parse_url($qs);

    if (!isset($parse['query'])) return array();

    parse_str($parse['query'], $elements);

    return $elements;
}

/**
 * Convert an array of elements to a query string
 * @param type $array
 */
function array_to_query_string($array)
{
    if (!$array) return "";

    $output = "";
    $cnt = count($array);
    $num = 0;

    foreach($array as $key => $val)
    {
        $num++;
        $output .= "{$key}={$val}";
        if ($num < $cnt) $output .= "&";
    }

    return $output;

}

/**
 * Strip an element from a query string
 * e.g. view=course&courseid=3&filterFirst=A -> STRIP "courseid" -> view=course&filterFirst=A
 * @param string $element The element to strip out
 * @param string $qs the query string
 */
function strip_from_query_string($element, $qs)
{

    $array = query_string_to_array($qs);
    if (isset($array[$element])) unset($array[$element]);

    $parts = explode("?", $qs);
    $url = $parts[0] . "?" . array_to_query_string($array);

    return $url;

}

/**
 * Run text through htmlspecialchars
 * @param string $str
 * @param bool $nl2br - Should we also run it through nl2br()?
 * @return type
 */
function elbp_html($str, $nl2br=false)
{

    if (is_array($str)){
        return implode(", ", $str);
    }

    $str = htmlspecialchars($str, ENT_QUOTES);
    if ($nl2br){
        $str = nl2br($str);
        // Now remove actual new line characters incase they are still there (has been noted in some instances)
        $str = str_replace("\n", "", $str);
        $str = str_replace("\r", "", $str);
    }
    return $str;
}

/**
 * Convert an HTML break tag back into a newline character
 * @param $str
 * @return mixed
 */
function elbp_br2nl($str){
    $str = str_replace("<br>", "\n", $str);
    return $str;
}

/**
 * Get a success message
 * @param type $msg
 * @return type
 */
function elbp_success_msg($msg)
{
    if (is_array($msg))
    {
        $output = "";
        foreach($msg as $m)
        {
            $output .= $m . "<br>";
        }
        $msg = $output;
    }
    return "<div class='elbp_success_box'>{$msg}</div>";
}

/**
 * Check if a string is empty, taking into account "0" and whitespace
 * @param type $i
 * @return boolean
 */
function elbp_is_empty($i)
{
    $i = (string)$i;
    $i = trim($i);
    if($i == '')
    {
        return true;
    }
    return false;
}

/**
 * Print a success message to screen
 * @param type $msg
 */
function elbp_print_success_msg($msg)
{
    echo elbp_success_msg($msg);
}

/**
 * Get an error message
 * @param type $msg
 * @return type
 */
function elbp_error_msg($msg)
{
    if (is_array($msg))
    {
        $output = "";
        foreach($msg as $m)
        {
            $output .= $m . "<br>";
        }
        $msg = $output;
    }
    return "<div class='elbp_err_box'>{$msg}</div>";
}

/**
 * Print the error message to screen
 * @param type $msg
 */
function elbp_print_error_msg($msg)
{
    echo elbp_error_msg($msg);
}

/**
 * Used for debugging during development, generally for AJAX requests to print things out that we can't see easily. * @global type $CFG
 * @param type $value
 */
function elbp_pn($value)
{
    global $CFG;
    $file = fopen($CFG->dirroot . '/blocks/elbp/tmp.txt', 'a');
    if ($file){
        fwrite($file, print_r($value, true));
        fwrite($file, "\n");
        fclose($file);
    }
}

/**
 * Pick a random colour to use as heading colour for plugin if none defined
 * @return string
 */
function elbp_random_colour()
{
    $array = array(
        "F53D3D",
        "F55C3D",
        "F57A3D",
        "F5993D",
        "F5B83D",
        "F5D63D",
        "F5F53D",
        "D6F53D",
        "B8F53D",
        "99F53D",
        "7AF53D",
        "5CF53D",
        "3DF53D",
        "3DF55C",
        "3DF57A",
        "3DF599",
        "3DF5B8",
        "3DF5D6",
        "3DF5F5",
        "3DD6F5",
        "3DB8F5",
        "3D99F5",
        "3D7AF5",
        "3D5CF5",
        "3D3DF5",
        "5C3DF5",
        "7A3DF5",
        "993DF5",
        "B83DF5",
        "D63DF5",
        "F53DF5",
        "F53DD6",
        "F53DB8",
        "F53D99",
        "F53D7A",
        "F53D5C"
    );
    return $array[ mt_rand(0, (count($array) - 1)) ];
}

/**
 * Convert a hex code to RGB values
 * http://css-tricks.com/snippets/php/convert-hex-to-rgb/
 * @param type $colour
 * @return boolean
 */
function elbp_hex_to_rgb( $colour ) {
        if ( $colour[0] == '#' ) {
                $colour = substr( $colour, 1 );
        }
        if ( strlen( $colour ) == 6 ) {
                list( $r, $g, $b ) = array( $colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5] );
        } elseif ( strlen( $colour ) == 3 ) {
                list( $r, $g, $b ) = array( $colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2] );
        } else {
                return false;
        }
        $r = hexdec( $r );
        $g = hexdec( $g );
        $b = hexdec( $b );
        return array( 'red' => $r, 'green' => $g, 'blue' => $b );
}

/**
 * Work out whether we should use white or black text colour for the given background colour
 * @param type $r
 * @param type $g
 * @param type $b
 * @return type
 */
function elbp_calc_font_colour($r, $g, $b)
{

    $r = $r / 255;
    $g = $g / 255;
    $b = $b / 255;

    return ( (0.213 * $r) + (0.715 * $g) + (0.072 * $b) < 0.5 ) ? '#fff': '#000' ;

}



/**
 * Strip the namespace from a class name
 * @param type $class
 */
function strip_namespace($class)
{
    return substr($class, strrpos($class, '\\') + 1);
}

/**
 * Convert a version number to a date string
 * @param type $version e.g. 2014070201
 * @return string e.g. V0.1, 2nd July 2014
 */
function elbp_convert_version($version)
{
    $parts = array();
    $parts['year'] = substr($version, 0, 4);
    $parts['month'] = substr($version, 4, 2);
    $parts['day'] = substr($version, 6, 2);
    $parts['version'] = substr($version, 8, 1) . '.' . substr($version, 9, 1);
    return $parts;
}

/**
 * Calls the core Moodle has_capability function, but only if we *can* call it
 * @param type $capability
 */
function elbp_has_capability($capability, $access){

    global $USER, $DB;

    if (!$access) return false;
    if (!isset($access['context']) || !$access['context']) return false;

    $cap = $DB->get_record("capabilities", array("name" => $capability));
    if (!$cap) return false;

    // First check if we have set a specific capability value for this user
    $userCap = $DB->get_record("lbp_user_capabilities", array("userid" => $USER->id, "capabilityid" => $cap->id));

    // We do have a user capability record, so check what the value is
    if ($userCap)
    {
        if ($userCap->value == 1) return true; // ALLOW, so return true without checking role capabilities
        elseif ($userCap->value == 0) return false; // PROHIBIT, so return false without checking role capabilities
    }

    // Loop through all our relevant contexts and if we have the capability for any of them, return true
    foreach ($access['context'] as $context){
        if (has_capability($capability, $context) ) return true;
    }

    return false;

}

/**
 * Log an action to the logs table
 * @global type $DB
 * @global type $USER
 * @param type $module
 * @param type $element
 * @param type $action
 * @param type $studentID
 * @param type $params
 */
function elbp_log($module, $element, $action, $studentID = null, $params = false)
{

    global $DB, $USER;

    $obj = new \stdClass();
    $obj->userid = $USER->id;
    $obj->module = $module;
    $obj->element = $element;
    $obj->action = $action;
    $obj->studentid = $studentID;
    $obj->time = time();

    if ( ($logID = $DB->insert_record("lbp_logs", $obj)) )
    {

        // Now params (attributes)
        if ($params)
        {

            foreach($params as $field => $value)
            {
                $obj = new \stdClass();
                $obj->logid = $logID;
                $obj->field = $field;
                $obj->value = $value;
                $DB->insert_record("lbp_log_attributes", $obj);
            }

        }

    }

}

/**
 * Get the background colour & border colour for comment boxes, depending on width
 * For now this is hard coded, but can allow admins tode fine their own colours at some point probably
 * @param type $width
 */
function elbp_get_comment_css($width)
{

    $mod = ($width % 8);

    // Use the width of the note to determine the colour, so that threading of comments creates different colours as it goes
    switch($mod)
    {
        case 0:
            $bdr = "#889995";
            $bg = "#F3FFFC";
        break;
        case 7:
            $bdr = "#A56504";
            $bg = "#FFE8C6";
        break;
        case 6:
            $bdr = "#8A0051";
            $bg = "#FFDFF2";
        break;
        case 5:
            $bdr = "#780404";
            $bg = "#FFCDCD";
        break;
        case 4:
            $bdr = "#02007E";
            $bg = "#BFBEFF";
        break;
        case 3:
            $bdr = "#005163";
            $bg = "#E4FAFF";
        break;
        case 2:
            $bdr = "#525401";
            $bg = "#96ebff";
        break;
        case 1:
            $bdr = "#5B5B5B";
            $bg = "#E7E7E7";
        break;
    }

    $css = new \stdClass();
    $css->bdr = $bdr;
    $css->bg = $bg;
    $css->mod = $mod;

    return $css;

}

/**
 * Convert a max_filesize value to an int of bytes
 * @param type $val e.g. 128M
 * @return int e.g. ..
 */
function return_bytes_from_upload_max_filesize($val)
{

    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;

    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;

}

/**
 * Convert a number of bytes into a human readable string
 * @param type $bytes
 * @param type $precision
 * @return type
 */
function convert_bytes_to_hr($bytes, $precision = 2)
{
	$kilobyte = 1024;
	$megabyte = $kilobyte * 1024;
	$gigabyte = $megabyte * 1024;
	$terabyte = $gigabyte * 1024;

	if (($bytes >= 0) && ($bytes < $kilobyte)) {
		return $bytes . ' B';

	} elseif (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
		return round($bytes / $kilobyte, $precision) . ' KB';

	} elseif (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
		return round($bytes / $megabyte, $precision) . ' MB';

	} elseif (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
		return round($bytes / $gigabyte, $precision) . ' GB';

	} elseif ($bytes >= $terabyte) {
		return round($bytes / $terabyte, $precision) . ' TB';
	} else {
		return $bytes . ' B';
	}
}

/**
 * Get the file extension from a file name
 * @param type $filename
 * @return type
 */
function elbp_get_file_extension($filename)
{
    $filename = strtolower($filename);
    $exts = explode(".", $filename);
    $n = count($exts) - 1;
    $ext = $exts[$n];
    return $ext;
}

/**
 * Get array of comment file extensions for types
 * @param type $type
 * @return type
 */
function get_common_mime_types($type = 'ALL')
{

    $office = array(

                   '.doc' => 'application/msword',
                   '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                   '.xls' => array('application/excel', 'application/vnd.ms-excel'),
                   '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                   '.ppt' => array('application/mspowerpoint', 'application/powerpoint', 'application/vnd.ms-powerpoint', 'application/x-mspowerpoint'),
                   '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            );

    $img = array(
                '.jpg .jpeg' => 'image/jpeg',
                '.png' => 'image/png',
                '.gif' => 'image/gif',
                '.bmp' => array('image/bmp', 'image/x-windows-bmp'),
            );

    $txt = array(

                '.txt' => 'text/plain',
                '.rtf' => array('text/richtext', 'application/rtf', 'application/x-rtf', 'text/rtf'),

            );

    $audio = array(

                '.mp3' => array('audio/mpeg', 'audio/mp3'),
                '.wav' => array('audio/wav', 'audio/x-wav')
            );

    $video = array(

                '.mpg' => 'video/mpeg',
                '.mov' => 'video/quicktime',
                '.avi' => array('video/avi', 'video/msvideo', 'video/x-msvideo'),
                '.flv' => 'video/x-flv',
                '.wmv' => 'video/x-ms-wmv'

            );

    $other = array(

                '.csv' => 'text/csv',
                '.zip .tgz .gz' => array('application/x-compressed', 'application/x-zip-compressed', 'application/zip', 'multipart/x-zip'),
                '.pdf' => 'application/pdf'

            );

    if ($type == 'ALL'){

        return array($office, $img, $txt, $audio, $video, $other);

    }
    else
    {

        switch($type)
        {

            case 'OFFICE':

                return $office;

            break;

            case 'IMG':

                return $img;

            break;

            case 'TXT':

                return $txt;

            break;

            case 'AUDIO':

                return $audio;

            break;

            case 'VIDEO':

                return $video;

            break;

            case 'OTHER':

                return $other;

            break;


        }

    }




}

/**
 * Generate a random string to be used for activation codes, salts, etc...
 * @param int $length
 * @param bool $alphaNumericOnly If true only a-z0-9 characters will be used
 * @return array
 */
function elbp_generate_random_string($length=10, $alphaNumericOnly=false)
{

    $chars = " _?!@#~ 23456789 _?!@#~ AzBbCcDdEeFfGgHhJjKkMmNn _?!@#~ PpQqRrSsTtUuVvWwXxYyZz _?!@#~ 23456789 _?!@#~ ";

    if ($alphaNumericOnly){
        $chars = preg_replace("/[^a-z0-9]/i", "", $chars);
    }

    $l = strlen($chars) - 1;

    $output = "";
    for($i = 0; $i < $length; $i++)
    {

        $r = mt_rand(0, $l);
        $output .= $chars[$r];

    }

    return $output;

}

/**
 * Get the file icon for a particular extension type
 * @param type $filename
 * @return string
 */
function elbp_get_file_icon($filename)
{

    $ext = pathinfo($filename, PATHINFO_EXTENSION);

    switch($ext)
    {

        case 'pdf':
            $img = 'page_white_acrobat.png';
        break;

        case 'csv':
        case 'xls':
        case 'xlsx':
            $img = 'page_white_excel.png';
        break;

        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'bmp':
        case 'gif':
            $img = 'page_white_picture.png';
        break;

        case 'ppt':
        case 'pptx':
            $img = 'page_white_powerpoint.png';
        break;

        case 'txt':
        case 'rtf':
            $img = 'page_white_text.png';
        break;

        case 'doc':
        case 'docx':
            $img = 'page_white_word.png';
        break;

        case 'zip':
        case 'gz':
        case 'tgz':
            $img = 'page_white_zip.png';
        break;

        case 'mp3':
        case 'wav':
        case 'ra':
            $img = 'music.png';
        break;

        case 'avi':
        case 'mov':
        case 'wmv':
        case 'flv':
        case 'mpg':
            $img = 'film.png';
        break;

        default:
            $img = 'page_white.png';
        break;

    }

    return $img;

}

/**
 * Convert rgb value (0-255) into hsl (0-1)
 * @param type $r
 * @param type $g
 * @param type $b
 */
function elbp_convert_rgb_hsl($r, $g, $b) {
   $var_R = ($r / 255);
   $var_G = ($g / 255);
   $var_B = ($b / 255);

   $var_Min = min($var_R, $var_G, $var_B);
   $var_Max = max($var_R, $var_G, $var_B);
   $del_Max = $var_Max - $var_Min;

   $v = $var_Max;

   if ($del_Max == 0) {
      $h = 0;
      $s = 0;
   } else {
      $s = $del_Max / $var_Max;

      $del_R = ( ( ( $var_Max - $var_R ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_G = ( ( ( $var_Max - $var_G ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_B = ( ( ( $var_Max - $var_B ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;

      if      ($var_R == $var_Max) $h = $del_B - $del_G;
      else if ($var_G == $var_Max) $h = ( 1 / 3 ) + $del_R - $del_B;
      else if ($var_B == $var_Max) $h = ( 2 / 3 ) + $del_G - $del_R;

      if ($h < 0) $h++;
      if ($h > 1) $h--;
   }

   return array($h, $s, $v);
}

/**
 * Convert hsl (0-1) into rgb (0-255)
 * @param type $h
 * @param type $s
 * @param type $v
 */
function elbp_convert_hsl_rgb($h, $s, $v)
{

    if($s == 0) {
        $r = $g = $B = $v * 255;
    } else {
        $var_H = $h * 6;
        $var_i = floor( $var_H );
        $var_1 = $v * ( 1 - $s );
        $var_2 = $v * ( 1 - $s * ( $var_H - $var_i ) );
        $var_3 = $v * ( 1 - $s * (1 - ( $var_H - $var_i ) ) );

        if       ($var_i == 0) { $var_R = $v     ; $var_G = $var_3  ; $var_B = $var_1 ; }
        else if  ($var_i == 1) { $var_R = $var_2 ; $var_G = $v      ; $var_B = $var_1 ; }
        else if  ($var_i == 2) { $var_R = $var_1 ; $var_G = $v      ; $var_B = $var_3 ; }
        else if  ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $v     ; }
        else if  ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $v     ; }
        else                   { $var_R = $v     ; $var_G = $var_1  ; $var_B = $var_2 ; }

        $r = $var_R * 255;
        $g = $var_G * 255;
        $B = $var_B * 255;
    }
    return array($r, $g, $B);

}

/**
 * Get the opposite of a rgb colour
 * @param type $r
 * @param type $g
 * @param type $b
 * @return type
 */
function elbp_convert_rgb_opposite($r, $g, $b){

    $r -= 255;
    if ($r < 0) $r = -($r);

    $g -= 255;
    if ($g < 0) $g = -($g);

    $b -= 255;
    if ($b < 0) $b = -($b);

    return array($r, $g, $b);

}

/**
 * Get the opposite of a hex colour
 * @param type $hex
 * @return type
 */
function elbp_convert_hex_opposite($hex){

    $rgb = elbp_hex_to_rgb($hex);
    $new = elbp_convert_rgb_opposite($rgb['red'], $rgb['green'], $rgb['blue']);
    $hex = elbp_rgb_to_hex($new[0], $new[1], $new[2]);
    return $hex;
}

/**
 * Convert RGB colour values into hexcode
 * @param type $r
 * @param type $g
 * @param type $b
 */
function elbp_rgb_to_hex($r, $g, $b)
{
    $hex = '#';
    $hex .= str_pad( dechex($r), 2, "0", STR_PAD_LEFT );
    $hex .= str_pad( dechex($g), 2, "0", STR_PAD_LEFT );
    $hex .= str_pad( dechex($b), 2, "0", STR_PAD_LEFT );
    return $hex;
}

/**
 * For a given hex colour, get a colour close to it to use in a gradient
 * @param type $hex
 * @return type
 */
function elbp_get_gradient_colour($hex)
{

    // Get RGB from hex code
    $rgb = elbp_hex_to_rgb($hex);

    // Convert that RGB to HSL(HSV)
    $hsl = elbp_convert_rgb_hsl($rgb['red'], $rgb['green'], $rgb['blue']);

    // If lightest is > 0.25 make it darker, otherwise make it ligher

    if ($hsl[2] >= 0.25){
        $diff = $hsl[2] * 0.9;
    } else {
        if ($hsl[2] == 0){
            $diff = 0.25;
        } else {
            $diff = $hsl[2] * 1.5;
        }
    }

    // Use the new HSL values to convert it back to RGB
    $newRGB = elbp_convert_hsl_rgb($hsl[0], $hsl[1], $diff);

    // Now finally convert that new RGB value back into a hex code
    $hex = elbp_rgb_to_hex($newRGB[0], $newRGB[1], $newRGB[2]);

    return $hex;

}

/**
 * Get the HTML bar for switching a user - list of courses & students, and list of mentees
 */
function elbp_switch_user_bar()
{

    global $CFG, $USER, $DBC; // ELBP DB class - common queries

    $id = optional_param('id', null, PARAM_INT);
    $cID = optional_param('cID', null, PARAM_TEXT);

    $output = "";
    $output .= "<div class='elbp_float_left'><form onsubmit='ELBP.switch_search_user( $(\"#search_other_student\").val() );return false;'><small>";
        $output .= get_string('switchuser', 'block_elbp') . " &nbsp;";
        $output .= "<select id='switch_user_select' onchange='ELBP.switch_users( this.value );return false;'>";
            $output .= "<option value=''></option>";

            $sel = '';
            if ($cID == 'mentees') $sel = 'selected';
            $output .= "<option value='mentees' {$sel}>".get_string('mentees', 'block_elbp')."</option>";

            $courseIDs = array();

            // List courses user has access to
            $courses = $DBC->getTeachersCourses($USER->id);
            if ($courses)
            {
                foreach($courses as $course)
                {
                    $sel = '';
                    if ($cID == $course->id) $sel = 'selected';
                    $output .= "<option value='{$course->id}' {$sel}>{$course->shortname}</option>";
                    $courseIDs[] = $course->id;
                }
            }

        $sel = '';
        $output .= "<option value='other' {$sel}>".get_string('other', 'block_elbp')."</option>";

        $output .= "</select>";
        $output .= " &nbsp;&nbsp; ";
        $output .= "<span id='switch_users_loading'></span>";

        $students = false;
        $style = 'display:none;';

        if ($cID == 'mentees')
        {
            $DBC = new ELBP\DB();
            $students = $DBC->getMenteesOnTutor($USER->id);
            $style = '';
        }
        elseif (ctype_digit($cID) && in_array($cID, $courseIDs))
        {
            $DBC = new ELBP\DB();
            $students = $DBC->getStudentsOnCourse($cID);
            $style = '';
        }

        if ($cID == 'other')
        {



        }
        else
        {

            $output .= "<select id='switch_user_users' style='{$style}' onchange='ELBP.switch_user( this.value, $(\"#switch_user_select\").val() );return false;'>";

                if ($students)
                {

                    foreach($students as $student)
                    {

                        $sel = '';
                        if ($id == $student->id) $sel = 'selected';
                        $output .= "<option value='{$student->id}' {$sel}>{$student->username} ::: ".elbp_html(fullname($student))."</option>";

                    }

                }


            $output .= "</select>";

        }


    $output .= "</small></form></div>";

//    $output .= "<div class='elbp_float_right'>";
//        $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/color_swatch.png' style='width:16px;' title='".get_string('changetheme', 'block_elbp')."' alt='".get_string('changetheme', 'block_elbp')."' />";
//    $output .= "</div>";


    return $output;

}

/**
 * Get the required JS functions for the creation of new plugin attributes
 * @global type $CFG
 * @global type $OUTPUT
 * @param type $obj
 */
function elbp_display_attribute_creation_js($obj)
{

    global $CFG, $OUTPUT;

    $FORM = new ELBP\ELBPForm();
    $FORM->load( $obj->getDefaultAttributes() );
    $elements = $FORM->getElements();

    $string = array();
    $string['mainelement'] = get_string('mainelement', 'block_elbp');
    $string['sideelement'] = get_string('sideelement', 'block_elbp');
    $string['addoption'] = get_string('addoption', 'block_elbp');
    $string['value'] = get_string('value', 'block_elbp');
    $string['name'] = get_string('name', 'block_elbp');
    $string['none'] = get_string('none', 'block_elbp');
    $string['edit'] = get_string('edit');
    $string['delete'] = get_string('delete');
    $string['nofields'] = get_string('nofieldselected', 'block_elbp');
    $string['nofieldsdesc'] = get_string('nofieldselected:desc', 'block_elbp');

    echo '
        <script>

        var ELBPFORM = {
            fields: []
        };

        ';

    $numA = 0;

    if ($elements)
    {
        foreach($elements as $element)
        {

            echo <<<JS

                var f = {};
                f.name = '{$element->getJsSafeName()}';
                f.type = '{$element->type}';
                f.display = '{$element->display}';
                f.default = '{$element->getJsSafeDefault()}';
                f.instructions = '{$element->getJsSafeInstructions()}';
                f.options = {$element->getJsSafeOptions()};
                f.validation = {$element->getJsSafeValidation()};
                f.other = {};
                {$element->getJsSafeOther()}
                ELBPFORM.fields[{$numA}] = f;

JS;

            $numA++;

        }
    }


    $elbp_image_url = 'elbp_image_url';

    echo <<<JS

        var numA = {$obj->countAttributes()};

        function resetSettingsTab(){

            $('#field_settings').html("<div class='elbp_err_box'><h2>{$string['nofields']}</h2><br><p>{$string['nofieldsdesc']}</p></div>");

        }

        function showAttributeTab(tab){

            $('#elbp_attributes_table tr').removeClass('active');
            $('.elbp_att_form_fields ul#nav li a').removeClass('active');
            $('.elbp_att_form_fields div#field_fields').hide();
            $('.elbp_att_form_fields div#field_settings').hide();
            $('.elbp_att_form_fields div#field_'+tab).show();

            if (tab == 'fields'){
                $('#fieldstab').addClass('active');
                resetSettingsTab();
            } else if (tab == 'settings'){
                $('#settingstab').addClass('active');
            }

        }

        function addAttribute(type){

            $('#elbp_attributes_table tbody').append('<tr id="attribute_row_'+numA+'"></tr>');
            $('#attribute_row_'+numA).append('<td id="attribute_row_'+numA+'_field_name"><span>-</span> <input id="attribute_row_'+numA+'_field_name_input" type="hidden" name="elementNames['+numA+']" value="" /></td>');
            $('#attribute_row_'+numA).append('<td id="attribute_row_'+numA+'_field_type"><span>'+type+'</span> <input id="attribute_row_'+numA+'_field_type_input" type="hidden" name="elementTypes['+numA+']" value="'+type+'" /></td>');
            $('#attribute_row_'+numA).append('<td id="attribute_row_'+numA+'_field_display" class="elbp_centre"><img src="{$CFG->wwwroot}/blocks/elbp/pix/icons/question.png" /> <input id="attribute_row_'+numA+'_field_display_input" type="hidden" name="elementDisplays['+numA+']" value="" /></td>');
            $('#attribute_row_'+numA).append('<td id="attribute_row_'+numA+'_field_edit_col" class="noSort"><input type="hidden" id="attribute_row_'+numA+'_field_default_input" name="elementDefault['+numA+']" value="" /><input type="hidden" id="attribute_row_'+numA+'_field_instructions_input" name="elementInstructions['+numA+']" value="" /><a href="#" onclick="editAttribute(\''+numA+'\');return false;" title="{$string['edit']}"><img src="{$elbp_image_url('t/edit')}" /></a></td>');
            $('#attribute_row_'+numA).append('<td><a href="#" onclick="removeField('+numA+');return false;" title="{$string['delete']}"><img src="{$elbp_image_url('t/delete')}" /></a></td>');

            var f = {};
            f.name = '';
            f.type = type;
            f.display = '';
            f.default = '';
            f.instructions = '';
            f.options = [];
            f.validation = [];
            f.other = {};

            ELBPFORM.fields[numA] = f;

            numA++;

        }

        function editAttribute(num){

            var attribute = ELBPFORM.fields[num];
            if (attribute == undefined) return false;

            // Create tmp options array to remove any that are now undefined, if we deleted them
            var tmpOptions = [];
            $(attribute.options).each( function(i, v){

                if (v !== undefined){
                    tmpOptions.push(v);
                }

            } );

            attribute.options = tmpOptions;

            if (attribute.other !== undefined){

                if (attribute.other.cols !== undefined){
                    // Same for any cols/rows in other
                    var tmpCols = [];
                    $(attribute.other.cols).each( function(i, v){
                        if (v !== undefined){
                            tmpCols.push(v);
                        }
                    } );
                    attribute.other.cols = tmpCols;
                }

                if (attribute.other.rows !== undefined){
                    var tmpRows = [];
                    $(attribute.other.rows).each( function(i, v){
                        if (v !== undefined){
                            tmpRows.push(v);
                        }
                    } );
                    attribute.other.rows = tmpRows;
                }

            }

            $('#elbp_attributes_table tr').removeClass('active');

            $('#elbp_att_overlay').show();

            $.post(M.cfg.wwwroot + '/blocks/elbp/js/ajaxHandler.php', {
                action: 'edit_plugin_attribute',
                params: attribute,
                num: num
            }, function(data){

                // Load into settings bit
                $('#field_settings').html(data);

                // Switch to settings tab
                showAttributeTab('settings');

                // Apply bindings
                applySettingsBindings();

                // Recalculate option numbers
                reCalculateOptionNumbers(num);

                // Select table row
                $('#attribute_row_'+num).addClass('active');

                // Hide loader
                $('#elbp_att_overlay').hide();

            });

        }


        function applySettingsBindings(){

            var num = $('#field_settings_dynamic_num').val();

            // Type
            $('#field_settings_type').unbind('change');
            $('#field_settings_type').change( function(){

                var value = $(this).val();

                // Change the value in the hidden input
                $('#attribute_row_'+num+'_field_type_input').val(value);

                // Change the display value in the table
                $('#attribute_row_'+num+'_field_type span').text(value);

                // Remove hidden inputs that could differ between types, e.g. options, other, etc...
                ELBPFORM.fields[num].options = [];
                ELBPFORM.fields[num].other = {};
                $('.attribute_row_'+num+'_field_option_inputs').remove();
                $('.attribute_row_'+num+'_field_other_cols_inputs').remove();
                $('.attribute_row_'+num+'_field_other_rows_inputs').remove();

                // CHange the value in the json object
                ELBPFORM.fields[num].type = value;

                // Reload settings, as different types will need different things
                editAttribute(num);

            } );



            // Name
            $('#field_settings_name').unbind('keyup blur');
            $('#field_settings_name').bind('keyup blur', function(){

                var value = $(this).val();

                // Change the value in the hidden input
                $('#attribute_row_'+num+'_field_name_input').val(value);

                // Change the display value in the table
                $('#attribute_row_'+num+'_field_name span').text(value);

                // Change the value in the json object
                ELBPFORM.fields[num].name = value;

            } );



            // Instructions
            $('#field_settings_instructions').unbind('keyup blur');
            $('#field_settings_instructions').bind('keyup blur', function(){

                var value = $(this).val();

                // Change the value in the hidden input
                $('#attribute_row_'+num+'_field_instructions_input').val(value);

                // Change the value in the json object
                ELBPFORM.fields[num].instructions = value;

            } );


            // Display
            $('#field_settings_display').unbind('change');
            $('#field_settings_display').change( function(){

                var value = $(this).val();

                // Change the value in the hidden input
                $('#attribute_row_'+num+'_field_display_input').val(value);

                // Change the display image in the table
                if (value == 'main'){
                    var img = 'layout_content.png';
                } else if(value == 'side'){
                    var img = 'layout_sidebar.png';
                } else {
                    var img = 'question.png';
                }

                $('#attribute_row_'+num+'_field_display img').attr('src', M.cfg.wwwroot + '/blocks/elbp/pix/icons/' + img);

                // Change the value in the json object
                ELBPFORM.fields[num].display = value;

            } );



            // Options

                // Add option
                $('.field_settings_option_add').unbind('click');
                $('.field_settings_option_add').click( function(e){

                    // Get the last option number
                    var l = $('#field_settings_options span').length
                    var lastOption = $('#field_settings_options span')[l-1];
                    var o = $($($('#field_settings_options span')[l-1]).find('a')[0]).attr('option-number');
                    o++;

                    var option = "<span>";
                    option += "<input type='text' class='normal attribute_popup_options' option-number='"+o+"' value=''> ";
                    option += "<a href='#' class='field_settings_option_delete' option-number='"+o+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/icons/delete.png'></a> ";
                    option += "<a href='#' class='field_settings_option_add' option-number='"+o+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/icons/add.png'></a>";
                    option += "<br></span>";

                    // Add to html
                    $('#field_settings_options').append(option);

                    // Add to json object
                    ELBPFORM.fields[num].options[o] = "";

                    // Add hidden input
                    $('.attribute_row_'+num+'_field_option_inputs').remove();
                    $(ELBPFORM.fields[num].options).each( function(k, v){

                        if (v !== undefined){
                            var input = "<input class='attribute_row_"+num+"_field_option_inputs field_opt_num_"+k+"' type='hidden' name='elementOptions["+num+"]["+k+"]' value='"+v+"'>";
                            $('#attribute_row_'+num+'_field_edit_col').append(input);
                        }

                    } );


                    // Bind this to the new option we just created as well
                    applySettingsBindings();

                    e.preventDefault();

                } );


                // Delete option
                $('.field_settings_option_delete').unbind('click');
                $('.field_settings_option_delete').click( function(e){

                    var o = $(this).attr('option-number');

                    // Get the last option number
                    $(this).parent().remove();

                    // Remove from json object
                    delete ELBPFORM.fields[num].options[o];

                    // Remove from hidden inputs
                    $('.attribute_row_'+num+'_field_option_inputs').remove();
                    $(ELBPFORM.fields[num].options).each( function(k, v){

                        if (v !== undefined){
                            var input = "<input class='attribute_row_"+num+"_field_option_inputs field_opt_num_"+k+"' type='hidden' name='elementOptions["+num+"]["+k+"]' value='"+v+"'>";
                            $('#attribute_row_'+num+'_field_edit_col').append(input);
                        }

                    } );

                    e.preventDefault();

                } );


                // Update option value
                $('.attribute_popup_options').unbind('keyup blur');
                $('.attribute_popup_options').bind('keyup blur', function(){

                    var value = $(this).val();
                    var o = $(this).attr('option-number');

                    // If hidden input doesn't exist, create it (e.g. default first input)
                    if ( $('.attribute_row_'+num+'_field_option_inputs.field_opt_num_'+o).length == 0 ){
                        var input = "<input class='attribute_row_"+num+"_field_option_inputs field_opt_num_"+o+"' type='hidden' name='elementOptions["+num+"]["+o+"]'>";
                        $('#attribute_row_'+num+'_field_edit_col').append(input);
                    }


                    // Change the value in the hidden input
                    $('.attribute_row_'+num+'_field_option_inputs.field_opt_num_'+o).val(value);

                    // Change the value in the json object
                    ELBPFORM.fields[num].options[o] = value;

                } );



                // Matrix - cols and rows

                // Add column
                    $('.field_settings_other_cols_add').unbind('click');
                    $('.field_settings_other_cols_add').click( function(e){

                        // Get the last option number
                        var l = $('#field_settings_cols span').length
                        var lastOption = $('#field_settings_cols span')[l-1];
                        var o = $($($('#field_settings_cols span')[l-1]).find('a')[0]).attr('col-number');
                        o++;

                        var option = "<span>";
                        option += "<input type='text' class='normal field_setting_other_cols' col-number='"+o+"' value=''> ";
                        option += "<a href='#' class='field_settings_other_cols_delete' col-number='"+o+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/icons/delete.png'></a> ";
                        option += "<a href='#' class='field_settings_other_cols_add' col-number='"+o+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/icons/add.png'></a>";
                        option += "<br></span>";

                        // Add to html
                        $('#field_settings_cols').append(option);

                        // Add to json object
                        if (ELBPFORM.fields[num].other == undefined){
                            ELBPFORM.fields[num].other = {};
                        }

                        if (ELBPFORM.fields[num].other.cols == undefined){
                            ELBPFORM.fields[num].other.cols = [];
                        }

                        ELBPFORM.fields[num].other.cols[o] = "";

                        // Add hidden input
                        $('.attribute_row_'+num+'_field_other_cols_inputs').remove();
                        $(ELBPFORM.fields[num].other.cols).each( function(k, v){

                            if (v !== undefined){
                                var input = "<input class='attribute_row_"+num+"_field_other_cols_inputs field_col_num_"+k+"' type='hidden' name='elementOther["+num+"][cols]["+k+"]' value='"+v+"'>";
                                $('#attribute_row_'+num+'_field_edit_col').append(input);
                            }

                        } );


                        // Bind this to the new option we just created as well
                        applySettingsBindings();

                        e.preventDefault();

                    });

                // Remove column
                    $('.field_settings_other_cols_delete').unbind('click');
                    $('.field_settings_other_cols_delete').click( function(e){

                        var o = $(this).attr('col-number');

                        // Get the last option number
                        $(this).parent().remove();

                        // Remove from json object
                        delete ELBPFORM.fields[num].other.cols[o];

                        // Remove from hidden inputs
                        $('.attribute_row_'+num+'_field_other_cols_inputs').remove();
                        $(ELBPFORM.fields[num].other.cols).each( function(k, v){

                            if (v !== undefined){
                                var input = "<input class='attribute_row_"+num+"_field_other_cols_inputs field_col_num_"+k+"' type='hidden' name='elementOther["+num+"][cols]["+k+"]' value='"+v+"'>";
                                $('#attribute_row_'+num+'_field_edit_col').append(input);
                            }

                        } );

                        e.preventDefault();

                    } );

                // Update column
                    $('.field_setting_other_cols').unbind('keyup blur');
                    $('.field_setting_other_cols').bind('keyup blur', function(){

                        var value = $(this).val();
                        var o = $(this).attr('col-number');

                        // Change the value in the hidden input

                        // If hidden input doesn't exist, create it (e.g. default first input)
                        if ( $('.attribute_row_'+num+'_field_other_cols_inputs.field_col_num_'+o).length == 0 ){
                            var input = "<input class='attribute_row_"+num+"_field_other_cols_inputs field_col_num_"+o+"' type='hidden' name='elementOther["+num+"][cols]["+o+"]'>";
                            $('#attribute_row_'+num+'_field_edit_col').append(input);
                        }

                        $('.attribute_row_'+num+'_field_other_cols_inputs.field_col_num_'+o).val(value);

                        // Add to json object
                        if (ELBPFORM.fields[num].other == undefined){
                            ELBPFORM.fields[num].other = {};
                        }
                        if (ELBPFORM.fields[num].other.cols == undefined){
                            ELBPFORM.fields[num].other.cols = [];
                        }


                        // Change the value in the json object
                        ELBPFORM.fields[num].other.cols[o] = value;

                    } );

                // Add row
                    $('.field_settings_other_rows_add').unbind('click');
                    $('.field_settings_other_rows_add').click( function(e){

                        // Get the last option number
                        var l = $('#field_settings_rows span').length
                        var lastOption = $('#field_settings_rows span')[l-1];
                        var o = $($($('#field_settings_rows span')[l-1]).find('a')[0]).attr('row-number');
                        o++;

                        var option = "<span>";
                        option += "<input type='text' class='normal field_setting_other_rows' row-number='"+o+"' value=''> ";
                        option += "<a href='#' class='field_settings_other_rows_delete' row-number='"+o+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/icons/delete.png'></a> ";
                        option += "<a href='#' class='field_settings_other_rows_add' row-number='"+o+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/icons/add.png'></a>";
                        option += "<br></span>";

                        // Add to html
                        $('#field_settings_rows').append(option);

                        // Add to json object
                        if (ELBPFORM.fields[num].other.rows == undefined){
                            ELBPFORM.fields[num].other.rows = [];
                        }

                        ELBPFORM.fields[num].other.rows[o] = "";

                        // Add hidden input
                        $('.attribute_row_'+num+'_field_other_rows_inputs').remove();
                        $(ELBPFORM.fields[num].other.rows).each( function(k, v){

                            if (v !== undefined){
                                var input = "<input class='attribute_row_"+num+"_field_other_rows_inputs field_row_num_"+k+"' type='hidden' name='elementOther["+num+"][rows]["+k+"]' value='"+v+"'>";
                                $('#attribute_row_'+num+'_field_edit_col').append(input);
                            }

                        } );


                        // Bind this to the new option we just created as well
                        applySettingsBindings();

                        e.preventDefault();

                    });

                // Remove row
                    $('.field_settings_other_rows_delete').unbind('click');
                    $('.field_settings_other_rows_delete').click( function(e){

                        var o = $(this).attr('row-number');

                        // Get the last option number
                        $(this).parent().remove();

                        // Remove from json object
                        delete ELBPFORM.fields[num].other.rows[o];

                        // Remove from hidden inputs
                        $('.attribute_row_'+num+'_field_other_rows_inputs').remove();
                        $(ELBPFORM.fields[num].other.rows).each( function(k, v){

                            if (v !== undefined){
                                var input = "<input class='attribute_row_"+num+"_field_other_rows_inputs field_row_num_"+k+"' type='hidden' name='elementOther["+num+"][rows]["+k+"]' value='"+v+"'>";
                                $('#attribute_row_'+num+'_field_edit_col').append(input);
                            }

                        } );

                        e.preventDefault();

                    } );

                // Update row
                    $('.field_setting_other_rows').unbind('keyup blur');
                    $('.field_setting_other_rows').bind('keyup blur', function(){

                        var value = $(this).val();
                        var o = $(this).attr('row-number');

                        // Change the value in the hidden input

                        // If hidden input doesn't exist, create it (e.g. default first input)
                        if ( $('.attribute_row_'+num+'_field_other_rows_inputs.field_row_num_'+o).length == 0 ){
                            var input = "<input class='attribute_row_"+num+"_field_other_rows_inputs field_row_num_"+o+"' type='hidden' name='elementOther["+num+"][rows]["+o+"]'>";
                            $('#attribute_row_'+num+'_field_edit_col').append(input);
                        }

                        $('.attribute_row_'+num+'_field_other_rows_inputs.field_row_num_'+o).val(value);

                        // Add to json object
                        if (ELBPFORM.fields[num].other.rows == undefined){
                            ELBPFORM.fields[num].other.rows = [];
                        }

                        // Change the value in the json object
                        ELBPFORM.fields[num].other.rows[o] = value;

                    } );



            // Default value
            $('#field_settings_default').unbind('keyup blur');
            $('#field_settings_default').bind('keyup blur', function(){

                var value = $(this).val();

                // Change the value in the hidden input
                $('#attribute_row_'+num+'_field_default_input').val(value);

                // Change the value in the json object
                ELBPFORM.fields[num].default = value;

            } );



            // Validation
            $('#field_settings_validation').unbind('change');
            $('#field_settings_validation').change( function(){

                // Remove existing hidden inputs
                $('.attribute_row_'+num+'_field_validation_inputs').remove();

                var value = $(this).val();

                $(value).each( function(k, v){

                    var input = "<input class='attribute_row_"+num+"_field_validation_inputs' type='hidden' name='elementValidation["+num+"][]' value='"+v+"'>";
                    $('#attribute_row_'+num+'_field_edit_col').append(input);

                } );

                // Change the value in the json object
                ELBPFORM.fields[num].validation = value;

            } );


            // Other
            $('.field_setting_other_max').unbind('change');
            $('.field_setting_other_max').change( function(){

                // Update hidden input, or create if not there
                if ( $('#attribute_row_'+num+'_field_other_max_input').length == 0 ){
                    var input = "<input id='attribute_row_"+num+"_field_other_max_input' type='hidden' name='elementOther["+num+"][max]' value=''>";
                    $('#attribute_row_'+num+'_field_edit_col').append(input);
                }

                $('#attribute_row_'+num+'_field_other_max_input').val( $(this).val() );


                // Update JSON object
                ELBPFORM.fields[num].other.max = $(this).val();


            } );





        }

        function removeField(num){

            $('#attribute_row_'+num).remove();
            delete ELBPFORM.fields[num];

            // Check if settings page is open for this field
            var sNum = $('#field_settings_dynamic_num').val();

            // If it is, close the settings thing
            if (num == sNum){
                showAttributeTab('fields');
            }

        }

        function reCalculateOptionNumbers(num){

            $('.attribute_row_'+num+'_field_option_inputs').each( function(k, v){

                $(this).removeAttr('class');
                $(this).addClass('attribute_row_'+num+'_field_option_inputs');
                $(this).addClass('field_opt_num_'+k);

            } );

        }


        function applySorting(id)
        {
            $('#'+id).sortable({
                containerSelector: 'tbody',
                itemSelector: 'tr',
                cancel: '.noSort',
                update: function(event, ui){
                    resortNumbers(id);
                }
            })
        }

        function resortNumbers(id)
        {

            var cnt = $('#'+id+' tbody tr').length;
            var n = 0;

            var tmpObj = {};
            tmpObj.fields = [];

            $('#'+id+' tr').each( function(){

                  var split = $(this).attr('id').split("_");
                  var old = split[2];

                  var TDs = $(this).children('td');


                  // Name
                  $(TDs[0]).children('input').attr('name', 'elementNames['+n+']');
                  $(TDs[0]).children('input').attr('id', 'attribute_row_'+n+'_field_name_input');

                  // Type
                  $(TDs[1]).children('input').attr('name', 'elementTypes['+n+']');
                  $(TDs[1]).children('input').attr('id', 'attribute_row_'+n+'_field_type_input');

                  // Display
                  $(TDs[2]).children('input').attr('name', 'elementDisplays['+n+']');
                  $(TDs[2]).children('input').attr('id', 'attribute_row_'+n+'_field_display_input');

                  // Default
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_default_input').attr('name', 'elementDefault['+n+']');
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_default_input').attr('id', 'attribute_row_'+n+'_field_default_input');

                  // Old Names
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_old_name').attr('name', 'elementOldNames['+n+']');
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_old_name').attr('id', 'attribute_row_'+n+'_field_old_name');

                  // Old IDs
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_old_id').attr('name', 'elementOldIDs['+n+']');
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_old_id').attr('id', 'attribute_row_'+n+'_field_old_id');

                  // Options
                  $(TDs[3]).children('input.attribute_row_'+old+'_field_option_inputs').attr('name', 'elementOptions['+n+'][]');
                  $(TDs[3]).children('input.attribute_row_'+old+'_field_option_inputs').attr('class', 'attribute_row_'+n+'_field_option_inputs');

                  // Validation
                  $(TDs[3]).children('input.attribute_row_'+old+'_field_validation_inputs').attr('name', 'elementValidation['+n+'][]');
                  $(TDs[3]).children('input.attribute_row_'+old+'_field_validation_inputs').attr('class', 'attribute_row_'+n+'_field_validation_inputs');

                  // Instructions
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_instructions_input').attr('name', 'elementInstructions['+n+']');
                  $(TDs[3]).children('input#attribute_row_'+old+'_field_instructions_input').attr('id', 'attribute_row_'+n+'_field_instructions_input');

                  // Other
                    // cols
                    $(TDs[3]).children('input.attribute_row_'+old+'_field_other_cols_inputs').each( function(){

                      var oClass = $(this).attr('class').split(" ");
                      var o = false;

                      $(oClass).each( function(i, v){

                          if ( v.match("^field_col_num_") ){

                              var split = v.split("_");
                              o = split[ split.length - 1 ];

                          }

                      } );

                      $(this).attr('name', 'elementOther['+n+'][cols]['+o+']');
                      $(this).attr('class', 'attribute_row_'+n+'_field_other_cols_inputs field_col_num_'+o);

                    } );

                    // rows
                    $(TDs[3]).children('input.attribute_row_'+old+'_field_other_rows_inputs').each( function(){

                      var oClass = $(this).attr('class').split(" ");
                      var o = false;

                      $(oClass).each( function(i, v){

                          if ( v.match("^field_row_num_") ){

                              var split = v.split("_");
                              o = split[ split.length - 1 ];

                          }

                      } );

                      $(this).attr('name', 'elementOther['+n+'][rows]['+o+']');
                      $(this).attr('class', 'attribute_row_'+n+'_field_other_rows_inputs field_row_num_'+o);

                    } );


                  // Edit Link
                  $(TDs[3]).children('a').attr('onclick', 'editAttribute('+n+');return false;');

                  // Delete link
                  $(TDs[4]).children('a').attr('onclick', 'removeField('+n+');return false;');

                  // TD ids
                  $(TDs[0]).attr('id', 'attribute_row_'+n+'_field_name');
                  $(TDs[1]).attr('id', 'attribute_row_'+n+'_field_type');
                  $(TDs[2]).attr('id', 'attribute_row_'+n+'_field_display');
                  $(TDs[3]).attr('id', 'attribute_row_'+n+'_field_edit_col');

                  // Row id
                  $(this).attr('id', 'attribute_row_'+n);

                  // JSON Object
                  tmpObj.fields[n] = ELBPFORM.fields[old];

                n++;

            } );

            ELBPFORM = tmpObj;

        }


</script>

JS;

}

/**
 * Get the form for the creation of new plugin attributes
 * @param type $obj
 */
function elbp_display_attribute_creation_form($obj)
{

    global $CFG, $OUTPUT;

    $FORM = new \ELBP\ELBPForm();
    $FORM->load( $obj->getDefaultAttributes() );

    $types = $FORM->getSupportedTypes();
    $validation = $FORM->getSupportValidationTypes();

    $output = "";

    $output .= "<div id='elbp_att_container'>";

        $output .= "<div id='elbp_att_overlay'><span></span><img src='{$CFG->wwwroot}/blocks/elbp/pix/loader.gif' alt='loading' /></div>";

        $output .= "<div class='elbp_att_form_fields'>";

            $output .= "<ul id='nav'>";
                $output .= "<li><a id='fieldstab' href='#' onclick='showAttributeTab(\"fields\", this);return false;' class='active'>".get_string('fields', 'block_elbp')."</a></li>";
                $output .= "<li><a id='settingstab' href='#' onclick='showAttributeTab(\"settings\", this);return false;'>".get_string('settings', 'block_elbp')."</a></li>";
            $output .= "</ul>";

            $output .= "<div id='field_fields'>";

                $output .= "<h3>".get_string('corefields', 'block_elbp')."</h3>";

                $output .= "<ul class='fields'>";

                    $output .= "<li class='field_single_text_line'><a href='#' onclick='addAttribute(\"Text\");return false;'>".get_string('fields:singletextline', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_textbox'><a href='#' onclick='addAttribute(\"Textbox\");return false;'>".get_string('fields:textbox', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_texteditor'><a href='#' onclick='addAttribute(\"Moodle Text Editor\");return false;'>".get_string('fields:texteditor', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_select'><a href='#' onclick='addAttribute(\"Select\");return false;'>".get_string('fields:select', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_multiselect'><a href='#' onclick='addAttribute(\"Multi Select\");return false;'>".get_string('fields:multiselect', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_checkbox'><a href='#' onclick='addAttribute(\"Checkbox\");return false;'>".get_string('fields:checkbox', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_radio'><a href='#' onclick='addAttribute(\"Radio Button\");return false;'>".get_string('fields:radio', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_date'><a href='#' onclick='addAttribute(\"Date\");return false;'>".get_string('fields:date', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_file'><a href='#' onclick='addAttribute(\"File\");return false;'>".get_string('fields:file', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_description'><a href='#' onclick='addAttribute(\"Description\");return false;'>".get_string('fields:description', 'block_elbp')."</a></li>";

                $output .= "</ul>";

                $output .= "<br class='elbp_cl' /><br>";


                $output .= "<h3>".get_string('specialfields', 'block_elbp')."</h3>";

                $output .= "<ul class='fields'>";

                    $output .= "<li class='field_userpicker'><a href='#' onclick='addAttribute(\"User Picker\");return false;'>".get_string('fields:userpicker', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_coursepicker'><a href='#' onclick='addAttribute(\"Course Picker\");return false;'>".get_string('fields:coursepicker', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_mycourses'><a href='#' onclick='addAttribute(\"My Courses\");return false;'>".get_string('fields:mycourses', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_rating'><a href='#' onclick='addAttribute(\"Rating\");return false;'>".get_string('fields:rating', 'block_elbp')."</a></li>";
                    $output .= "<li class='field_matrix'><a href='#' onclick='addAttribute(\"Matrix\");return false;'>".get_string('fields:matrix', 'block_elbp')."</a></li>";

                $output .= "</ul>";

                $output .= "<br class='elbp_cl' /><br>";

            $output .= "</div>";

            $output .= "<div id='field_settings'>";

                // Default form
                $output .= "<div class='elbp_err_box'><h2>".get_string('nofieldselected', 'block_elbp')."</h2><br><p>".get_string('nofieldselected:desc', 'block_elbp')."</p></div>";

            $output .= "</div>";



        $output .= "</div>";


        $output .= "<div class='elbp_att_form_preview'>";

            $attributes = $obj->getElementsFromAttributeString();

            $output .= '<table id="elbp_attributes_table">';
            $output .= "<thead>";
            $output .= '<tr>';
            $output .= "<th>".get_string('name', 'block_elbp')."</th>";
            $output .= "<th>".get_string('type', 'block_elbp')."</th>";
            $output .= "<th>".get_string('display', 'block_elbp')."</th>";
            $output .= "<th></th>";
            $output .= "<th></th>";
            $output .= '</tr>';
            $output .= '</thead>';

            $output .= '<tbody>';

                $numA = 0;

                if ($attributes)
                {
                    foreach($attributes as $attribute)
                    {

                        $output .= '<tr id="attribute_row_'.$numA.'">';

                            $output .= '<td id="attribute_row_'.$numA.'_field_name"><span>'.$attribute->name.'</span> <input id="attribute_row_'.$numA.'_field_name_input" type="hidden" name="elementNames['.$numA.']" value="'.$attribute->name.'" /></td>';
                            $output .= '<td id="attribute_row_'.$numA.'_field_type"><span>'.$attribute->type.'</span> <input id="attribute_row_'.$numA.'_field_type_input" type="hidden" name="elementTypes['.$numA.']" value="'.$attribute->type.'" /></td>';
                            $output .= '<td id="attribute_row_'.$numA.'_field_display" class="elbp_centre"><img src="'.$CFG->wwwroot.'/blocks/elbp/pix/icons/'.$attribute->getDisplayIcon().'" alt="'.$attribute->display.'" title="'.$attribute->display.'" /> <input id="attribute_row_'.$numA.'_field_display_input" type="hidden" name="elementDisplays['.$numA.']" value="'.$attribute->display.'" /></td>';

                            $hiddenInputs = '<input type="hidden" id="attribute_row_'.$numA.'_field_default_input" name="elementDefault['.$numA.']" value="'.$attribute->default.'" />';
                            $hiddenInputs .= '<input type="hidden" id="attribute_row_'.$numA.'_field_old_name" name="elementOldNames['.$numA.']" value="'.$attribute->name.'" />';
                            $hiddenInputs .= '<input type="hidden" id="attribute_row_'.$numA.'_field_old_id" name="elementOldIDs['.$numA.']" value="'.$attribute->id.'" />';

                            $optionsInputs = "";

                            if ($attribute->options)
                            {
                                $aCnt = 0;
                                foreach($attribute->options as $option)
                                {

                                    $optionsInputs .= '<input class="attribute_row_'.$numA.'_field_option_inputs field_opt_num_'.$aCnt.'" type="hidden" name="elementOptions['.$numA.']['.$aCnt.']" value="'.$option.'" />';
                                    $aCnt++;

                                }
                            }

                            $hiddenInputs .= $optionsInputs;


                            $validationInputs = "";

                            if ($attribute->validation)
                            {

                                $aCnt = 0;
                                foreach($attribute->validation as $value)
                                {

                                    $validationInputs .= '<input class="attribute_row_'.$numA.'_field_validation_inputs" type="hidden" name="elementValidation['.$numA.'][]" value="'.$value.'" />';
                                    $aCnt++;

                                }

                            }

                            $hiddenInputs .= $validationInputs;


                            $otherInputs = "";

                            if ($attribute->canHaveOther() && $attribute->other)
                            {
                                $oCnt = array();
                                foreach($attribute->other as $key => $val)
                                {
                                    if ($key == 'cols' || $key == 'rows')
                                    {

                                        $pKey = $key;
                                        $key = rtrim($key, "s");
                                        $oCnt[$key] = 0;

                                        if (is_object($val)){
                                            $val = (array)$val;
                                        }

                                        if (is_array($val)){
                                            foreach($val as $v){
                                                $otherInputs .= "<input type='hidden' name='elementOther[{$numA}][{$pKey}][{$oCnt[$key]}]' class='attribute_row_{$numA}_field_other_{$pKey}_inputs field_{$key}_num_{$oCnt[$key]}' {$key}-number='{$oCnt[$key]}' value='{$v}' />";
                                                $oCnt[$key]++;
                                            }
                                        } else {
                                            $otherInputs .= "<input type='hidden' name='elementOther[{$numA}][{$pKey}][{$oCnt[$key]}]' class='attribute_row_{$numA}_field_other_{$pKey}_inputs field_{$key}_num_{$oCnt[$key]}' {$key}-number='{$oCnt[$key]}' value='{$val}' />";
                                            $oCnt[$key]++;
                                        }
                                    }
                                    else
                                    {
                                        if (is_array($val)){
                                            // do this when i need it
                                        } else {
                                            $otherInputs .= "<input type='hidden' name='elementOther[{$numA}][{$key}]' class='attribute_row_{$numA}_field_other_{$key}_inputs' value='{$val}' />";
                                        }
                                    }
                                }
                            }

                            $hiddenInputs .= $otherInputs;

                            $hiddenInputs .= '<input type="hidden" id="attribute_row_'.$numA.'_field_instructions_input" name="elementInstructions['.$numA.']" value="'.$attribute->instructions.'" />';

                            $output .= '<td id="attribute_row_'.$numA.'_field_edit_col" class="noSort">'.$hiddenInputs.'<a href="#" onclick="editAttribute(\''.$numA.'\');return false;" title="'.get_string('edit').'"><img src="'.elbp_image_url('t/edit').'" /></a></td>';
                            $output .= '<td class="noSort"><a href="#" onclick="removeField('.$numA.');return false;" title="'.get_string('delete').'"><img src="'.elbp_image_url('t/delete').'" /></a></td>';

                        $output .= "</tr>";

                        $numA++;

                    }
                }

            $output .= '</tbody>';
            $output .= '</table>';


        $output .= "</div>";

        $output .= "<br class='elbp_cl' /><br>";

    $output .= "</div>";

    echo $output;

}

function elbp_get_attribute_edit_form( \ELBP\ELBPFormElement $element )
{

    global $CFG;

    $output = "";

    $FORM = new \ELBP\ELBPForm();
    $types = $FORM->getSupportedTypes();
    $validation = $FORM->getSupportValidationTypes();

    $output .= "<input type='hidden' id='field_settings_dynamic_num' value='{$element->num}' />";

    // Type
    $output .= "<h3>".get_string('type', 'block_elbp')."</h3>";

    $output .= "<select id='field_settings_type'>";

        // Core types
        $output .= "<optgroup label='".get_string('corefields', 'block_elbp')."'>";

            if ($types['core'])
            {
                foreach($types['core'] as $type)
                {
                    $chk = ($element->type == $type) ? 'selected' : '';
                    $output .= "<option value='{$type}' {$chk} >{$type}</option>";
                }
            }

        $output .= "</optgroup>";

        // Special types
        $output .= "<optgroup label='".get_string('specialfields', 'block_elbp')."'>";

            // Core types
            if ($types['special'])
            {
                foreach($types['special'] as $type)
                {
                    $chk = ($element->type == $type) ? 'selected' : '';
                    $output .= "<option value='{$type}' {$chk} >{$type}</option>";
                }
            }

        $output .= "</optgroup>";

    $output .= "</select>";
    $output .= "<br><br>";

    // Name
    $output .= "<h3>".get_string('name', 'block_elbp')."</h3>";
    $output .= "<input type='text' id='field_settings_name' class='elbp_95' value='{$element->name}' />";
    $output .= "<br><br>";

    // Display
    $output .= "<h3>".get_string('display', 'block_elbp')."</h3>";
    $output .= "<select id='field_settings_display'>";
        $output .= "<option value=''></option>";
        $output .= "<option value='main' ".( ($element->display == 'main') ? 'selected' : '' )." >".get_string('mainelement', 'block_elbp')."</option>";
        $output .= "<option value='side' ".( ($element->display == 'side') ? 'selected' : '' )." >".get_string('sideelement', 'block_elbp')."</option>";
    $output .= "</select>";
    $output .= "<br><br>";

    // Options
    if ($element->canHaveOptions())
    {

        $output .= "<h3>".get_string('options', 'block_elbp')."</h3>";

        $output .= "<div id='field_settings_options'>";

            $o = 0;

            if ($element->options)
            {
                foreach($element->options as $option)
                {
                    $output .= "<span><input type='text' class='normal attribute_popup_options' option-number='{$o}' value='{$option}' /> <a href='#' class='field_settings_option_delete' option-number='{$o}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/delete.png' /></a> <a href='#' class='field_settings_option_add' option-number='{$o}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/add.png' /></a><br></span>";
                    $o++;
                }
            }
            else
            {
                $output .= "<span><input type='text' class='normal attribute_popup_options' option-number='{$o}' value='' /> <a href='#' class='field_settings_option_delete' option-number='{$o}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/delete.png' /></a> <a href='#' class='field_settings_option_add' option-number='{$o}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/add.png' /></a><br></span>";
            }

        $output .= "</div>";

        $output .= "<br><br>";

    }


    // Matrix has special things
    if ($element->type == 'Matrix')
    {

        $cols = (isset($element->other['cols'])) ? $element->other['cols'] : false;
        $rows = (isset($element->other['rows'])) ? $element->other['rows'] : false;

        $output .= "<h3>".get_string('columns', 'block_elbp')."</h3>";

        $output .= "<div id='field_settings_cols'>";

        $c = 0;

        if ($cols)
        {
            foreach($cols as $col)
            {
                $output .= "<span><input type='text' class='normal field_setting_other_cols' col-number='{$c}' value='{$col}' /> <a href='#' class='field_settings_other_cols_delete' col-number='{$c}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/delete.png' /></a> <a href='#' class='field_settings_other_cols_add' col-number='{$c}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/add.png' /></a><br></span>";
                $c++;
            }
        }
        else
        {
            $output .= "<span><input type='text' class='normal field_setting_other_cols' col-number='{$c}' value='' /> <a href='#' class='field_settings_other_cols_delete' col-number='{$c}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/delete.png' /></a> <a href='#' class='field_settings_other_cols_add' col-number='{$c}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/add.png' /></a><br></span>";
        }

        $output .= "</div>";

        $output .= "<br><br>";


        $output .= "<h3>".get_string('rows', 'block_elbp')."</h3>";

        $output .= "<div id='field_settings_rows'>";

        $r = 0;

        if ($rows)
        {
            foreach($rows as $row)
            {
                $output .= "<span><input type='text' class='normal field_setting_other_rows' row-number='{$r}' value='{$row}' /> <a href='#' class='field_settings_other_rows_delete' row-number='{$r}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/delete.png' /></a> <a href='#' class='field_settings_other_rows_add' row-number='{$r}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/add.png' /></a><br></span>";
                $r++;
            }
        }
        else
        {
            $output .= "<span><input type='text' class='normal field_setting_other_rows' row-number='{$r}' value='' /> <a href='#' class='field_settings_other_rows_delete' row-number='{$r}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/delete.png' /></a> <a href='#' class='field_settings_other_rows_add' row-number='{$r}'><img src='{$CFG->wwwroot}/blocks/elbp/pix/icons/add.png' /></a><br></span>";
        }

        $output .= "</div>";

        $output .= "<br><br>";

    }

    // Rating lets you choose a max scale
    if ($element->type == 'Rating')
    {

        $max = (isset($element->other['max'])) ? $element->other['max'] : false;
        $output .= "<h3>".get_string('rangemax', 'block_elbp')."</h3>";
        $output .= "<input type='radio' class='field_setting_other_max' name='field_setting_other_max' value='5' ".( ($max == 5) ? 'checked': '' )." />5";
        $output .= "<br>";
        $output .= "<input type='radio' class='field_setting_other_max' name='field_setting_other_max' value='10' ".( ($max == 10) ? 'checked': '' )." />10";
        $output .= "<br>";
        $output .= "<input type='radio' class='field_setting_other_max' name='field_setting_other_max' value='20' ".( ($max == 20) ? 'checked': '' )." />20";
        $output .= "<br><br>";

    }



    // Default
    if ($element->canHaveDefault()){

        $default = $element->default;
        if (is_array($default)){
            $default = implode(",", $default);
        }

        $output .= "<h3>".get_string('defaultvalue', 'block_elbp')."</h3>";
        $output .= "<textarea id='field_settings_default'>{$default}</textarea>";
        $output .= "<br><br>";

    }


    // Validation
    if ($element->canHaveValidation())
    {

        $output .= "<h3>".get_string('validation', 'block_elbp')."</h3>";
        $output .= "<select id='field_settings_validation' multiple='multiple' class='elbp_select'>";

            if ($validation)
            {
                foreach($validation as $vald)
                {
                    $chk = (in_array($vald, $element->validation)) ? 'selected' : '';
                    $output .= "<option value='{$vald}' {$chk} >{$vald}</option>";
                }
            }

        $output .= "</select>";
        $output .= "<br><br>";

    }


    // Instructions label
    $output .= "<h3>".get_string('instructions', 'block_elbp')."</h3>";
    $output .= "<input type='text' id='field_settings_instructions' class='elbp_95' value='".\elbp_html($element->instructions)."' />";
    $output .= "<br><br>";


    return $output;

}



/**
 * Save the data sent in the attribute creation/edit form for a plugin
 * @global type $MSGS
 * @param type $obj THe plugin object
 * @return boolean
 */
function elbp_save_attribute_script($obj)
{

    global $MSGS;

    if(isset($_POST['submit_attributes'])){

        $form = new \ELBP\ELBPForm();

        $elementNames = (isset($_POST['elementNames'])) ? $_POST['elementNames'] : array();
        $elementTypes = (isset($_POST['elementTypes'])) ? $_POST['elementTypes'] : array();
        $elementOptions = (isset($_POST['elementOptions'])) ? $_POST['elementOptions'] : array();
        $elementDisplays = (isset($_POST['elementDisplays'])) ? $_POST['elementDisplays'] : array();
        $elementValidation = (isset($_POST['elementValidation'])) ? $_POST['elementValidation'] : array();
        $elementDefault = (isset($_POST['elementDefault'])) ? $_POST['elementDefault'] : array();
        $elementInstructions = (isset($_POST['elementInstructions'])) ? $_POST['elementInstructions'] : array();
        $elementOther = (isset($_POST['elementOther'])) ? $_POST['elementOther'] : array();
        $elementOldNames = (isset($_POST['elementOldNames'])) ? $_POST['elementOldNames'] : array();
        $elementOldIDs = (isset($_POST['elementOldIDs'])) ? $_POST['elementOldIDs'] : array();

        $elementNames = array_map('trim', $elementNames);

        $keys = array_keys($elementNames);
        $names = array();


        if ($keys)
        {
            foreach($keys as $i)
            {

                $element = new \ELBP\ELBPFormElement();

                // Reset variables
                $options = false;
                $validation = false;
                $instructions = false;
                $default = false;
                $other = false;

                $name = str_replace('"', '', $elementNames[$i]);
                $origName = $name;

                $n = 1;
                while(in_array($name, $names)){
                    $name = $origName . " ({$n})";
                    $n++;
                }

                $names[] = $name;

                $type = $elementTypes[$i];
                $display = $elementDisplays[$i];

                if(isset($elementOptions[$i])){
                    $options = $elementOptions[$i];
                }

                if(isset($elementValidation[$i]) && $elementValidation[$i]){
                    $validation = array_filter($elementValidation[$i]);
                }

                if(isset($elementInstructions[$i])){
                    $instructions = $elementInstructions[$i];
                }

                if(isset($elementDefault[$i])){
                    $default = $elementDefault[$i];
                }

                if(isset($elementOther[$i])){
                    $other = $elementOther[$i];
                }

                // If name or type is empty, skip it
                if (elbp_is_empty($name) || elbp_is_empty($type)){
                    continue;
                }

                // Must be valid type, else skip it
                if (!$form->isSupportedType($type)){
                    continue;
                }

                // Order options properly
                if ($options)
                {

                    // Sort them by key
                    ksort($options);

                    // Then re-index to sort out issue with missing indexes, if any options have been removed
                    $options = array_values($options);

                }

                // Order other properly
                if ($other)
                {
                    foreach($other as $kType => &$arr)
                    {
                        if (is_array($arr))
                        {

                            // Sort them by key
                            ksort($arr);

                            // Then re-index to sort out issue with missing indexes, if any options have been removed
                            $arr = array_values($arr);

                        }
                    }
                }

                // Start creating the element object
                $element->setName($name)
                        ->setType($type)
                        ->setDisplay($display)
                        ->setDefault($default)
                        ->setOptions($options)
                        ->setValidation($validation)
                        ->setInstructions($instructions)
                        ->setOther($other);

                // Keep the existing ID
                if (isset($elementOldIDs[$i])){
                    $element->setID($elementOldIDs[$i]);
                }

                $form->addElement($element);

            }
        }


        $data = $form->convertElementsToDataString();

        $obj->updateSetting("attributes", $data);

        // If we have changed the name of an attribute, we need to change all the data linked to that
        // old name
        if (!$obj->updateChangedAttributeNames($elementNames, $elementOldNames)){
            $MSGS['errors'] = get_string('attnamesnotoverridden', 'block_elbp');
        }

        $MSGS['success'] = get_string('attributesupdated', 'block_elbp');

        // Reload for display
        $obj->loadDefaultAttributes();

        return true;


    }

}










/**
 * Trigger an event so that we can send alerts to people
 * @param string $event Name of the event
 * @param int $pluginID ID of the plugin it's triggered from
 * @param int $studentID ID of the student involved
 * @param string $content Content of the alert to email
 */
function elbp_event_trigger($event, $pluginID, $studentID, $content, $htmlContent, $confidentialityLevel = null)
{

    // Trigger email alerts
    $obj = new \ELBP\EmailAlert();
    $obj->run($event, $pluginID, $studentID, $content, $htmlContent, $confidentialityLevel);

    // Trigger SMS alerts




}

/**
 * Trigger the student alerts for a plugin
 * @param type $event
 * @param type $pluginID
 * @param type $studentID
 * @param type $content
 * @param type $htmlContent
 */
function elbp_event_trigger_student($event, $pluginID, $studentID, $content, $htmlContent)
{

    // Trigger email alerts

    // Check if this plugin has disabled student alerts
    $setting = (int)\ELBP\Setting::getSetting('plugin_stud_alerts_enabled', null, $pluginID);
    if ($setting !== 0)
    {
        $obj = new \ELBP\EmailAlert();
        $obj->runStudent($event, $pluginID, $studentID, $content, $htmlContent);
    }

}



// Moodle events

/**
 * Event to call when a user is enrolled on a course - this will check personal tutor assignments and add student to pt
 * @global type $DB
 * @param type $data
 * @return true
 */
function event_course_user_enrolled($data)
{

    global $DB;

    $ELBPDB = new \ELBP\DB();

    // Get context & role assignment
    $context = $DB->get_record("context", array("contextlevel" => CONTEXT_COURSE, "instanceid" => $data->courseid));
    if (!$context) return true;

    $role = $DB->get_record("role_assignments", array("userid" => $data->userid, "contextid" => $context->id));
    if (!$role) return true;

    // Must be student
    if ($role->roleid <> $ELBPDB->getRole("student")) return true;

    // Find any PTs assigned to this course and add this user to them
    $assigned = $DB->get_records("lbp_tutor_assignments", array("courseid" => $data->courseid));
    if ($assigned)
    {
        foreach($assigned as $record)
        {

            $PT = new \ELBP\PersonalTutor();
            $PT->loadTutorID($record->tutorid);
            $PT->assignMentee($data->userid);

        }
    }

    return true;

}

// FOr now we won't use this. We will let PTs delete their students they don't need, as it'll be a hassle if
// students move courses but keep the same mentees
function event_course_user_unenrolled($data){
    return true;
}


/**
 * Event to call when a user is added to a group
 * @global type $DB
 * @param type $data
 * @return boolean
 */
function event_group_user_added($data){

    global $DB;

    // Find any PTs assigned to this group
    $assigned = $DB->get_records("lbp_tutor_assignments", array("groupid" => $data->groupid));
    if ($assigned)
    {
        foreach($assigned as $record)
        {

            $PT = new \ELBP\PersonalTutor();
            $PT->loadTutorID($record->tutorid);
            $PT->assignMentee($data->userid);

        }
    }

    return true;

}


/**
 * Event to call when a user is added to a group
 * @global type $DB
 * @param type $data
 * @return boolean
 */
function event_group_user_removed($data){

    global $DB;

    // Find any PTs assigned to this group
    $assigned = $DB->get_records("lbp_tutor_assignments", array("groupid" => $data->groupid));
    if ($assigned)
    {
        foreach($assigned as $record)
        {

            $PT = new \ELBP\PersonalTutor();
            $PT->loadTutorID($record->tutorid);
            $PT->removeMentee($data->userid);

        }
    }

    return true;

}

/**
 * Convert a timestamp to an "ago" string, e.g. "2 days ago"
 * http://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago
 * @param type $ptime
 * @return string
 */
function elbp_time_elapsed_string($ptime)
{
    $etime = time() - $ptime;

    if ($etime < 1)
    {
        return '0 seconds ago';
    }

    $a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
                30 * 24 * 60 * 60       =>  'month',
                24 * 60 * 60            =>  'day',
                60 * 60                 =>  'hour',
                60                      =>  'minute',
                1                       =>  'second'
                );

    foreach ($a as $secs => $str)
    {
        $d = $etime / $secs;
        if ($d >= 1)
        {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
        }
    }
}

/**
 * Print out a table of MIS fields and their mappings for a connection
 * @param type $fields
 * @param type $connection
 * @param type $string
 */
function elbp_print_mis_mappings_table($fields, $connection, $string){

    $output = "";

    if ($fields)
    {
        foreach($fields as $field)
        {

            $output .= "<small><strong>{$field['name']}</strong> - {$field['desc']}</small><br>";
            $output .= "<input type='text' name='mis_map[{$field['field']}]' value='".$connection->getFieldMap($field['field'], true)."' placeholder='".get_string('misfield', 'block_elbp')."' /> ";
            $output .= "<input class='elbp_fairly_large' type='text' name='mis_func[{$field['field']}]' value='".$connection->getFieldFunc($field['field'], true)."' placeholder='".get_string('misfieldfunc', 'block_elbp')."' title='".get_string('misfieldfunc:desc', 'block_elbp')."' /> ";
            $output .= "<input class='elbp_smallish' name='mis_alias[{$field['field']}]' type='text' placeholder='{$string['alias']}' title='{$string['misalias:desc']}' value='".$connection->getFieldAlias($field['field'], true)."' /> ";
            $output .= "<br><br>";

        }
    }

    echo $output;

}



/**
 * Get a list of users who are assigned to a given user
 * @param type $userID
 */
function elbp_get_users_personaltutors($userID){

    global $DB;

    $params = array();
    $sql = array();

    $sql['select'] = " SELECT DISTINCT u.* ";
    $sql['from'] = " FROM {role_assignments} r ";
    $sql['join'] = " INNER JOIN {context} cx ON cx.id = r.contextid ";
    $sql['join'] .= " INNER JOIN {user} u ON u.id = r.userid ";

    $sql['where'] = " WHERE cx.contextlevel = ? ";
    $params[] = CONTEXT_USER;
    $sql['where'] .= " AND cx.instanceid = ? ";
    $params[] = $userID;

    $fullSQL = implode(" ", $sql);

    $users = $DB->get_records_sql($fullSQL, $params);

    return $users;


}

/**
 * Get the fullname of a user from their id
 * @global type $DB
 * @param type $id
 * @return type
 */
function elbp_get_fullname($id){

    global $DB;

    $user = $DB->get_record("user", array("id" => $id));
    return ($user) ? \fullname($user) : false;

}

/**
 * Get the username of a user from their id
 * @global type $DB
 * @param type $id
 * @return type
 */
function elbp_get_username($id){
    global $DB;
    $user = $DB->get_record("user", array("id" => $id));
    return ($user) ? $user->username : false;
}

/**
 * Get the username of a user from their id
 * @global type $DB
 * @param type $id
 * @return type
 */
function elbp_get_user($username){
    global $DB;
    $user = $DB->get_record("user", array("username" => $username));
    return $user;
}

/**
 * Parse text and do things like change %student% to student's fullname, %course% to the course name, etc...
 * @param type $txt
 */
function elbp_parse_text_code($txt, $params){

    if (isset($params['student'])){

        $fullname = fullname($params['student']) . " ({$params['student']->username})";
        $txt = preg_replace("/%fullname%/", $fullname, $txt);
        $txt = preg_replace("/%fname%/", $params['student']->firstname, $txt);
        $txt = preg_replace("/%sname%/", $params['student']->lastname, $txt);

    }


    return $txt;

}

/**
 * Get the fullname of a course from its id
 * @global type $DB
 * @param type $id
 * @return type
 */
function elbp_get_course_fullname($id){

    global $DB;

    $record = $DB->get_record("course", array("id" => $id));

    return ($record) ? $record->fullname : false;

}

/**
 * Cut off a string at a certain number of characters, adding an ellipsis at the end
 * @param string $str
 * @param type $length
 * @return string
 */
function elbp_cut_string($str, $length){

    if (strlen($str) > $length){
        $str = substr($str, 0, $length) . '...';
    }

    return $str;

}

/**
 * Get the name of a moodle role from its id
 * @global type $DB
 * @param type $roleID
 * @return type
 */
function elbp_get_role_name($roleID){

    global $DB;

    $record = $DB->get_record("role", array("id" => $roleID));

    return ($record) ? $record->name . " (".$record->shortname.")" : false;

}

/**
 * Strip everything except alphanumeric and underscores from a string
 * @param type $txt
 * @return type
 */
function elbp_strip_to_plain($txt){

    if (is_array($txt))
    {
        foreach($txt as &$t)
        {
            $t = \elbp_strip_to_plain($t);
        }

        return $txt;

    }
    else
    {
        $txt = str_replace(" ", "_", $txt);
        $txt = preg_replace("/[^a-z0-9_]/i", "", $txt);
        return $txt;
    }

}

/**
 * Create a random string
 * @param type $length
 * @return string
 */
function elbp_rand_str($length)
{

    $str = "987654321AaBbCcDdEeFfGgHhJjKkMmNnPpQqRrSsTtUuVvWwXxYyZz123456789";

    $count = strlen($str) - 1;

    $output = "";

    for($i = 0; $i < $length; $i++)
    {
        $output .= $str[mt_rand(0, $count)];
    }

    return $output;

}

/**
 * Implode an array whilst keeping the key assocations
 * http://darklaunch.com/2009/05/30/php-implode-with-key-implode-array-with-key-and-value
 * @param type $assoc
 * @param type $inglue
 * @param type $outglue
 * @return type
 */
function elbp_implode_with_key($assoc, $outglue = ', ') {
    $return = '';

    foreach ($assoc as $tk => $tv) {
        $return .= $outglue . $tk . ' [' . $tv . ']';
    }

    return substr($return, strlen($outglue));
}

/**
 * Create a moodle course from a shortname
 * @param type $shortname
 * @return type
 */
function elbp_create_course_from_shortname($shortname){

    $data = new \stdClass();
    $data->shortname = $shortname;
    $data->fullname = $shortname;
    $data->idnumber = $shortname;
    $data->timecreated = time();
    $data->category = 1;
    $data->visible = 0;
    return create_course($data);

}

/**
 * Create a moodle user from a username, with a password default of xxxx
 * @param type $username
 * @return type
 */
function elbp_create_user_from_username($username){

    return create_user_record($username, 'xxxx');

}

/**
 * Print out that you have no access due to confidentiality levels
 * @global type $CFG
 */
function elbp_confidentiality_print_no_access(){

    global $CFG;

    echo "<div class='elbp_centre'>
        <p><img class='no_access_img' src='{$CFG->wwwroot}/blocks/elbp/pix/no.png' /></p>
        <p>".get_string('confidentiality:noaccess', 'block_elbp')."</p>
    </div>";

}

/**
 * Get the image extension (e.g. jpg, gif, etc...) from the base64 encoded string of an image
 * @param type $data
 * @return type
 */
function elbp_get_image_ext_from_base64($data){

    preg_match("/data:image\/(.*?);/", $data, $match);
    return (isset($match[1])) ? $match[1] : false;

}

/**
 * Create an image file and save it, from the base64 encoded string of an image
 * @param type $data
 * @param type $path
 * @return boolean
 */
function elbp_save_base64_image($data, $path){

    $pos = strpos($data, ',');
    $start = $pos - strlen($data) + 1;
    $data = substr($data, $start);

    $data = base64_decode($data);

    $source = imagecreatefromstring($data);
    if ($source){
        imagejpeg($source, $path);
        imagedestroy($source);
        return true;
    } else {
        return false;
    }


}

/**
 * Check if a block is installed
 * @param type $block
 */
function elbp_is_block_installed($block){

    global $DB;

    $check = $DB->get_record("block", array("name" => $block));

    return ($check) ? true : false;

}

/**
 * Get a success alert box
 * @param type $text
 * @param type $title
 * @return string
 */
function elbp_success_alert_box($text, $title = "Success")
{

    $output = "";
    $output .= "<div class='elbp_alert_good fade in'>";
        $output .= "<strong>{$title}</strong> ";
        $output .= "<span>{$text}</span>";
    $output .= "</div>";

    return $output;

}

/**
 * Get an error alert box
 * @param type $text
 * @param type $title
 * @return string
 */
function elbp_error_alert_box($text, $title = "Error")
{

    $output = "";
    $output .= "<div class='elbp_alert_bad fade in'>";
        $output .= "<strong>{$title}</strong> ";

        if (is_array($text))
        {
            foreach($text as $t)
            {
                $output .= "<span>{$t}</span><br>";
            }
        }
        else
        {
            $output .= "<span>{$text}</span>";
        }

    $output .= "</div>";

    return $output;

}

/**
 * Check if your current IP address is within an array of allowed ranges
 * @param type $ranges
 * @return boolean
 */
function elbp_ip_in_range($ranges){

    $result = false;

    // Get current IP address - Just going to use REMOTE_ADDR, doesn't really matter if its spoofed, it's not exactly a high security system
    $ip = $_SERVER['REMOTE_ADDR'];

    // Get possible ranges
    $ranges = explode(",", $ranges);

    // Loop through and check if any match
    if ($ranges)
    {
        foreach($ranges as $range)
        {

            // Strip any whitespace, incase they did comma and space
            $range = trim($range);

            // Contains wildcards -  Only supports IPv4
            if (strpos($range, "*") !== false){

                // Split range by dots
                $rangeSplit = explode(".", $range);

                if ($rangeSplit)
                {

                    // Split ip by dots
                    $ipSplit = explode(".", $ip);
                    $match = 0;

                    for ($j = 0; $j < 4; $j++)
                    {

                        $r = (isset($rangeSplit[$j])) ? $rangeSplit[$j] : false;
                        $i = (isset($ipSplit[$j])) ? $ipSplit[$j] : false;

                        // If wildcard
                        if ($r == "*")
                        {
                            $match++;
                        }
                        elseif (is_numeric($r) && $r == $i)
                        {
                            $match++;
                        }

                    }

                    if ($match == 4){
                        $result = true;
                    }

                }


            } else {

                // Otherwise is just a normal IPV4 address
                if ($ip == $range){
                    $result = true;
                }

            }

        }
    }


    return $result;


}

/**
 * Get all block_elbp capabilities
 * @global type $DB
 * @return type
 */
function elbp_get_all_capabilities()
{

    global $DB;
    return $DB->get_records_select("capabilities", "component = 'block_elbp' AND name NOT LIKE '%use_quick_tool'", array(), "name ASC");

}

/**
 * Get all individual capabilities set in the User Actions configuration
 * @global type $DB
 * @return type
 */
function elbp_get_all_user_capabilities()
{

    global $DB;

    return $DB->get_records_sql("SELECT uc.*, c.name, u.username, u.firstname, u.lastname
                                    FROM {lbp_user_capabilities} uc
                                    INNER JOIN {capabilities} c ON c.id = uc.capabilityid
                                    INNER JOIN {user} u ON u.id = uc.userid
                                    ORDER BY u.lastname, u.firstname, c.name");

}


/**
 * Create directory in Moodledata to store files
 * Will create the directory: /moodledata/ELBP/$dir
 * Will attempt to create the parent directories if they don't exist yet
 * Uses chmod of 0764:
 *      Owner: rwx,
 *      Group: rw,
 *      Public: r
 *  @param type $dir
 */
function elbp_create_data_directory($dir)
{

    global $CFG;

    // First check if a directory for this plugin exists - Should do as they should be created on install

    // Check for ELBP directory
    if (!is_dir( $CFG->dataroot . '/ELBP' )){
        if (is_writeable($CFG->dataroot)){
            if (!mkdir($CFG->dataroot . '/ELBP', 0764, true)){
                return false;
            }
        } else {
            return false;
        }
    }


    // Now try and make the actual dir we want
    if (!is_dir( $CFG->dataroot . '/ELBP/' . $dir )){
        if (is_writeable($CFG->dataroot . '/ELBP/')){
            if (!mkdir($CFG->dataroot . '/ELBP/' . $dir, 0764, true)){
                return false;
            }
        } else {
            return false;
        }
    }

    // If we got this far must be ok
    return true;


}

/**
 * For a given file path create a code we can use to download that file
 * @global type $DB
 * @param type $path
 * @return type
 */
function elbp_create_data_path_code($path){

    global $DB;

    // See if one already exists for this path
    $record = $DB->get_record("lbp_file_path_codes", array("path" => $path));
    if ($record){
        return $record->code;
    }

    // Create one
    $code = \elbp_rand_str(10);

    // Unlikely, but check if code has already been used
    $cnt = $DB->count_records("lbp_file_path_codes", array("code" => $code));
    while ($cnt > 0)
    {
        $code = \elbp_rand_str(10);
        $cnt = $DB->count_records("lbp_file_path_codes", array("code" => $code));
    }


    $ins = new \stdClass();
    $ins->path = $path;
    $ins->code = $code;

    $DB->insert_record("lbp_file_path_codes", $ins);
    return $code;

}

/**
 * Get the download code for a given file path
 * @global type $DB
 * @param type $path
 * @return type
 */
function elbp_get_data_path_code($path){

    global $DB;
    $record = $DB->get_record("lbp_file_path_codes", array("path" => $path));
    return ($record) ? $record->code : false;

}

/**
 * Replace an array element by value
 * @param type $ar
 * @param type $value
 * @param type $replacement
 */
function elbp_array_replace_value($ar, $value, $replacement)
{
    if (($key = array_search($value, $ar)) !== FALSE) {
        $ar[$key] = $replacement;
    }
    return $ar;
}


/**
 * Format some code to look nicer and maybe highlight things?
 * @param type $code
 * @param type $type
 */
function elbp_format_code($code, $type){

    switch ($type)
    {

        case 'sql':

            $keywords = array("/(select)/i", "/(from)/i", "/(where)/i", "/(order by)/i", "/(group by)/i",
                        "/(join)/i", "/(left join)/i", "/(right join)/i", "/(inner join)/i", "/(union)/i", "/( in )/i");

            $funcs = array("/(isnull)/i", "/(convert)/i", "/(cast)/i", "/(sum)/i", "/(max)/i", "/(min)/i",
                            "/(avg)/i", "/(count)/i", "/(format)/i", "/(top)/i", "/(limit)/i");

            // Colour quotes
            $code = preg_replace('/"/', '<span class="elbp_code_quote">&quot;</span>', $code);
            $code = preg_replace("/'/", '<span class="elbp_code_quote">&apos;</span>', $code);

            // Add a new line after each keyword & colour them
            $code = preg_replace($keywords, '<br><span class="elbp_code_keyword">$1</span><br>', $code);

            // Funcs and other things
            $code = preg_replace($funcs, '<span class="elbp_code_func">$1</span>', $code);

            $code = preg_replace("/^<br>/", "", $code);

            return $code;

        break;

    }

}


function elbp_display_hooks_form($OBJ){

    global $ELBP;

    $hooks = $ELBP->getAllPossibleHooks();
    $string = $ELBP->getString();

    $output = "";

    $output .= "<form action='' method='post'>";

    $output .= "<table id='hooks_table'>";

        $output .= "<tr>";

        foreach($hooks as $pluginID => $hook)
        {
            if(array_key_exists($hook['name'], $OBJ->supportedHooks))
            {
                if($OBJ->getID() <> $pluginID)
                {
                    $output .= "<th>{$hook['name']}</th>";
                }
            }
        }

        $output .= "</tr>";


        $output .= "<tr>";

        foreach($hooks as $pluginID => $hook)
        {
            if(array_key_exists($hook['name'], $OBJ->supportedHooks))
            {
                if($OBJ->getID() <> $pluginID)
                {
                    $output .= "<td>";

                        foreach($hook['hooks'] as $hk)
                        {
                            if(array_key_exists($hook['name'], $OBJ->supportedHooks) && in_array($hk['name'], $OBJ->supportedHooks[$hook['name']]))
                            {
                                $output .= "<input type='checkbox' name='hooks[]' value='{$hk['id']}' ".( ($OBJ->hasHookEnabled($hk['id'])) ? 'checked' : '') ." / >{$hk['name']}<br>";
                            }
                        }

                    $output .= "</td>";
                }
            }
        }

        $output .= "</tr>";

    $output .= "</table>";



    $output .= "<p class='elbp_centre'><input type='submit' name='submit_hooks' value='{$string['save']}' /></p>";

    $output .= "</form>";

    echo $output;

}

/**
 * Output a missing image to browser
 */
function elbp_output_missing_image(){

    global $CFG;

    header('Content-Type: image/png');
    $img = imagecreatefrompng($CFG->dirroot . '/blocks/elbp/pix/missing_img.png');
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagepng($img);
    exit;

}

/**
 * FOrmat a string with values being placed into the get_string value
 * @param type $string
 * @param type $values
 */
function elbp_sprintf($string, $values = false, $location = 'block_elbp')
{

    $txt = get_string($string, $location);
    if ($values)
    {
        if (is_array($values))
        {
            return vsprintf($txt, $values);
        }
        else
        {
            return sprintf($txt, $values);
        }
    }
    else
    {
        return $txt;
    }

}

/**
 * Get an imploded list of question marks to use for placeholders when need to pass an array into SQL statement
 * @param type $array
 * @return type
 */
function elbp_implode_placeholders($array){

    if ($array){
        return implode(',', array_fill(0, count($array), '?'));
    } else {
        return false;
    }

}

function block_elbp_extend_navigation_user(navigation_node $navigation, stdClass $user)
{
    $navigation->add(get_string('viewelbp', 'block_elbp'), new moodle_url('/blocks/elbp/view.php', array('id' => $user->id)));
}


/**
 * Get an image URL from Moodle
 * This used to be using the normal $OUTPUT->image_url() or $OUTPUT->pix_url(), but it doesn't work in AJAX calls, as $OUTPUT is not initialised, so changed to $PAGE->theme
 * @global type $PAGE
 * @param type $imagename
 * @param type $component
 * @return type
 */
function elbp_image_url($imagename, $component = 'moodle'){

    global $PAGE;

    if (method_exists($PAGE->theme, 'image_url')){
        return $PAGE->theme->image_url($imagename, $component);
    } else {
        return $PAGE->theme->pix_url($imagename, $component);
    }


}



/**
 * Get how long ago a timestamp was
 * http://phppot.com/php/php-time-ago-function/
 * @param type $timestamp
 * @return type
 */
function elbp_time_ago($timestamp) {

   $strTime = array("second", "minute", "hour", "day", "month", "year");
   $length = array("60","60","24","30","12","10");

   $currentTime = time();
   if($currentTime >= $timestamp) {
        $diff = time() - $timestamp;
        for($i = 0; $diff >= $length[$i] && $i < count($length)-1; $i++) {
            $diff = $diff / $length[$i];
        }
        $diff = round($diff);
        return $diff . " " . $strTime[$i] . "(s) ago ";
   }

}

/**
 * Sanitize a user-submitted file name, in case they try to go back into directories they shouldn't
 * @param  [type] $path [description]
 * @return [type]       [description]
 */
function elbp_sanitize_path($path){

  $path = str_replace("\"", "", $path);
  $path = str_replace("`", "", $path);
  $path = str_replace("../", "", $path);
  $path = str_replace("..", "", $path);
  $path = str_replace("./", "", $path);
  return $path;

}