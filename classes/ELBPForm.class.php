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

namespace ELBP;

require_once $CFG->dirroot.'/course/lib.php';

class ELBPForm {

    private $types = array(
        'core' => array(),
        'special' => array()
    );
    private $validation;
    private $elements = array();
    private $data;
    private $studentID = false;
    private $obj;

    /**
     * Construct the form object
     */
    public function __construct() {

        $this->types['core'] = array(
            "Text",
            "Textbox",
            "Moodle Text Editor",
            "Select",
            "Multi Select",
            "Checkbox",
            "Radio Button",
            "Date",
            "File",
            "Description"
        );

        $this->types['special'] = array(
            "User Picker",
            "Course Picker",
            "My Courses",
            "Rating",
            "Matrix"
        );

        $this->validation = array(
            "REQUIRED",
            "TEXT_ONLY",
            "NUMBERS_ONLY",
            "ALPHANUMERIC_ONLY",
            "DATE",
            "EMAIL",
            "PHONE",
            "URL",
            //"MIN_LENGTH" - will do this another time, as it requires more stuff to set the value
        );

    }

    public function getSupportedTypes(){
        return $this->types;
    }

    public function isSupportedType($type){
        return ( in_array($type, $this->types['core']) || in_array($type, $this->types['special']) );
    }

    public function getSupportValidationTypes(){
        return $this->validation;
    }

    public function loadStudentID($studentID){
        $this->studentID = $studentID;
        if ($this->elements){
            foreach($this->elements as &$element){
                $element->setStudentID($studentID);
            }
        }
    }

    public function loadObject($obj){
        $this->obj = $obj;
    }

    public function addElement($element){
        $element->studentID = $this->studentID;
        $this->elements[] = $element;
    }

    public function getElements(){
        return $this->elements;
    }

    public function setElements($elements){
        foreach($elements as &$element)
        {
            $element->studentID = $this->studentID;
        }
        $this->elements = $elements;
    }

    /**
     * Load data string into the Form object
     * @param type $data
     */
    public function load($data){
        $this->data = $data;
        $this->elements = array();
        return $this->convertDataStringToElements();
    }

    /**
     * Convert an array of element objects into a data string to store in the db
     * @return string
     */
    public function convertElementsToDataString(){

        if ($this->elements) return "elbpform:" . json_encode($this->elements);
        else return "";

    }

    public function convertDataStringToElements(){

        if ($this->data){

            // If stored in new form
            if ( preg_match("/^elbpform:/", $this->data) ){

                $this->data = str_replace("elbpform:", "", $this->data);
                $elementsArray = \json_decode($this->data);
                $elements = array();

                foreach($elementsArray as $element)
                {

                    $el = \ELBP\ELBPFormElement::create($element);
                    $el->loadObject($this->obj);

                    if ($this->studentID){
                        $el->setStudentID($this->studentID);
                    }

                    $elements[] = $el;

                }

                $this->elements = $elements;
                return $this->elements;

            }

        } else {
            return false;
        }

    }

    /**
     * Get the javascript code for text editor elements
     */
    public static function getEndCode(){

        global $CFG, $PAGE;

        $output = "";

        $editor = \editors_get_preferred_editor();
        $class = get_class($editor);
        $editorName = substr( $class, 0, strpos($class, '_') );
        $output .= "<script>if (M.editor_{$editorName} !== undefined){ M.editor_{$editorName}.initialised = false; }</script>";

        // This bit is dependant on Moodle version

        // Moodle 2.8 and lower
        if ((int)$CFG->version <= 2014111012){
            $output .= $PAGE->requires->get_end_code();
        } else {
            // Moodle 2.9 and higher
            $endcode = $PAGE->requires->get_end_code();
            preg_match_all("/(Y.M.editor_(.*?).Editor.init\((.*)\))/", $endcode, $matches);
            if ($matches && $matches[0]){
                foreach($matches[0] as $script){
                    $output .= "<script>{$script}</script>";
                }
            }
        }


        return $output;

    }

    public static function generateNewIDs(&$attributes){

        if ($attributes){

            foreach($attributes as $attribute){

                $attribute->id = \elbp_rand_str(10);

            }

        }

    }


}










class ELBPFormElement {

    public $id;
    public $name;
    public $type;
    public $display;
    public $default;
    public $instructions;
    public $options = array();
    public $validation = array();
    public $other = array();
    public $studentID = false;

    public $usersValue = false; // The value the user submitted/stored in db

