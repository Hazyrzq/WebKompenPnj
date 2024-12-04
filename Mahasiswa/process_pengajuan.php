<?php
// process_pengajuan.php
session_start();
header('Content-Type: application/json');
require_once "../config/koneksi.php";
require_once "functions.php";

// Definisikan konstanta untuk batas maksimal
define('MAX_MENIT_PENGAJUAN', 1500);

function calculateSisaKompen($currentSisa, $totalJam) {
    // Konversi jam ke menit
    $totalMenit = $totalJam * 60;
    
    // Batasi pengurangan maksimal ke MAX_MENIT_PENGAJUAN
    $menitYangDikurangkan = min($totalMenit, MAX_MENIT_PENGAJUAN);
    
    // Hitung sisa dengan memastikan tidak kurang dari maksimal pengurangan
    return max($currentSisa - $menitYangDikurangkan, $currentSisa - MAX_MENIT_PENGAJUAN);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Validate required input
    if (!isset($_POST['nim']) || !isset($_POST['pekerjaan']) || !isset($_POST['selected_pj'])) {
        throw new Exception('Missing required fields');
    }

    $nim = $_POST['nim'];
    $selected_pekerjaan = $_POST['pekerjaan'];
    $selected_pj = $_POST['selected_pj'];

    // Get mahasiswa data
    $mahasiswa = getMahasiswaData($nim);
    if (!$mahasiswa) {
        throw new Exception('Data mahasiswa tidak ditemukan');
    }

    // Generate unique kode_kegiatan
    function generateUniqueKode($conn, $nim) {
        $maxAttempts = 5;
        $attempt = 0;
        
        do {
            if ($attempt >= $maxAttempts) {
                throw new Exception('Tidak dapat menghasilkan kode unik setelah beberapa percobaan');
            }
            
            // Format: KK + YYYYMMDD + HMS + Random 3 digits + Last 4 of NIM
            $kode = 'KK' . date('Ymd_His') . rand(100, 999) . substr($nim, -4);
            
            // Check if code exists
            $sql = "SELECT COUNT(*) as count FROM tbl_pengajuan WHERE kode_kegiatan = :kode";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['kode' => $kode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $exists = $result['COUNT'] > 0;
            $attempt++;
            
        } while ($exists);
        
        return $kode;
    }

    // Generate single unique kode_kegiatan for all selected pekerjaan
    $kode_kegiatan = generateUniqueKode($conn, $nim);
    
    // Calculate total jam from all selected pekerjaan
    $total_jam = 0;
    $pekerjaan_details = [];
    foreach ($selected_pekerjaan as $kode_pekerjaan) {
        $sql = "SELECT * FROM tbl_pekerjaan WHERE kode_pekerjaan = :kode";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['kode' => $kode_pekerjaan]);
        $pekerjaan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pekerjaan) {
            $total_jam += intval($pekerjaan['JAM_PEKERJAAN']);
            $pekerjaan_details[] = $pekerjaan;
        }
    }

    $sisa_kompen = getSisaJamKompen($nim);
    
    // Convert total_jam to minutes for comparison
    $total_menit = $total_jam * 60;
    
    // Cap the reduction amount to MAX_MENIT_PENGAJUAN
    $menit_yang_dikurangkan = min($total_menit, MAX_MENIT_PENGAJUAN);
    
    // Calculate new sisa
    $sisa_after = max($sisa_kompen - $menit_yang_dikurangkan, $sisa_kompen - MAX_MENIT_PENGAJUAN);
    
    // Additional validation to ensure sisa doesn't go below minimum possible value
    if ($sisa_kompen > MAX_MENIT_PENGAJUAN) {
        $minimum_sisa = $sisa_kompen - MAX_MENIT_PENGAJUAN;
        $sisa_after = max($sisa_after, $minimum_sisa);
    }

    $conn->beginTransaction();

    // Generate ID pengajuan
    $sql = "SELECT SEQ_ID_PENGAJUAN.NEXTVAL AS ID_PENGAJUAN FROM DUAL";
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_pengajuan = $result['ID_PENGAJUAN'];

    // Get first pekerjaan PJ info
    $first_pekerjaan = $pekerjaan_details[0];

    // Insert single pengajuan record
    $sql = "INSERT INTO tbl_pengajuan (
                id_pengajuan, 
                kode_kegiatan, 
                kode_user, 
                nama_user, 
                kelas, 
                prodi,
                semester, 
                jumlah_terlambat, 
                jumlah_alfa, 
                total, 
                sisa,
                id_penanggung_jawab, 
                penanggung_jawab, 
                tanggal_pengajuan,
                status_approval1, 
                status_approval2, 
                status_approval3,
                status, 
                created_at, 
                user_create
            ) VALUES (
                :id_pengajuan, 
                :kode_kegiatan, 
                :nim, 
                :nama, 
                :kelas, 
                :prodi,
                :semester, 
                :jumlah_terlambat, 
                :jumlah_alfa, 
                :total, 
                :sisa,
                :id_pj, 
                :penanggung_jawab, 
                CURRENT_TIMESTAMP,
                'Pending', 
                'Pending', 
                'Pending',
                'Pending', 
                CURRENT_TIMESTAMP, 
                :user_nim
            )";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'id_pengajuan' => $id_pengajuan,
        'kode_kegiatan' => $kode_kegiatan,
        'nim' => $nim,
        'nama' => $mahasiswa['NAMA'],
        'kelas' => $mahasiswa['KELAS'],
        'prodi' => $mahasiswa['PRODI'],
        'semester' => $mahasiswa['SEMESTER'],
        'jumlah_terlambat' => $mahasiswa['JUMLAH_TERLAMBAT'],
        'jumlah_alfa' => $mahasiswa['JUMLAH_ALFA'],
        'total' => $mahasiswa['TOTAL'],
        'sisa' => $sisa_after,
        'id_pj' => $first_pekerjaan['ID_PENANGGUNG_JAWAB'],
        'penanggung_jawab' => $first_pekerjaan['PENANGGUNG_JAWAB'],
        'user_nim' => $nim
    ]);

    // Insert all selected pekerjaan as details
    foreach ($pekerjaan_details as $pekerjaan) {
        // Get next ID for pengajuan detail
        $sql = "SELECT SEQ_ID_PENG_DET.NEXTVAL AS ID_DETAIL FROM DUAL";
        $stmt = $conn->query($sql);
        $det_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_detail = $det_result['ID_DETAIL'];

        // Insert detail
        $sql = "INSERT INTO tbl_pengajuan_detail (
                    id_pengajuan_detail,
                    kode_kegiatan, 
                    kode_pekerjaan, 
                    nama_pekerjaan,
                    jam_pekerjaan, 
                    batas_pekerja, 
                    created_at, 
                    user_create
                ) VALUES (
                    :id_detail,
                    :kode_kegiatan, 
                    :kode_pekerjaan, 
                    :nama_pekerjaan,
                    :jam_pekerjaan, 
                    :batas_pekerja, 
                    CURRENT_TIMESTAMP, 
                    :user_create
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_detail' => $id_detail,
            'kode_kegiatan' => $kode_kegiatan,
            'kode_pekerjaan' => $pekerjaan['KODE_PEKERJAAN'],
            'nama_pekerjaan' => $pekerjaan['NAMA_PEKERJAAN'],
            'jam_pekerjaan' => $pekerjaan['JAM_PEKERJAAN'],
            'batas_pekerja' => $pekerjaan['BATAS_PEKERJA'],
            'user_create' => $nim
        ]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pengajuan berhasil dibuat dengan ID: ' . $id_pengajuan,
        'kode_kegiatan' => $kode_kegiatan,
        'total_jam' => $total_jam
    ]);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error in process_pengajuan.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
    exit();
}