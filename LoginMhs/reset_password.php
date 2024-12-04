<?php
// reset_password.php
session_start();
require_once '../config/config.php';
require_once 'functions.php';

$error = null;
$success = null;

// Verify token from URL
if (!isset($_GET['token']) || !isset($_GET['nim'])) {
    header('Location: login.php');
    exit;
}

$token = $_GET['token'];
$nim = $_GET['nim'];

// Verify if token is valid and not expired
if (!verifyResetToken($nim, $token)) {
    $error = "Link reset password tidak valid atau sudah kadaluarsa.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$error) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    if (strlen($password) < 8) {
        $error = "Password harus minimal 8 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak cocok.";
    } else {
        // Update password
        if (updatePassword($nim, $password)) {
            $success = "Password berhasil diubah. Silakan login dengan password baru Anda.";
        } else {
            $error = "Gagal mengubah password. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Kompensasi Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full bg-white p-8 rounded-xl shadow-lg">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-extrabold text-gray-900">Reset Password</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Masukkan password baru Anda
                </p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                    <?php echo $success; ?>
                    <p class="mt-2">
                        <a href="login.php" class="font-medium text-green-700 hover:text-green-600">
                            Klik disini untuk login
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <form method="POST" class="mt-8 space-y-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password Baru</label>
                        <input type="password" name="password" id="password" required
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Ubah Password
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>