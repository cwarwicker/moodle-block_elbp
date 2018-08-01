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
class Oracle extends Manager {
    
    protected static $acceptedTypes = array(
        'pdo_oci',
    ); 
    
    private $extension = false;
    protected $conn = false;
    
    /**
     * Construct object
     * @param mixed $params If null we're building dynamically with parameters. If array/object
     * @return boolean
     * @throws \ELBP\ELBPException
     */
    public function __construct($params = null) {
        
        // First try php_pdo_oci
        if (extension_loaded('pdo_oci')) $this->extension = 'pdo_oci';
                
        if (!$this->extension){
            throw new \ELBP\ELBPException( get_string('mismanager', 'block_elbp'), get_string('noextension', 'block_elbp'), implode(', ', self::$acceptedTypes), get_string('installextension', 'block_elbp') );
            return false;
        }
        
        if (is_array($params) || is_object($params)) $this->conn = $params;
                        
    }
    
    public function wrapValue($value){
        return '"'.$value.'"';
    }
    
    /**
     * Here I am
    */
    public function getConn(){
        return $this->conn;
    }
    
    /**
     * Connect to a database
     * @param mixed $params If null we're using the connection record in the db as specified in constructor. Else we're giving details
     */
    public function connect($params = null){        
        
        $func = 'connect_'.$this->extension;
        
        // use connection record
        if (is_null($params)){
            return $this->$func($this->conn->host, $this->conn->un, $this->conn->pw);
        }
        else
        {
            return $this->$func($params['host'], $params['user'], $params['pass']);
        }
        
    }
    
   
    
    private function connect_pdo_oci($host, $user, $pass){
        
         try {
            $DBH = new \PDO("oci:dbname={$host}", $user, $pass);
            $DBH->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC );
            $DBH->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
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
    
    
    public function disconnect(){
        $func = 'disconnect_'.$this->extension;
        return $this->$func();
    }
    
    
    private function disconnect_pdo_oci(){
        $this->dbh = null;
    }
    
    
    
    public function query($sql, $params = array()){
        $this->lastSQL = $sql;
        $func = 'query_'.$this->extension;
        return $this->$func($sql, $params);
    }
    
    
    private function query_pdo_oci($sql, $params = array()){
        
        try {
            $st = $this->dbh->prepare($sql);        
            $st->execute($params);
            return $st;
        } catch (\PDOException $e){
            $this->last_error = $e->getMessage();
            return false;
        }
        
    }
    
    
    
    
    
    public function fetch($qry){
        $func = 'fetch_'.$this->extension;
        return $this->$func($qry);
    }
    
    
    private function fetch_pdo_oci($qry){
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
    
    
    
    public function execute($sql, $params = array()){
        $this->lastSQL = $sql;
        $func = 'execute_'.$this->extension;
        return $this->$func($sql, $params);
    }
    
    
    
    private function execute_pdo_oci($sql, $params = array()){
        $st = $this->query($sql, $params);
        return $st->rowCount();
    }
    
    
    
    /**
     * Select from a DB
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
                $name = $this->wrapValue($name);
                $sql .= " {$name} = :{$name} AND ";
                $params[$name] = $value;
            }
        }
        
        if (strrpos($sql, " AND") !== false){
            $sql = substr_replace($sql, "", strrpos($sql, " AND"), strlen($sql));
        }
        
        if (!is_null($order))
        {
            $sql .= " ORDER BY {$order} ";
        }
        
        if (!is_null($limit))
        {
            $sql = "SELECT * FROM ( " . $sql . " ) WHERE ROWNUM <= {$limit}";
        }
                
        $query = $this->query($sql, $params);
                        
        if (!$query) return array();
        
        return $this->getRecordSet($query);
        
        
    }
    
        
    /**
     * Given the result of a query, put the rows it found into a recordset
     * @param type $query
     * @return type
     */
    protected function getRecordSet($query)
    {
        $func = 'getRecordSet_'.$this->extension;
        return $this->$func($query);
    }
    
    
    
    private function getRecordSet_pdo_oci($query)
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
            $p = $this->convertWhiteSpace($field);
            $field = $this->wrapValue($field);
            $sql .= " {$field} = :{$p} ,";
            $params[$p] = $value;
        }
        
        // Strip comma
        $sql = substr($sql, 0, strlen($sql)-1);
        
        if (!is_null($where))
        {
            
            $sql .= " WHERE ";
            
            foreach($where as $field => $value)
            {
                $p = $this->convertWhiteSpace($field);
                $field = $this->wrapValue($field);
                $sql .= " {$field} = :{$p} AND";
                $params[$p] = $value;
            }

            // Strip AND
            $sql = substr($sql, 0, strlen($sql)-3);
        
        }
        
        // Limit
        if (!is_null($limit))
        {
            
            if ($where)
            {
                $sql .= " AND ROWNUM <= :RNUM ";
                $params['RNUM'] = $limit;
            }
            else
            {
                $sql .= " WHERE ROWNUM <= :RNUM ";
                $params['RNUM'] = $limit;
            }
            
        }
                                
        return $this->execute($sql, $params);
        
    }
    
    
    
    
    
    public function delete($table, $where = null, $limit = null){
        
        if (!is_object($where) && !is_array($where) && !is_null($where)) return false;        
        $where = (array) $where;
        $params = array();
        $sql = "";
        
        $sql .= "DELETE  ";        
        $sql .= " FROM ".$this->wrapValue($table)." ";        
        
        if (!is_null($where))
        {
            
            $sql .= " WHERE ";

            foreach($where as $field => $value)
            {
                $field = $this->wrapValue($field);
                $p = $this->convertWhiteSpace($field);
                $sql .= " {$field} = :{$p} AND";
                $params[$p] = $value;
            }

            // Strip AND
            $sql = substr($sql, 0, strlen($sql)-3);
        
        }
        
        // Limit
        if (!is_null($limit))
        {
            if ($where)
            {
                $sql .= " AND ROWNUM <= :RNUM ";
                $params['RNUM'] = $limit;
            }
            else
            {
                $sql .= " WHERE ROWNUM <= :RNUM ";
                $params['RNUM'] = $limit;
            }
        }
                
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
                $field = $this->wrapValue($field);
                $sql .= "{$field},";
            }
            $sql = substr($sql, 0, strlen($sql)-1);
        $sql .= ") ";
        
        $sql .= "VALUES (";
            foreach($data as $field => $value)
            {
                $field = $this->convertWhiteSpace($field);
                $sql .= ":{$field},";
                $params[$field] = $value;
            }
            $sql = substr($sql, 0, strlen($sql)-1);
        $sql .= ")";
                        
        return $this->execute($sql, $params);
        
    }
    
    /**
     * This is assuming the date is in the format: YYYYMMDD
     * @param type $field
     * @param string $operator
     * @return type
     */
    public function compareDatesSQL($field, $operator){
        return false; // Do this in PHP instead, too much of a hassle
    }
    
    public function convertDateSQL($field, $format){
        return false; // DO this in PHP
    }
    
    
    /**
     * Get the table info for the environment page
     * @param type $tableName
     * @param type $tablePrefix
     */
    public function getTableInfo($tableName = null, $tablePrefix = null){
                
        // todo
        
        // note for self: continue testing mis connections with select(), update(), etc...
        // also test this one with pdo_oci 
        
    }
    
}