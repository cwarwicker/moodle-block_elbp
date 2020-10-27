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
 * This class is for querying Firebird databases.
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

namespace block_elbp\MIS;

class Firebird extends Manager {

    protected static $acceptedTypes = array(
        'pdo_firebird'
    );

    private $extension = false;

    /**
     * Construct object
     * @param mixed $params If null we're building dynamically with parameters. If array/object
     * @return boolean
     * @throws \block_elbp\ELBPException
     */
    public function __construct($params = null) {

        if (extension_loaded('pdo_firebird')) {
            $this->extension = 'pdo_firebird';
        }

        if (!$this->extension) {
            throw new \block_elbp\ELBPException( get_string('mismanager', 'block_elbp'), get_string('noextension', 'block_elbp'), implode(' / ', self::$acceptedTypes), get_string('installextension', 'block_elbp') );
            return false;
        }

        if (is_array($params) || is_object($params)) {
            $this->conn = $params;
        }

    }

    /**
     * Wrap in double "quotes"
     * @param type $value
     * @return type
     */
    public function wrapValue($value) {
        return '"'.$value.'"';
    }

    /**
     * Connect to a database
     * @param mixed $params If null we're using the connection record in the db as specified in constructor. Else we're giving details
     */
    public function connect($params = null) {

        $func = 'connect_'.$this->extension;

        // use connection record
        if (is_null($params)) {
            return $this->$func($this->conn->host, $this->conn->un, $this->conn->pw, $this->conn->db);
        } else {
            return $this->$func($params['host'], $params['user'], $params['pass'], $params['db']);
        }

    }



