<?php
// File: /kalab/DaftarPengajuan/DaftarPengajuanKalab.php

require_once(__DIR__ . '/../../Config.php');

// Check session and role
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['role']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Get user data from session
$userNama = $_SESSION['nama_user'];
$userRole = $_SESSION['role'];

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: ../../login.php');
    exit();
}

// Process submission if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
    try {
        $pdo = getDB();
        $selected_ids = $_POST['selected_ids'];

        // Begin transaction
        $pdo->beginTransaction();

        try {
            $success_count = 0;

            // Prepare statement untuk approval 3 (Kalab)
            $stmt = $pdo->prepare("UPDATE tbl_pengajuan 
                      SET status_approval3 = 'Approved',
                         keterangan_approval3 = 'Disetujui oleh Kalab',
                         approval3_by = :user_nama,
                         updated_at = CURRENT_TIMESTAMP 
                     WHERE id_pengajuan = :id 
                     AND status_approval1 = 'Approved'
                     AND status_approval2 = 'Approved'
                     AND (status_approval3 IS NULL OR status_approval3 = 'Pending')");

            // Process each ID
            foreach ($selected_ids as $id) {
                $stmt->bindValue(':id', $id);
                $stmt->bindValue(':user_nama', $userNama);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $success_count++;
                }
            }

            // Commit transaction
            $pdo->commit();

            if ($success_count > 0) {
                $_SESSION['success_message'] = "Berhasil menyetujui $success_count pengajuan tahap 3.";
            } else {
                $_SESSION['error_message'] = "Tidak ada pengajuan yang dapat disetujui untuk tahap 3.";
            }

        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error_message'] = "Terjadi kesalahan saat memproses data: " . $e->getMessage();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
    }

    // Redirect to refresh the page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Initialize variables for pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Initialize search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $pdo = getDB();

    // Base query - adjusted for Oracle syntax
    $base_query = "FROM tbl_pengajuan p 
           JOIN tbl_mahasiswa m ON p.kode_user = m.nim 
           WHERE p.status_approval1 = 'Approved' 
           AND p.status_approval2 = 'Approved'
           AND (p.status_approval3 IS NULL OR p.status_approval3 = 'Pending')";

    // Where clause for search
    $where = "";
    $params = [];

    if (!empty($search)) {
        $where = " WHERE UPPER(m.nama) LIKE UPPER(:search) 
                   OR UPPER(p.kode_kegiatan) LIKE UPPER(:search) 
                   OR UPPER(p.status_approval1) LIKE UPPER(:search) 
                   OR UPPER(p.status_approval2) LIKE UPPER(:search) 
                   OR UPPER(p.status_approval3) LIKE UPPER(:search)";
        $params[':search'] = "%$search%";
    }

    // Count total records - adjusted for Oracle
    $count_query = "SELECT COUNT(*) as total " . $base_query . $where;
    $stmt = executeQuery($count_query, $params);
    $total_rows = $stmt->fetch(PDO::FETCH_ASSOC)['TOTAL'];
    $total_pages = ceil($total_rows / $per_page);

    // Main query for data - adjusted for Oracle pagination
    $query = "SELECT * FROM (
                SELECT a.*, ROWNUM rnum FROM (
                    SELECT p.id_pengajuan,
                           p.kode_kegiatan,
                           m.nama as nama_mahasiswa,
                           p.total as total_menit,
                           p.sisa as sisa_menit,
                           p.status_approval1,
                           p.status_approval2,
                           p.status_approval3
                    " . $base_query . $where . "
                     ORDER BY p.created_at ASC
                ) a WHERE ROWNUM <= :end_row
            ) WHERE rnum > :start_row";

    $params[':start_row'] = $offset;
    $params[':end_row'] = $offset + $per_page;

    $stmt = executeQuery($query, $params);
    $pengajuan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in daftarpengajuan.php: " . $e->getMessage());
    $error_message = "Terjadi kesalahan dalam mengambil data";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pengajuan Kalab</title>
    <link rel="icon" type="image/x-icon" href="images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <!-- Left side with logo and title -->
                <div class="flex items-center space-x-4">
                    <img src="images/LogoPNJ.png" alt="Logo" class="h-12 w-12">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Daftar Pengajuan Kalab</h1>
                        <p class="text-sm text-gray-600">Manage the Details of Your Menu,
                            <?php echo htmlspecialchars($userNama); ?>
                        </p>
                    </div>
                </div>

                <!-- Right side with profile -->
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="relative inline-block text-left">
                            <div>
                                <button type="button" onclick="toggleDropdown()"
                                    class="flex items-center focus:outline-none">
                                    <img src="images/profile.png" alt="Profile"
                                        class="h-10 w-10 rounded-full border-2 border-emerald-500 hover:border-emerald-600 transition-colors duration-200">
                                    <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($userNama); ?></span>
                                </button>
                            </div>

                            <!-- Dropdown menu -->
                            <div id="dropdownMenu"
                                class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                                <div class="py-1">
                                    <div class="px-4 py-2 text-sm text-gray-600 border-b">
                                        <div><?php echo htmlspecialchars($userRole); ?></div>
                                    </div>
                                    <hr class="my-1">
                                    <a href="../UpdateProfileKalab.php?ref=<?php echo urlencode('/Sikompen/kalab/daftarpengajuan/daftarpengajuankalab.php'); ?>"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-600">
                                        <i class="fas fa-user-edit mr-2"></i>
                                        Update Profile
                                    </a>
                                    <a href="?action=logout"
                                        class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                        onclick="return confirm('Are you sure you want to logout?')">
                                        <i class="fas fa-sign-out-alt mr-2"></i>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Menu Tabs -->
    <div class="max-w-7xl mx-auto px-4 mt-6">
        <div class="border-b border-gray-200 bg-white rounded-t-lg">
            <nav class="flex">
                <a href="/sikompen/kalab/DaftarPengajuan/DaftarPengajuanKalab.php"
                    class="border-b-2 border-emerald-500 text-emerald-600 px-6 py-3 text-sm font-medium">
                    Pengajuan
                </a>
                <a href=/sikompen/kalab/Daftardisetujui/DaftardisetujuiKalab.php
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Daftar Disetujui
                </a>
                <a href="/sikompen/kalab/pekerjaan/pekerjaan.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Pekerjaan
                </a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Search and Actions -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex-1 max-w-lg">
                <form method="GET" class="flex space-x-3">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by nama, status approval..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-500">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                        Search
                    </button>
                </form>
            </div>
            <div class="flex space-x-3">
                <button type="submit" form="submission-form"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Submit Semua Pengajuan
                </button>

            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php else: ?>
            <!-- Table -->
            <form id="submission-form" method="POST" action="process_submission.php">
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <!-- Table Header -->
                        <thead class="bg-gray-50">
                            <tr>
                                <!-- Tambahkan kolom checkbox baru -->
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" onclick="toggleAllCheckboxes(this)"
                                        class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID Pengajuan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kode Kegiatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nama Mahasiswa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total(Menit)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sisa(Menit)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status Approval 1</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status Approval 2</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status Approval 3</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <!-- Table Body -->
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($pengajuan_list)): ?>
                                <tr>
                                    <td colspan="11" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Tidak ada pengajuan yang dapat disetujui
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pengajuan_list as $index => $pengajuan): ?>
                                    <tr>
                                        <!-- Kolom Checkbox -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($pengajuan['STATUS_APPROVAL3'] != 'Approved'): ?>
                                                <input type="checkbox" name="selected_ids[]"
                                                    value="<?php echo $pengajuan['ID_PENGAJUAN']; ?>"
                                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            <?php endif; ?>
                                        </td>

                                        <!-- Kolom No -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-500">
                                                <?php echo $offset + $index + 1; ?>
                                            </span>
                                        </td>

                                        <!-- Data Columns -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['ID_PENGAJUAN']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['KODE_KEGIATAN']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['NAMA_MAHASISWA']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['TOTAL_MENIT']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['SISA_MENIT']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['STATUS_APPROVAL1']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['STATUS_APPROVAL2']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($pengajuan['STATUS_APPROVAL3']); ?>
                                        </td>

                                        <!-- Action Column -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <?php if ($pengajuan['STATUS_APPROVAL3'] !== 'BERHASIL'): ?>
                                                <button type="submit" formaction="process_approval.php" formmethod="POST"
                                                    name="pengajuan_id" value="<?php echo $pengajuan['ID_PENGAJUAN']; ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 
    font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700 
    focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                                                    <i class="fas fa-check mr-2"></i>
                                                    Setujui
                                                </button>
                                            <?php else: ?>
                                                <span
                                                    class="px-3 py-2 inline-flex text-sm leading-4 font-medium rounded-md text-emerald-700 bg-emerald-100">
                                                    <i class="fas fa-check-circle mr-2"></i>
                                                    Sudah Disetujui
                                                </span>
                                            <?php endif; ?>

                                            <!-- Tombol Lihat Detail -->
                                            <a href="DetailPengajuan.php?id=<?php echo $pengajuan['ID_PENGAJUAN']; ?>"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 
                                                    font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                                                <i class="fas fa-eye mr-2"></i>
                                                Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Pagination -->
            <div class="flex justify-between items-center mt-4">
                <div class="flex justify-center mt-4">
                    <?php if ($total_pages > 1): ?>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo ($page == $i) ? 'text-emerald-600 bg-emerald-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>

                <div class="flex items-center">
                    <select onchange="window.location.href=this.value"
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm rounded-md">
                        <?php foreach ([10, 25, 50, 100] as $value): ?>
                            <option value="?page=1&per_page=<?php echo $value; ?>&search=<?php echo urlencode($search); ?>"
                                <?php echo ($per_page == $value) ? 'selected' : ''; ?>>
                                <?php echo $value; ?> per page
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Your existing JavaScript functions remain the same
        function viewPengajuan(id) {
            window.location.href = `view_pengajuan.php?id=${id}`;
        }

        function editPengajuan(id) {
            window.location.href = `edit_pengajuan.php?id=${id}`;
        }

        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.classList.toggle('hidden');

            // Close dropdown when clicking outside
            document.addEventListener('click', function closeDropdown(e) {
                if (!e.target.closest('.relative')) {
                    dropdown.classList.add('hidden');
                    document.removeEventListener('click', closeDropdown);
                }
            });
        }

        function deletePengajuan(id) {
            if (confirm('Are you sure you want to delete this submission?')) {
                fetch('delete_pengajuan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Failed to delete submission');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting');
                    });
            }
        }

        // Fungsi Checkbox
        function toggleAllCheckboxes(source) {
            const checkboxes = document.getElementsByName('selected_ids[]');
            for (let checkbox of checkboxes) {
                if (!checkbox.disabled) {
                    checkbox.checked = source.checked;
                }
            }
        }
    </script>
</body>

</html>