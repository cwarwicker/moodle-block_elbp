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
 * This class represents the instance of an 'item'/'record' inside a plugin.
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

namespace block_elbp;

defined('MOODLE_INTERNAL') or die();

/**
 *
 */
abstract class BasePluginObject {

    /**
     * Return an attribute from the $this->attributes property, which were loaded earlier
     * @param type $name
     * @param type $na
     * @return string
     */
    public function getAttribute($name, $na = false) {

        // First check to do is see if it's an array. If it is, we will implode it to a string
        if (isset($this->attributes[$name]) && is_array($this->attributes[$name]) && !empty($this->attributes[$name])) {
            return implode(", ", $this->attributes[$name]);
        }

        if (isset($this->attributes[$name]) && is_array($this->attributes[$name]) && empty($this->attributes[$name]) && $na) {
            return get_string('na', 'block_elbp');
        }

        // Not an array - string/int (well...string)
        if (isset($this->attributes[$name]) && $this->attributes[$name] == '' && $na) {
            return get_string('na', 'block_elbp');
        }

        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        if ($na) {
            return get_string('na', 'block_elbp');
        }

        return "";
    }

    /**
     * Return an attribute without doing anything to it.
     * So for eaxmple if it's an array, it'll return an array instead of imploding it
     * @param type $name
     * @return type
     */
    public function getAttributeAsIs($name) {
        return (isset($this->attributes[$name])) ? $this->attributes[$name] : false;
    }

    /**
     * Return all the attributes
     * @return type
     */
    public function getAttributes() {
        return $this->attributes;
    }

    public function getStudentAttributes() {
        return $this->getAttributes();
    }


    /**
     * Load all the attributes into an array
     * @param type $check
     * @return type
     */
    protected function _loadAttributes($check) {

        $results = array();

        if ($check) {
            foreach ($check as $att) {
                // If something already set for this, turn it into an array
                if ( isset($results[$att->field]) && !is_array($results[$att->field]) ) {
                    $tmpArray = array();
                    $tmpArray[] = $results[$att->field];
                    $tmpArray[] = $att->value;
                    $results[$att->field] = $tmpArray;
                } else if ( isset($results[$att->field]) && is_array($results[$att->field]) ) {
                    // If it's already set but it's already been converted to an array, just append new element
                    $results[$att->field][] = $att->value;
                } else {
                    $results[$att->field] = $att->value;
                }
            }
        }

        return $results;

    }

    /**
     * Load object into all attributes
     * @param type $attributes
     */
    public function loadObjectIntoAttributes($attributes) {

        if ($attributes) {
            foreach ($attributes as $attribute) {
                $attribute->loadObject($this);
            }
        }

    }

    /**
     * Given an array of attributes on this plugin, set the student values for all of them for the
     * currently loaded student
     * @param type $data
     */
    protected function setSubmittedAttributes(&$data, $obj) {

        $possibleAttributes = $obj->getElementsFromAttributeString();

        $this->attributes = array();

        if ($possibleAttributes) {
            foreach ($possibleAttributes as $attribute) {

                // If we submitted something for that attribute, add it to the target object
                if (isset($data[$attribute->name])) {

                    if (isset($attribute->options) && $attribute->options && !is_array($data[$attribute->name])) {
                        $this->attributes[$attribute->name] = array($data[$attribute->name]);
                    } else {
                        $this->attributes[$attribute->name] = $data[$attribute->name];
                    }

                    unset($data[$attribute->name]);

                } else {

                    // Matrix elements can't have the exact name, as they need the row in their name
                    // So it won't be found by doing the above, we need to check if there are any
                    // that start with that name
                    $like = false;

                    foreach ($data as $key => $d) {
                        $explode = explode($attribute->name . "_", $key);
                        if ($explode && count($explode) > 1) {
                            $this->attributes[$key] = $d;
                            $like = true;
                        }
                    }

                    if ($like) {
                        unset($data[$attribute->name]);
                    }

                }

            }
        }

        // ANything left over must be attributes from hooks
        foreach ($data as $att => $val) {
            $this->attributes[$att] = $val;
        }

    }

    /**
     * Having uploaded any file attributes to the /tmp/ directory, we now want to move them
     * to a proper directory so they don't get deleted
     * @param type $defaultAttributes
     */
    protected function moveTmpUploadedFiles($defaultAttributes, $obj) {

        global $CFG, $USER;

        $result = true;

        $itemID = $this->getID();

        if ($defaultAttributes) {
            foreach ($defaultAttributes as $attribute) {
                if ($attribute->type == 'File') {

                    // Sanitize the path
                    $this->attributes[$attribute->name] = \elbp_sanitize_path($this->attributes[$attribute->name]);

                    $value = (isset($this->attributes[$attribute->name])) ? $this->attributes[$attribute->name] : false;
                    if ($value) {

                        // Is it a tmp file?
                        if (strpos($value, "tmp:") === 0) {

                            $value = \elbp_sanitize_path( substr($value, (4 - strlen($value))) );
                            $tmpFile = $CFG->dataroot . '/ELBP/tmp/' . $USER->id . '/' . $value;

                            // Create directory
                            if ( $obj->createDataDirectory($itemID) ) {

                                $explode = explode("/", $value);
                                $value = end($explode);

                                // If we specified an itemID then we can use that as the path, as items will be user-specific
                                if ($itemID > 0) {
                                    $newPath = $value;
                                } else {

                                    // If not, we cannot just store it under "0" as uploads with the same name will override and users could see files from
                                    // other user's PLPs. So we will store it under "user-<uid>"
                                    if ( $obj->createDataDirectory($itemID . '/u-' . $this->student->id) ) {
                                        $newPath = 'u-' . $this->student->id . '/' . $value;
                                    } else {
                                        $result = false;
                                    }

                                }

                                // Set the newFile path
                                if ($result) {
                                    $newFile = $CFG->dataroot . '/ELBP/' . $obj->getName() . '/' . $itemID . '/' . $newPath;
                                }

                                // Try and move the tmp file to its new location
                                if ($result && \rename($tmpFile, $newFile)) {
                                    $this->attributes[$attribute->name] = $obj->getName() . '/' . $itemID . '/' . $value;
                                } else {
                                    $result = false;
                                }

                            } else {
                                $result = false;
                            }

                        }

                    }
                }
            }
        }

        return $result;

    }


}