    public $obj;

    const DEFAULT_RATING_MAX = 5;

    public function __construct() {

        $this->id = \elbp_rand_str(10);

    }

    public function setID($id){
        $this->id = $id;
    }

    public function setName($name){
        $this->name = $name;
        return $this;
    }

    public function setType($type){
        $this->type = $type;
        return $this;
    }

    public function getDisplayIcon(){
        if ($this->display == 'main'){
            return 'layout_content.png';
        } elseif ($this->display == 'side'){
            return 'layout_sidebar.png';
        } else {
            return 'question.png';
        }
    }

    public function setDisplay($display){
        $this->display = trim($display);
        return $this;
    }

    public function setDefault($default){
        $this->default = trim($default);
        return $this;
    }

    public function setStudentID($id){
        $this->studentID = $id;
        return $this;
    }

    public function setValue($value){
        $this->usersValue = $value;
        return $this;
    }

    public function setInstructions($val){
        $this->instructions = $val;
        return $this;
    }

    public function getValue(){

        if (!$this->usersValue || $this->usersValue == '') return $this->default;
        else return $this->usersValue;

    }

    public function displayValue($print = false, $summary = false){

        global $CFG;

        if ($this->type == 'Rating' && !$print)
        {

            $name = \elbp_html($this->name);
            $studentAttributes = $this->obj->getStudentAttributes();
            if (isset($studentAttributes[$name])){
                $this->setValue($studentAttributes[$name]);
            }
            $this->readonly = true;
            $el = $this->convertToFormElement();
            $this->readonly = false;
            return $el;

        }
        elseif ($this->type == 'Rating')
        {
            $value = $this->obj->getAttribute( $this->name );
            $this->setValue($value);
            return $this->getValue() . '/' . $this->other['max'];
        }
        elseif ($this->type == 'File')
        {

            $value = $this->obj->getAttributeAsIs( $this->name );
            $this->setValue($value);

            $output = "";

            if (!$value) return get_string('na', 'block_elbp');

            $icon = \elbp_get_file_icon($value);
            if ($icon)
            {
                $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/file_icons/{$icon}' alt='' /> ";
            }

            if (!$print)
            {
                $code = \elbp_create_data_path_code( $CFG->dataroot . '/ELBP/' . $this->getValue() );
                $output .= "<a href='{$CFG->wwwroot}/blocks/elbp/download.php?f={$code}' target='_blank'>";
            }

            $output .= \basename($value);

            if (!$print)
            {
                $output .= "</a>";
            }

            return $output;

        }
        elseif ($this->type == 'Matrix')
        {

            $name = \elbp_html($this->name);

            if ($this->obj){
                $studentAttributes = $this->obj->getStudentAttributes();
            }

            $output = "";

            if ($this->display == 'side' || $summary)
            {

                $output .= "<br>";
                if ($this->other['rows'])
                {
                    foreach($this->other['rows'] as $row)
                    {
                        $output .= "<b>{$row}</b>: ";

                        if (isset($studentAttributes[$name . '_' . $row]))
                        {
                            $output .= $studentAttributes[$name . '_' . $row];
                        }
                        else
                        {
                            $output .= get_string('na', 'block_elbp');
                        }

                        $output .= "<br>";
                    }
                }

            }
            elseif ($this->display == 'main')
            {

                $output .= "<table class='elbp_matrix_display'>";

                    $output .= "<tr>";

                        $output .= "<th></th>";

                        if ($this->other['cols'])
                        {
                            foreach($this->other['cols'] as $col)
                            {
                                $output .= "<th>{$col}</th>";
                            }
                        }

                    $output .= "</tr>";

                    if ($this->other['rows'])
                    {
                        foreach($this->other['rows'] as $row)
                        {
                            $output .= "<tr>";

                                $output .= "<td>{$row}</td>";

                                $row = elbp_html($row);

                                foreach($this->other['cols'] as $col)
                                {
                                    $chk = (isset($studentAttributes[$name . '_' . $row]) && $studentAttributes[$name . '_' . $row] == $col) ? true : false;
                                    $output .= "<td>";
                                        if ($chk)
                                        {
                                            $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/tick.png' style='width:16px;' alt='ticked' />";
                                        }
                                    $output .= "</td>";
                                }

                            $output .= "</tr>";
                        }
                    }

                $output .= "</table>";

            }

            return $output;

        }
        elseif ($this->type == 'Description')
        {
            return $this->default;
        }
        else
        {

            if ($this->obj){
                $value = $this->obj->getAttribute( $this->name, true );
                $this->setValue($value);
            }
            return $this->getValue();

        }
    }