    /**
     * Connect to Access database using PDO for ODBC
     * @param type $host
     * @param type $user
     * @param type $pass
     * @param type $db
     */
    private function connect_pdo_firebird($host, $user, $pass = "", $db = "localhost") {

        try {
            $DBH = new \PDO("firebird:dbname={$db}:{$host}", $user, $pass);
            $DBH->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC );
            $DBH->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->dbh = $DBH;
            return $this->dbh;
        } catch (\Exception $e) {
            if (!$this->show_conn_err) {
                $this->last_error = $e->getMessage();
                return false;
            }
            echo $e->getMessage();
            return false;
        }

    }

    /**
     * Disconnect
     */
    public function disconnect() {
        $func = 'disconnect_'.$this->extension;
        return $this->$func();
    }

    private function disconnect_pdo() {
        $this->dbh = null;
    }

    /**
     * Disconnect using odbc PDO
     */
    private function disconnect_pdo_firebird() {
        $this->disconnect_pdo();
    }

    /**
     * Run an SQL query and return a statement - to be used for things like selecting
     * @param type $sql
     * @param type $params
     * @return type
     */
    public function query($sql, $params) {
        $this->lastSQL = $sql;
        $func = 'query_'.$this->extension;
        return $this->$func($sql, $params);

    }

    /**
     * Run SQL query using PDO
     * @param type $sql
     * @param type $params
     * @return $st Statement
     */
    private function query_pdo($sql, $params) {
        try {
            $st = $this->dbh->prepare($sql);
            $st->execute($params);
            return $st;
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * ODBC PDO
     * @param type $sql
     * @param type $params
     * @return type
     */
    private function query_pdo_firebird($sql, $params) {
        return $this->query_pdo($sql, $params);
    }

    /**
     * Execute an SQL query and return the number of affect rows - to be used for things like updating & inserting
     * @param type $sql
     * @param type $params
     * @return type
     */
    public function execute($sql, $params) {
        $this->lastSQL = $sql;
        $func = 'execute_'.$this->extension;
        return $this->$func($sql, $params);
    }

    private function execute_pdo($sql, $params) {
        $st = $this->query($sql, $params);
        return $st->rowCount();
    }

    /**
     * Execute an SQL query using odbc PDO
     * @param type $sql
     * @return type
     */
    private function execute_pdo_firebird($sql, $params) {
        return $this->execute_pdo($sql, $params);
    }

    /**
     * Select from a DB
     * @param type $table
     * @param type $where
     * @param type $fields
     * @param type $limit
     */
    public function select($table, $where = null, $fields = "*", $order = null, $limit = null) {

        $sql = "";

        $params = array();

        $sql .= " SELECT ";
        if (!is_null($limit)) {
            $sql .= " FIRST {$limit} ";
        }
        $sql .= " {$fields} ";

        $sql .= ' FROM '.$this->wrapValue($table).' ';

        if (is_array($where)) {
            $sql .= " WHERE ";
            foreach ($where as $name => $value) {
                $sql .= ' '.$this->wrapValue($name).' = ? AND ';
                $params[] = $value;
            }
        }

        if (preg_match("/ AND $/", $sql)) {
            $sql = substr_replace($sql, "", strrpos($sql, " AND"), strlen($sql));
        }

        if (!is_null($order)) {
            $sql .= " ORDER BY {$order} ";
        }

        $query = $this->query($sql, $params);
        if (!$query) {
            return array();
        }

        return $this->getRecordSet($query);

    }

    /**
     * Given the result of a query, put the rows it found into a recordset
     * @param type $query
     * @return type
     */
    protected function getRecordSet($query) {
        $func = 'getRecordSet_'.$this->extension;
        return $this->$func($query);
    }

    private function getRecordSet_pdo($query) {
        $results = array();
        while ($row = $query->fetch()) {
            $results[] = $row;
        }
        return $results;
    }

    /**
     * Get recordset for odbc PDO
     * @param type $query
     * @return type
     */
    private function getRecordSet_pdo_firebird($query) {
        return $this->getRecordSet_pdo($query);
    }

    /**
     * Update a table in the DB
     * @param type $table
     * @param type $data
     * @param type $where
     * @param type $limit
     * @return boolean
     */
    public function update($table, $data, $where = null, $limit = null) {

        if (!is_object($data) && !is_array($data)) {
            return false;
        }

        $data = (array) $data;
        if (!$data) {
            return false;
        }

        $params = array();
        $sql = "";
        $sql .= "UPDATE ".$this->wrapValue($table)." ";
        $sql .= "SET ";

        foreach ($data as $field => $value) {
            $sql .= ' '.$this->wrapValue($field).' = ? ,';
            $params[] = $value;
        }

        // Strip comma
        $sql = substr($sql, 0, strlen($sql) - 1);

        if (!is_null($where)) {

            $sql .= " WHERE ";

            foreach ($where as $field => $value) {
                $sql .= ' '.$this->wrapValue($field).' = ? AND';
                $params[] = $value;
            }

            // Strip AND
            $sql = substr($sql, 0, strlen($sql) - 3);

        }

        // Limit
        if (!is_null($limit)) {
            $sql .= " ROWS {$limit} ";
        }

        return $this->execute($sql, $params);

    }

    /**
     * Delete records from a DB table
     * @param string $table
     * @param array $where
     * @param int $limit
     * @return boolean
     */
    public function delete($table, $where = null, $limit = null) {

        if (!is_object($where) && !is_array($where) && !is_null($where)) {
            return false;
        }

        if (is_object($where)) {
            $where = (array) $where;
        }

        $params = array();
        $sql = "";

        $sql .= "DELETE FROM ".$this->wrapValue($table)." ";

        if (!is_null($where)) {

            $sql .= " WHERE ";

            foreach ($where as $field => $value) {
                $sql .= ' '.$this->wrapValue($field).' = ? AND';
                $params[] = $value;
            }

            // Strip AND
            $sql = substr($sql, 0, strlen($sql) - 3);

        }

        // Limit
        if (!is_null($limit)) {
            $sql .= " ROWS {$limit} ";
        }

        return $this->execute($sql, $params);

    }

    /**
     * Insert records into a DB table
     * @param type $table
     * @param type $data
     * @return boolean
     */
    public function insert($table, $data) {

        if (!is_object($data) && !is_array($data)) {
            return false;
        }

        $data = (array) $data;
        if (!$data) {
            return false;
        }

        $params = array();
        $sql = "";

        $sql .= 'INSERT INTO '.$this->wrapValue($table).' ';
        $sql .= "( ";
        foreach ($data as $field => $value) {
            $sql .= $this->wrapValue($field) . ',';
        }
        $sql = substr($sql, 0, strlen($sql) - 1);
        $sql .= ") ";
        $sql .= "VALUES (";
        foreach ($data as $field => $value) {
            $sql .= "?,";
        }
        $sql = substr($sql, 0, strlen($sql) - 1);
        $sql .= ")";

        foreach ($data as $value) {
            $params[] = $value;
        }

        return $this->execute($sql, $params);

    }

    /**
     * Given a query result, fetch the next row of records
     * @param type $qry
     * @return type
     */
    public function fetch($qry) {
        $func = 'fetch_'.$this->extension;
        return $this->$func($qry);
    }

    /**
     * Fetch row for pdo sqlsrv
     * @param type $qry
     * @return type
     */
    private function fetch_pdo($qry) {
        return $qry->fetch();
    }

    /**
     * Fetch row for pdo sqlsrv
     * @param type $qry
     * @return type
     */
    private function fetch_pdo_firebird($qry) {
        return $this->fetch_pdo($qry);
    }

    public function fetchAll($qry) {
        return $qry->fetchAll();
    }

    public function convertDateSQL($field, $format) {

        // Might remove these methods
        return false;

    }

    /**
     * This is assuming the date is in the format: YYYYMMDD
     * @param type $field
     * @param string $operator
     * @return type
     */
    public function compareDatesSQL($field, $operator) {

        // Might remove these methods
        return false;

    }

    /**
     * Get info about a specific table, or a list of tables defined by the same prefix, e.g. mdl_lbp_*
     * @param type $tableName
     * @param type $tablePrefix
     */
    public function getTableInfo($tableName = null, $tablePrefix = null) {

        // Might remove these methods.
        return false;

    }

}