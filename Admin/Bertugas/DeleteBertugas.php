<?php
session_start();
require_once('../../Config.php');
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

try {
    if (isset($_GET['delete_all']) && $_GET['delete_all'] === 'true') {
        // Delete all records
        $query = "BEGIN DELETE_ALL_SETUP_BERTUGAS; END;";
        executeQuery($query);
    } elseif (isset($_GET['id'])) {
        // Delete single record
        $query = "BEGIN DELETE_SETUP_BERTUGAS(:p_id); END;";
        executeQuery($query, ['p_id' => $_GET['id']]);
    }

    header('Location: Bertugas.php');
    exit();
} catch (Exception $e) {
    // Log error and redirect with error message
    error_log($e->getMessage());
    header('Location: Bertugas.php?error=' . urlencode('Failed to delete record(s)'));
    exit();
}
?>