    public function loadObject($obj){
        $this->obj = $obj;
        return $this;
    }

    /**
     * Get the name of the element safe for passing through js
     * @return type
     */
    public function getJsSafeName(){
        $name = addslashes($this->name);
        $name = str_replace( array("\r\n", "\r", "\n"), '\n', $name );
        return $name;
    }

    public function getJsSafeInstructions(){
        $inst = addslashes($this->instructions);
        $inst = str_replace( array("\r\n", "\r", "\n"), '\n', $inst );
        return $inst;
    }

    /**
     * Get the default of the element safe for passing through js
     * @return type
     */
    public function getJsSafeDefault(){
        $default = addslashes($this->default);
        $default = str_replace( array("\r\n", "\r", "\n"), '\n', $default );
        return $default;
    }

    /**
     * Get a js array of the options
     * @return string
     */
    public function getJsSafeOptions(){

        $output = "[";

        $opt = array();

        if ($this->options)
        {
            foreach($this->options as $option)
            {
                $opt[] = "'" . addslashes($option) . "'";
            }
        }

        $output .= implode(",", $opt);

        $output .= "]";
        return $output;

    }

    /**
     * Get a js array of the validation
     * @return string
     */
    public function getJsSafeValidation(){

        $output = "[";

        $opt = array();

        if ($this->validation)
        {
            foreach($this->validation as $vald)
            {
                $opt[] = "'" . addslashes($vald) . "'";
            }
        }

        $output .= implode(",", $opt);

        $output .= "]";
        return $output;

    }

    public function getJsSafeOther(){

        $output = "";

        if ($this->other)
        {

            foreach($this->other as $key => $value)
            {

                // If object (from saved json encoded object) convert to array
                if (is_object($value)){
                    $value = (array)$value;
                }

                if (is_array($value)){

                    $arr = array();

                    $output .= "f.other.{$key} = [";

                    foreach($value as $val)
                    {
                        $arr[] = "'".addslashes($val)."'";
                    }

                    $output .= implode(",", $arr);

                    $output .= "]\n";

                } else {

                    $output .= "f.other.{$key} = '{$value}';\n";

                }

            }

        }

        return $output;

    }

    /**
     * Get any extra fields this element type has and put them into the js object
     */
    public function getJsExtraFields(){
        ;
    }

    /**
     * Set property value
     * @param type $field
     * @param type $value
     */
    public function set($field, $value){
        $this->$field = $value;
        return $this;
    }

    public function addOption($option){
        $this->options[] = $option;
        return $this;
    }

    public function setOptions($options){
        $this->options = $options;
        return $this;
    }

    public function addValidation($validation, $value = false){
        $v = array(
            'type' => $validation,
            'value' => $value
        );
        $this->validation[] = $v;
        return $this;
    }

    public function setValidation($validation){
        $this->validation = (array)$validation;
        return $this;
    }

    public function setOther($other){

        if ($this->canHaveOther()){
            if ($other){
                foreach($other as $key => $val){
                    $this->other[$key] = $val;
                }
            }
        }

    }

