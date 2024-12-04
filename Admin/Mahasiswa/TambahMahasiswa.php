<?php
session_start();
require_once('../../Config.php');// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ../../login.php');
    exit();
}

// Get user data from session
$userNama = $_SESSION['nama_user'];
$userRole = $_SESSION['role'];

$error = '';
$success = '';

// Form validation function
function validateInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();

        // Validate and sanitize input
        $nim = validateInput($_POST['nim']);
        $nama = validateInput($_POST['nama']);
        $email = validateInput($_POST['email']);
        $prodi = validateInput($_POST['prodi']);
        $kelas = validateInput($_POST['kelas']);
        $semester = validateInput($_POST['semester']);
        $notelp = validateInput($_POST['notelp']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $jumlah_terlambat = validateInput($_POST['jumlah_terlambat']);
        $jumlah_alfa = validateInput($_POST['jumlah_alfa']);

        // Calculate total minutes
        $hasilTerlambat = $jumlah_terlambat * 2;
        $hasilAlfa = $jumlah_alfa * 60;
        $total = $hasilTerlambat + $hasilAlfa;

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid");
        }

        // Validate NIM (assuming it should be numeric and have specific length)
        if (!is_numeric($nim) || strlen($nim) != 10) {
            throw new Exception("NIM harus berupa 10 digit angka");
        }

        // Check if NIM already exists
        $checkNim = $db->prepare("SELECT COUNT(*) FROM tbl_mahasiswa WHERE nim = :nim");
        $checkNim->bindParam(':nim', $nim);
        $checkNim->execute();
        if ($checkNim->fetchColumn() > 0) {
            throw new Exception("NIM sudah terdaftar");
        }

        // Check if email already exists
        $checkEmail = $db->prepare("SELECT COUNT(*) FROM tbl_mahasiswa WHERE email = :email");
        $checkEmail->bindParam(':email', $email);
        $checkEmail->execute();
        if ($checkEmail->fetchColumn() > 0) {
            throw new Exception("Email sudah terdaftar");
        }

        $query = "INSERT INTO tbl_mahasiswa (
                    id_mhs, nim, nama, email, prodi, kelas, 
                    semester, notelp, password, jumlah_terlambat,
                    jumlah_alfa, total
                ) VALUES (
                    seq_id_mhs.NEXTVAL, :nim, :nama, :email, :prodi, :kelas, 
                    :semester, :notelp, :password, :jumlah_terlambat,
                    :jumlah_alfa, :total
                )";

        $stmt = $db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':nim', $nim);
        $stmt->bindParam(':nama', $nama);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':prodi', $prodi);
        $stmt->bindParam(':kelas', $kelas);
        $stmt->bindParam(':semester', $semester);
        $stmt->bindParam(':notelp', $notelp);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':jumlah_terlambat', $jumlah_terlambat);
        $stmt->bindParam(':jumlah_alfa', $jumlah_alfa);
        $stmt->bindParam(':total', $total);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data mahasiswa berhasil ditambahkan";
            header('Location: mahasiswa.php');
            exit();
        } else {
            throw new Exception("Gagal menambahkan data");
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Mahasiswa</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Tambah Mahasiswa</h1>
                        <p class="text-sm text-gray-600">Tambah Data Mahasiswa Baru</p>
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
                                    <a href="../UpdateProfile.php"
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
                <a href="/sikompen/admin/Dashboard/Dashboard.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Dashboard
                </a>
                <a href="/sikompen/admin/Pekerjaan/Pekerjaan.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Pekerjaan
                </a>
                <a href="/sikompen/admin/User/User.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    User
                </a>
                <a href="/sikompen/admin/Mahasiswa/Mahasiswa.php"
                    class="border-b-2 border-emerald-500 text-emerald-600 font-bold hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Mahasiswa
                </a>
                <a href="/sikompen/admin/Bertugas/Bertugas.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Bertugas
                </a>
                <a href="/sikompen/admin/Pengajuan/Pengajuan.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    Pengajuan
                </a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">NIM</label>
                        <input type="text" name="nim" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama</label>
                        <input type="text" name="nama" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prodi</label>
                        <select name="prodi" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="TMD">TMD</option>
                            <option value="TMJ">TMJ</option>
                            <option value="TI">TI</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kelas</label>
                        <input type="text" name="kelas" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                        <input type="number" name="semester" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">No. Telp</label>
                        <input type="text" name="notelp" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" name="password" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>


                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Terlambat (menit)</label>
                        <input type="number" id="terlambat" name="jumlah_terlambat" required value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="mt-1 text-sm text-gray-500">Hasil: <span id="hasilTerlambat">0</span> menit</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Alfa (Jam)</label>
                        <input type="number" id="alfa" name="jumlah_alfa" required value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="mt-1 text-sm text-gray-500">Hasil: <span id="hasilAlfa">0</span> menit</p>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Total Menit</label>
                        <div class="mt-1">
                            <span id="total" class="px-3 py-2 border border-gray-300 rounded-md bg-gray-50">0
                                menit</span>
                        </div>
                    </div>
                    <div>
                        <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                            Save Changes
                        </button>
                        <a href="mahasiswa.php"
                            class="ml-3 px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
            </form>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.classList.toggle('hidden');
        }

        function hitungTotal() {
            let terlambat = document.getElementById('terlambat').value || 0;
            let alfa = document.getElementById('alfa').value || 0;

            let hasilTerlambat = terlambat * 2;
            let hasilAlfa = alfa * 60;
            let total = hasilTerlambat + hasilAlfa;

            document.getElementById('hasilTerlambat').textContent = hasilTerlambat;
            document.getElementById('hasilAlfa').textContent = hasilAlfa;
            document.getElementById('total').textContent = total;
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function () {
            const terlambatInput = document.getElementById('terlambat');
            const alfaInput = document.getElementById('alfa');

            terlambatInput.addEventListener('input', hitungTotal);
            alfaInput.addEventListener('input', hitungTotal);

            // Hitung total awal saat halaman dimuat
            hitungTotal();
        });

        // Close dropdown when clicking outside
        window.onclick = function (event) {
            if (!event.target.matches('.dropdown-toggle')) {
                const dropdowns = document.getElementsByClassName("dropdown-content");
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (!openDropdown.classList.contains('hidden')) {
                        openDropdown.classList.add('hidden');
                    }
                }
            }
        }
    </script>
</body>

</html>