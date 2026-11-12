<?php
// Database Configuration for BnB Restaurant
// Using Procedural PHP Functions

// Database connection constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bnb_restaurant');

// Global connection variable
$GLOBALS['db_connection'] = null;

/**
 * Get database connection
 * @return mysqli|false Returns MySQLi connection or false on failure
 */
function getDBConnection() {
    // Return existing connection if available
    if ($GLOBALS['db_connection'] !== null) {
        // Test connection
        if (mysqli_ping($GLOBALS['db_connection'])) {
            return $GLOBALS['db_connection'];
        }
    }
    
    // Create new connection
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    // Store connection in global variable
    $GLOBALS['db_connection'] = $conn;
    
    return $conn;
}

/**
 * Close database connection
 */
function closeDBConnection() {
    if ($GLOBALS['db_connection'] !== null) {
        mysqli_close($GLOBALS['db_connection']);
        $GLOBALS['db_connection'] = null;
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Register shutdown function to close connection
register_shutdown_function('closeDBConnection');

?>