    /**
     * Validate a value given in an input field to see if it meets the validation requirements
     * @param type $response
     * @param type $type
     * @param mixed $val If this is set it's a value related to the validation. E.g. if LENGTH, it is the min length
     * @return boolean
     */
    public function validateResponse($response, $type, $val = false)
    {

        // If we can't validate this element type, or the validation type is invalid, there is nothing to
        // pass, so return true
        if (!$this->canHaveValidation() || !$this->validation || !$type || $type == ''){
            return true;
        }



        // If is array - This could happen. E.g. select/checkbox, etc... because they CAN have multiple values
        // In which case, recursivly call this
        if (is_array($response)){

            // If the type is MIN_LENGTH and we are a Checkbox, count the responses
            if ($this->type == 'Checkbox' && $type == "MIN_LENGTH"){
                return ( count($response) >= $val );
            }

            foreach($response as $value){

                if (!$this->validateResponse($value, $type)){
                    return false;
                }
            }

            return true;

        }

        // If it's a text editor, it can send blank tags, so remove those
        if ($this->type == 'Moodle Text Editor'){
            $response = str_replace("<p> </p>", "", $response);
        }

        // Trim whitespace from the ends
        $response = trim($response);

        // If the element has options, and the response sent is not in the options array, return false
        if ($this->options && !empty($this->options))
        {
            if (!in_array($response, $this->options))
            {
                return false;
            }
        }


        switch($type)
        {

            // Just must contain something, other than just whitespace
            case 'REQUIRED':
                $regex = "/.+/";
                if (preg_match($regex, $response) && $response != '') return true;
            break;

            // Letters and spaces only
            case 'TEXT_ONLY':
                $regex = "/[^a-z ]/i";
                if (!preg_match($regex, $response) && $response != '') return true;
            break;

            // Numbers only (allow decimals)
            case 'NUMBERS_ONLY':
                $regex = "/^[0-9]+\.?[0-9]*$/i";
                if (preg_match($regex, $response) && $response != '') return true;
            break;

            // Letters, spaces and numbers only
            case 'ALPHANUMERIC_ONLY':
                $regex = "/[^0-9a-z ]/i";
                if (!preg_match($regex, $response) && $response != '') return true;
            break;

            // Date in the format used by datepicker (dd-mm-yyyy)
            case 'DATE':
                $regex = "/^\d{2}-\d{2}-\d{4}$/i";
                if (preg_match($regex, $response) && $response != '') return true;
            break;

            // Email address
            case 'EMAIL':
                // This regex has a few trade offs:
                // Due to the possibility of having the account on a subdomain (E.g. something@student.bedford.ac.uk),
                // it allows dots in the domain. So there, something like: something@student.bed would in fact validate as true
                // All we can do is try the best to validate what it should be, but if the user wants to enter in incorrect
                // address in, there's nothing we could do to stop them anyway.
                $regex = "/^[a-z0-9_\.]+@[a-z0-9\.]+\.[a-z\.]{2,4}[a-z]{1}$/i";
                if (preg_match($regex, $response) && $response != '') return true;
            break;

            // Phone number
            case 'PHONE':
                // This one just checks that it is at least 6 numbers, as it could be mobile number, home number w/ area code,
                // home number w/o area code, etc... so just do a basic check.
                // Doesn't allow spaces though
                $regex = "/^(\+\d{1,}\s?)?0\d{4}\s?\d{6}$/";
                if (preg_match($regex, $response) && $response != '') return true;
            break;

            // Website url
            case 'URL':
                $regex = "/(((http|ftp|https):\/{2})+(([0-9a-z_-]+\.)+(aero|asia|biz|cat|com|coop|edu|gov|info|int|jobs|mil|mobi|museum|name|net|org|pro|tel|travel|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cx|cy|cz|cz|de|dj|dk|dm|do|dz|ec|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mn|mn|mo|mp|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|nom|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ra|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw|arpa)(:[0-9]+)?((\/([~0-9a-zA-Z\#\+\%@\.\/_-]+))?(\?[0-9a-zA-Z\+\%@\/&\[\];=_-]+)?)?))\b/imuS";
                if (preg_match($regex, $response) && $response != '') return true;
            break;

            // Length - If the type if a checkbox, then it will check at least that many ticked
            // Otherwise it'll just do a strlen
            case 'MIN_LENGTH':
                $response = (string)$response;
                return (strlen($response) >= $val);
            break;

        }

        return false;

    }

    public function canHaveDefault(){

        switch($this->type)
        {

            case "User Picker":
            case "Course Picker":
            case "My Courses":
            case "File":
            case "Rating":
                return false;
            break;

            default:
                return true;
            break;

        }

    }

    /**
     * Can a given element type have validation?
     * @param type $type
     * @return type
     */
    public function canHaveValidation(){

        switch($this->type)
        {

            case "Text":
            case "Textbox":
            case "Moodle Text Editor":
            case "Select":
            case "Multi Select":
            case "Radio Button":
            case "Date":
            case "My Courses":
            case "Checkbox":
            case "Rating":
                return true;
            break;

            default:
                return false;
            break;

        }

    }

    public function canHaveOther(){
        switch($this->type)
        {
            case "Matrix":
            case "Rating":
                return true;
            break;
            default:
                return false;
            break;
        }
    }

    public function canHaveOptions(){

        switch($this->type)
        {
            case "Select":
            case "Multi Select":
            case "Radio Button":
            case "Checkbox":
                return true;
            break;

            default:
                return false;
            break;

        }

    }

    private function getValidationString()
    {

        $return = array();
        if ($this->validation)
        {
            foreach($this->validation as $validation)
            {
                $return[] = $validation;
            }
        }

        return implode(",", $return);

    }

