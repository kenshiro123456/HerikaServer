<?php
/**
 * PostgreSQL Database Class for New Vegas
 * 
 * This class provides a separate database connection for New Vegas data.
 * It implements the necessary methods for gamedata_nv.php without extending sql class.
 */

require_once(__DIR__ . "/logger.php");

class sql_nv
{
    private static $link = null;
    private $connString = "host=localhost dbname=dwemer_nv user=dwemer password=dwemer connect_timeout=90";
    
    public function __construct()
    {
        // Use separate static link for NV to avoid conflicts with Skyrim
        if (self::$link === null) {
            self::$link = @pg_connect($this->connString);

            if (!self::$link || self::$link === false) {
                Logger::error("SQL_NV: connection init failed to dwemer_nv database");
                die("SQL_NV: Error in connection to New Vegas database.");
            }

            $stat = pg_connection_status(self::$link);
            if ((!isset($stat)) || ($stat !== PGSQL_CONNECTION_OK)) {
                Logger::error("SQL_NV: connection init FAILED [$stat] to dwemer_nv database");
                die("SQL_NV: Error in connection to New Vegas database.");
            }
            
            // Ensure consistent schema resolution across sessions
            pg_query(self::$link, "SET search_path TO public");
            
            Logger::debug("SQL_NV: connected to " . pg_host(self::$link) . "/" . pg_dbname(self::$link));
        }
    }
    
    public function close()
    {
        if (self::$link) {
            Logger::debug("SQL_NV: close connection to " . pg_host(self::$link) . "/" . pg_dbname(self::$link));
            pg_close(self::$link);
            self::$link = null;
        }
    }
    
    /**
     * Insert a row into a table
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return bool Success status
     */
    public function insert($table, $data)
    {
        if (!self::$link) {
            Logger::error("SQL_NV: No database connection for insert");
            return false;
        }
        
        $columns = array_keys($data);
        $values = array_values($data);
        
        // Escape column names
        $escapedColumns = array_map(function($col) {
            return pg_escape_identifier(self::$link, $col);
        }, $columns);
        
        // Build placeholders
        $placeholders = [];
        for ($i = 1; $i <= count($values); $i++) {
            $placeholders[] = '$' . $i;
        }
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            pg_escape_identifier(self::$link, $table),
            implode(', ', $escapedColumns),
            implode(', ', $placeholders)
        );
        
        Logger::debug("SQL_NV: " . $sql);
        
        $result = pg_query_params(self::$link, $sql, $values);
        
        if (!$result) {
            Logger::error("SQL_NV: Insert failed - " . pg_last_error(self::$link));
            return false;
        }
        
        return true;
    }
    
    /**
     * Execute a raw SQL query
     * @param string $sql SQL query
     * @return resource|false Query result
     */
    public function execQuery($sql)
    {
        if (!self::$link) {
            Logger::error("SQL_NV: No database connection for execQuery");
            return false;
        }
        
        Logger::debug("SQL_NV: " . $sql);
        
        $result = pg_query(self::$link, $sql);
        
        if (!$result) {
            Logger::error("SQL_NV: Query failed - " . pg_last_error(self::$link));
            return false;
        }
        
        return $result;
    }
    
    /**
     * Execute a SQL query (alias for execQuery)
     * @param string $sql SQL query
     * @return resource|false Query result
     */
    public function query($sql)
    {
        return $this->execQuery($sql);
    }
    
    /**
     * Fetch all rows from a query result
     * @param resource|string $result Query result or SQL string
     * @return array Array of rows
     */
    public function fetchAll($result)
    {
        // If $result is a string, execute it as a query first
        if (is_string($result)) {
            $result = $this->execQuery($result);
        }
        
        if (!$result) {
            return [];
        }
        
        $rows = pg_fetch_all($result);
        return $rows === false ? [] : $rows;
    }
    
    /**
     * Fetch one row from a query result
     * @param resource|string $result Query result or SQL string
     * @return array|null Single row or null
     */
    public function fetchOne($result)
    {
        // If $result is a string, execute it as a query first
        if (is_string($result)) {
            $result = $this->execQuery($result);
        }
        
        if (!$result) {
            return null;
        }
        
        $row = pg_fetch_assoc($result);
        return $row === false ? null : $row;
    }
    
    /**
     * Escape a string for SQL
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape($value)
    {
        if (!self::$link) {
            Logger::error("SQL_NV: No database connection for escape");
            return addslashes($value);
        }
        
        return pg_escape_string(self::$link, $value);
    }
}
