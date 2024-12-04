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
            header('Location: ../../Kalab/Dashboard/dashboard.php');
            break;
        case 'PLP':
            header('Location: ../../PLP/Dashboard/dashboard.php');
            break;
        case 'PENGAWAS':
            header('Location: ../../Pengawas/Dashboard/dashboard.php');
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

    // Base query
    $query = "SELECT p.*, m.NAMA 
              FROM TBL_PAYMENTS p 
              LEFT JOIN TBL_MAHASISWA m ON p.NIM = m.NIM 
              WHERE 1=1";
    $params = array();

    // Add search condition if search term exists
    if (!empty($searchQuery)) {
        $query .= " AND (
            LOWER(p.PAYMENT_ID) LIKE LOWER(:search) 
            OR LOWER(p.NIM) LIKE LOWER(:search)
            OR LOWER(m.NAMA) LIKE LOWER(:search)
            OR LOWER(p.STATUS) LIKE LOWER(:search)
        )";
        $params[':search'] = "%$searchQuery%";
    }

    // Count total rows for pagination
    $countStmt = $db->prepare(str_replace('p.*, m.NAMA', 'COUNT(*)', $query));
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRows = $countStmt->fetchColumn();

    // Get paginated data
    $query .= " ORDER BY p.CREATED_AT DESC OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
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
    <title>History Payment</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">History Payment</h1>
                        <p class="text-sm text-gray-600">Track All Payment History,
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

                                    <a href="../UpdateProfile.php?ref=<?php echo urlencode('/Sikompen/admin/pembayaran/historypembayaran.php'); ?>"                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-600">
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
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Mahasiswa
                </a>
                <a href=/sikompen/admin/Bertugas/Bertugas.php
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Bertugas
                </a>
                <a href="/sikompen/admin/Pembayaran/historypembayaran.php"
                    class="border-b-2 border-emerald-500 text-emerald-600 px-6 py-3 text-sm font-medium">
                    History Payment
                </a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Search -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex-1 max-w-lg">
                <form action="" method="GET" class="flex space-x-3">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>"
                            placeholder="Search by Payment ID, NIM, or Status..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-500">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                        Search
                    </button>
                </form>
            </div>
            <!-- Export Buttons -->
            <div class="flex space-x-3">
                <button onclick="window.location.href='export_pdf_pembayaran.php'"
                    class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> PDF
                </button>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Payment ID</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIM
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Payment Method</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Payment Channel</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Created At</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Verified At</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $no = $offset + 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $status_class = match ($row['STATUS']) {
                            'verified' => 'bg-green-100 text-green-800',
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            default => 'bg-gray-100 text-gray-800'
                        };
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $no++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['PAYMENT_ID']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['NIM']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['NAMA']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['AMOUNT']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['PAYMENT_METHOD']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['PAYMENT_CHANNEL']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($row['STATUS']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['CREATED_AT']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['VERIFIED_AT'] ?? 'N/A'); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="px-6 py-4 bg-white flex items-center justify-between border-t border-gray-200">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchTerm); ?>"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($currentPage * $itemsPerPage < $totalRows): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchTerm); ?>"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium"><?php echo $offset + 1; ?></span>
                            to
                            <span class="font-medium"><?php echo min($offset + $itemsPerPage, $totalRows); ?></span>
                            of
                            <span class="font-medium"><?php echo $totalRows; ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchTerm); ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($currentPage * $itemsPerPage < $totalRows): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchTerm); ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    Next
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Optional: Dropdown Toggle Script -->
        <script>
            function toggleDropdown() {
                const dropdown = document.getElementById('dropdownMenu');
                dropdown.classList.toggle('hidden');
            }
        </script>
</body>

</html>