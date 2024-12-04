<?php
session_start();
require_once '../config/config.php';
require_once 'functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Gregwar\Captcha\CaptchaBuilder;

// Generate CAPTCHA baru
$builder = new CaptchaBuilder;
$builder->build();
$_SESSION['captcha'] = $builder->getPhrase();

// Redirect if already logged in
if (isset($_SESSION['nim'])) {
    header("Location: ../Mahasiswa/dashboard.php");
    exit;
}

// Check remember me cookie
if (isset($_COOKIE['remember_token']) && isset($_COOKIE['nim'])) {
    $user = verifyRememberToken($_COOKIE['nim'], $_COOKIE['remember_token']);
    
    if ($user) {
        $_SESSION['nim'] = $user['NIM'];
        $_SESSION['nama'] = $user['NAMA'];
        header("Location: login.php");
        exit;
    }
}

$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'login':
                // Verifikasi CAPTCHA
                if (!isset($_POST['captcha']) || !isset($_SESSION['captcha']) || 
                    strtolower($_POST['captcha']) !== strtolower($_SESSION['captcha'])) {
                    $error = "Kode CAPTCHA tidak sesuai";
                    break;
                }

                $nim = $_POST['nim'];
                $password = $_POST['password'];
                $remember = isset($_POST['remember']) ? true : false;
                
                if (!checkLoginAttempts($nim)) {
                    $error = "Terlalu banyak percobaan login. Silakan coba lagi setelah 30 menit.";
                    break;
                }
                
                $user = verifyLogin($nim, $password);
                
                if ($user) {
                    $_SESSION['nim'] = $user['NIM'];
                    $_SESSION['nama'] = $user['NAMA'];
                    
                    if ($remember) {
                        $token = setRememberToken($nim);
                        setcookie('remember_token', $token, time() + (86400 * 30), "/", "", true, true);
                        setcookie('nim', $nim, time() + (86400 * 30), "/", "", true, true);
                    }
                    
                    updateLoginAttempts($nim, true);
                    header("Location: ../Mahasiswa/dashboard.php");
                    exit;
                } else {
                    updateLoginAttempts($nim);
                    $error = "NIM atau Password salah!";
                }
                break;
                
            case 'reset':
                // ... rest of your reset code ...
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kompensasi Mahasiswa</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full bg-white p-8 rounded-xl shadow-lg grid grid-cols-2 gap-8">
            <div class="p-8">
                <div class="flex items-center justify-center mb-4">
                    <img src="../images/logo_pnj.png" alt="Logo Kampus" class="h-40 mr-1">
                    <h2 class="text-3xl font-extrabold text-gray-900">Login Mahasiswa</h2>
                </div>

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

                <form class="mb-4 space-y-6" method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    <div class="rounded-md shadow-sm -space-y-px">
                        <div>
                            <label for="nim" class="sr-only">NIM</label>
                            <input id="nim" name="nim" type="text" required 
                                class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                                placeholder="NIM">
                        </div>
                        <div>
                            <label for="password" class="sr-only">Password</label>
                            <input id="password" name="password" type="password" required 
                                class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                                placeholder="Password">
                        </div>
                        <!-- CAPTCHA Section -->
                        <div class="border border-gray-300 p-3 rounded-b-md bg-gray-50">
                            <label for="captcha" class="block text-sm font-medium text-gray-700 mb-1">
                                CAPTCHA
                            </label>
                            <div class="flex items-center space-x-2">
                                <div class="bg-white p-1 rounded">
                                    <img src="../generate_captcha.php" alt="CAPTCHA" id="captcha-image" class="h-8">
                                </div>
                                <button type="button" onclick="refreshCaptcha()" 
                                        class="text-gray-400 hover:text-gray-500">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <input type="text" id="captcha" name="captcha" required
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   placeholder="Masukkan CAPTCHA">
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox" 
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-900">
                                Ingat saya
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="forgot_password.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                                Lupa password?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt text-indigo-500 group-hover:text-indigo-400"></i>
                            </span>
                            Login
                        </button>
                    </div>
                </form>

                <p class="mt-2 text-center text-sm text-gray-600">
                    Belum punya akun?
                    <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Daftar disini
                    </a>
                </p>
            </div>
            <div class="flex items-center justify-center">
                <img src="../images/login_mhs.png" alt="Student Compensation">
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    function refreshCaptcha() {
        document.getElementById('captcha-image').src = 'generate_captcha.php?' + Date.now();
    }
    </script>
</body>
</html>