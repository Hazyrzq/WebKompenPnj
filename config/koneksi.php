<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SERVICE', 'XE');
define('DB_USER', 'c##hafiz');
define('DB_PASS', 'hafiz');
define('DB_DSN', "oci:dbname=//".DB_HOST.":".DB_PORT."/".DB_SERVICE);

// Zona waktu
date_default_timezone_set('Asia/Jakarta');

// Global connection variable
global $conn;

// Enhanced PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_CASE => PDO::CASE_UPPER,
    // Additional options for better LOB handling
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_AUTOCOMMIT => false
];

// Fungsi koneksi database menggunakan PDO
function connectDB() {
    global $conn, $options;
    
    // If connection already exists, return it
    if (isset($conn) && $conn instanceof PDO) {
        return $conn;
    }
    
    try {
        // Create new connection with enhanced options
        $conn = new PDO(DB_DSN, DB_USER, DB_PASS, $options);
        
        // Set session parameters for better LOB handling
        $conn->exec("ALTER SESSION SET NLS_LENGTH_SEMANTICS='CHAR'");
        $conn->exec("ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.,'");
        
        return $conn;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception("Koneksi gagal: " . $e->getMessage());
    }
}

// Enhanced query execution function with better error handling
function executeQuery($query, $params = [], $options = []) {
    try {
        $pdo = connectDB();
        
        // Start transaction if not already in one
        if (!$pdo->inTransaction() && !isset($options['no_transaction'])) {
            $pdo->beginTransaction();
        }
        
        $stmt = $pdo->prepare($query);
        
        // Handle LOB parameters if present
        foreach ($params as $key => $value) {
            if (is_resource($value)) {
                $stmt->bindParam($key, $value, PDO::PARAM_LOB);
            }
        }
        
        $stmt->execute($params);
        
        // Commit if we started the transaction
        if ($pdo->inTransaction() && !isset($options['no_commit'])) {
            $pdo->commit();
        }
        
        return $stmt;
    } catch (PDOException $e) {
        // Rollback if we started the transaction
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Query execution error: " . $e->getMessage());
        throw new Exception("Query error: " . $e->getMessage());
    }
}

// Initialize connection when file is included
try {
    $conn = connectDB();
} catch (Exception $e) {
    error_log("Fatal database error: " . $e->getMessage());
    die("Fatal Error: " . $e->getMessage());
}
?>