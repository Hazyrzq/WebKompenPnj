<?php
require_once('../../Config.php');
// Cek jika ada ID yang dikirim
if (isset($_GET['id'])) {
    try {
        $db = getDB();
        $db->beginTransaction();

        // Delete the specific job
        $id_pekerjaan = $_GET['id'];
        $deleteQuery = "DELETE FROM tbl_pekerjaan WHERE id_pekerjaan = :id_pekerjaan";
        executeQuery($deleteQuery, ['id_pekerjaan' => $id_pekerjaan]);

        // Get all remaining records ordered by id_pekerjaan
        $getAllQuery = "SELECT id_pekerjaan FROM tbl_pekerjaan ORDER BY id_pekerjaan";
        $stmt = executeQuery($getAllQuery, []);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Reset sequence
        $dropSequenceQuery = "
            BEGIN
                EXECUTE IMMEDIATE 'DROP SEQUENCE seq_id_pekerjaan';
                EXCEPTION WHEN OTHERS THEN NULL;
            END;";
        executeQuery($dropSequenceQuery, []);

        // Get the next available ID
        $maxIdQuery = "SELECT NVL(MAX(id_pekerjaan), 0) + 1 as next_id FROM tbl_pekerjaan";
        $stmt = executeQuery($maxIdQuery, []);
        $nextId = $stmt->fetch()['NEXT_ID'];

        // Create new sequence starting with next available ID
        $createSequenceQuery = "CREATE SEQUENCE seq_id_pekerjaan 
                               START WITH {$nextId} 
                               INCREMENT BY 1 
                               NOMAXVALUE 
                               NOCACHE";
        executeQuery($createSequenceQuery, []);

        $db->commit();
        header('Location: pekerjaan.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        die("Error menghapus pekerjaan: " . $e->getMessage());
    }
}
// Cek jika perintah delete all
else if (isset($_GET['delete_all'])) {
    try {
        $db = getDB();
        $db->beginTransaction();

        // Delete all jobs
        $deleteAllQuery = "DELETE FROM tbl_pekerjaan";
        executeQuery($deleteAllQuery, []);

        // Reset sequence
        $resetSequenceQuery = "
            BEGIN
                EXECUTE IMMEDIATE 'DROP SEQUENCE seq_id_pekerjaan';
                EXCEPTION WHEN OTHERS THEN NULL;
            END;";
        executeQuery($resetSequenceQuery, []);

        // Create new sequence
        $createSequenceQuery = "CREATE SEQUENCE seq_id_pekerjaan 
                               START WITH 1 
                               INCREMENT BY 1 
                               NOMAXVALUE 
                               NOCACHE";
        executeQuery($createSequenceQuery, []);

        $db->commit();
        header('Location: pekerjaan.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        die("Error menghapus semua pekerjaan: " . $e->getMessage());
    }
} else {
    // Jika tidak ada ID atau perintah delete all, kembali ke halaman pekerjaan
    header('Location: pekerjaan.php');
    exit;
}
?>