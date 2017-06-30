<?php

/**
 * Class to deal with connections to external MIS databases
 * 
 * We are going to use this for all MIS connections, rather than build up new Moodle DB objects. The reasonning for this is:
 * - More control
 * - Easier customisation
 * - Moodle seems to only allow one extension type for each db type as far as I can tell, 
 *   we will perform a check of the server to pick one if some are not installed
 * 
 * It should attempt to mimic the Moodle database classes as much as possible in its core usage, so that if in future other people want to 
 * develop plugins they won't have to learn how to use a new DB manager. But at the same time, make it a lot simpler, as obviously
 * it would be kind of pointless re-writing the whole thing. Besides, this will really only be used for simple SELECTs mostly.
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