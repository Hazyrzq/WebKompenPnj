<?php
session_start();
require_once('../../Config.php');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Validate user role - Only allow ADMIN
if ($_SESSION['role'] !== 'ADMIN') {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'KALAB':
            header('Location: ../Kalab/Dashboard/dashboard.php');
            break;
        case 'PLP':
            header('Location: ../PLP/Dashboard/dashboard.php');
            break;
        case 'PENGAWAS':
            header('Location: ../Pengawas/Dashboard/dashboard.php');
            break;
        default:
            header('Location: ../../login.php');
            break;
    }
    exit();
}

// Get user data from session
$userNama = $_SESSION['nama_user'];
$userRole = $_SESSION['role'];

// Handle search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchQuery = $searchTerm;

// Pagination settings 
$itemsPerPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $db = getDB();

    // Get unique prodi values for filter dropdown
    $prodiQuery = $db->query("SELECT DISTINCT PRODI FROM TBL_MAHASISWA ORDER BY PRODI");
    $prodiList = $prodiQuery->fetchAll(PDO::FETCH_COLUMN);

    // Get filter value
    $filterProdi = isset($_GET['filter_prodi']) ? $_GET['filter_prodi'] : '';

    // Base query
    $query = "SELECT * FROM tbl_MAHASISWA WHERE 1=1";
    $params = array();

    // Add search condition if search term exists
    if (!empty($searchQuery)) {
        $query .= " AND (
            LOWER(NAMA) LIKE LOWER(:search) 
            OR LOWER(KELAS) LIKE LOWER(:search) 
            OR LOWER(NIM) LIKE LOWER(:search)
            OR LOWER(PRODI) LIKE LOWER(:search)
        )";
        $params[':search'] = "%$searchQuery%";
    }

    // Add prodi filter if selected
    if (!empty($filterProdi)) {
        $query .= " AND PRODI = :filterProdi";
        $params[':filterProdi'] = $filterProdi;
    }

    // Count total rows for pagination
    $countStmt = $db->prepare(str_replace('*', 'COUNT(*)', $query));
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRows = $countStmt->fetchColumn();

    // Get paginated data
    $query .= " ORDER BY NIM OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
    $stmt = $db->prepare($query);

    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->execute();

    // Handle logout action
    if (isset($_GET['action']) && $_GET['action'] == 'logout') {
        session_destroy();
        header('Location: ../../login.php');
        exit();
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Mahasiswa</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Data Mahasiswa</h1>
                        <p class="text-sm text-gray-600">Manage the Details of Your Menu Mahasiswa,
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
                                    <!-- User Info -->
                                    <div class="px-4 py-2 text-sm text-gray-600 border-b">
                                        <div><?php echo htmlspecialchars($userRole); ?></div>
                                    </div>

                                    <hr class="my-1">

                                    <a href="../UpdateProfile.php?ref=<?php echo urlencode('/Sikompen/admin/mahasiswa/mahasiswa.php'); ?>"
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
                <a href=/sikompen/admin/Dashboard/Dashboard.php
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Dashboard
                </a>
                <a href=/sikompen/admin/Pekerjaan/Pekerjaan.php
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Pekerjaan
                </a>
                <a href=/sikompen/admin/User/User.php
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    User
                </a>
                <a href=/sikompen/admin/Mahasiswa/Mahasiswa.php
                    class="border-b-2 border-emerald-500 text-emerald-600 px-6 py-3 text-sm font-medium">
                    Mahasiswa
                </a>
                <a href=/sikompen/admin/Bertugas/Bertugas.php
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Bertugas
                </a>
                <a href="/sikompen/admin/Pembayaran/historypembayaran.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    History Payment
                </a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Search dan Filter -->
        <div class="flex justify-between items-center mb-6 gap-4">
            <!-- Search Form -->
            <div class="flex-1 max-w-lg">
                <form action="" method="GET" class="flex space-x-3">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>"
                            placeholder="Search by NIM, nama, kelas..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-500">
                    </div>
                    <!-- Filter Dropdown -->
                    <select name="filter_prodi"
                        class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500">
                        <option value="">All Prodi</option>
                        <?php foreach ($prodiList as $prodi): ?>
                            <option value="<?php echo htmlspecialchars($prodi); ?>" <?php echo $filterProdi === $prodi ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prodi); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                        Filter
                    </button>
                </form>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <!-- Export Buttons Group -->
                <div class="bg-gray-600 rounded-lg flex">
                    <button onclick="window.location.href='import_excel_mahasiswa.php'"
                        class="px-4 py-2 text-white hover:bg-gray-700 rounded-l-lg flex items-center border-r border-gray-500">
                        <i class="fas fa-file-excel mr-2"></i> Excel
                    </button>
                    <button onclick="window.location.href='export_pdf_mahasiswa.php'"
                        class="px-4 py-2 text-white hover:bg-gray-700 rounded-r-lg flex items-center">
                        <i class="fas fa-file-pdf mr-2"></i> PDF
                    </button>
                </div>

                <!-- Main Action Buttons -->
                <button onclick="window.location.href='TambahMahasiswa.php'"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i> Tambah Data
                </button>

                <button
                    onclick="if(confirm('Are you sure you want to delete all users?')) window.location.href='user.php?delete_all=true'"
                    class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Semua
                </button>

                <button onclick="window.location.href='send_notification.php'"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-envelope mr-2"></i> Email Notifikasi
                </button>
            </div>
        </div>

        <!-- Tabel Data Mahasiswa -->
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIM
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama
                            Mahasiswa</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Semester</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prodi
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Jumlah Terlambat (Menit)</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Jumlah Alfa (Menit)</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $no = $offset + 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $terlambat = $row['JUMLAH_TERLAMBAT'] * 2; // Multiply by 2
                        $alfa = $row['JUMLAH_ALFA'] * 60; // Convert hours to minutes
                        $total = $terlambat + $alfa;
                        ?>
                        <tr class="hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $no++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['NIM']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['NAMA']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['KELAS']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['SEMESTER']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['PRODI']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($terlambat); ?> menit
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($alfa); ?> menit
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($total); ?> menit
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center items-center space-x-2">
                                    <a href="editMahasiswa.php?id=<?php echo $row['ID_MHS']; ?>"
                                        class="inline-flex items-center px-3 py-1 bg-white border border-emerald-500 text-emerald-600 rounded-md hover:bg-emerald-50 transition-colors duration-200">
                                        <i class="fas fa-edit mr-1"></i>
                                        Edit
                                    </a>
                                    <a href="DeleteMahasiswa.php?id=<?php echo $row['ID_MHS']; ?>"
                                        onclick="return confirm('Are you sure you want to delete this item?')"
                                        class="inline-flex items-center px-3 py-1 bg-white border border-red-500 text-red-600 rounded-md hover:bg-red-50 transition-colors duration-200">
                                        <i class="fas fa-trash-alt mr-1"></i>
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if ($stmt->rowCount() === 0): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">No data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex items-center justify-between">
            <div class="flex items-center">
                <span class="text-sm text-gray-700">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $itemsPerPage, $totalRows); ?> of
                    <?php echo $totalRows; ?> entries
                </span>
            </div>
            <div class="flex space-x-2">
                <?php
                $totalPages = ceil($totalRows / $itemsPerPage);
                $queryParams = $_GET;

                // Previous page link
                if ($currentPage > 1) {
                    $queryParams['page'] = $currentPage - 1;
                    echo '<a href="?' . http_build_query($queryParams) . '" class="px-3 py-1 rounded-md bg-gray-200 text-sm hover:bg-gray-300">Previous</a>';
                }

                // Page numbers
                for ($i = 1; $i <= $totalPages; $i++) {
                    $queryParams['page'] = $i;
                    $activeClass = $i === $currentPage ? 'bg-emerald-600 text-white' : 'bg-gray-200 hover:bg-gray-300';
                    echo '<a href="?' . http_build_query($queryParams) . '" class="px-3 py-1 rounded-md ' . $activeClass . ' text-sm">' . $i . '</a>';
                }

                // Next page link
                if ($currentPage < $totalPages) {
                    $queryParams['page'] = $currentPage + 1;
                    echo '<a href="?' . http_build_query($queryParams) . '" class="px-3 py-1 rounded-md bg-gray-200 text-sm hover:bg-gray-300">Next</a>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.classList.toggle('hidden');

            const handleClickOutside = (event) => {
                const isClickInside = dropdown.contains(event.target) ||
                    event.target.closest('button');
                if (!isClickInside) {
                    dropdown.classList.add('hidden');
                    document.removeEventListener('click', handleClickOutside);
                }
            };

            setTimeout(() => {
                document.addEventListener('click', handleClickOutside);
            }, 0);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.getElementById('dropdownMenu').classList.add('hidden');
            }
        });
    </script>
</body>

</html>