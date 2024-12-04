<?php
function getTotalJamKompen($nim) {
    global $conn;
    $sql = "SELECT NVL(TOTAL, 0) as total FROM tbl_mahasiswa WHERE nim = :nim";
    $stmt = executeQuery($sql, ['nim' => $nim]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? intval($result['TOTAL']) : 0;
}

function getSisaJamKompen($nim) {
    try {
        global $conn;
        $totalMenit = getTotalJamKompen($nim);
        $MAX_MENIT_PENGAJUAN = 1500;
        
        // Hitung kelebihan kompen
        $kelebihanKompen = max(0, $totalMenit - $MAX_MENIT_PENGAJUAN);
        
        // Check payment status first
        $paymentSql = "SELECT status FROM tbl_payments WHERE nim = :nim";
        $paymentStmt = executeQuery($paymentSql, ['nim' => $nim]);
        $paymentResult = $paymentStmt->fetch(PDO::FETCH_ASSOC);
        
        // Initialize remaining minutes with total minutes
        $sisaMenit = $totalMenit;
        
        // If payment is successful, reduce by excess amount
        if ($paymentResult && strtoupper($paymentResult['STATUS']) === 'SUCCESS') {
            $sisaMenit = max(0, $totalMenit - $kelebihanKompen);
            error_log("Payment found for NIM: " . $nim . ". Reducing kompen by excess amount: " . $kelebihanKompen . " minutes");
        }
        
        // Calculate work minutes
        $sql = "SELECT 
                    NVL(SUM(TO_NUMBER(REPLACE(pd.jam_pekerjaan, ',', '.')) * 60), 0) as total_menit_pekerjaan
                FROM tbl_pengajuan_detail pd 
                JOIN tbl_pengajuan pg ON pd.kode_kegiatan = pg.kode_kegiatan 
                WHERE pg.kode_user = :nim 
                AND UPPER(pg.status) != 'REJECTED'";
                
        $stmt = executeQuery($sql, ['nim' => $nim]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $menitPekerjaan = floatval($result['TOTAL_MENIT_PEKERJAAN']);
        
        // Limit work reduction to maximum allowed
        $adjustedMenitPekerjaan = min($menitPekerjaan, $MAX_MENIT_PENGAJUAN);
        
        // Calculate final remaining minutes after both payment and work
        $sisaMenit = max(0, $sisaMenit - $adjustedMenitPekerjaan);
        
        // Update database with the calculated value
        $updateSql = "UPDATE tbl_pengajuan 
                     SET sisa = :sisa 
                     WHERE kode_user = :nim";
        
        $updateStmt = executeQuery($updateSql, [
            'sisa' => (string)$sisaMenit,
            'nim' => $nim
        ]);
        
        error_log("Detail perhitungan getSisaJamKompen:");
        error_log("Total menit kompen awal: " . $totalMenit);
        error_log("Batas maksimal gratis: " . $MAX_MENIT_PENGAJUAN);
        error_log("Kelebihan kompen: " . $kelebihanKompen);
        error_log("Status pembayaran: " . ($paymentResult ? $paymentResult['STATUS'] : 'Not found'));
        error_log("Sisa setelah pembayaran: " . ($totalMenit - $kelebihanKompen));
        error_log("Total menit pekerjaan aktual: " . $menitPekerjaan);
        error_log("Menit pekerjaan yang dihitung (max 1500): " . $adjustedMenitPekerjaan);
        error_log("Sisa menit final: " . $sisaMenit);
        
        return $sisaMenit;
        
    } catch (Exception $e) {
        error_log("Error in getSisaJamKompen: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return $totalMenit;
    }
}

function formatTimeWithBoth($minutes) {
    if ($minutes <= 0) return "0 menit (0 jam)";
    
    $hours = floor($minutes / 60);
    return sprintf("%d menit (%d jam)", $minutes, $hours);
}

function getAvailablePekerjaan(){
    try {
        global $conn;
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
            WHERE UPPER(NVL(p.status, 'ACTIVE')) = 'ACTIVE'";

        $stmt = executeQuery($sql, []);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("SQL Query: " . $sql);
        error_log("Number of jobs found: " . count($result));

        foreach ($result as &$job) {
            $job['AVAILABLE_SLOTS'] = $job['BATAS_PEKERJA'] - $job['CURRENT_WORKERS'];
            error_log("Job: {$job['KODE_PEKERJAAN']}, Batas: {$job['BATAS_PEKERJA']}, Current: {$job['CURRENT_WORKERS']}, Available: {$job['AVAILABLE_SLOTS']}");
        }

        return $result;
    } catch (Exception $e) {
        error_log("Error in getAvailablePekerjaan: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

function displayListPekerjaanWithSearch($currentPage = 1) {
    try {
        $itemsPerPage = 6;
        $jobs = getAvailablePekerjaan();

        if (empty($jobs)) {
            return '<div class="alert alert-info shadow-sm border-0 rounded-lg">
                <i class="fas fa-info-circle me-2"></i>
                Belum ada pekerjaan yang tersedia saat ini.
            </div>';
        }

        $supervisors = array_unique(array_column($jobs, 'PENANGGUNG_JAWAB'));
        $minutes = array_unique(array_map(function($time) {
            return $time * 60;
        }, array_column($jobs, 'JAM_PEKERJAAN')));
        sort($supervisors);
        sort($minutes);

        $output = '<link rel="stylesheet" href="css/list_pekerjaan.css">';

        $output .= '<div id="jobListingContainer" class="job-listing-container">
        <h2 class="mb-4 text-primary fw-bold">List Pekerjaan</h2>
        <div class="row mb-4 g-3">
            <div class="col-md-6">
                <div class="search-input input-group">
                    <input type="text" class="form-control border-0 py-2" id="searchInput" placeholder="Cari kode atau nama pekerjaan">
                    <button class="btn btn-link text-primary border-0" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select custom-select" id="supervisorFilter">
                    <option value="">Semua Pengawas</option>';
        foreach ($supervisors as $supervisor) {
            $output .= '<option value="' . htmlspecialchars($supervisor) . '">' . htmlspecialchars($supervisor) . '</option>';
        }
        $output .= '</select>
            </div>
            <div class="col-md-3">
                <select class="form-select custom-select" id="minutesFilter">
                    <option value="">Semua Menit</option>';
        foreach ($minutes as $minute) {
            $output .= '<option value="' . $minute . '">' . $minute . ' menit</option>';
        }
        $output .= '</select>
            </div>
        </div>';

        foreach ($jobs as &$job) {
            $job['MENIT_PEKERJAAN'] = $job['JAM_PEKERJAAN'] * 60;
        }

        $output .= '<script>
            window.allJobs = ' . json_encode($jobs) . ';
            window.itemsPerPage = ' . $itemsPerPage . ';
        </script>';
        
        $output .= '<div class="row g-4" id="jobList"></div>
        <div class="d-flex justify-content-center mt-5">
            <nav aria-label="Page navigation">
                <ul class="pagination" id="pagination"></ul>
            </nav>
        </div>';

        $output .= '<script src="js/list_pekerjaan.js">
        </script>
        </div>';

        return $output;
    } catch (Exception $e) {
        error_log("Error in displayListPekerjaanWithSearch: " . $e->getMessage());
        return '<div class="alert alert-danger shadow-sm border-0 rounded-lg">
            <i class="fas fa-exclamation-circle me-2"></i>
            Terjadi kesalahan saat memuat daftar pekerjaan: ' . htmlspecialchars($e->getMessage()) . '
        </div>';
    }
}

function getMahasiswaData($nim){
    $sql = "SELECT * FROM tbl_mahasiswa WHERE nim = :nim";
    $stmt = executeQuery($sql, ['nim' => $nim]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateProgress($nim) {
    try {
        $sql = "SELECT 
                    p.kode_kegiatan,
                    p.status,
                    p.status_approval1,
                    p.status_approval2,
                    p.status_approval3
                FROM tbl_pengajuan p
                WHERE p.kode_user = :nim 
                AND UPPER(p.status) != 'REJECTED'";         
        $stmt = executeQuery($sql, ['nim' => $nim]);
        $pengajuans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pengajuans)) {
            return 0;
        }

        $totalProgress = 0;
        foreach ($pengajuans as $pengajuan) {
            $progress = 0;
            
            if ($pengajuan['STATUS'] != 'Rejected') {
                $progress = 25; // Phase 1: Pengajuan dibuat
                
                if ($pengajuan['STATUS_APPROVAL1'] == 'Approved') {
                    $progress = 50; // Phase 2: Approval 1 approved
                    
                    if ($pengajuan['STATUS_APPROVAL2'] == 'Approved') {
                        $progress = 75; // Phase 3: Approval 2 approved
                        
                        if ($pengajuan['STATUS_APPROVAL3'] == 'Approved') {
                            $progress = 100; // Phase 4: Approval 3 approved
                        }
                    }
                }
            }  
            $totalProgress += $progress;
        }
        if (count($pengajuans) > 0) {
            $totalProgress = $totalProgress / count($pengajuans);
        }
        return min(100, round($totalProgress));

    } catch (Exception $e) {
        error_log("Error in calculateProgress: " . $e->getMessage());
        return 0;
    }
}

function displayPengajuanStatus($nim) {
    try {
        $mahasiswaData = getMahasiswaData($nim);
        $totalKompenMenit = getTotalJamKompen($nim); 
        $sisaKompenMenit = getSisaJamKompen($nim);
        $progressPercentage = calculateProgress($nim);
        
        $sql = "SELECT 
                    p.kode_kegiatan,
                    p.tanggal_pengajuan,
                    p.status,
                    p.status_approval1,
                    p.status_approval2,
                    p.status_approval3,
                    p.penanggung_jawab,
                    LISTAGG(pd.nama_pekerjaan, ', ') WITHIN GROUP (ORDER BY pd.nama_pekerjaan) as pekerjaan_list,
                    SUM(TO_NUMBER(pd.jam_pekerjaan)) as total_jam
                FROM tbl_pengajuan p
                LEFT JOIN tbl_pengajuan_detail pd ON p.kode_kegiatan = pd.kode_kegiatan
                WHERE p.kode_user = :nim
                GROUP BY 
                    p.kode_kegiatan,
                    p.tanggal_pengajuan,
                    p.status,
                    p.status_approval1,
                    p.status_approval2,
                    p.status_approval3,
                    p.penanggung_jawab
                ORDER BY p.tanggal_pengajuan DESC";
                
        $stmt = executeQuery($sql, ['nim' => $nim]);
        $pengajuan = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $output = '<link rel="stylesheet " href="css/pengajuan_status.css">';
        
        // Stats Cards
        $output .= '
        <div class="stats-container mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="stats-card total-card">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Total Kompen</h3>
                            <div class="stats-value">' . formatTimeWithBoth($totalKompenMenit) . '</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card remaining-card">
                        <div class="stats-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Sisa Kompen</h3>
                            <div class="stats-value">' . formatTimeWithBoth($sisaKompenMenit) . '</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card progress-card">
                        <div class="stats-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Progress</h3>
                            <div class="stats-value">' . $progressPercentage . '%</div>
                            <div class="progress-wrapper">
                                <div class="progress">
                                    <div class="progress-bar" style="width: ' . $progressPercentage . '%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        // Submission History
        $output .= '
        <div class="submission-history card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Pengajuan</h5>
                </div>
                <a href="?page=new-pengajuan" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Pengajuan Baru
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kode</th>
                                <th>Pekerjaan</th>
                                <th>Total Jam</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>';

                        if (empty($pengajuan)) {
                            $output .= '
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state text-center py-5">
                                            <div class="empty-icon mb-3">
                                                <i class="fas fa-clipboard-list fa-3x text-muted"></i>
                                            </div>
                                            <h4 class="fw-normal text-muted mb-3">Belum Ada Pengajuan</h4>
                                            <p class="text-muted mb-4">Mulai dengan membuat pengajuan baru</p>
                                            <a href="?page=new-pengajuan" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Buat Pengajuan
                                            </a>
                                        </div>
                                    </td>
                                </tr>';
                        } else {
                            foreach ($pengajuan as $p) {
                                $totalJamMinutes = $p['TOTAL_JAM'] * 60;
                                
                                // Perbaikan logika untuk button delete
                                $canDelete = (
                                    (strtolower($p['STATUS']) == 'pending' || strtolower($p['STATUS']) == 'belum melakukan pekerjaan') &&
                                    (
                                        empty($p['STATUS_APPROVAL1']) || strtolower($p['STATUS_APPROVAL1']) == 'pending' ||
                                        $p['STATUS_APPROVAL1'] === null
                                    ) &&
                                    (
                                        empty($p['STATUS_APPROVAL2']) || strtolower($p['STATUS_APPROVAL2']) == 'pending' ||
                                        $p['STATUS_APPROVAL2'] === null
                                    ) &&
                                    (
                                        empty($p['STATUS_APPROVAL3']) || strtolower($p['STATUS_APPROVAL3']) == 'pending' ||
                                        $p['STATUS_APPROVAL3'] === null
                                    )
                                );
                
                                $output .= '<tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="submission-date">
                                                <div class="date">' . date('d', strtotime($p['TANGGAL_PENGAJUAN'])) . '</div>
                                                <div class="month">' . date('M', strtotime($p['TANGGAL_PENGAJUAN'])) . '</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="submission-code">' . htmlspecialchars($p['KODE_KEGIATAN']) . '</span></td>
                                    <td>
                                        <div class="text-wrap work-list">' . htmlspecialchars($p['PEKERJAAN_LIST']) . '</div>
                                    </td>
                                    <td><span class="hours-badge">' . formatTimeWithBoth($totalJamMinutes) . '</span></td>
                                    <td>' . getStatusBadge($p['STATUS'], $p) . '</td>
                                    <td>' . getApprovalProgress($p) . '</td>
                                    <td class="text-end">
                                        <div class="actions">
                                            <a href="?page=pengajuan-detail&kode_kegiatan=' . htmlspecialchars($p['KODE_KEGIATAN']) . '" 
                                               class="btn btn-primary" title="Lihat Detail">
                                                Lanjutkan
                                            </a>
                                            ' . ($canDelete ? '
                                            <button class="btn btn-sm btn-danger ms-1" 
                                                    title="Hapus" 
                                                    onclick="confirmDelete(\'' . htmlspecialchars($p['KODE_KEGIATAN']) . '\')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            ' : '') . '
                                        </div>
                                    </td>
                                </tr>';
                            }
                        }

        $output .= '</tbody>
                    </table>
                </div>';
        
        // Check if any submission has completed all three approvals
        $hasCompletedApprovals = false;
        foreach ($pengajuan as $p) {
            if ($p['STATUS_APPROVAL1'] === 'Approved' && 
                $p['STATUS_APPROVAL2'] === 'Approved' && 
                $p['STATUS_APPROVAL3'] === 'Approved') {
                $hasCompletedApprovals = true;
                break;
            }
        }

        if ($totalKompenMenit === 0) {
            $output = '
            <div style="background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                <div style="color: #0f172a; font-size: 1.5rem; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center;">
                    <span style="color: #22c55e; margin-right: 0.75rem; font-size: 1.75rem;">
                        <i class="fas fa-check-circle"></i>
                    </span>
                    Selamat, Anda Bebas Kompen!
                </div>
                <p style="color: #475569; font-size: 1rem; margin-bottom: 1.5rem;">Anda tidak memiliki kompensasi yang perlu diselesaikan.</p>
                <div style="border-top: 1px solid rgba(203, 213, 225, 0.4); margin: 1.5rem 0;"></div>
                <div style="display: flex; justify-content: flex-end;">
                    <a href="generate_surat_bk.php?nim=' . $nim . '" style="background: #0ea5e9; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; text-decoration: none;" onmouseover="this.style.background=\'#0284c7\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 4px 12px rgba(14, 165, 233, 0.15)\';" onmouseout="this.style.background=\'#0ea5e9\'; this.style.transform=\'none\'; this.style.boxShadow=\'none\';">
                        <i class="fas fa-download" style="font-size: 1.25rem;"></i>
                        Unduh Surat Bebas Kompen
                    </a>
                </div>
            </div>';
        }

        // Add Bebas Kompen container if approvals are complete
        if ($hasCompletedApprovals) {
            $output .= '
            <div class="mt-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-1"><i class="fas fa-certificate text-success me-2"></i>Bebas Kompen</h5>
                                <p class="text-muted mb-0">Anda telah menyelesaikan semua kompensasi. Silakan unduh surat bebas kompen.</p>
                            </div>
                            <div>
                                <a href="generate_surat_bk.php?nim=' . $nim . '" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i>Download Surat Bebas Kompen
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }

        $output .= '</div></div>';

        // JavaScript remains the same
        $output .= '
        <script>
        function confirmDelete(kodeKegiatan) {
            Swal.fire({
                title: "Hapus Pengajuan?",
                text: "Pengajuan yang sudah dihapus tidak dapat dikembalikan",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#dc2626",
                cancelButtonColor: "#6b7280",
                confirmButtonText: "Ya, Hapus",
                cancelButtonText: "Batal",
                customClass: {
                    popup: "rounded-lg",
                    confirmButton: "rounded-md",
                    cancelButton: "rounded-md"
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("delete_pengajuan.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: "kode_kegiatan=" + encodeURIComponent(kodeKegiatan)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Berhasil!",
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Gagal!",
                                text: data.message
                            });
                        }
                    });
                }
            });
        }
        </script>';

        return $output;

    } catch (Exception $e) {
        error_log("Error in displayPengajuanStatus: " . $e->getMessage());
        return '<div class="alert alert-danger">Terjadi kesalahan saat memuat status pengajuan</div>';
    }
}

function generateSuratBebasKompen($nim) {
    require_once('..\vendor\tecnickcom\tcpdf\tcpdf.php');
    
    function convertBlobToImageTag($blobResource, $width = 80, $height = 40) {
        if ($blobResource) {
            if (is_resource($blobResource)) {
                $blobData = stream_get_contents($blobResource);
            } else {
                $blobData = $blobResource;
            }
            $base64Image = base64_encode($blobData);
            return '<img src="data:image/png;base64,' . $base64Image . '" width="' . $width . '" height="' . $height . '">';
        }
        return '';
    }

    try {
        ob_clean();
        setlocale(LC_TIME, 'id_ID');
        $currentDate = date('d F Y');

        // Get kalab signature (for Lab Jaringan dan Komputer)
        $sqlKalab = "SELECT ttd FROM (
                        SELECT ttd 
                        FROM tbl_user 
                        WHERE role = 'KALAB' 
                        AND ttd IS NOT NULL
                    ) WHERE ROWNUM = 1";
        $stmtKalab = executeQuery($sqlKalab);
        $blobKalab = $stmtKalab->fetchColumn();
        
        if (!$blobKalab) {
            throw new Exception("Tanda tangan Kepala Lab tidak ditemukan atau belum diatur");
        }

        // Get PLP signature (for Lab Cyber Security)
        $sqlPlp = "SELECT ttd FROM (
                    SELECT ttd 
                    FROM tbl_user 
                    WHERE role = 'PLP' 
                    AND ttd IS NOT NULL
                ) WHERE ROWNUM = 1";
        $stmtPlp = executeQuery($sqlPlp);
        $blobPlp = $stmtPlp->fetchColumn();
        
        if (!$blobPlp) {
            throw new Exception("Tanda tangan PLP tidak ditemukan atau belum diatur");
        }

        // Get pengawas signature
        $sqlPengawas = "SELECT ttd FROM (
            SELECT ttd FROM (
                SELECT u.ttd 
                FROM tbl_user u
                JOIN tbl_pekerjaan p ON u.id = p.id_penanggung_jawab
                JOIN tbl_pengajuan_detail pd ON p.kode_pekerjaan = pd.kode_pekerjaan
                JOIN tbl_pengajuan pg ON pd.kode_kegiatan = pg.kode_kegiatan
                WHERE pg.kode_user = :nim 
                AND u.ttd IS NOT NULL
            )
            WHERE ROWNUM = 1
            
            UNION ALL
            
            SELECT ttd FROM (
                SELECT u2.ttd
                FROM tbl_user u2
                WHERE u2.ttd IS NOT NULL 
                AND u2.role NOT IN ('MAHASISWA')
                AND NOT EXISTS (
                    SELECT 1 
                    FROM tbl_user u
                    JOIN tbl_pekerjaan p ON u.id = p.id_penanggung_jawab
                    JOIN tbl_pengajuan_detail pd ON p.kode_pekerjaan = pd.kode_pekerjaan
                    JOIN tbl_pengajuan pg ON pd.kode_kegiatan = pg.kode_kegiatan
                    WHERE pg.kode_user = :nim 
                    AND u.ttd IS NOT NULL
                )
            )
            WHERE ROWNUM = 1
        ) WHERE ROWNUM = 1";
        
        $stmtPengawas = executeQuery($sqlPengawas, ['nim' => $nim]);
        $blobPengawas = $stmtPengawas->fetchColumn();
        
        if (!$blobPengawas) {
            throw new Exception("Tanda tangan Pengawas tidak ditemukan atau belum diatur");
        }

        $kalabImageTag = convertBlobToImageTag($blobKalab);
        $plpImageTag = convertBlobToImageTag($blobPlp);
        $pengawasImageTag = convertBlobToImageTag($blobPengawas);

        // Get student data
        $sql = "SELECT 
                    m.NAMA as \"nama\",
                    m.NIM as \"nim\",
                    m.PRODI as \"prodi\",
                    m.KELAS as \"kelas\",
                    m.total as \"jumlah_terlambat\"
                FROM TBL_MAHASISWA m 
                WHERE m.NIM = :nim";
                
        $stmt = executeQuery($sql, ['nim' => $nim]);
        $mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mahasiswa) {
            throw new Exception("Data mahasiswa tidak ditemukan");
        }
        
        // Initialize PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistem Kompen');
        $pdf->SetAuthor('Administrator');
        $pdf->SetTitle('Lembar Bebas Pinjaman, Administrasi dan Kompensasi');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();

        // Create header
        $header = '
        <table border="1" cellpadding="4" cellspacing="0">
            <tr>
                <td width="15%" align="center" rowspan="2"><img src="..\images\Logo_PNJ.png" width="60"></td>
                <td width="70%" align="center"><b>KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET,<br>DAN TEKNOLOGI<br>POLITEKNIK NEGERI JAKARTA<br>JURUSAN TEKNIK INFORMATIKA DAN KOMPUTER</b></td>
                <td width="15%" align="center"><b>K-01</b></td>
            </tr>
            <tr>
                <td colspan="2" align="center"><b>LEMBAR BEBAS PINJAMAN, ADMINISTRASI DAN KOMPENSASI</b></td>
            </tr>
        </table>';
        
        $pdf->writeHTML($header, true, false, true, false, '');
        $pdf->Ln(5);

        // Add student information
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(45, 6, 'NAMA MAHASISWA', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, $mahasiswa['nama'], 0, 1);
        
        $pdf->Cell(45, 6, 'NIM', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, $mahasiswa['nim'], 0, 1);
        
        $pdf->Cell(45, 6, 'PRODI', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, $mahasiswa['prodi'], 0, 1);
        
        $pdf->Cell(45, 6, 'KELAS', 0, 0);
        $pdf->Cell(5, 6, ':', 0, 0);
        $pdf->Cell(0, 6, $mahasiswa['kelas'], 0, 1);
        
        $pdf->Ln(5);
        
        // Create equipment section
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'BEBAS PEMINJAMAN ALAT', 0, 1);
        
        $alat_table = '
        <table border="1" cellpadding="4" cellspacing="0">
            <tr>
                <th width="8%" align="center">NO.</th>
                <th width="37%" align="center">URAIAN</th>
                <th width="20%" align="center">TGL</th>
                <th width="17.5%" align="center">Tanda Tangan<br>Laboran</th>
                <th width="17.5%" align="center">Tanda Tangan<br>Ka./Penanggung Jawab</th>
            </tr>
            <tr>
                <td height="25" align="center">1</td>
                <td>LAB. JARINGAN DAN KOMPUTER</td>
                <td align="center">' . $currentDate . '</td>
                <td>' . $plpImageTag . '</td>
                <td>' . $kalabImageTag . '</td>
            </tr>
            <tr>
                <td height="25" align="center">2</td>
                <td>LAB. CYBER SECURITY</td>
                <td align="center">' . $currentDate . '</td>
                <td>' . $plpImageTag . '</td>
                <td>' . $kalabImageTag . '</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($alat_table, true, false, true, false, '');
        $pdf->Ln(5);
        
        // Create administration section
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'BEBAS ADMINISTRASI', 0, 1);
        
        $admin_table = '
        <table border="1" cellpadding="4" cellspacing="0">
            <tr>
                <th width="8%" align="center">No</th>
                <th width="37%" align="center">URAIAN</th>
                <th width="20%" align="center">TGL</th>
                <th width="17.5%" align="center">Tanda Tangan<br>Pustakawan</th>
                <th width="17.5%" align="center">Tanda Tangan<br>Ka./Penanggung Jawab</th>
            </tr>
            <tr>
                <td height="25" align="center">1</td>
                <td>PERPUSTAKAAN JURUSAN TIK</td>
                <td align="center">' . $currentDate . '</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td height="25" align="center">2</td>
                <td>PERPUSTAKAAN POLITEKNIK</td>
                <td align="center">' . $currentDate . '</td>
                <td></td>
                <td></td>
            </tr>
        </table>';
        
        $pdf->writeHTML($admin_table, true, false, true, false, '');
        $pdf->Ln(5);
        
        // Create compensation section
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'KOMPENSASI', 0, 1);
        
        $kompen_table = '
        <table border="1" cellpadding="4" cellspacing="0">
            <tr>
                <th width="45%" align="center">URAIAN</th>
                <th width="20%" align="center">JUMLAH</th>
                <th width="17.5%" align="center">Tanda tangan Pengawas<br>Kompen</th>
                <th width="17.5%" align="center">Tanda Tangan<br>Koord.Kompensasi</th>
            </tr>
            <tr>
                <td height="25">Jumlah Kompensasi</td>
                <td align="center">' . $mahasiswa['jumlah_terlambat'] . ' Menit</td>
                <td>' . $pengawasImageTag . '</td>
                <td>' . $pengawasImageTag . '</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($kompen_table, true, false, true, false, '');
        
        // Clean output buffer and generate PDF
        while (ob_get_level()) {
            ob_end_clean();
        }

        $cleanName = preg_replace('/[^A-Za-z0-9]/', ' ', $mahasiswa['nama']);
        $pdfFileName = 'SuratBebasKompen_' . $cleanName . '_' . $mahasiswa['nim'] . '.pdf';
        $pdf->Output($pdfFileName, 'D');
        exit();
        
    } catch (Exception $e) {
        error_log("Error generating Lembar Bebas Kompen: " . $e->getMessage());
        throw $e;
    }
}