    /**
     * Convert an old type to the new type
     * @param type $oldType
     * @return string
     */
    public function convertOldTypeToNew($oldType){

        switch ($oldType)
        {

            case 'Text':
                return "Text";
            break;

            case 'Radio':
                return "Radio Button";
            break;

            case 'Datepicker':
                return "Date";
            break;

            case 'MyCoursePicker':
                return "My Courses";
            break;

            case 'Coursepicker':
                return "Course Picker";
            break;

            case 'Userpicker':
                return "User Picker";
            break;

            default:
                return $oldType;
            break;

        }

    }

    /**
     * Convert an element object to an actual HTML: form element
     * @global type $CFG
     * @param type $value
     * @param type $options
     * @return string
     */
    public function convertToFormElement( $value = null, $options = false )
    {

        global $CFG;

        if (!is_null($value))
        {
            $this->setValue($value);
        }
        elseif ($value === false)
        {
            $this->setValue(false);
        }
        else
        {

            // if the object is loaded, get the attribute as it is, otherwise it will bring back N/A
            // if it's empty
            if ($this->obj && method_exists($this->obj, 'getAttributeAsIs')){
                $value = $this->obj->getAttributeAsIs( $this->name, true );
                $this->setValue($value);
            }

        }

        if (isset($options['wrap-name'])){
            $this->name = "{$options['wrap-name']}[{$this->name}]";
        }

        switch ( strtolower($this->type) )
        {

            // Single text input
            case 'text':
                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }
                $output .= "<input type='text' name='".elbp_html($this->name)."' class='normal elbp_form_field' value='".elbp_html($this->getValue())."' validation='{$this->getValidationString()}' />";
                return $output;
            break;

            // Textbox
            case 'textbox':
                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }
                $output .= "<textarea class='elbp_textarea elbp_form_field' name='".elbp_html($this->name)."' validation='{$this->getValidationString()}'>".$this->getValue()."</textarea>";
                return $output;
            break;

            // Moodle Text Editor
            case 'moodle text editor':

                // Replace all non-alphanumeric & spaces
                $id = str_replace(" ", "_", $this->name);
                $id = preg_replace("/[^a-z0-9_]/i", "", $id);

                require_once $CFG->dirroot.'/lib/form/editor.php';
                require_once $CFG->dirroot . '/lib/editorlib.php';

                $editor = \editors_get_preferred_editor();
                $editor->use_editor("elbpfe_{$id}_{$this->id}", array('autosave' => false));

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                $output .= \html_writer::tag('textarea', $this->getValue(),
                    array('id' => "elbpfe_{$id}_{$this->id}", 'name' => elbp_html($this->name), 'class' => 'elbp_textarea elbp_texteditor elbp_form_field', 'validation' => $this->getValidationString(), 'rows' => 5, 'cols' => 10));

                //$output .= "<textarea id='elbpfe_{$id}_{$this->id}' class='elbp_textarea elbp_texteditor' name='".elbp_html($this->name)."' validation='{$this->getValidationString()}'>".$this->getValue()."</textarea>";

                return $output;

            break;

            case 'select':

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                $output .= "<select name='".elbp_html($this->name)."' validation='{$this->getValidationString()}' class='elbp_form_field'>";
                    if ($this->options)
                    {
                        $output .= "<option value=''></option>";
                        foreach($this->options as $option)
                        {
                            $sel = ($option == $this->getValue()) ? "selected" : "";
                            $output .= "<option value='".elbp_html($option)."' {$sel}>{$option}</option>";
                        }
                    }
                $output .= "</select>";
                return $output;

            break;

            case 'multi select':

                $value = (array)$this->getValue();

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                $output .= "<select name='".elbp_html($this->name)."' multiple='multiple' validation='{$this->getValidationString()}' class='elbp_form_field'>";
                    if ($this->options)
                    {
                        foreach($this->options as $option)
                        {
                            $sel = (in_array($option, $value)) ? "selected" : "";
                            $output .= "<option value='".elbp_html($option)."' {$sel}>{$option}</option>";
                        }
                    }
                $output .= "</select>";
                return $output;

            break;

