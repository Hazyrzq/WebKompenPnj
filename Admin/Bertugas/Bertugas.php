<?php
session_start();
require_once('../../Config.php');// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Get user data from session
$userNama = $_SESSION['nama_user'];
$userRole = $_SESSION['role'];

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ../../login.php');
    exit();
}

// Pagination setup
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$endRow = $offset + $itemsPerPage;

// Modified query to support search and filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$filterRole = isset($_GET['filter_role']) ? $_GET['filter_role'] : '';

// Get unique roles for dropdown
$roleQuery = "SELECT DISTINCT ROLE FROM TBL_USER ORDER BY ROLE";
$roleStmt = executeQuery($roleQuery);
$roleList = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

// Initialize params array
$params = [];

// Main query dengan filter
$query = "SELECT * FROM (
    SELECT a.*, ROWNUM rnum 
    FROM (
        SELECT 
            b.ID,
            b.NIP,
            TO_CHAR(b.TANGGAL_BERTUGAS, 'DD-MM-YYYY HH24:MI') as TANGGAL_BERTUGAS,
            u.NAMA_USER,
            u.ROLE
        FROM SETUP_BERTUGAS b
        JOIN TBL_USER u ON b.NIP = u.NIP
        WHERE 1=1";

// Menambahkan filter pencarian
if (!empty($searchTerm)) {
    $query .= " AND (u.NAMA_USER LIKE :searchTerm 
                OR u.NIP LIKE :searchTerm 
                OR u.ROLE LIKE :searchTerm)";
    $params['searchTerm'] = "%$searchTerm%";
}

// Menambahkan filter role
if (!empty($filterRole)) {
    $query .= " AND u.ROLE = :filterRole";
    $params['filterRole'] = $filterRole;
}

$query .= " ORDER BY b.TANGGAL_BERTUGAS DESC) a 
            WHERE ROWNUM <= :endRow)
          WHERE rnum > :offset";

// Add pagination parameters
$params['endRow'] = $endRow;
$params['offset'] = $offset;

// Query total records dengan filter yang sama
$totalQuery = "SELECT COUNT(*) 
               FROM SETUP_BERTUGAS b
               JOIN TBL_USER u ON b.NIP = u.NIP
               WHERE 1=1";

$totalParams = [];

if (!empty($searchTerm)) {
    $totalQuery .= " AND (u.NAMA_USER LIKE :searchTerm 
                    OR u.NIP LIKE :searchTerm 
                    OR u.ROLE LIKE :searchTerm)";
    $totalParams['searchTerm'] = "%$searchTerm%";
}

// Tambahkan filter role ke total query
if (!empty($filterRole)) {
    $totalQuery .= " AND u.ROLE = :filterRole";
    $totalParams['filterRole'] = $filterRole;
}

