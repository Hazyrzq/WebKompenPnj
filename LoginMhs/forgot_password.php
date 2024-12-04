<?php
session_start();
require_once '../config/config.php';
require_once 'functions.php';
require_once 'email_handler.php';

$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nim = trim($_POST['nim']);
    
    // Get user email from database
    $query = "SELECT email FROM tbl_mahasiswa WHERE nim = :nim";
    $stmt = executeQuery($query, [':nim' => $nim]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generate and save reset token
        $reset_token = setPasswordResetToken($nim);
        
        // Initialize email handler and send reset email
        try {
            $emailHandler = new EmailHandler();
            if ($emailHandler->sendPasswordResetEmail($user['EMAIL'], $reset_token, $nim)) {
                $success = "Link reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.";
            } else {
                $error = "Gagal mengirim email reset password. Silakan coba lagi nanti.";
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
        }
    } else {
        $error = "NIM tidak ditemukan dalam sistem.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Kompensasi Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full bg-white p-8 rounded-xl shadow-lg">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-extrabold text-gray-900">Lupa Password</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Masukkan NIM Anda untuk menerima link reset password
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
                </div>
            <?php endif; ?>

            <form method="POST" class="mt-8 space-y-6">
                <div>
                    <label for="nim" class="block text-sm font-medium text-gray-700">NIM</label>
                    <input type="text" name="nim" id="nim" required
                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Kirim Link Reset Password
                    </button>
                </div>
            </form>

            <div class="mt-4 text-center">
                <a href="login.php" class="text-sm text-indigo-600 hover:text-indigo-500">
                    Kembali ke halaman login
                </a>
            </div>
        </div>
    </div>
</body>
</html>