            case 'checkbox':

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                    if ($this->options)
                    {
                        foreach($this->options as $option)
                        {
                            $value = $this->getValue();
                            if (is_array($value)){
                                $chk = (in_array($option, $value)) ? "checked" : "";
                            } else {
                                $chk = ($option == $value) ? "checked" : "";
                            }
                            $output .= "<input type='checkbox' name='".elbp_html($this->name)."' value='".elbp_html($option)."' {$chk} validation='{$this->getValidationString()}' class='elbp_form_field' /> {$option} <br>";
                        }
                    }

                return $output;

            break;

            case 'radio button':

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                if ($this->options)
                {
                    foreach($this->options as $option)
                    {
                        $chk = ($option == $this->getValue()) ? "checked" : "";
                        $output .= "<input type='radio' name='".elbp_html($this->name)."' value='".elbp_html($option)."' {$chk} validation='{$this->getValidationString()}' class='elbp_form_field' /> {$option} <br>";
                    }
                }
                return $output;

            break;

            case 'date':
                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }
                $output .= "<input type='text' name='".elbp_html($this->name)."' class='elbp_datepicker elbp_form_field' value='".elbp_html($this->getValue())."' validation='{$this->getValidationString()}' />";
                return $output;
            break;

            // New file section using new fileupload plugin
            case 'file':

              $value = $this->getValue();
              $file = $CFG->dataroot . '/ELBP/' . \elbp_sanitize_path($value);
              $id = \elbp_strip_to_plain($this->name) . "_" . $this->id;

              $output = "";

              if ($this->instructions){
                  $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
              }

              if ($value && file_exists($file))
              {
                  $output .= "<span id='filevalue-{$id}'>";
                  $icon = \elbp_get_file_icon($file);
                  if ($icon)
                  {
                      $output .= "<img src='{$CFG->wwwroot}/blocks/elbp/pix/file_icons/{$icon}' alt='' /> ";
                  }
                  $output .= \basename($file) . "<br></span>";
              }


              // Output messages
              $output .= "<div id='output_messages-{$id}' class='elbp_centre'></div>";

              // File upload button
              $output .= "<span class='btn btn-success fileinput-button'><i class='glyphicon glyphicon-plus'></i><span>".get_string('selectfile', 'block_elbp')."</span><input id='{$id}' class='elbp_fileupload' type='file' name='file' multiple></span>";
              $output .= "<input id='hidden-file-{$id}' type='hidden' name='".elbp_html($this->name)."' value='' class='elbp_form_field' />";

              $output .= "<br><br>";

              // Progress bar
              $output .= "<div class='elbp_progress'><div id='progress-{$id}' class='elbp_progress progress-bar green stripes' style='display:none;'><div id='progress-amount-{$id}' style='width:0;'></div></div></div>";

              // Uploaded files container
              $output .= "<div id='files-{$id}' class='files'></div>";

              return $output;


            break;


            case 'description':
                return "<span>{$this->getValue()}</span>";
            break;

            case 'user picker':

                $value = $this->getValue();

                $output = "";

                    if ($this->instructions){
                        $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                    }

                    $output .= "<table class='elbp_user_picker'>";
                    $output .= "<tr>";

                        $output .= "<td class='elbp_user_picker_search_div'>";

                            // Results from AJAX search
                            $output .= "<small>".get_string('resultslimited', 'block_elbp').": 100</small><br>";
                            $output .= "<select class='user_list' multiple='multiple'>";

                            // By default get the first 100 users
                            $limit = 100;
                            $ELBPDB = new \ELBP\DB();
                            $users = $ELBPDB->getUsers($limit);
                            if ($users)
                            {

                                foreach($users as $user)
                                {
                                    $output .= "<option value='".elbp_html($user->username)."'>".fullname($user)." ({$user->username})</option>";
                                }

                                if (count($users) == $limit)
                                {
                                    $output .= "<option value='' disabled>---- ".get_string('moreresults', 'block_elbp')." ----</option>";
                                }

                            }

                            $output .= "</select>";
                            $output .= "<br><br>";

                            // Search box
                            $output .= "<input type='text' placeholder='".get_string('searchuser', 'block_elbp')."...' onkeyup='ELBP.user_picker.search_user(this.value, this);return false;' />";

                        $output .= "</td>";


                        $output .= "<td class='elbp_user_picker_buttons_div'>";
                            $output .= "<button onclick='ELBP.user_picker.add( this );return false;'>&#9654;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".get_string('add')."</button><br>";
                            $output .= "<button onclick='ELBP.user_picker.remove( this );return false;'>&#9664;&nbsp;&nbsp;&nbsp;".get_string('remove')."</button><br>";
                        $output .= "</td>";


                        $output .= "<td class='elbp_user_picker_chosen_div'>";

                            $output .= "<small>".get_string('selectedusers', 'block_elbp')."</small><br>";
                            $output .= "<select class='userholder' multiple='multiple'>";

                                if ($value){
                                    if (is_array($value)){
                                        foreach($value as $val){
                                            $output .= "<option value='".elbp_html($val)."'>".elbp_html($val)."</option>";
                                        }
                                    } else {
                                        $output .= "<option value='".elbp_html($value)."'>".elbp_html($value)."</option>";
                                    }
                                }

                            $output .= "</select>";
                            $output .= "<div class='userpickerhiddeninputs' fieldname='".elbp_html($this->name)."' style='display:none;'>";

                                if ($value){
                                    if (is_array($value)){
                                        foreach($value as $val){
                                            $output .= "<input type='hidden' name='".elbp_html($this->name)."' value='".elbp_html($val)."' />";
                                        }
                                    } else {
                                        $output .= "<input type='hidden' name='".elbp_html($this->name)."' value='".elbp_html($value)."' />";
                                    }
                                }

                            $output .= "</div>";

                        $output .= "</td>";

                        $output .= "</tr>";
                    $output .= "</table>";

                return $output;

            break;

            case 'course picker':

                $value = $this->getValue();
                $output = "";

                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                $use = '';
                if (isset($options['use'])){
                    $use = $options['use'];
                }

                $output .= "<table class='elbp_course_picker'>";
                $output .= "<tr>";

                    $output .= "<td class='elbp_course_picker_search_div'>";

                        // Category
                        $output .= "<select class='cat_picker' onchange='ELBP.course_picker.choose_category(this.value, this, \"{$use}\");return false;'>";
                        $output .= "<option value=''>".get_string('choosecategory', 'block_elbp')."...</option>";
                            $cats = \core_course_category::make_categories_list();
                            asort($cats);
                            if ($cats)
                            {
                                foreach($cats as $catID => $path)
                                {
                                    $output .= "<option value='{$catID}'>{$path}</option>";
                                }
                            }
                        $output .= "</select>";
                        $output .= "<br><br>";

                        // Results from AJAX search
                        $output .= "<div id='category_picker_pick_courses'>";
                        $output .= "<select class='course_list' multiple='multiple'>";

                        $output .= "</select>";
                        $output .= "<br><br>";

                        // Search box

                        $output .= "<input type='text' placeholder='".get_string('searchcourse', 'block_elbp')."...' onkeyup='ELBP.course_picker.search_course(this.value, this, \"{$use}\");return false;' />";
                        $output .= "</div>";

                    $output .= "</td>";


                    $output .= "<td class='elbp_course_picker_buttons_div'>";
                        $output .= "<button onclick='ELBP.course_picker.add( this );return false;'>&#9654;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".get_string('add')."</button><br>";
                        $output .= "<button onclick='ELBP.course_picker.remove( this );return false;'>&#9664;&nbsp;&nbsp;&nbsp;".get_string('remove')."</button><br>";
                    $output .= "</td>";


                    $output .= "<td class='elbp_course_picker_chosen_div'>";

                        $output .= "<small>".get_string('selectedcourses', 'block_elbp')."</small><br>";
                        $output .= "<select class='courseholder' multiple='multiple'>";

                            if ($value){
                                if (is_array($value)){
                                    foreach($value as $val){
                                        $output .= "<option value='".elbp_html($val)."'>".elbp_html($val)."</option>";
                                    }
                                } else {
                                    $output .= "<option value='".elbp_html($value)."'>".elbp_html($value)."</option>";
                                }
                            }

                        $output .= "</select>";
                        $output .= "<div class='coursepickerhiddeninputs' fieldname='".elbp_html($this->name)."' style='display:none;'>";

                            if ($value){
                                if (is_array($value)){
                                    foreach($value as $val){
                                        $output .= "<input type='hidden' name='".elbp_html($this->name)."' value='".elbp_html($val)."' />";
                                    }
                                } else {
                                    $output .= "<input type='hidden' name='".elbp_html($this->name)."' value='".elbp_html($value)."' />";
                                }
                            }

                        $output .= "</div>";

                    $output .= "</td>";

                    $output .= "</tr>";
                $output .= "</table>";

                return $output;

            break;

            case 'my courses':

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                $output .= "<select name='".elbp_html($this->name)."' validation='{$this->getValidationString()}' class='elbp_form_field'>";
                    $output .= "<option value=''></option>";

                    if ($this->studentID)
                    {
                        $ELBPDB = new \ELBP\DB();
                        $courses = $ELBPDB->getStudentsCourses($this->studentID);
                        if ($courses)
                        {
                            foreach($courses as $course)
                            {
                                $courseName = \elbp_html($course->fullname);
                                $sel = ($course->fullname == $this->getValue()) ? 'selected' : '';
                                $output .= "<option value='{$courseName}' {$sel} >{$courseName}</option>";
                            }
                        }
                    }

                $output .= "</select>";
                return $output;

            break;

            case 'rating':

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                $max = (isset($this->other['max'])) ? $this->other['max'] : self::DEFAULT_RATING_MAX;
                $readonly = (isset($this->readonly) && $this->readonly == true) ? 1 : 0;
                $output .= "<div class='elbp_rate' data-readonly='{$readonly}' score-name='".elbp_html($this->name)."' data-score='{$this->getValue()}' data-number='{$max}'></div>";
                return $output;
            break;

            case 'matrix':

                $name = elbp_html($this->name);
                $value = $this->getValue();

                $output = "";
                if ($this->instructions){
                    $output .= "<span class='elbp_attribute_instructions'><small>".elbp_html($this->instructions)."</small><br><br></span>";
                }

                if ($this->other && isset($this->other['cols'], $this->other['rows']) && $this->other['cols'] && $this->other['rows'] )
                {

                    $output .= "<table class='elbp_matrix'>";

                        $output .= "<tr style='".( ($this->obj) ? $this->obj->getHeaderStyle() : '' )."'>";

                            $output .= "<th></th>";
                            foreach($this->other['cols'] as $col)
                            {
                                $output .= "<th>{$col}</th>";
                            }

                        $output .= "</tr>";

                        $i = 0;

                        foreach($this->other['rows'] as $row)
                        {

                            $i++;
                            $output .= "<tr>";

                                $output .= "<td style='".( ($this->obj) ? $this->obj->getHeaderStyle() : '' )."'>{$row}</td>";

                                foreach($this->other['cols'] as $col)
                                {
                                    $row = elbp_html($row);
                                    $chk = (isset($value["{$row}"]) && $value["{$row}"] == $col) ? 'checked' : '';
                                    $output .= "<td><input type='radio' name='{$name}_{$row}' class='elbp_form_field' value='{$col}' {$chk} /></td>";
                                }

                            $output .= "</tr>";

                        }

                    $output .= "</table>";

                }

                return $output;

            break;

        }

    }

    /**
     * Create an element object from an array of params, sent from edit form
     * @param type $params
     */
    public static function create($params){

        $obj = new \ELBP\ELBPFormElement();

        if (is_array($params))
        {

            if (isset($params['id'])){
                $obj->setID($params['id']);
            }

            if (isset($params['name'])){
                $obj->setName($params['name']);
            }

            if (isset($params['type'])){
                $obj->setType($params['type']);
            }

            if (isset($params['display'])){
                $obj->setDisplay($params['display']);
            }

            if (isset($params['options'])){
                $obj->setOptions($params['options']);
            }

            if (isset($params['default'])){
                $obj->setDefault($params['default']);
            }

            if (isset($params['validation'])){
                $obj->setValidation($params['validation']);
            }

            if (isset($params['instructions'])){
                $obj->setInstructions($params['instructions']);
            }

            // Anything else here, e.g. matrix has more complex options
            if (isset($params['other'])){
                $obj->setOther($params['other']);
            }

            // Dynamic number on screen
            if (isset($params['num'])){
                $obj->num = $params['num'];
            }

        } elseif (is_object($params)){

            if (isset($params->id)){
                $obj->setID($params->id);
            }

            if (isset($params->name)){
                $obj->setName($params->name);
            }

            if (isset($params->type)){
                $obj->setType($params->type);
            }

            if (isset($params->display)){
                $obj->setDisplay($params->display);
            }

            if (isset($params->options)){
                $obj->setOptions($params->options);
            }

            if (isset($params->default)){
                $obj->setDefault($params->default);
            }

            if (isset($params->validation)){
                $obj->setValidation($params->validation);
            }

            if (isset($params->instructions)){
                $obj->setInstructions($params->instructions);
            }

            if (isset($params->other)){
                $obj->setOther($params->other);
            }

        }

        return $obj;

    }




}