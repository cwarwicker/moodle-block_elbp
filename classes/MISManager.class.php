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

namespace ELBP\MIS;

// >>BEDCOLL TODO add in post-connection execuet to mis

/**
 * 
 */
abstract class Manager {
    
    
    protected $dbc = false;
    protected $conn = false;
    public $show_conn_err = true;
    protected $lastSQL;
    protected $last_error;
    protected static $acceptedTypes = array();
    private $i = 0;
    
    abstract public function connect($params);
    abstract public function disconnect();
    abstract public function query($sql, $params);
    abstract public function execute($sql, $params);
    abstract public function select($table, $where = null, $fields = "*", $order = null, $limit = null);
    abstract public function update($table, $data, $where, $limit = null);
    abstract public function delete($table, $params, $limit = 1);
    abstract public function insert($table, $data);
    abstract public function fetch($qry);
    abstract public function fetchAll($qry);
    abstract protected function getRecordSet($qry);
    abstract public function wrapValue($value);
    abstract public function convertDateSQL($field, $format);
    abstract public function compareDatesSQL($field, $operator);
    abstract public function getTableInfo($tableName = null, $tablePrefix = null);
    
    public function getRecords($query){
        return $this->getRecordSet($query);
    }
    
    /**
     * Get the mysql error if there is one
     */
    public function getError(){
        return $this->last_error;
    }
    
    public function getDBH(){
        return $this->dbh;
    }
    

    /**
     * Print out the last sql queried
     */
    public function printLastQuery(){
        echo $this->lastSQL;
    }
    
    /**
     * Get the last SQL run
     * @return type
     */
    public function getLastSQL(){
        return $this->lastSQL;
    }
        
    public function comparisonOperator(){
        return " = ";
    }
    
    protected function convertWhiteSpace($value){
        $str = str_replace(" ", "_", $value) . "_" . $this->i;
        $this->i++;
        return $str;
    }

    /**
     * Get an MIS connection from the lbp_mis_connections table, based on a name
     * @param type $name
     */
    public static function getMISConnection($name)
    {
        global $DB;
        return $DB->get_record("lbp_mis_connections", array("name"=>$name));
    }
        
    /**
     * Instantiate object of database connection
     * @param mixed $params Could be string - name of connection in DB, or could be array of type
     */
    public static function instantiate($params)
    {
        
        global $CFG;
        
        if (is_string($params))
        {
            
            $record = self::getMISConnection($params);
            if (!$record){
                throw new \ELBP\ELBPException( get_string('mismanager', 'block_elbp'),  get_string('misconnectionnotfound', 'block_elbp'), $params );
                return false;
            }
            
            $className = ucfirst($record->type);
            $fileName = $CFG->dirroot . '/blocks/elbp/classes/db/'.$className.'.class.php';
            if (!file_exists($fileName)){
                throw new \ELBP\ELBPException( get_string('mismanager', 'block_elbp'),  get_string('filenotfound', 'block_elbp'), $className .'.class.php' );
                return false;
            }
            
            require_once $fileName;
            $className = 'ELBP\MIS\\'.$className;
            return new $className($record);
            
        }
        elseif (is_array($params) || is_object($params))
        {
            if (!isset($params['type'])){
                throw new \ELBP\ELBPException( get_string('mismanager', 'block_elbp'),  get_string('missingparams', 'block_elbp'), 'type' );
                return false;
            }

            // See if we have a class for that
            $className = ucfirst($params['type']);
            $fileName = $CFG->dirroot . '/blocks/elbp/classes/db/'.$className.'.class.php';
            if (!file_exists($fileName)){
                throw new \ELBP\ELBPException( get_string('mismanager', 'block_elbp'),  get_string('filenotfound', 'block_elbp'), $className .'.class.php' );
                return false;
            }
            
            require_once $fileName;
            $className = 'ELBP\MIS\\'.$className;
            return new $className();
            
        }
 
        
    }
    
    /**
     * Get all defined mis connections
     * @global type $DB
     * @return type
     */
    public static function listConnections()
    {
        
        global $DB;
        return $DB->get_records("lbp_mis_connections");        
        
    }
    
    /**
     * Get an array of the types we currently accept
     * @return type
     */
    public static function getAcceptedTypes(){
        return static::$acceptedTypes;
    }
    
    /**
     * Get a MIS connection object based on the database type we are using for Moodle
     * @global type $CFG
     */
    public static function getMoodleConnectionType(){
        
        global $CFG;
                
        $obj = false;
        
        switch($CFG->dbtype)
        {
            
            case 'mysqli':
            case 'mariadb';
                $type = 'MySQL';
            break;
            
            default:
                $type = false;
            break;
            
        }
        
        if ($type)
        {
            $obj = self::instantiate( array('type' => $type) );
        }
        
        return $obj;
        
    }
    
    
}