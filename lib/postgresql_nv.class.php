<?php
/**
 * PostgreSQL Database Class for New Vegas
 * Extends the base sql class to connect to a separate New Vegas database
 */

require_once(__DIR__ . "/postgresql.class.php");

class sql_nv extends sql
{
    private static $link_nv = null;
    private $connString = "host=localhost dbname=dwemer_nv user=dwemer password=dwemer connect_timeout=90";
    
    public function __construct()
    {
        // Use separate static link for NV to avoid conflicts with Skyrim
        if (self::$link_nv === null) {
            self::$link_nv = @pg_connect($this->connString);

            if (!self::$link_nv || self::$link_nv === false) {
                Logger::error("SQL_NV: connection init failed. " . $this->extract_caller());
                die("SQL_NV: Error in connection to New Vegas database.");
            }

            $stat = pg_connection_status(self::$link_nv);
            if ((!isset($stat)) || ($stat !== PGSQL_CONNECTION_OK)) {
                Logger::error("SQL_NV: connection init FAILED [$stat] " . $this->extract_caller());
                die("SQL_NV: Error in connection to New Vegas database.");
            }
            
            // Ensure consistent schema resolution across sessions
            pg_query(self::$link_nv, "SET search_path TO public");
            
            if ($this->debug_level > 4) {
                Logger::debug("SQL_NV: connected $stat to " . pg_host(self::$link_nv) . "/" . pg_dbname(self::$link_nv) . " " . $this->extract_caller());
            }
        }
        
        // Set the parent's static link to our NV link
        self::$link = self::$link_nv;
    }
    
    public function close()
    {
        if (self::$link_nv) {
            if ($this->debug_level > 4) {
                Logger::debug("SQL_NV: close connection to " . pg_host(self::$link_nv) . "/" . pg_dbname(self::$link_nv) . " " . $this->extract_caller());
            }
            pg_close(self::$link_nv);
            self::$link_nv = null;
            self::$link = null;
        }
    }
}