function displayPengajuanForm($nim) {
    $sisaKompen = getSisaJamKompen($nim);
    
    // Handle AJAX request
    if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
        $currentPage = isset($_GET['form_page']) ? (int)$_GET['form_page'] : 1;
        $rowsPerPage = 5;
        $offset = ($currentPage - 1) * $rowsPerPage;
        $totalRows = getTotalPekerjaan();
        $totalPages = ceil($totalRows / $rowsPerPage);
        
        $output = '<div class="row g-4">';
        
        $availablePekerjaan = getAvailablePekerjaanPaginated($offset, $rowsPerPage);
        foreach ($availablePekerjaan as $pekerjaan) {
            $currentWorkers = $pekerjaan['CURRENT_WORKERS'];
            $maxWorkers = $pekerjaan['BATAS_PEKERJA'];
            $menitPekerjaan = $pekerjaan['JAM_PEKERJAAN'] * 60;
            
            $output .= '
            <div class="col-md-6">
                <div class="job-card" 
                    data-kode="' . $pekerjaan['KODE_PEKERJAAN'] . '"
                    data-jam="' . $pekerjaan['JAM_PEKERJAAN'] . '"
                    data-menit="' . $menitPekerjaan . '"
                    data-pj="' . htmlspecialchars($pekerjaan['PENANGGUNG_JAWAB']) . '"
                    ' . ($currentWorkers >= $maxWorkers ? 'data-disabled="true"' : '') . '>
                    <div class="job-card-header">
                        <span class="job-code">' . htmlspecialchars($pekerjaan['KODE_PEKERJAAN']) . '</span>
                        <span class="slot-badge ' . ($currentWorkers < $maxWorkers ? 'available' : 'full') . '">
                            <i class="fas fa-user-friends"></i> ' . $currentWorkers . '/' . $maxWorkers . ' slot
                        </span>
                    </div>
                    <div class="job-card-body">
                        <h5 class="job-title">' . htmlspecialchars($pekerjaan['NAMA_PEKERJAAN']) . '</h5>
                        <div class="job-detail">
                            <p class="detail-text">' . nl2br(htmlspecialchars($pekerjaan['DETAIL_PEKERJAAN'])) . '</p>
                        </div>
                        <div class="job-info">
                            <div class="info-item">
                                <i class="fas fa-user-tie"></i>
                                <span>' . htmlspecialchars($pekerjaan['PENANGGUNG_JAWAB']) . '</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span>' . formatTimeWithBoth($menitPekerjaan) . '</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }
        $output .= '</div>';
        
        // Add pagination
        $output .= '<div class="pagination-container mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">';
        
        // Previous button
        $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
        $output .= '<li class="page-item' . $prevDisabled . '">
                        <a class="page-link" data-page="' . ($currentPage - 1) . '" href="javascript:void(0)" ' . ($prevDisabled ? 'tabindex="-1" aria-disabled="true"' : '') . '>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>';
        
        // Page numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $currentPage == $i ? ' active' : '';
            $output .= '<li class="page-item' . $active . '">
                        <a class="page-link" data-page="' . $i . '" href="javascript:void(0)">' . $i . '</a>
                    </li>';
        }
        
        // Next button
        $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
        $output .= '<li class="page-item' . $nextDisabled . '">
                        <a class="page-link" data-page="' . ($currentPage + 1) . '" href="javascript:void(0)" ' . ($nextDisabled ? 'tabindex="-1" aria-disabled="true"' : '') . '>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>';
        
        $output .= '</ul></nav></div>';
        
        echo $output;
        exit;
    }
    
    // Regular page display
    $currentPage = isset($_GET['form_page']) ? (int)$_GET['form_page'] : 1;
    $rowsPerPage = 5;
    $offset = ($currentPage - 1) * $rowsPerPage;
    $totalRows = getTotalPekerjaan();
    $totalPages = ceil($totalRows / $rowsPerPage);
    
    $output = '
    <link rel="stylesheet" href="css/pengajuan_form.css">
    <div class="submission-wrapper">
        <form id="formPengajuan" method="POST" action="process_pengajuan.php">
            <input type="hidden" name="nim" value="' . $nim . '">
            <input type="hidden" id="sisaKompen" value="' . $sisaKompen . '">
            <input type="hidden" id="selectedPJ" name="selected_pj" value="">
            
            <div class="submission-summary mb-4">
                <div class="summary-card">
                    <div class="summary-header">
                        <h5><i class="fas fa-clipboard-check me-2"></i>Ringkasan Pengajuan</h5>
                    </div>
                    <div class="summary-content">
                        <div class="summary-item">
                            <i class="fas fa-clock"></i>
                            <div class="item-content">
                                <span class="item-label">Total waktu terpilih</span>
                                <span class="item-value" id="totalWaktu">0 menit</span>
                            </div>
                        </div>
                        <div class="summary-item">
                            <i class="fas fa-clock"></i>
                            <div class="item-content">
                                <span class="item-label">Sisa waktu</span>
                                <span class="item-value" id="sisaMenit"></span>
                            </div>
                        </div>
                        <div class="summary-item">
                            <i class="fas fa-user-tie"></i>
                            <div class="item-content">
                                <span class="item-label">Pengawas terpilih</span>
                                <span class="item-value" id="selectedPJName">-</span>
                            </div>
                        </div>
                        <div class="progress mt-3">
                            <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="action-buttons mt-4">
                        <a href="?page=pengajuan" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitPengajuan" disabled>
                            <i class="fas fa-save me-2"></i>Submit Pengajuan
                        </button>
                    </div>
                </div>
            </div>

            <div class="jobs-section">
                <div class="filter-section mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-user-tie me-2"></i>Filter Pengawas
                                        </label>
                                        <select class="form-select" id="filterPengawasNew">
                                            <option value="">Semua Pengawas</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-clock me-2"></i>Filter Menit Kompen
                                        </label>
                                        <select class="form-select" id="filterJamNew">
                                            <option value="">Semua Menit</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-light w-100" id="resetFiltersNew">
                                        <i class="fas fa-sync-alt me-2"></i>Reset Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="job-cards-container">
                    <div class="row g-4">';

    // Load initial job cards
    $availablePekerjaan = getAvailablePekerjaanPaginated($offset, $rowsPerPage);
    foreach ($availablePekerjaan as $pekerjaan) {
        $currentWorkers = $pekerjaan['CURRENT_WORKERS'];
        $maxWorkers = $pekerjaan['BATAS_PEKERJA'];
        $menitPekerjaan = $pekerjaan['JAM_PEKERJAAN'] * 60;
        
        $output .= '
        <div class="col-md-6">
            <div class="job-card">
                <div class="job-card-header">
                    <div class="form-check">
                        <input type="checkbox" 
                            class="form-check-input pekerjaan-checkbox" 
                            name="pekerjaan[]" 
                            value="' . $pekerjaan['KODE_PEKERJAAN'] . '"
                            data-jam="' . $pekerjaan['JAM_PEKERJAAN'] . '"
                            data-menit="' . $menitPekerjaan . '"
                            data-pj="' . htmlspecialchars($pekerjaan['PENANGGUNG_JAWAB']) . '"
                            ' . ($currentWorkers >= $maxWorkers ? 'disabled' : '') . '>
                        <label class="form-check-label">
                            <span class="job-code">' . htmlspecialchars($pekerjaan['KODE_PEKERJAAN']) . '</span>
                        </label>
                    </div>
                    <span class="slot-badge ' . ($currentWorkers < $maxWorkers ? 'available' : 'full') . '">
                        <i class="fas fa-user-friends"></i> ' . $currentWorkers . '/' . $maxWorkers . ' slot
                    </span>
                </div>
                <div class="job-card-body">
                    <h5 class="job-title">' . htmlspecialchars($pekerjaan['NAMA_PEKERJAAN']) . '</h5>
                    <div class="job-detail">
                        <p class="detail-text">' . nl2br(htmlspecialchars($pekerjaan['DETAIL_PEKERJAAN'])) . '</p>
                    </div>
                    <div class="job-info">
                        <div class="info-item">
                            <i class="fas fa-user-tie"></i>
                            <span>' . htmlspecialchars($pekerjaan['PENANGGUNG_JAWAB']) . '</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span>' . formatTimeWithBoth($menitPekerjaan) . '</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    $output .= '</div>';

    // Add pagination
    $output .= '<div class="pagination-container mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">';
    
    // Previous button
    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    $output .= '<li class="page-item' . $prevDisabled . '">
                    <a class="page-link" data-page="' . ($currentPage - 1) . '" href="javascript:void(0)" ' . ($prevDisabled ? 'tabindex="-1" aria-disabled="true"' : '') . '>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>';
    
    // Page numbers 
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $currentPage == $i ? ' active' : '';
        $output .= '<li class="page-item' . $active . '">
                        <a class="page-link" data-page="' . $i . '" href="javascript:void(0)">' . $i . '</a>
                    </li>';
    }
    
    // Next button
    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    $output .= '<li class="page-item' . $nextDisabled . '">
                    <a class="page-link" data-page="' . ($currentPage + 1) . '" href="javascript:void(0)" ' . ($nextDisabled ? 'tabindex="-1" aria-disabled="true"' : '') . '>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>';
    
    $output .= '</ul></nav></div>
                </div>
            </div>
        </form>
    </div>
    <script src="js/pengajuan_pekerjaan.js">
    </script>';

    return $output;
}

