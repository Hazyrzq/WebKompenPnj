<?php
session_start();

require_once "../config/koneksi.php";
require_once "functions.php";
require_once "..\payment\payment_ui_components.php";

if (!isset($_SESSION['nim'])) {
    header("Location: ../loginmhs/login.php");
    exit();
}

$nim = $_SESSION['nim'];
$page = isset($_GET['page']) ? $_GET['page'] : 'beranda';

$sql = "SELECT * FROM tbl_mahasiswa WHERE nim = :nim";
$stmt = executeQuery($sql, ['nim' => $nim]);
$mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($_GET['page']) && $_GET['page'] === 'edit-profile') {
    include 'edit-profile.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav id="sidebar">
        <div class="logo-container d-flex align-items-center" id="toggleNav">
            <img src="../images/logo_pnj.png" alt="Logo" class="me-3">
            <span class="logo-text nav-text">Hi, <?= htmlspecialchars($mahasiswa['NAMA']) ?></span>
        </div>
        <div class="mt-4">
            <a href="?page=beranda" class="nav-link <?= $page === 'beranda' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span class="nav-text">Beranda</span>
            </a>
            <a href="?page=list-pekerjaan" class="nav-link <?= $page === 'list-pekerjaan' ? 'active' : '' ?>">
                <i class="fas fa-briefcase"></i>
                <span class="nav-text">List Pekerjaan</span>
            </a>
            <a href="?page=pengajuan" class="nav-link <?= $page === 'pengajuan' ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i>
                <span class="nav-text">Pengajuan Pekerjaan</span>
            </a>
            <a href="?page=bebas-kompen" class="nav-link <?= $page === 'bebas-kompen' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice"></i>
                <span class="nav-text">Form Bebas Kompen</span>
            </a>
            <a href="?page=payment" class="nav-link <?= $page === 'payment' ? 'active' : '' ?>">
                <i class="fas fa-money-bill"></i>
                <span class="nav-text">Pembayaran</span>
            </a>
        </div>
        <div class="profile-container">
    <div class="profile-menu" id="profileButton">
        <i class="fas fa-user-circle"></i>
        <span class="nav-text">Profile</span>
        <div class="dropdown-menu" id="profileDropdown">
        <a class="nav-link" href="?page=edit-profile">
            <i class="fas fa-user-edit"></i>
            Edit Profile
        </a>
            <a href="../loginmhs/login.php" class="dropdown-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
    </nav>

    <div id="main-content">
        <div class="container-fluid">
            <?php
            // Content berdasarkan halaman yang dipilih
            switch($page) {
                case 'beranda':
                    // Tampilkan content beranda (yang sudah ada)
                    ?>
            <h1 class="section-title">Data Mahasiswa</h1>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="data-card">
                        <div class="data-row">
                            <div class="row align-items-center">
                                <div class="col-4 col-md-3">
                                    <span class="data-label">NIM</span>
                                </div>
                                <div class="col-8 col-md-9">
                                    <span class="data-value"><?= htmlspecialchars($mahasiswa['NIM']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="row align-items-center">
                                <div class="col-4 col-md-3">
                                    <span class="data-label">Nama</span>
                                </div>
                                <div class="col-8 col-md-9">
                                    <span class="data-value"><?= htmlspecialchars($mahasiswa['NAMA']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="row align-items-center">
                                <div class="col-4 col-md-3">
                                    <span class="data-label">Email</span>
                                </div>
                                <div class="col-8 col-md-9">
                                    <span class="data-value"><?= htmlspecialchars($mahasiswa['EMAIL']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="row align-items-center">
                                <div class="col-4 col-md-3">
                                    <span class="data-label">No.HP</span>
                                </div>
                                <div class="col-8 col-md-9">
                                    <span class="data-value"><?= htmlspecialchars($mahasiswa['NOTELP']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="row align-items-center">
                                <div class="col-4 col-md-3">
                                    <span class="data-label">Kelas</span>
                                </div>
                                <div class="col-8 col-md-9">
                                    <span class="data-value"><?= htmlspecialchars($mahasiswa['KELAS']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="row align-items-center">
                                <div class="col-4 col-md-3">
                                    <span class="data-label">Prodi</span>
                                </div>
                                <div class="col-8 col-md-9">
                                    <span class="data-value"><?= htmlspecialchars($mahasiswa['PRODI']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="row mb-4">
                            <div class="col-8">
                                <span class="stats-label">Jumlah Terlambat (menit)</span>
                            </div>
                            <div class="col-4 text-end">
                                <span class="stats-value"><?= htmlspecialchars($mahasiswa['JUMLAH_TERLAMBAT']) ?></span>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-8">
                                <span class="stats-label">Jumlah Alfa</span>
                            </div>
                            <div class="col-8">
                                <span class="stats-label">(jam)</span>
                            </div>
                            <div class="col-4 text-end">
                                <span class="stats-value"><?= htmlspecialchars($mahasiswa['JUMLAH_ALFA']) ?></span>
                            </div>
                        </div>
                        <hr class="opacity-25">
                        <div class="row mt-3">
                            <div class="col-8">
                                <span class="stats-label">Total</span>
                            </div>
                            <div class="col-4 text-end">
                                <span class="stats-value"><?= htmlspecialchars($mahasiswa['TOTAL']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="section-title mt-4">Video Tutorial</h2>
            <div class="video-container">
                <div class="play-button">
                    <i class="fas fa-play fa-2x" style="color: var(--primary-color)"></i>
                </div>
            </div>
        </div>
    </div>
                    <?php
                    break;

                case 'list-pekerjaan':
                    ?>
                    <h1 class="section-title"></h1>
                    <div class="mt-4">
                        <?= displayListPekerjaanWithSearch() ?>
                    </div>
                    <?php
                    break;

                case 'pengajuan':
                    ?>
                    <h1 class="section-title">Status Pengajuan Pekerjaan</h1>
                    <div class="mt-4">
                        <?= displayPengajuanStatus($nim) ?>
                    </div>
                    <?php
                    break;

                case 'new-pengajuan':
                        ?>
                        <div class="mt-4">
                            <?= displayPengajuanForm($nim) ?>
                        </div>
                        <?php
                        break;

                case 'pengajuan-detail':
                    include 'pengajuan-detail.php';
                        break;

                case 'bebas-kompen':
                    ?>
                    <h1 class="section-title"></h1>
                    <div class="mt-4">
                        <?= displayBebasKompenForm($mahasiswa) ?>
                    </div>
                    <?php
                    break;

                case 'payment':
                    ?>
                    <h1 class="section-title">Pembayaran Kompen</h1>
                    <div class="mt-4">
                        <?= displayPaymentPage($nim) ?>
                    </div>
                    <?php
                    break;
            }
            ?>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initial setup for global functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
    initializeProfileDropdown();
    initializeJobDetails();
    initializeFormPengajuan();
});

// =============== Sidebar Functionality ===============
function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleNav = document.getElementById('toggleNav');

    const savedState = localStorage.getItem('sidebarState');
    if (savedState === 'collapsed') {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }

    toggleNav.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        localStorage.setItem('sidebarState', 
            sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
        );
        updateProfileDropdownPosition();
    });
}

// =============== Profile Dropdown Functionality ===============
function initializeProfileDropdown() {
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');
    const sidebar = document.getElementById('sidebar');

    function updateDropdownPosition() {
        const sidebarRect = sidebar.getBoundingClientRect();
        const buttonRect = profileButton.getBoundingClientRect();
        
        if (sidebar.classList.contains('collapsed')) {
            // When sidebar is collapsed, position dropdown next to the sidebar
            profileDropdown.style.position = 'fixed';
            profileDropdown.style.left = `${sidebarRect.right + 5}px`; // 5px gap
            profileDropdown.style.bottom = `${window.innerHeight - buttonRect.bottom}px`;
            profileDropdown.style.right = 'auto';
            profileDropdown.style.top = 'auto';
        } else {
            // When sidebar is expanded, position dropdown above the button
            profileDropdown.style.position = 'absolute';
            profileDropdown.style.left = 'auto';
            profileDropdown.style.bottom = '100%';
            profileDropdown.style.right = '0';
            profileDropdown.style.top = 'auto';
        }
    }

    profileButton.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
        
        if (profileDropdown.classList.contains('show')) {
            updateDropdownPosition();
        }
    });

    // Update position on window resize
    window.addEventListener('resize', function() {
        if (profileDropdown.classList.contains('show')) {
            updateDropdownPosition();
        }
    });

    // Update position when sidebar toggles
    function updateProfileDropdownPosition() {
        if (profileDropdown.classList.contains('show')) {
            updateDropdownPosition();
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });

    // Make updateProfileDropdownPosition available globally
    window.updateProfileDropdownPosition = updateProfileDropdownPosition;
}

</script>
</body>
</html>