<?php
// End any existing session to apply new session settings (if necessary)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();     // Free all session variables
    session_destroy();    // End the session
}

// Konfigurasi session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SERVICE', 'XE');
define('DB_USER', 'c##hafiz');
define('DB_PASS', 'hafiz');
define('DB_DSN', "oci:dbname=//".DB_HOST.":".DB_PORT."/".DB_SERVICE);

// Application configuration
define('SITE_URL', 'localhost');
define('EMAIL_FROM', 'noreply@yourdomain.com');

// Zona waktu
date_default_timezone_set('Asia/Jakarta');

// Fungsi koneksi database menggunakan PDO
function connectDB() {
    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Koneksi gagal: " . $e->getMessage());
    }
}

// Fungsi untuk menjalankan query dengan PDO
function executeQuery($query, $params = []) {
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die("Query error: " . $e->getMessage());
    }
}
?>
