<?php
require_once('../config/koneksi.php');
require_once('functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nim = isset($_GET['nim']) ? $_GET['nim'] : '';

try {
    // Cek total kompen mahasiswa
    $totalKompen = getTotalJamKompen($nim);
    
    if ($totalKompen === 0) {
        // Jika total kompen 0, langsung generate surat tanpa pengecekan approval
        generateSuratBebasKompen($nim);
        exit;
    }

    // Jika total kompen bukan 0, lakukan pengecekan approval seperti sebelumnya
    $sql = "SELECT COUNT(*) as \"COUNT\"
            FROM TBL_PENGAJUAN 
            WHERE KODE_USER = :nim 
            AND STATUS_APPROVAL1 = 'Approved'
            AND STATUS_APPROVAL2 = 'Approved'
            AND STATUS_APPROVAL3 = 'Approved'";
            
    $stmt = executeQuery($sql, ['nim' => $nim]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!isset($result['COUNT'])) {
        $checkSql = "SELECT 
                        STATUS_APPROVAL1,
                        STATUS_APPROVAL2,
                        STATUS_APPROVAL3
                    FROM TBL_PENGAJUAN 
                    WHERE KODE_USER = :nim";
        $checkStmt = executeQuery($checkSql, ['nim' => $nim]);
        $statusCheck = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Debug - Current status for NIM $nim: " . print_r($statusCheck, true));
        throw new Exception('Error retrieving approval status');
    }
    
    $completeCount = (int)$result['COUNT'];
    
    if ($completeCount == 0) {
        $checkSql = "SELECT 
                        STATUS_APPROVAL1,
                        STATUS_APPROVAL2,
                        STATUS_APPROVAL3
                    FROM TBL_PENGAJUAN 
                    WHERE KODE_USER = :nim";
        $checkStmt = executeQuery($checkSql, ['nim' => $nim]);
        $statusCheck = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($statusCheck)) {
            throw new Exception('Tidak ditemukan pengajuan untuk NIM ini');
        } else {
            $statusInfo = [];
            foreach ($statusCheck as $status) {
                $statusInfo[] = 
                               "Approval1: {$status['STATUS_APPROVAL1']}, " .
                               "Approval2: {$status['STATUS_APPROVAL2']}, " .
                               "Approval3: {$status['STATUS_APPROVAL3']}";
            }
            throw new Exception('Tidak dapat menggenerate surat. Semua approval harus sudah selesai. ' .
                              'Status saat ini: ' . implode('; ', $statusInfo));
        }
    }

    // Jika total kompen bukan 0 dan semua approval sudah selesai, generate surat
    generateSuratBebasKompen($nim);
    
} catch (Exception $e) {
    error_log("Error in generate_surat.php: " . $e->getMessage());
    die($e->getMessage());
}