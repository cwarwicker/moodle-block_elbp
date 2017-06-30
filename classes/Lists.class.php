<?php

/**
 * ELBP List class
 * 
 * This is used to create lists of students in a table, for instance on the mystudents page
 * 
 * Depending on where its called from, you may want various different columns, values, etc... 
 * So we use a class to build up the list
 * 
 * This is barely used, as the lists in My Students isn't used if you have the bc_dashboard block
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
class Lists {
    
    private $content;
    private $header;
    private $rows;
    private $footer;
    
    private $style;
    private $atts;
    
    public function __construct() {
        $this->content = '';
        $this->header = '';
        $this->rows = array();
        $this->footer = '';
        
        $this->style = '';
        $this->atts = '';
        
    }
    
    
    public function addRow(ELBPListRow $row)
    {
        $this->rows[] = $row;
    }
   
    /**
     * Set attributes of the given element
     * e.g. ("colspan"=>"3", "height"=>"100px", "id"=>"mytable") or "colspan='3'"
     * @param type $params
     */
    public function setAttributes($params)
    {
        
        $atts = '';
        
        // If it's an array, set all vals
        if (is_array($params) || is_object($params))
        {
            $params = (array) $params;
            foreach($params as $att => $val)
            {
                $atts .= "{$att}='{$val}'";
            }
        }
        // Else just set the one value sent down
        else
        {
            $atts .= $params;
        }
                
        $this->atts = " {$atts} ";
        
        return $this->atts;
        
    }
    
    
    /**
     * Set a css style for part of the list (e.g. the TABLE or a TD, etc...)
     * @param mixed $params Could be array of styles, or could be one in a string. Format: ("color"=>"red","width"=>"100%") or "color:red;"
     * @return string The style string
     */
    public function setStyle($params)
    {
        
        $style = '';
        
        // If it's an array, set all vals
        if (is_array($params) || is_object($params))
        {
            $params = (array) $params;
            foreach($params as $att => $val)
            {
                $style .= "{$att}:{$val};";
            }
        }
        // Else just set the one value sent down
        else
        {
            $style .= $params;
        }
        
        $style = ' style="'.$style.'" ';
        
        $this->style = $style;
        
        return $this->style;
        
    }
    
    
}


/**
 * Class for a table row
 * Extends the main class, as can reuse some of the methods, such as setStyle, setAttributes, etc...
 * Although not all properties will be used, e.g. header/footer not relevant
 */
class Row extends Lists
{
    
    private $cols = array();
    
    /**
     * Add a column object to the row
     * @param ELBPListCol $col
     */
    public function addCol(ELBPListCol $col)
    {
        $this->cols[] = $col;
    }
    
    /**
     * Get the display HTML to be put into the table
     */
    public function getDisplay()
    {
        
    }
    
    
}

/**
 * Class for table column
 * Extends the main class, as can reuse some of the methods, such as setStyle, setAttributes, etc...
 * Although not all properties will be used, e.g. header/footer not relevant
 */
class Col extends Lists
{
    
    
    private $tag;
    
    public function __construct($tag = 'td') {
        $this->tag = $tag;
        parent::__construct();
    }


    public function setContent($content)
    {
        $this->content = $content;
    }
    
    /**
     * Get the display HTML to be put into the row
     */
    public function getDisplay()
    {
        $output = "";
        $output .= "<{$this->tag}";
        
            // Atts
            if (!empty($this->atts))
            {
                $output .= $this->atts;
            }
            
            // Style
            if (!empty($this->style))
            {
                $output .= $this->style;
            }
        
        $output .= ">";
        
        
        $output .= "</{$this->tag}>";
        
    }
    
}