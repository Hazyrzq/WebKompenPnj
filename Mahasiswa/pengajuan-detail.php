<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkUserAuthentication() {
    if (!isset($_SESSION['nim'])) {
        header("Location: ../login.php");
        exit();
    }
    return $_SESSION['nim'];
}
$userNim = checkUserAuthentication();

if (!isset($_GET['kode_kegiatan'])) {
    header("Location: ?page=pengajuan");
    exit();
}

$kodeKegiatan = $_GET['kode_kegiatan'];
$detailsQuery = "SELECT 
    p.kode_kegiatan,
    TO_CHAR(p.tanggal_pengajuan, 'DD-MM-YYYY') as tanggal_pengajuan,
    p.penanggung_jawab,
    p.status,
    p.status_approval1,
    p.status_approval2,
    p.status_approval3
FROM tbl_pengajuan p 
WHERE p.kode_kegiatan = :kode";

$stmt = executeQuery($detailsQuery, ['kode' => $kodeKegiatan]);
$pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

$workQuery = "SELECT 
    pd.kode_pekerjaan,
    pd.nama_pekerjaan,
    pd.jam_pekerjaan,
    CASE 
        WHEN pd.before_pekerjaan IS NOT NULL 
        AND pd.user_create = :user_nim THEN 'true'
        ELSE 'false' 
    END as HAS_FOTO_BEFORE,
    CASE 
        WHEN pd.after_pekerjaan IS NOT NULL 
        AND pd.user_create = :user_nim THEN 'true'
        ELSE 'false' 
    END as HAS_FOTO_AFTER,
    pd.bukti_tambahan,
    pd.user_create,
    pd.user_update
FROM tbl_pengajuan_detail pd
WHERE pd.kode_kegiatan = :kode
ORDER BY pd.kode_pekerjaan ASC";

$stmt = executeQuery($workQuery, [
    'kode' => $kodeKegiatan,
    'user_nim' => $userNim 
]);
$pekerjaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<html lang="en">
<head>
<meta charset="UTF-8">
<link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="css/pengajuan_detail.css">
<div class="container-fluid p-4">
    <!-- Header Section -->
    <div class="header-section mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1 fw-semibold">Detail Pengajuan</h4>
                <p class="text-muted mb-0">Kode: <?= htmlspecialchars($pengajuan['KODE_KEGIATAN']) ?></p>
            </div>
            <a href="?page=pengajuan" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>
    
    <!-- Info Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="fas fa-calendar text-primary"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Tanggal Pengajuan</div>
                            <div class="fw-medium"><?= htmlspecialchars($pengajuan['TANGGAL_PENGAJUAN']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="fas fa-user-tie text-info"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Penanggung Jawab</div>
                            <div class="fw-medium"><?= htmlspecialchars($pengajuan['PENANGGUNG_JAWAB']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Status</div>
                            <div><?= getStatusBadge($pengajuan['STATUS'], $pengajuan) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="fas fa-list-check text-warning"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Progress Approval</div>
                            <?= getApprovalProgress([
                                'STATUS_APPROVAL1' => $pengajuan['STATUS_APPROVAL1'],
                                'STATUS_APPROVAL2' => $pengajuan['STATUS_APPROVAL2'],
                                'STATUS_APPROVAL3' => $pengajuan['STATUS_APPROVAL3']
                            ]) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Work Details -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0">
                <i class="fas fa-briefcase me-2"></i>Daftar Pekerjaan
            </h5>
        </div>
        <div class="card-body">
            <div class="accordion" id="workAccordion">
                <?php foreach ($pekerjaan as $index => $work): ?>
                <div class="accordion-item border mb-3 rounded-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#work-<?= $work['KODE_PEKERJAAN'] ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary bg-opacity-10 p-2 rounded-2">
                                    <i class="fas fa-tools text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-medium"><?= htmlspecialchars($work['NAMA_PEKERJAAN']) ?></div>
                                    <small class="text-muted"><?= $work['JAM_PEKERJAAN'] ?> jam</small>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="work-<?= $work['KODE_PEKERJAAN'] ?>" 
                         class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
                         data-bs-parent="#workAccordion">
                        <div class="accordion-body">
                            <form class="evidence-form" data-id="<?= $work['KODE_PEKERJAAN'] ?>">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="evidence-upload before">
                                            <label class="form-label fw-medium mb-2">Foto Sebelum Pengerjaan</label>
                                            <div class="upload-area rounded-3" id="beforeArea-<?= $work['KODE_PEKERJAAN'] ?>">
                                                <?php if ($work['HAS_FOTO_BEFORE'] === 'true'): ?>
                                                    <div class="upload-placeholder" style="display: none;">
                                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                                        <p class="mb-0">Klik atau drop foto sebelum pengerjaan</p>
                                                    </div>
                                                    <img src="get_image.php?work_id=<?= $work['KODE_PEKERJAAN'] ?>&type=before&t=<?= time() ?>" 
                                                         class="preview-image img-fluid" 
                                                         alt="Before"
                                                         onerror="handleImageError(this)"
                                                         style="max-height: 200px; width: auto; object-fit: contain;">
                                                <?php else: ?>
                                                    <div class="upload-placeholder">
                                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                                        <p class="mb-0">Klik atau drop foto sebelum pengerjaan</p>
                                                    </div>
                                                    <img src="" class="preview-image img-fluid" alt="Before" style="display: none;">
                                                <?php endif; ?>
                                                <input type="file" class="file-input" name="before" accept="image/*" data-type="before">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="evidence-upload after">
                                            <label class="form-label fw-medium mb-2">Foto Setelah Pengerjaan</label>
                                            <div class="upload-area rounded-3" id="afterArea-<?= $work['KODE_PEKERJAAN'] ?>">
                                                <?php if ($work['HAS_FOTO_AFTER'] === 'true'): ?>
                                                    <div class="upload-placeholder" style="display: none;">
                                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                                        <p class="mb-0">Klik atau drop foto setelah pengerjaan</p>
                                                    </div>
                                                    <img src="get_image.php?work_id=<?= $work['KODE_PEKERJAAN'] ?>&type=after&t=<?= time() ?>" 
                                                         class="preview-image img-fluid" 
                                                         alt="After"
                                                         onerror="handleImageError(this)"
                                                         style="max-height: 200px; width: auto; object-fit: contain;">
                                                <?php else: ?>
                                                    <div class="upload-placeholder">
                                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                                        <p class="mb-0">Klik atau drop foto setelah pengerjaan</p>
                                                    </div>
                                                    <img src="" class="preview-image img-fluid" alt="After" style="display: none;">
                                                <?php endif; ?>
                                                <input type="file" class="file-input" name="after" accept="image/*" data-type="after">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-medium">Bukti Tambahan</label>
                                        <textarea class="form-control" name="bukti_tambahan" rows="3" 
                                                  placeholder="Tambahkan bukti tambahan jika diperlukan"
                                        ><?= htmlspecialchars($work['BUKTI_TAMBAHAN'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <span class="normal-text">
                                                <i class="fas fa-save me-2"></i>Simpan
                                            </span>
                                            <span class="loading-text d-none">
                                                <span class="spinner-border spinner-border-sm me-2"></span>
                                                Menyimpan...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script src="js/pengajuan_detail.js">
</script>
</html>