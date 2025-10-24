<?php
/**
 * Database Connection File
 * 
 * This file establishes connection to MySQL database using MySQLi.
 * It includes error handling and connection verification.
 */

// Include configuration file
require_once __DIR__ . '/config.php';

/**
 * Create database connection using MySQLi
 * Note: We don't specify database name initially to allow database creation
 */
function createConnection($include_db = true) {
    $conn = null;
    
    try {
        // Create connection without database name for initial setup
        if ($include_db) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        } else {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        }
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset
        if (!$conn->set_charset(DB_CHARSET)) {
            throw new Exception("Error loading character set " . DB_CHARSET);
        }
        
        return $conn;
        
    } catch (Exception $e) {
        // Log error or display message
        error_log($e->getMessage());
        return null;
    }
}

/**
 * Check if database exists
 */
function databaseExists() {
    $conn = createConnection(false);
    if (!$conn) return false;
    
    $db_name = DB_NAME;
    $result = $conn->query("SHOW DATABASES LIKE '$db_name'");
    $exists = ($result && $result->num_rows > 0);
    
    $conn->close();
    return $exists;
}

/**
 * Check if users table exists
 */
function usersTableExists() {
    $conn = createConnection(true);
    if (!$conn) return false;
    
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    $exists = ($result && $result->num_rows > 0);
    
    $conn->close();
    return $exists;
}

// Create the main database connection
$conn = createConnection(true);

// Close connection function
function closeConnection($connection) {
    if ($connection && !$connection->connect_error) {
        $connection->close();
    }
}
?>
