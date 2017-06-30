<?php

/**
 * Class for file uploading
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

namespace ELBP;

/**
 * 
 */
class Upload {
    
    private $file;
    private $mime_types;
    private $upload_dir;
    private $max_size;
    private $result;
    private $error_msg;
    public $filename; 
    
    public function __construct() {
        
        global $CFG;
        
        $this->result = false;
        $this->error_msg = '';
        $this->mime_types = array();
        
    }
        
    /**
     * Set the mime types we are allowing
     * @param type $mimes
     * @return \ELBP\Upload
     */
    public function setMimeTypes($mimes){
        $this->mime_types = $mimes;
        return $this;
    }
    
    /**
     * Set the directory we are uploading to
     * @param type $dir
     * @return \ELBP\Upload
     */
    public function setUploadDir($dir){
        $this->upload_dir = $dir;
        return $this;
    }
    
    /**
     * Set the max file size we are allowing
     * @param type $size
     * @return \ELBP\Upload
     */
    public function setMaxSize($size){
        $this->max_size = $size;
        return $this;
    }
    
    /**
     * Get the max file size allowed. Either set by us, or get the server default.
     * @return type
     */
    private function getMaxSize(){
        return (is_null($this->max_size)) ? return_bytes_from_upload_max_filesize( ini_get('upload_max_filesize') ) : return_bytes_from_upload_max_filesize($this->max_size);
    }
    
    /**
     * Get a human readable string of the max file size allowed
     * ???
     * @return type
     */
    private function getMaxSizeString(){
        return (is_null($this->max_size)) ? ini_get('upload_max_filesize') : $this->max_size;
    }
    
    /**
     * Set the _FILES file into the object
     * @param type $file
     * @return \ELBP\Upload
     */
    public function setFile($file){
        $this->file = $file;
        return $this;
    }
    
    /**
     * Get any error messages
     * @return type
     */
    public function getErrorMessage(){
        return $this->error_msg;
    }
    
    /**
     * Get the result of the upload
     * @return bool
     */
    public function getResult(){
        return $this->result;
    }
    
    /**
     * If there are errors, get a string for what that error type was
     * @return string
     */
    public function getUploadErrorCodeMessage(){
        
        if ($this->file['error'] > 0)
        {
            switch($this->file['error'])
            {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:    
                    return get_string('uploads:filetoolarge', 'block_elbp');
                break;
                
                case UPLOAD_ERR_PARTIAL:
                    return get_string('uploads:onlypartial', 'block_elbp');
                break;
            
                case UPLOAD_ERR_NO_FILE:
                    return get_string('uploads:filenotset', 'block_elbp');
                break;
            
                case UPLOAD_ERR_NO_TMP_DIR:
                    return get_string('uploads:notmpdir', 'block_elbp');
                break;
            
                case UPLOAD_ERR_CANT_WRITE:
                    return get_string('uploads:dirnoexist', 'block_elbp');
                break;
            
                case UPLOAD_ERR_EXTENSION:
                    return get_string('uploads:phpextension', 'block_elbp');
                break;
            
            }
        }
        
        return '';
        
    }
    
    /**
     * Run the file upload
     * @return type
     */
    public function doUpload(){
        
        // Make sure required things are set:
        
        $fInfo = \finfo_open(FILEINFO_MIME_TYPE);
            $mime = \finfo_file($fInfo, $this->file['tmp_name']);
        \finfo_close($fInfo);
        
        // Mime types not set
        if (is_null($this->mime_types)){
            return array('success' => false,'error' => get_string('uploads:mimetypesnotset', 'block_elbp'));
        }
        
        // Upload directory not set
        if (is_null($this->upload_dir)){
            return array('success' => false,'error' => get_string('uploads:uploaddirnotset', 'block_elbp'));
        }
        
        // File not set
        if (is_null($this->file)){
            return array('success' => false,'error' => get_string('uploads:filenotset', 'block_elbp'));
        }
        
        // Check size of file
        if ($this->file['size'] > $this->getMaxSize()){
            return array('success' => false,'error' => get_string('uploads:filetoolarge', 'block_elbp') . " ( ".convert_bytes_to_hr($this->file['size'])." ::: {$this->getMaxSizeString()} )");
        }
        
        // Check mime type
        if ($this->mime_types && !in_array($mime, $this->mime_types)){
            return array('success' => false,'error' => get_string('uploads:invalidmimetype', 'block_elbp') . " ( {$mime} )");
        }
        
        // Check upload directory exists and is writable
        if (!is_dir($this->upload_dir)){
            return array('success' => false,'error' => get_string('uploads:dirnoexist', 'block_elbp'));
        }
        
        // Get the ext and name from the file
        $fileExt = elbp_get_file_extension($this->file['name']);
        $fileName = $this->file['name'];
              
        if (!isset($this->doNotChangeFileName) || !$this->doNotChangeFileName){
        
            $fileName = elbp_generate_random_string(15, true);

            // If filename already exists, try a different one
            while (file_exists($this->upload_dir . $fileName . '.' . $fileExt)){
                $fileName = elbp_generate_random_string(15, true);
            }
        
        }
        
        if (!isset($this->doNotChangeFileName) || !$this->doNotChangeFileName){
            $this->filename = $fileName . '.' . $fileExt;
        } else {
            $this->filename = $fileName;
        }

        // Try and move the file
        $result = move_uploaded_file($this->file['tmp_name'], $this->upload_dir . $this->filename);
        if (!$result){
            return array('success' => false, 'error' => get_string('uploads:unknownerror', 'block_elbp') . '['.$this->file['error'].']');
        }
        
        // OK
        $this->result = true;
                
        return array('success' => true);
        
    }
    
}