<?php
session_start();
require_once('../../Config.php');// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Request: ID is required.");
}

try {
    $db = getDB();
    $id = $_GET['id'];

    // Prepare and execute delete query using the correct column name
    $stmt = $db->prepare("DELETE FROM tbl_mahasiswa WHERE id_mhs = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Redirect back with success message
        header('Location: mahasiswa.php?message=Data successfully deleted');
        exit();
    } else {
        // Redirect back with error message
        header('Location: mahasiswa.php?message=Failed to delete data');
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
