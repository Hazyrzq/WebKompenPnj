<?php
session_start();
require_once('../../Config.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Validate user role - Only allow PLP
if ($_SESSION['role'] !== 'PLP') {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'ADMIN':
            header('Location: ../../Admin/Dashboard/dashboard.php');
            break;
        case 'KALAB':
            header('Location: ../../Kalab/Dashboard/dashboard.php');
            break;
        case 'PENGAWAS':
            header('Location: ../../Pengawas/Dashboard/dashboard.php');
            break;
        default:
            header('Location: ../login.php');
            break;
    }
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
$filterPJ = isset($_GET['filter_pj']) ? $_GET['filter_pj'] : '';

// Get unique penanggung jawab for dropdown
$pjQuery = "SELECT DISTINCT penanggung_jawab FROM tbl_pekerjaan ORDER BY penanggung_jawab";
$pjStmt = executeQuery($pjQuery);
$pjList = $pjStmt->fetchAll(PDO::FETCH_COLUMN);

// Query untuk data pekerjaan dengan pagination menggunakan ROWNUM
$query = "SELECT * FROM (
            SELECT a.*, ROWNUM rnum 
            FROM (
                SELECT * FROM tbl_pekerjaan WHERE 1=1";

// Menambahkan filter pencarian
if (!empty($searchTerm)) {
    $query .= " AND (id_penanggung_jawab LIKE :searchTerm 
                OR penanggung_jawab LIKE :searchTerm 
                OR kode_pekerjaan LIKE :searchTerm
                OR jam_pekerjaan LIKE :searchTerm)";
}

// Menambahkan filter penanggung jawab
if (!empty($filterPJ)) {
    $query .= " AND penanggung_jawab = :filterPJ";
}

$query .= " ORDER BY id_pekerjaan) a 
            WHERE ROWNUM <= :endRow)
          WHERE rnum > :offset";

$params = [];
if (!empty($searchTerm)) {
    $params['searchTerm'] = "%$searchTerm%";
}
if (!empty($filterPJ)) {
    $params['filterPJ'] = $filterPJ;
}
$params['endRow'] = $endRow;
$params['offset'] = $offset;

// Query total records untuk pagination
$totalQuery = "SELECT COUNT(*) FROM tbl_pekerjaan WHERE 1=1";
$totalParams = [];

if (!empty($searchTerm)) {
    $totalQuery .= " AND (id_penanggung_jawab LIKE :searchTerm 
                    OR penanggung_jawab LIKE :searchTerm 
                    OR kode_pekerjaan LIKE :searchTerm
                    OR jam_pekerjaan LIKE :searchTerm)";
    $totalParams['searchTerm'] = "%$searchTerm%";
}
if (!empty($filterPJ)) {
    $totalQuery .= " AND penanggung_jawab = :filterPJ";
    $totalParams['filterPJ'] = $filterPJ;
}

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
    <title>Menu Pekerjaan</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Menu Pekerjaan PLP</h1>
                        <p class="text-sm text-gray-600">Manage the Details of Your Menu Pekerjaan,
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
                                    <a href="../UpdateProfilePLP.php"
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
                <a href="/Sikompen/plp/DaftarPengajuan/DaftarPengajuanPLP.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Pengajuan
                </a>
                <a href="/Sikompen/plp/Daftardisetujui/DaftardisetujuiPLP.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Daftar Disetujui
                </a>
                <a href="#" class="border-b-2 border-emerald-500 text-emerald-600 px-6 py-3 text-sm font-medium">

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
                <form action="" method="GET" class="flex space-x-3">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>"
                            placeholder="Search by ID PJ, penanggung jawab, kode pekerjaan, jam pekerjaan..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-500">
                    </div>
                    <select name="filter_pj"
                        class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500">
                        <option value="">All PJ</option>
                        <?php foreach ($pjList as $pj): ?>
                            <option value="<?php echo htmlspecialchars($pj); ?>" <?php echo $filterPJ === $pj ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pj); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                        Filter
                    </button>
                    <?php if (!empty($searchTerm) || !empty($filterPJ)): ?>
                        <a href="PekerjaanPLP.php"
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 flex items-center">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="flex space-x-3">
                <button onclick="window.location.href='ExportPLP_pdf.php'"
                    class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> PDF
                </button>
            </div>
        </div>

        <!-- Table -->
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No
                        Pekerjaan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode
                        Pekerjaan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama
                        Pekerjaan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam
                        Pekerjaan (Menit)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detail
                        Pekerjaan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Limit
                        Kerja</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID PJ
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Penanggung Jawab</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Convert hours to minutes for display
                    $jamPekerjaan = floatval($row['JAM_PEKERJAAN']);
                    $menitPekerjaan = intval($jamPekerjaan * 60);
                    ?>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ID_PEKERJAAN']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['KODE_PEKERJAAN']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['NAMA_PEKERJAAN']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($menitPekerjaan); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php echo htmlspecialchars($row['DETAIL_PEKERJAAN']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['BATAS_PEKERJA']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php echo htmlspecialchars($row['ID_PENANGGUNG_JAWAB']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php echo htmlspecialchars($row['PENANGGUNG_JAWAB']); ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="mt-4 flex justify-center items-center space-x-4">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo htmlspecialchars($searchTerm); ?>&filter_pj=<?php echo htmlspecialchars($filterPJ); ?>"
                    class="px-4 py-2 bg-gray-300 text-sm text-gray-700 rounded-lg hover:bg-gray-400">
                    Previous
                </a>
            <?php endif; ?>
            <span class="text-sm text-gray-600">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo htmlspecialchars($searchTerm); ?>&filter_pj=<?php echo htmlspecialchars($filterPJ); ?>"
                    class="px-4 py-2 bg-gray-300 text-sm text-gray-700 rounded-lg hover:bg-gray-400">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.classList.toggle('hidden');
        }
    </script>
</body>

</html>