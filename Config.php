<?php
// Konfigurasi session yang aman
function initializeSecureSession() {
    // Hanya inisialisasi session jika belum ada
    if (session_status() === PHP_SESSION_NONE) {
        // Konfigurasi session yang lebih aman
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.gc_maxlifetime', 1800); // 30 minutes
        ini_set('session.cookie_lifetime', 0);    // Session cookie
        
        session_start();
    }
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SERVICE', 'XE');
define('DB_USER', 'c##hafiz');
define('DB_PASS', 'hafiz');

// Perbaikan format TNS untuk Oracle
define('DB_TNS', sprintf(
    '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=%s))(CONNECT_DATA=(SERVICE_NAME=%s)))',
    DB_HOST,
    DB_PORT,
    DB_SERVICE
));

// Application configuration
define('SITE_URL', 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']);
define('EMAIL_FROM', 'noreply@yourdomain.com');

// Zona waktu
date_default_timezone_set('Asia/Jakarta');

// Fungsi koneksi database yang ditingkatkan
function getDB() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            "oci:dbname=" . DB_TNS,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_CASE => PDO::CASE_UPPER,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_AUTOCOMMIT => true
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new PDOException("Database connection failed: " . $e->getMessage());
    }
}

// Fungsi execute query yang ditingkatkan
function executeQuery($query, $params = [], $returnLastInsertId = false) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new PDOException("Query execution failed");
        }
        
        if ($returnLastInsertId) {
            return $pdo->lastInsertId();
        }
        
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution error: " . $e->getMessage());
        throw new PDOException("Query execution failed: " . $e->getMessage());
    }
}

// Inisialisasi session saat config.php dimuat
initializeSecureSession();
?>