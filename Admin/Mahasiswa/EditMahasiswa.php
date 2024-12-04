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

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: mahasiswa.php');
    exit;
}

$id_mhs = $_GET['id'];

// Fetch existing data
$query = "SELECT * FROM tbl_mahasiswa WHERE id_mhs = :id_mhs";
$stmt = executeQuery($query, ['id_mhs' => $id_mhs]);
$mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);

// If record not found, redirect back
if (!$mahasiswa) {
    header('Location: mahasiswa.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $nim = $_POST['nim'];
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $prodi = $_POST['prodi'];
        $kelas = $_POST['kelas'];
        $semester = $_POST['semester'];
        $notelp = $_POST['notelp'];
        $jumlah_terlambat = $_POST['jumlah_terlambat'];
        $jumlah_alfa = $_POST['jumlah_alfa'];

        // Calculate total minutes
        $hasilTerlambat = $jumlah_terlambat * 2;
        $hasilAlfa = $jumlah_alfa * 60;
        $total = $hasilTerlambat + $hasilAlfa;

        // Password update handling
        $passwordUpdate = "";
        $params = [
            'nim' => $nim,
            'nama' => $nama,
            'email' => $email,
            'prodi' => $prodi,
            'kelas' => $kelas,
            'semester' => $semester,
            'notelp' => $notelp,
            'jumlah_terlambat' => $jumlah_terlambat,
            'jumlah_alfa' => $jumlah_alfa,
            'total' => $total,
            'id_mhs' => $id_mhs
        ];


        if (!empty($_POST['password'])) {
            $passwordUpdate = ", password = :password";
            $params['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $updateQuery = "UPDATE tbl_mahasiswa SET 
            nim = :nim,
            nama = :nama,
            email = :email,
            prodi = :prodi,
            kelas = :kelas,
            semester = :semester,
            notelp = :notelp,
            jumlah_terlambat = :jumlah_terlambat,
            jumlah_alfa = :jumlah_alfa,
            total = :total
            $passwordUpdate
            WHERE id_mhs = :id_mhs";

        executeQuery($updateQuery, $params);
        header('Location: mahasiswa.php?success=1');
        exit;
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Mahasiswa</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Edit Mahasiswa</h1>
                        <p class="text-sm text-gray-600">Edit Data Mahasiswa</p>
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
                                    <a href=../UpdateProfile.php
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
                <a href="/sikompen/admin/Pembayaran/Bertugas.php"
                    class="border-b-2 border-transparent text-gray-500 hover:text-emerald-600 hover:border-emerald-300 px-6 py-3 text-sm font-medium">
                    History Payment
                </a>

            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">NIM</label>
                        <input type="text" name="nim" required
                            value="<?php echo htmlspecialchars($mahasiswa['NIM']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama</label>
                        <input type="text" name="nama" required
                            value="<?php echo htmlspecialchars($mahasiswa['NAMA']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required
                            value="<?php echo htmlspecialchars($mahasiswa['EMAIL']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prodi</label>
                        <select name="prodi" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="TMD" <?php echo ($mahasiswa['PRODI'] === 'TMD') ? 'selected' : ''; ?>>TMD
                            </option>
                            <option value="TMJ" <?php echo ($mahasiswa['PRODI'] === 'TMJ') ? 'selected' : ''; ?>>TMJ
                            </option>
                            <option value="TI" <?php echo ($mahasiswa['PRODI'] === 'TI') ? 'selected' : ''; ?>>TI</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kelas</label>
                        <input type="text" name="kelas" required
                            value="<?php echo htmlspecialchars($mahasiswa['KELAS']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                        <input type="number" name="semester" required
                            value="<?php echo htmlspecialchars($mahasiswa['SEMESTER']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">No. Telp</label>
                        <input type="text" name="notelp" value="<?php echo htmlspecialchars($mahasiswa['NOTELP']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password (Opsional)</label>
                        <input type="password" name="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500"
                            placeholder="Kosongkan jika tidak ingin mengubah">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Terlambat (Menit)</label>
                        <input type="number" id="terlambat" name="jumlah_terlambat" required
                            value="<?php echo htmlspecialchars($mahasiswa['JUMLAH_TERLAMBAT']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="mt-1 text-sm text-gray-500">Hasil: <span id="hasilTerlambat">0</span> menit</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Alfa (Jam)</label>
                        <input type="number" id="alfa" name="jumlah_alfa" required
                            value="<?php echo htmlspecialchars($mahasiswa['JUMLAH_ALFA']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="mt-1 text-sm text-gray-500">Hasil: <span id="hasilAlfa">0</span> menit</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total Menit</label>
                        <div class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                            <span id="total">0</span> menit
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="mahasiswa.php"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.classList.toggle('hidden');
        }

        function hitungTotal() {
            // Ambil nilai input
            let terlambat = document.getElementById('terlambat').value || 0;
            let alfa = document.getElementById('alfa').value || 0;

            // Konversi ke number
            terlambat = parseInt(terlambat);
            alfa = parseInt(alfa);

            // Kalkulasi
            let hasilTerlambat = terlambat * 2;
            let hasilAlfa = alfa * 60;
            let total = hasilTerlambat + hasilAlfa;

            // Tampilkan hasil
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

        // Event listener untuk input terlambat dan alfa
        document.getElementsByName('jumlah_terlambat')[0].addEventListener('input', hitungTotal);
        document.getElementsByName('jumlah_alfa')[0].addEventListener('input', hitungTotal);
    </script>
</body>

</html>