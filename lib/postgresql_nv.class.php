<?php
/**
 * PostgreSQL Database Class for New Vegas
 * 
 * This class wraps the standard sql class but connects to dwemer_nv database.
 * It uses composition (delegation) pattern to reuse all functionality from sql class
 * while maintaining a separate database connection.
 */

require_once(__DIR__ . "/postgresql.class.php");
require_once(__DIR__ . "/logger.php");

class sql_nv
{
    private $sql;
    private static $instance = null;
    
    public function __construct()
    {
        // Create a new sql instance but override its connection
        // We need to temporarily change the connection string
        $this->sql = $this->createSqlInstanceForNV();
        
        // Store singleton instance
        self::$instance = $this;
    }
    
    /**
     * Create an sql instance connected to dwemer_nv database
     * This is a workaround since we can't directly change the private $connString
     */
    private function createSqlInstanceForNV()
    {
        // Temporarily override environment to force dwemer_nv connection
        $originalDbName = getenv('HERIKA_DB_NAME');
        putenv('HERIKA_DB_NAME=dwemer_nv');
        
        // Check if sql class respects environment variable
        // If not, we need to use reflection to change the connection
        try {
            // Try to create sql instance
            $sql = new sql();
            
            // Verify it's connected to dwemer_nv
            // We'll need to reconnect manually if it's not
            $this->ensureNVConnection($sql);
            
            return $sql;
            
        } finally {
            // Restore original environment
            if ($originalDbName !== false) {
                putenv("HERIKA_DB_NAME=$originalDbName");
            } else {
                putenv('HERIKA_DB_NAME');
            }
        }
    }
    
    /**
     * Ensure the sql instance is connected to dwemer_nv
     * Uses reflection to access and modify private properties
     */
    private function ensureNVConnection($sql)
    {
        try {
            $reflection = new ReflectionClass($sql);
            
            // Get the static $link property
            $linkProperty = $reflection->getProperty('link');
            $linkProperty->setAccessible(true);
            
            // Close existing connection if any
            $currentLink = $linkProperty->getValue();
            if ($currentLink) {
                @pg_close($currentLink);
            }
            
            // Create new connection to dwemer_nv
            $connString = "host=localhost dbname=dwemer_nv user=dwemer password=dwemer connect_timeout=90";
            $newLink = @pg_connect($connString);
            
            if (!$newLink || $newLink === false) {
                Logger::error("SQL_NV: connection init failed to dwemer_nv database");
                die("SQL_NV: Error in connection to New Vegas database.");
            }
            
            $stat = pg_connection_status($newLink);
            if ((!isset($stat)) || ($stat !== PGSQL_CONNECTION_OK)) {
                Logger::error("SQL_NV: connection init FAILED [$stat] to dwemer_nv database");
                die("SQL_NV: Error in connection to New Vegas database.");
            }
            
            // Set search path
            pg_query($newLink, "SET search_path TO public");
            
            // Update the static $link property
            $linkProperty->setValue(null, $newLink);
            
            Logger::debug("SQL_NV: connected to " . pg_host($newLink) . "/" . pg_dbname($newLink));
            
        } catch (ReflectionException $e) {
            Logger::error("SQL_NV: Failed to override connection - " . $e->getMessage());
            die("SQL_NV: Error in connection setup.");
        }
    }
    
    /**
     * Delegate all method calls to the wrapped sql instance
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this->sql, $method)) {
            return call_user_func_array([$this->sql, $method], $arguments);
        }
        
        throw new BadMethodCallException("Method $method does not exist in sql class");
    }
    
    /**
     * Explicitly define commonly used methods for better IDE support
     */
    
    public function insert($table, $data)
    {
        return $this->sql->insert($table, $data);
    }
    
    public function execQuery($sql)
    {
        return $this->sql->execQuery($sql);
    }
    
    public function query($sql)
    {
        return $this->sql->query($sql);
    }
    
    public function fetchAll($result)
    {
        return $this->sql->fetchAll($result);
    }
    
    public function fetchOne($result)
    {
        return $this->sql->fetchOne($result);
    }
    
    public function escape($value)
    {
        return $this->sql->escape($value);
    }
    
    public function updateRow($table, $data, $where)
    {
        return $this->sql->updateRow($table, $data, $where);
    }
    
    public function deleteRow($table, $where)
    {
        return $this->sql->deleteRow($table, $where);
    }
    
    public function close()
    {
        if ($this->sql) {
            $this->sql->close();
        }
    }
}
