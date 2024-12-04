<?php
session_start();
require_once('../../Config.php');

// Check if user is logged in
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
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Reset koneksi database
$db = getDB();
$db = null;

// Query untuk menampilkan data dengan memperhatikan constraint
$query = "SELECT * FROM tbl_user ORDER BY id";
// Get user data from session
$userNama = $_SESSION['nama_user'];
$userRole = $_SESSION['role'];

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ../../login.php');
    exit();
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$params = [];
$where_conditions = [];

// Build search conditions
if ($search) {
    $where_conditions[] = "(LOWER(NIP) LIKE LOWER(:search) OR LOWER(EMAIL) LIKE LOWER(:search) OR LOWER(NAMA_USER) LIKE LOWER(:search))";
    $params[':search'] = "%$search%";
}

if ($role_filter) {
    $where_conditions[] = "ROLE = :role";
    $params[':role'] = $role_filter;
}

if ($status_filter) {
    $where_conditions[] = "STATUS = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
try {
    $total_records_query = "SELECT COUNT(*) AS TOTAL FROM TBL_USER $where_clause";
    $stmt = executeQuery($total_records_query, $params);
    $total_records = $stmt->fetch()['TOTAL'];
    $total_pages = ceil($total_records / $records_per_page);

    // Get users with pagination
    $query = "SELECT * FROM (
                SELECT a.*, ROWNUM rnum FROM (
                    SELECT ID, NAMA_USER, NIP, EMAIL, ROLE, STATUS, 
                           TO_CHAR(CREATED_AT, 'DD-MON-YYYY HH24:MI:SS') AS CREATED_AT,
                           TO_CHAR(LAST_LOGIN, 'DD-MON-YYYY HH24:MI:SS') AS LAST_LOGIN
                    FROM TBL_USER $where_clause 
                    ORDER BY ID
                ) a WHERE ROWNUM <= :upper_limit
              ) WHERE rnum > :lower_limit";

    $params[':upper_limit'] = $offset + $records_per_page;
    $params[':lower_limit'] = $offset;

    $stmt = executeQuery($query, $params);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    $total_pages = 1;
    echo "Database error: " . $e->getMessage();
}

