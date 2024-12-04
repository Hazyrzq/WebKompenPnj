<?php
session_start();
require_once('../../Config.php');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'ADMIN') {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['users'])) {
    try {
        $users = json_decode($_POST['users'], true);
        $db = getDB();
        
        $placeholders = str_repeat('?,', count($users) - 1) . '?';
        $query = "UPDATE TBL_USER SET STATUS = 'ACTIVE' WHERE ID IN ($placeholders) AND STATUS = 'INACTIVE'";
        
        $stmt = $db->prepare($query);
        $stmt->execute($users);
        
        $affectedRows = $stmt->rowCount();
        $_SESSION['success_message'] = "$affectedRows users successfully activated.";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error activating users: " . $e->getMessage();
    }
}

header('Location: user.php');
exit();
?>