function getTotalPekerjaan() {
    $sql = "SELECT COUNT(*) as total FROM tbl_pekerjaan";
    $stmt = executeQuery($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['TOTAL'];
}

function getAvailablePekerjaanPaginated($offset, $rowsPerPage) {
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM tbl_pengajuan_detail pd 
             WHERE pd.kode_pekerjaan = p.kode_pekerjaan) as current_workers
            FROM tbl_pekerjaan p
            ORDER BY p.kode_pekerjaan
            OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
            
    $stmt = executeQuery($sql, [
        'offset' => $offset,
        'limit' => $rowsPerPage
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatusBadge($status, $pengajuan) {
    $actualStatus = determineStatus($status, $pengajuan);
    
    $badges = [
        'Belum Melakukan Pekerjaan' => '<span class="badge" style="background-color: #FFA500;">Belum Melakukan Pekerjaan</span>',
        'Menunggu Approval' => '<span class="badge" style="background-color: #3498db;">Menunggu Approval</span>',
        'Approved by Pengawas' => '<span class="badge" style="background-color: #2ecc71;">Approved by Pengawas</span>',
        'Approved by PLP' => '<span class="badge" style="background-color: #27ae60;">Approved by PLP</span>',
        'Approved by KaLab' => '<span class="badge" style="background-color: #219653;">Approved by KaLab</span>',
        'Pekerjaan Ditolak' => '<span class="badge" style="background-color: #e74c3c;">Pekerjaan Ditolak</span>'
    ];
    
    return $badges[$actualStatus] ?? '<span class="badge bg-secondary">' . htmlspecialchars($actualStatus) . '</span>';
}

function updatePengajuanStatus($kodeKegiatan, $status) {
    try {
        $sql = "UPDATE tbl_pengajuan SET status = :status WHERE kode_kegiatan = :kode_kegiatan";
        $params = [
            'status' => $status,
            'kode_kegiatan' => $kodeKegiatan
        ];
        executeQuery($sql, $params);
    } catch (Exception $e) {
        error_log("Error updating pengajuan status: " . $e->getMessage());
    }
}

function determineStatus($status, $pengajuan) {
    $hasBeforeAfterImages = checkBeforeAfterImages($pengajuan['KODE_KEGIATAN']);
    
    if ($pengajuan['STATUS_APPROVAL1'] === 'Rejected') {
        updatePengajuanStatus($pengajuan['KODE_KEGIATAN'], 'Pekerjaan Ditolak');
        return 'Pekerjaan Ditolak';
    }
    
    if ($pengajuan['STATUS_APPROVAL3'] === 'Approved') {
        updatePengajuanStatus($pengajuan['KODE_KEGIATAN'], 'Approved by KaLab');
        return 'Approved by KaLab';
    }
    
    if ($pengajuan['STATUS_APPROVAL2'] === 'Approved') {
        updatePengajuanStatus($pengajuan['KODE_KEGIATAN'], 'Approved by PLP');
        return 'Approved by PLP';
    }
    
    if ($pengajuan['STATUS_APPROVAL1'] === 'Approved') {
        updatePengajuanStatus($pengajuan['KODE_KEGIATAN'], 'Approved by Pengawas');
        return 'Approved by Pengawas';
    }
    
    if ($hasBeforeAfterImages) {
        updatePengajuanStatus($pengajuan['KODE_KEGIATAN'], 'Menunggu Approval');
        return 'Menunggu Approval';
    }
    
    updatePengajuanStatus($pengajuan['KODE_KEGIATAN'], 'Belum Melakukan Pekerjaan');
    return 'Belum Melakukan Pekerjaan';
}

function checkBeforeAfterImages($kodeKegiatan) {
    try {
        $sql = "SELECT COUNT(*) 
                FROM tbl_pengajuan_detail 
                WHERE kode_kegiatan = :kode 
                AND before_pekerjaan IS NOT NULL 
                AND after_pekerjaan IS NOT NULL";
        
        $stmt = executeQuery($sql, ['kode' => $kodeKegiatan]);
        $result = $stmt->fetch(PDO::FETCH_NUM);  
        
        return ($result && $result[0] > 0); 

    } catch (Exception $e) {
        error_log("Error in checkBeforeAfterImages: " . $e->getMessage());
        return false;
    }
}

function getApprovalProgress($pengajuan) {
    $approvals = [
        1 => [
            'status' => $pengajuan['STATUS_APPROVAL1'] ?? 'Pending',
            'label' => 'Pengawas',
            'icon' => 'user-check'
        ],
        2 => [
            'status' => $pengajuan['STATUS_APPROVAL2'] ?? 'Pending',
            'label' => 'PLP',
            'icon' => 'shield-check'
        ],
        3 => [
            'status' => $pengajuan['STATUS_APPROVAL3'] ?? 'Pending',
            'label' => 'KaLab',
            'icon' => 'award'
        ]
    ];
    $output = '<link rel="stylesheet" href="css/approvalbadge.css">';
    $output .= '<div class="approval-steps">';

    foreach ($approvals as $level => $data) {
        list($bgColor, $textColor, $dotColor) = match($data['status']) {
            'Approved' => ['#dcfce7', '#166534', '#22c55e'], // Light green bg, dark green text, green dot
            'Rejected' => ['#fee2e2', '#991b1b', '#ef4444'], // Light red bg, dark red text, red dot
            'Pending' => ['#fef3c7', '#92400e', '#f59e0b'],  // Light yellow bg, dark yellow text, yellow dot
            default => ['#f3f4f6', '#4b5563', '#9ca3af']     // Light gray bg, dark gray text, gray dot
        };
        $output .= sprintf(
            '<div class="approval-step" style="background-color: %s; color: %s;"
                  data-bs-toggle="tooltip" data-bs-placement="top" 
                  title="%s: %s">
                %d
                <div class="status-dot" style="background-color: %s;"></div>
                <div class="approval-tooltip">%s: %s</div>
            </div>',
            $bgColor,
            $textColor,
            $data['label'],
            $data['status'],
            $level,
            $dotColor,
            $data['label'],
            $data['status']
        );
    }
    $output .= '</div>';
    return $output;
}

function displayBebasKompenForm($mahasiswa) {
    $output = '
    <link rel="stylesheet" href="css/bebas_kompen.css">
    <div class="container-fluid">
        <div class="row justify-content-center">
                    <div class="card-body p-4">
                        <!-- Header with Summary -->
                        <div class="submission-header mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="submission-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-1">Form Bebas Kompen</h4>
                                    <p class="text-muted mb-0">
                                        ' . htmlspecialchars($mahasiswa['NAMA']) . ' (' . htmlspecialchars($mahasiswa['NIM']) . ')
                                    </p>
                                </div>
                            </div>
                            
                            <div class="kompen-summary">
                                <div class="row g-3">
                                    <div class="col-sm-4">
                                        <div class="summary-item">
                                            <span class="summary-label">Total Terlambat</span>
                                            <span class="summary-value">' . htmlspecialchars($mahasiswa['JUMLAH_TERLAMBAT']) . ' menit</span>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="summary-item">
                                            <span class="summary-label">Total Alfa</span>
                                            <span class="summary-value">' . htmlspecialchars($mahasiswa['JUMLAH_ALFA']) . ' jam</span>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="summary-item highlight">
                                            <span class="summary-label">Total Kompen</span>
                                            <span class="summary-value">' . htmlspecialchars($mahasiswa['TOTAL']) . ' menit</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Section -->
                        <div class="upload-section bg-white rounded-3">
                            <h5 class="section-subtitle mb-4">
                                <i class="fas fa-file-upload me-2"></i>Upload Dokumen Pengajuan
                            </h5>
                            
                            <form action="process_bebas_kompen.php" method="POST" enctype="multipart/form-data" id="bebasKompenForm">
                                <div class="mb-4">
                                    <label for="surat" class="form-label">
                                        Surat Pengajuan Bebas Kompen
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="upload-area">
                                        <div class="input-group">
                                            <input type="file" class="form-control" id="surat" name="surat" 
                                                required accept=".pdf,.doc,.docx"
                                                onchange="validateFile(this)">
                                            <label class="input-group-text" for="surat">
                                                <i class="fas fa-folder-open me-2"></i>Browse
                                            </label>
                                        </div>
                                        <div class="form-text mt-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Format yang diterima: PDF, DOC, DOCX (Maksimal 5MB)
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-3">
                                    <a href="?page=bebas-kompen" class="btn btn-light">
                                        <i class="fas fa-times me-2"></i>Batal
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Pengajuan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/bk.js"></script>';

    return $output;
}

function calculatePaymentAmount($sisaKompen) {
    $RATE_PER_HOUR = 10000;
    $MAX_FREE_MINUTES = 1500; 
    $payableMinutes = max(0, $sisaKompen - $MAX_FREE_MINUTES);
    $payableHours = floor($payableMinutes / 60);
    return $payableHours * $RATE_PER_HOUR;
}