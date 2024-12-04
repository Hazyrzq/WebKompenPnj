<?php
session_start();
require_once('../../Config.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'ADMIN') {
    header('Location: ../../login.php');
    exit();
}

try {
    if (isset($_GET['id'])) {
        $db = getDB();
        
        // Activate single user
        $query = "UPDATE TBL_USER SET STATUS = 'ACTIVE' WHERE ID = :id AND STATUS IN ('UNSENT', 'INACTIVE')";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $_GET['id']]);
        
        $_SESSION['success_message'] = "User successfully activated.";
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error activating user: " . $e->getMessage();
}

header('Location: user.php');
exit();
?>