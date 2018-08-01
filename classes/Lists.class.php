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