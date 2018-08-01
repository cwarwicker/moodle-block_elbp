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

/**
 * 
 */
class MySQL extends Manager {
    
    
    protected static $acceptedTypes = array(
        'pdo_mysql'
    ); 
    
    private $extension = false;
    
     /**
     * Construct object
     * @param mixed $params If null we're building dynamically with parameters. If array/object
     * @return boolean
     * @throws \ELBP\ELBPException
     */
    public function __construct($params = null) {
        
        // First try php_pdo_oci
        if (extension_loaded('pdo_mysql')) $this->extension = 'pdo_mysql';
        if (!$this->extension){
            throw new \ELBP\ELBPException( get_string('mismanager', 'block_elbp'), get_string('noextension', 'block_elbp'), implode(' / ', self::$acceptedTypes), get_string('installextension', 'block_elbp') );
            return false;
        }
        
        if (is_array($params) || is_object($params)) $this->conn = $params;
                                        
    }
    
    
    
    /**
     * Connect to MySQL database
     * @param type $params
     * @return type
     */
    public function connect($params = null){
        
        $func = 'connect_'.$this->extension;
                
        // use connection record
        if (is_null($params)){
            return $this->$func($this->conn->host, $this->conn->un, $this->conn->pw, $this->conn->db);
        }
        else
        {
            $this->conn = (object)$params;
            return $this->$func($params['host'], $params['user'], $params['pass'], $params['db']);
        }
        
    }
    
    private function connect_pdo_mysql($host, $user, $pass, $db)
    {
        try {
            $DBH = new \PDO("mysql:host={$host};dbname={$db}", $user, $pass);
            $DBH->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $this->dbh = $DBH;
            return $this->dbh;
        } catch (\Exception $e){
            if (!$this->show_conn_err){
                $this->last_error = $e->getMessage();
                return false;
            }
            echo $e->getMessage();
            return false;
        }
    }    
    
    
    /**
     * Disconnect frmo database
     */
    public function disconnect(){
        $func = 'disconnect_'.$this->extension;
        $this->$func();
    }
    
    private function disconnect_pdo_mysql(){
        $this->dbh = null;
    }

    
    
    /**
     * Run SQL query and return statement/query handle
     * @param type $sql
     * @param type $params
     * @return type
     */
    public function query($sql, $params){
        $this->lastSQL = $sql;
        $func = 'query_'.$this->extension;
        return $this->$func($sql, $params);
    }
    
    private function query_pdo_mysql($sql, $params)
    {
        try 
        {
            $st = $this->dbh->prepare($sql);        
            $st->execute($params);
            return $st;
        } catch (\PDOException $e){
            $this->last_error = $e->getMessage();
            return false;
        }
    }
        
    
    
    
    public function execute($sql, $params){
        $this->lastSQL = $sql;
        $func = 'execute_'.$this->extension;
        return $this->$func($sql, $params);
    }
    
    private function execute_pdo_mysql($sql, $params)
    {
        $st = $this->query($sql, $params);
        return $st->rowCount();
    }
    
    
    
    
    
    /**
     * Select something from MySQL database table
     * @param type $table
     * @param type $where
     * @param type $fields
     * @param type $limit
     */
    public function select($table, $where = null, $fields = "*", $order = null, $limit = null){
        
        $params = array();
        $sql = "";
        $sql .= " SELECT {$fields} ";
        
        $sql .= " FROM ".$this->wrapValue($table)." ";
        
        if (is_array($where)){
            $sql .= " WHERE ";
            foreach($where as $name => $value){
                $sql .= " ".$this->wrapValue($name)." = ? AND ";
                $params[] = $value;
            }
            
            // Remove trailing AND
            $sql = substr_replace($sql, "", strrpos($sql, " AND"), strlen($sql));
            
        }
        
                // Order
        if (!is_null($order)) $sql .= " ORDER BY {$order} ";
        
        // Limit
        if (!is_null($limit)) $sql .= " LIMIT {$limit} ";
                        
        $query = $this->query($sql, $params);
                
        if (!$query) return array();
        
        return $this->getRecordSet($query);
        
    }
    
    public function update($table, $data, $where = null, $limit = null){
        
        if (!is_object($data) && !is_array($data)) return false;        
        $data = (array) $data;
        if (!$data) return false;
        
        $params = array();
        $sql = "";
        $sql .= "UPDATE ".$this->wrapValue($table)." ";
        $sql .= "SET ";
        
        foreach($data as $field => $value)
        {
            $sql .= " ".$this->wrapValue($field)." = ? ,";
            $params[] = $value;
        }
        
        // Strip comma
        $sql = substr($sql, 0, strlen($sql)-1);
        
        if (!is_null($where))
        {
            
            $sql .= " WHERE ";

            foreach($where as $field => $value)
            {
                $sql .= " ".$this->wrapValue($field)." = ? AND";
                $params[] = $value;
            }

            // Strip AND
            $sql = substr($sql, 0, strlen($sql)-3);
        
        }
        
        if (!is_null($limit)) $sql .= " LIMIT {$limit} ";
                
        return $this->execute($sql, $params);
        
    }
    