// Handle delete all action
if (isset($_GET['delete_all']) && $userRole === 'ADMIN') {
    try {
        $delete_query = "DELETE FROM TBL_USER WHERE ROLE != 'ADMIN'";
        executeQuery($delete_query, []);
        header("Location: user.php");
        exit;
    } catch (Exception $e) {
        die("Error deleting users: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu User</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="js/checkbox-enhancement.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Poppins', sans-serif;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .bg-gray-100 {
            background-color: #f9fafb;
        }

        .cursor-not-allowed {
            cursor: not-allowed;
        }

        table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-container {
            margin-bottom: 1.5rem;
        }

        th,
        td {
            border-bottom-width: 1px;
        }

        tr:last-child td {
            border-bottom: none;
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
                        <h1 class="text-2xl font-bold text-gray-800">Menu User</h1>
                        <p class="text-sm text-gray-600">Manage the Details of Your Menu User,
                            <?php echo htmlspecialchars($userNama); ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="relative inline-block text-left">
                            <button type="button" onclick="toggleDropdown()"
                                class="flex items-center focus:outline-none">
                                <img src="images/profile.png" alt="Profile"
                                    class="h-10 w-10 rounded-full border-2 border-emerald-500 hover:border-emerald-600 transition-colors duration-200">
                                <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($userNama); ?></span>
                            </button>

                            <!-- Dropdown menu -->
                            <div id="dropdownMenu"
                                class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                                <div class="py-1">
                                    <div class="px-4 py-2 text-sm text-gray-600 border-b">
                                        <div><?php echo htmlspecialchars($userRole); ?></div>
                                    </div>

                                    <hr class="my-1">

                                    <a href="../UpdateProfile.php?ref=<?php echo urlencode('/Sikompen/admin/user/user.php'); ?>"
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
                    class="border-b-2 border-emerald-500 text-emerald-600 px-6 py-3 text-sm font-medium">
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
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    History Payment
                </a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Search dan Filter -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex-1 max-w-lg">
                <form action="" method="GET" class="flex space-x-3">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by NIP or Email..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-500">
                    </div>
                    <select name="role"
                        class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500">
                        <option value="">All Roles</option>
                        <option value="ADMIN" <?php echo $role_filter === 'ADMIN' ? 'selected' : ''; ?>>Admin</option>
                        <option value="KALAB" <?php echo $role_filter === 'KALAB' ? 'selected' : ''; ?>>Kalab</option>
                        <option value="PLP" <?php echo $role_filter === 'PLP' ? 'selected' : ''; ?>>PLP</option>
                        <option value="PENGAWAS" <?php echo $role_filter === 'PENGAWAS' ? 'selected' : ''; ?>>Pengawas
                        </option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                        Filter
                    </button>
                    <?php if (!empty($search) || !empty($role_filter)): ?>
                        <a href="user.php"
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 flex items-center">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-3">
                <button onclick="window.location.href='TambahUser.php'"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i> Tambah Data
                </button>
                <button onclick="unsentSelected()"
                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 flex items-center disabled:opacity-50"
                    id="unsentSelectedBtn" disabled>
                    <i class="fas fa-undo-alt mr-2"></i> Unsent Selected
                </button>
                <button onclick="activateSelected()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center disabled:opacity-50"
                    id="activateSelectedBtn" disabled>
                    <i class="fas fa-user-check mr-2"></i> Activate Selected
                </button>
            </div>
        </div><!-- Table Container -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 w-10">
                            <input type="checkbox" id="selectAll"
                                class="rounded border-gray-300 text-emerald-600 shadow-sm focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
                            User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama
                            User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIP
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $row): ?>
                        <tr class="<?php echo $row['STATUS'] === 'INACTIVE' ? 'bg-gray-100' : ''; ?>">
                            <td class="px-6 py-4">
                                <input type="checkbox" name="selected[]" value="<?php echo $row['ID']; ?>"
                                    class="user-checkbox rounded border-gray-300 text-emerald-600 shadow-sm focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ID']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($row['NAMA_USER']); ?>
                                <?php if ($row['STATUS'] === 'UNSENT'): ?>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 ml-2">
                                        <i class="fas fa-clock mr-1"></i> Unsent
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['NIP']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['EMAIL']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($row['ROLE']); ?></td>
                            <td class="px-6 py-4 text-sm text-center space-x-2">
                                <a href="EditUser.php?id=<?php echo $row['ID']; ?>"
                                    class="inline-flex items-center px-3 py-1 border border-emerald-500 text-emerald-600 rounded-md hover:bg-emerald-50 transition-colors duration-200">
                                    <i class="fas fa-edit mr-1"></i>
                                    Edit
                                </a>
                                <?php if ($row['STATUS'] !== 'INACTIVE'): ?>
                                    <a href="#" onclick="unsentUser(<?php echo $row['ID']; ?>); return false;"
                                        class="inline-flex items-center px-3 py-1 border border-orange-500 text-orange-600 rounded-md hover:bg-orange-50 transition-colors duration-200">
                                        <i class="fas fa-undo-alt mr-1"></i>
                                        Unset
                                    </a>
                                <?php else: ?>
                                    <a href="#" onclick="activateUser(<?php echo $row['ID']; ?>); return false;"
                                        class="inline-flex items-center px-3 py-1 border border-blue-500 text-blue-600 rounded-md hover:bg-blue-50 transition-colors duration-200">
                                        <i class="fas fa-user-check mr-1"></i>
                                        Activate
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?>"
                    class="px-3 py-2 rounded-l-md text-sm font-medium text-gray-500 hover:bg-gray-50">
                    First
                </a>
                <a href="?page=<?php echo max(1, $page - 1) . ($search ? '&search=' . urlencode($search) : '') . ($role_filter ? '&role=' . urlencode($role_filter) : ''); ?>"
                    class="px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50">
                    Previous
                </a>
                <a href="?page=<?php echo min($total_pages, $page + 1) . ($search ? '&search=' . urlencode($search) : '') . ($role_filter ? '&role=' . urlencode($role_filter) : ''); ?>"
                    class="px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50">
                    Next
                </a>
                <a href="?page=<?php echo $total_pages . ($search ? '&search=' . urlencode($search) : '') . ($role_filter ? '&role=' . urlencode($role_filter) : ''); ?>"
                    class="px-3 py-2 rounded-r-md text-sm font-medium text-gray-500 hover:bg-gray-50">
                    Last
                </a>
            </nav>
            <div class="text-sm text-gray-500">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </div>
        </div>
    </div>

    <script>
        // Update button states
        function updateButtonStates() {
            const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
            const selectedCount = selectedCheckboxes.length;

            // Update unsent button
            const unsentBtn = document.getElementById('unsentSelectedBtn');
            const hasActiveSelected = Array.from(selectedCheckboxes)
                .some(cb => !cb.closest('tr').classList.contains('bg-gray-100'));
            unsentBtn.disabled = !hasActiveSelected;

            // Update activate button
            const activateBtn = document.getElementById('activateSelectedBtn');
            const hasInactiveSelected = Array.from(selectedCheckboxes)
                .some(cb => cb.closest('tr').classList.contains('bg-gray-100'));
            activateBtn.disabled = !hasInactiveSelected;
        }


        // Handle unsent selected
        function unsentUser(userId) {
    if (confirm('Are you sure you want to set this user as Unsent?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'UnsentMultipleUsers.php';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'users';
        input.value = JSON.stringify([userId]);

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function activateUser(userId) {
    if (confirm('Are you sure you want to activate this user?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'ActivateMultipleUsers.php';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'users';
        input.value = JSON.stringify([userId]);

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
        function unsentSelected() {
            const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                .filter(cb => !cb.closest('tr').classList.contains('bg-gray-100'))
                .map(cb => cb.value);

            if (selectedUsers.length === 0) return;

            if (confirm('Are you sure you want to unsent selected users?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'UnsentMultipleUsers.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'users';
                input.value = JSON.stringify(selectedUsers);

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Handle activate selected

        function activateSelected() {
            const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                .filter(cb => cb.closest('tr').classList.contains('bg-gray-100'))
                .map(cb => cb.value);

            if (selectedUsers.length === 0) return;

            if (confirm('Are you sure you want to activate selected users?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'ActivateMultipleUsers.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'users';
                input.value = JSON.stringify(selectedUsers);

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Update event listeners
        document.getElementById('selectAll').addEventListener('change', function () {
            const checkboxes = document.getElementsByClassName('user-checkbox');
            for (let checkbox of checkboxes) {
                if (!checkbox.disabled) {
                    checkbox.checked = this.checked;
                }
            }
            updateButtonStates();
        });

        const checkboxes = document.getElementsByClassName('user-checkbox');
        for (let checkbox of checkboxes) {
            checkbox.addEventListener('change', function () {
                updateButtonStates();
            });
        }

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