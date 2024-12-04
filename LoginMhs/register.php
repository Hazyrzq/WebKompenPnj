<?php
session_start();
require_once '../config/config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isset($_SESSION['nim'])) {
    header("Location: ../Mahasiswa/dashboard.php");
    exit;
}

$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = trim($_POST['nama']);
    $nim = trim($_POST['nim']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validasi input
    if (empty($nama) || empty($nim) || empty($email) || empty($password)) {
        $error = "Semua field harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif (strlen($password) < 8) {
        $error = "Password harus minimal 8 karakter!";
    } elseif ($password !== $confirm_password) {
        $error = "Password konfirmasi tidak cocok!";
    } else {
        try {
            // Cek apakah NIM sudah terdaftar
            $query = "SELECT COUNT(*) FROM tbl_mahasiswa WHERE nim = :nim";
            $stmt = executeQuery($query, [':nim' => $nim]);
            if ($stmt->fetchColumn() > 0) {
                $error = "NIM sudah terdaftar!";
            } else {
                // Cek apakah email sudah terdaftar
                $query = "SELECT COUNT(*) FROM tbl_mahasiswa WHERE email = :email";
                $stmt = executeQuery($query, [':email' => $email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Email sudah terdaftar!";
                } else {
                    // Proses registrasi
                    registerUser($nama, $nim, $password, $email);
                    $success = "Registrasi berhasil! Silakan login.";
                    
                    // Redirect ke halaman login setelah 3 detik
                    header("refresh:3;url=login.php");
                }
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Mahasiswa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full bg-white p-8 rounded-xl shadow-lg grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Left Side: Form and Title -->
            <div>
                <h2 class="text-center text-3xl font-extrabold text-gray-900">Registrasi Mahasiswa</h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Sudah punya akun?
                    <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Login disini
                    </a>
                </p>

                <!-- Error and Success Messages -->
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded my-4" role="alert">
                        <p class="font-bold">Error</p>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded my-4" role="alert">
                        <p class="font-bold">Sukses</p>
                        <p><?php echo $success; ?></p>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <form class="mt-8 space-y-6" method="POST" action="">
                    <div class="space-y-4">
                        <!-- Nama Lengkap -->
                        <div>
                            <label for="nama" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                            <div class="mt-0 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input id="nama" name="nama" type="text" required class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 py-2 sm:text-sm border-gray-300 rounded-md" placeholder="Masukkan nama lengkap">
                            </div>
                        </div>

                        <!-- NIM -->
                        <div>
                            <label for="nim" class="block text-sm font-medium text-gray-700">NIM</label>
                            <div class="mt-0 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-id-card text-gray-400"></i>
                                </div>
                                <input id="nim" name="nim" type="text" required class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 py-2 sm:text-sm border-gray-300 rounded-md" placeholder="Masukkan NIM">
                            </div>
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <div class="mt-0 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input id="email" name="email" type="email" required class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 py-2 sm:text-sm border-gray-300 rounded-md" placeholder="nama@example.com">
                            </div>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <div class="mt-0 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input id="password" name="password" type="password" required class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 py-2 sm:text-sm border-gray-300 rounded-md" placeholder="Minimal 8 karakter">
                            </div>
                        </div>

                        <!-- Konfirmasi Password -->
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                            <div class="mt-0 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input id="confirm_password" name="confirm_password" type="password" required class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 py-2 sm:text-sm border-gray-300 rounded-md" placeholder="Masukkan ulang password">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div>
                        <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-user-plus text-indigo-500 group-hover:text-indigo-400"></i>
                            </span>
                            Daftar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Side: Image Upload -->
            <div class="flex items-center justify-center">
                <img src="../images/hp.png" alt="Student Compensation">
            </div>

    <script>
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function(){
                const output = document.getElementById('preview');
                output.src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    </script>
</body>
</html>
