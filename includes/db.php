<?php
/**
 * Database connection class using PDO
 * Implements singleton pattern for efficient resource usage
 */

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            // Create directory if it doesn't exist
            $dbDir = dirname(DB_PATH);
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->conn = new PDO(
                "sqlite:" . DB_PATH,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            // Enable foreign keys for SQLite
            $this->conn->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get PDO connection
    public function getConnection() {
        return $this->conn;
    }
    
    // Prepare and execute a query
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . " - SQL: " . $sql);
            throw new Exception("Database query failed. Please try again later.");
        }
    }
    
    // Get a single record
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Get multiple records
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Insert a record and return last insert ID
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->conn->lastInsertId();
    }
    
    // Update records and return affected rows
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Delete records and return affected rows
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Begin a transaction
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    // Commit a transaction
    public function commit() {
        return $this->conn->commit();
    }
    
    // Rollback a transaction
    public function rollback() {
        return $this->conn->rollBack();
    }
}
//

