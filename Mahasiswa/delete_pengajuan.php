<?php
// delete_pengajuan.php
session_start();
require_once '../config/koneksi.php';
require_once 'functions.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['kode_kegiatan'])) {
        throw new Exception('Kode kegiatan tidak ditemukan');
    }

    $kode_kegiatan = $_POST['kode_kegiatan'];
    
    $conn->beginTransaction();

    // First check if pengajuan can be deleted
    $checkSql = "SELECT status, status_approval1, status_approval2, status_approval3 
                 FROM tbl_pengajuan 
                 WHERE kode_kegiatan = :kode_kegiatan";
    $stmt = $conn->prepare($checkSql);
    $stmt->execute(['kode_kegiatan' => $kode_kegiatan]);
    $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pengajuan) {
        throw new Exception('Pengajuan tidak ditemukan');
    }

    if ($pengajuan['STATUS'] !== 'Belum Melakukan Pekerjaan' || 
        $pengajuan['STATUS_APPROVAL1'] !== 'Pending' || 
        $pengajuan['STATUS_APPROVAL2'] !== 'Pending' || 
        $pengajuan['STATUS_APPROVAL3'] !== 'Pending') {
        throw new Exception('Pengajuan tidak dapat dihapus karena sudah diproses');
    }

    // Delete from detail first
    $deleteSql = "DELETE FROM tbl_pengajuan_detail WHERE kode_kegiatan = :kode_kegiatan";
    $stmt = $conn->prepare($deleteSql);
    $stmt->execute(['kode_kegiatan' => $kode_kegiatan]);

    // Then delete from main table
    $deleteSql = "DELETE FROM tbl_pengajuan WHERE kode_kegiatan = :kode_kegiatan";
    $stmt = $conn->prepare($deleteSql);
    $stmt->execute(['kode_kegiatan' => $kode_kegiatan]);

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pengajuan berhasil dihapus'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Delete error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus pengajuan: ' . $e->getMessage()
    ]);
}