    public function delete($table, $where = null, $limit = null){
        
        if (!is_object($where) && !is_array($where) && !is_null($where)) return false;        
        if (is_object($where)){
            $where = (array) $where;
        }
        
        $params = array();
        $sql = "";
        
        $sql .= "DELETE FROM ".$this->wrapValue($table)." ";  
        
        if (!is_null($where))
        {
        
            $sql .= " WHERE ";

            foreach($where as $field => $value)
            {
                $sql .= " ".$this->wrapValue($field)." = ? AND";
                $params[] = $value;
            }

            // Strip AND
            $sql = substr($sql, 0, strlen($sql)-3);
        
        }
        
        if (!is_null($limit)) $sql .= " LIMIT {$limit} ";
        
        return $this->execute($sql, $params);
        
    }
    
    public function insert($table, $data){
        
        if (!is_object($data) && !is_array($data)) return false;        
        $data = (array) $data;
        if (!$data) return false;
        
        $params = array();
        $sql = "";
        
        $sql .= "INSERT INTO ".$this->wrapValue($table)." ";
        $sql .= "( ";
            foreach($data as $field => $value)
            {
                $sql .= $this->wrapValue($field) . ",";
            }
        $sql = substr($sql, 0, strlen($sql)-1);
        $sql .= ") ";
        $sql .= "VALUES (";
            foreach($data as $field => $value)
            {
                $sql .= "?,";
            }
        $sql = substr($sql, 0, strlen($sql)-1);
        $sql .= ")";
        
        foreach($data as $value)
        {
            $params[] = $value;
        }
        
        return $this->execute($sql, $params);
        
    }
    
    public function fetch($qry){
        $func = 'fetch_'.$this->extension;
        return $this->$func($qry);
    }
    
    /**
     * Fetch row for pdo sqlsrv
     * @param type $qry
     * @return type
     */
    private function fetch_pdo_mysql($qry)
    {
        return $qry->fetch();
    }
    
    /**
     * No point having different ones for extnesion, always pdo
     * @param type $qry
     * @return type
     */
    public function fetchAll($qry) {
        return $qry->fetchAll();
    }
    
    
    
    protected function getRecordSet($query) {
        $func = 'getRecordSet_'.$this->extension;
        return $this->$func($query);
    }
    
    private function getRecordSet_pdo_mysql($query)
    {
        $results = array();
        while($row = $query->fetch())
        {
            $results[] = $row;
        }
        
        // If only one, return that one object rather than an array with one element
        //if (count($results) == 1) return $results[0];
        
        return $results;
    }
    
    /**
     * Wrap value in MySQL-specific format
     * @param type $value
     * @return type
     */
    public function wrapValue($value)
    {
        return "`{$value}`";
    }
    
    // >>>BEDCOLL convertDateSQL methods
    public function convertDateSQL($field, $format)
    {
        // Too many issues with cross database support dealing with dates, so we'll just do all that in PHP
        return false;
    }
    
    /**
     * This is assuming the date is in the format: YYYYMMDD
     * @param type $field
     * @param string $operator
     * @return type
     */
    public function compareDatesSQL($field, $operator){
        // Too many issues with cross database support dealing with dates, so we'll just do all that in PHP
        return false;   
    }
    
    /**
     * Get info about a specific table, or a list of tables defined by the same prefix, e.g. mdl_lbp_*
     * @param type $tableName
     * @param type $tablePrefix
     */
    public function getTableInfo($tableName = null, $tablePrefix = null){
        
        if (is_null($tableName) && is_null($tablePrefix)) return false;
        
        if (!is_null($tableName)){
            
            $query = $this->query("SELECT table_name as name, index_length as indexsize, table_comment as cmt, (data_length + index_length) as size
                                   FROM information_schema.tables
                                   WHERE table_schema = ? AND table_name = ?
                                   ORDER BY table_name", array($this->conn->db, $tableName));
            
        } elseif (!is_null($tablePrefix)){
            
            $query = $this->query("SELECT table_name as name, index_length as indexsize, table_comment as cmt, ( data_length + index_length ) as size
                                   FROM information_schema.tables
                                   WHERE table_schema = ? AND table_name LIKE ?
                                   ORDER BY table_name", array($this->conn->db, '%'.$tablePrefix.'%'));
            
        }
        
        
        $records = $this->getRecordSet($query);        
        
        if ($records)
        {
            foreach ($records as &$record)
            {
                $check = $this->query("SELECT COUNT(id) as cnt FROM {$record['name']}", array());
                $count = $check->fetch();
                $record['records'] = $count['cnt'];
            }
        }
        
        return $records;
        
    }
        
    
}