// Execute queries
$stmt = executeQuery($query, $params);
$totalRecordsStmt = executeQuery($totalQuery, $totalParams);
$totalRecords = $totalRecordsStmt->fetchColumn();
$totalPages = ceil($totalRecords / $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Setup Bertugas</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
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
                <div class="flex items-center space-x-4">
                    <img src="images/LogoPNJ.png" alt="Logo" class="h-12 w-12">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Menu Setup Bertugas</h1>
                        <p class="text-sm text-gray-600">Manage the Details of Your Setup Bertugas,
                            <?php echo htmlspecialchars($userNama); ?>
                        </p>
                    </div>
                </div>
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
                                    <a href="../UpdateProfile.php?ref=<?php echo urlencode('/Sikompen/Admin/bertugas/bertugas.php'); ?>"
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
                <a href="/sikompen/Admin/Dashboard/Dashboard.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Dashboard
                </a>
                <a href="/sikompen/Admin/Pekerjaan/Pekerjaan.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Pekerjaan
                </a>
                <a href="/sikompen/Admin/User/User.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    User
                </a>
                <a href="/sikompen/Admin/Mahasiswa/Mahasiswa.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Mahasiswa
                </a>
                <a href="/sikompen/Admin/Bertugas/Bertugas.php"
                    class="border-b-2 border-emerald-500 text-emerald-600 font-bold hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Bertugas
                </a>
                <a href="/sikompen/Admin/Pembayaran/historypembayaran.php"

                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    History Payment
                </a>
            </nav>
        </div>
    </div>

  <!-- Content -->
<div class="max-w-7xl mx-auto px-4 py-6">
    <!-- Search and Actions -->
    <div class="flex justify-between items-center mb-6">
        <div class="flex-1 max-w-lg">
            <form action="" method="GET" class="flex space-x-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                        <i class="fas fa-search text-gray-400"></i>
                    </span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>"
                        placeholder="Search by nama, nip, role..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-500">
                </div>
                <select name="filter_role"
                    class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500">
                    <option value="">All Roles</option>
                    <?php foreach ($roleList as $role): ?>
                        <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $filterRole === $role ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    Filter
                </button>
                <?php if (!empty($searchTerm) || !empty($filterRole)): ?>
                    <a href="bertugas.php"
                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 flex items-center">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="flex space-x-3">
            <button onclick="window.location.href='TambahBertugas.php'"
                class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center">
                <i class="fas fa-plus mr-2"></i> Tambah Data
            </button>
            <button
                onclick="if(confirm('Are you sure you want to delete all data?')) window.location.href='DeleteBertugas.php?delete_all=true'"
                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 flex items-center">
                <i class="fas fa-trash-alt mr-2"></i> Delete Semua
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NO</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIP</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Bertugas</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $no = ($currentPage - 1) * $itemsPerPage + 1;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo $no++; ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['NIP']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['NAMA_USER']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ROLE']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo date('d-m-Y H:i', strtotime($row['TANGGAL_BERTUGAS'])); ?></td>
                        <td class="px-6 py-4 text-sm text-center">
                            <a href="editBertugas.php?id=<?php echo $row['ID']; ?>"
                                class="inline-flex items-center px-3 py-1 border border-emerald-500 text-emerald-600 rounded-md hover:bg-emerald-50 transition-colors duration-200">
                                <i class="fas fa-edit mr-1"></i>
                                Edit
                            </a>
                            <a href="DeleteBertugas.php?id=<?php echo $row['ID']; ?>"
                                onclick="return confirm('Are you sure you want to delete this item?')"
                                class="inline-flex items-center px-3 py-1 border border-red-500 text-red-600 rounded-md hover:bg-red-50 transition-colors duration-200">
                                <i class="fas fa-trash-alt mr-1"></i>
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6 flex justify-between items-center">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
            <a href="?page=1<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $filterRole ? '&filter_role=' . urlencode($filterRole) : ''; ?>"
                class="px-3 py-2 rounded-l-md text-sm font-medium text-gray-500 hover:bg-gray-50">
                First
            </a>
            <a href="?page=<?php echo max(1, $currentPage - 1) . ($searchTerm ? '&search=' . urlencode($searchTerm) : '') . ($filterRole ? '&filter_role=' . urlencode($filterRole) : ''); ?>"
                class="px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50">
                Previous
            </a>
            <a href="?page=<?php echo min($totalPages, $currentPage + 1) . ($searchTerm ? '&search=' . urlencode($searchTerm) : '') . ($filterRole ? '&filter_role=' . urlencode($filterRole) : ''); ?>"
                class="px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50">
                Next
            </a>
            <a href="?page=<?php echo $totalPages . ($searchTerm ? '&search=' . urlencode($searchTerm) : '') . ($filterRole ? '&filter_role=' . urlencode($filterRole) : ''); ?>"
                class="px-3 py-2 rounded-r-md text-sm font-medium text-gray-500 hover:bg-gray-50">
                Last
            </a>
        </nav>
        <div class="text-sm text-gray-500">
            Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
        </div>
    </div>
</div>

        <script>
            function toggleDropdown() {
                const dropdown = document.getElementById('dropdownMenu');
                dropdown.classList.toggle('hidden');
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function (event) {
                const dropdown = document.getElementById('dropdownMenu');
                const button = event.target.closest('button');

                if (!button && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            });

            // Flash messages fade out
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    const flashMessages = document.querySelectorAll('.flash-message');
                    flashMessages.forEach(function (message) {
                        message.style.opacity = '0';
                        setTimeout(function () {
                            message.remove();
                        }, 300);
                    });
                }, 3000);
            });
        </script>

</body>

</html>