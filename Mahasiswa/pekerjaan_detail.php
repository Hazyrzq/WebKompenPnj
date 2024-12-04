<?php
// get_job_details.php
require_once "../config/koneksi.php";
require_once "functions.php";

if (!isset($_GET['kode'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Kode pekerjaan tidak ditemukan']);
    exit;
}

try {
    $kode_pekerjaan = $_GET['kode'];
    
    // Get job details including current workers count
    $sql = "SELECT 
            p.*,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM tbl_pengajuan_detail pd 
                 JOIN tbl_pengajuan pg ON pd.kode_kegiatan = pg.kode_kegiatan 
                 WHERE pd.kode_pekerjaan = p.kode_pekerjaan 
                 AND UPPER(pg.status) NOT IN ('REJECTED', 'CANCELLED')
                ), 0
            ) as current_workers
        FROM tbl_pekerjaan p
        WHERE p.kode_pekerjaan = :kode_pekerjaan";
    
    $stmt = executeQuery($sql, ['kode_pekerjaan' => $kode_pekerjaan]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        http_response_code(404);
        echo json_encode(['error' => 'Pekerjaan tidak ditemukan']);
        exit;
    }
    
    echo json_encode($job);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan server']);
}