<?php
// Path: /vendor/Admin/Mahasiswa/send_notification.php
session_start();
require_once('../../Config.php');
require_once('email_config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getBaseUrl() {
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https://';
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    }
    
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    
    // Bersihkan path untuk mendapatkan root folder aplikasi
    $baseDir = dirname(dirname(dirname($script))); // Naik 3 level dari current script
    $baseDir = str_replace('\\', '/', $baseDir);
    $baseDir = rtrim($baseDir, '/');
    
    return $protocol . $host . $baseDir;
}

$appUrl = getBaseUrl() . '/sikompen/loginmhs/login.php';

try {
    $db = getDB();
    
    // Gunakan query original tanpa password
    $query = "SELECT ID_MHS, NIM, NAMA, EMAIL, KELAS, PRODI, SEMESTER, JUMLAH_TERLAMBAT, JUMLAH_ALFA 
             FROM TBL_MAHASISWA 
             WHERE EMAIL IS NOT NULL";
    
    $stmt = $db->query($query);
    $mahasiswaList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($mahasiswaList)) {
        $_SESSION['warning_message'] = "Tidak ada data mahasiswa dengan email yang ditemukan.";
        header('Location: Mahasiswa.php');
        exit();
    }

    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $successCount = 0;
    $errorCount = 0;
    $errorMessages = [];

    foreach ($mahasiswaList as $mahasiswa) {
        try {
            $mail->clearAddresses();
            $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
            $mail->addAddress($mahasiswa['EMAIL'], $mahasiswa['NAMA']);

            $mail->isHTML(true);
            $mail->Subject = 'Informasi Akun dan Keterlambatan - Sikompen PNJ';

            // Hitung keterlambatan
            $terlambat = $mahasiswa['JUMLAH_TERLAMBAT'] * 2;
            $alfa = $mahasiswa['JUMLAH_ALFA'] * 60;
            $total = $terlambat + $alfa;

            // Template email - gunakan NIM sebagai password
            $nim = $mahasiswa['NIM']; // Password sama dengan NIM

            

            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #10B981;'>Informasi Akun dan Keterlambatan Mahasiswa</h2>
                
                <!-- Informasi Login -->
                <div style='background-color: #f0fdf4; padding: 15px; border-radius: 5px; margin: 15px 0; border: 1px solid #10B981;'>
                    <h3 style='color: #10B981; margin-top: 0;'>Informasi Akun Sikompen</h3>
                    <p><strong>Username:</strong> {$nim}</p>
                    <p><strong>Password:</strong> {$nim}</p>
                    <p style='font-size: 0.9em; color: #374151;'>
                        Silakan login di <a href='{$appUrl}' style='color: #10B981; text-decoration: none;'>{$appUrl}</a>
                    </p>
                </div>
                
                <!-- Data Mahasiswa -->
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; border: 1px solid #e5e7eb;'>
                    <h3 style='color: #374151; margin-top: 0;'>Data Mahasiswa</h3>
                    <p><strong>Nama:</strong> {$mahasiswa['NAMA']}</p>
                    <p><strong>NIM:</strong> {$nim}</p>
                    <p><strong>Kelas:</strong> {$mahasiswa['KELAS']}</p>
                    <p><strong>Program Studi:</strong> {$mahasiswa['PRODI']}</p>
                    <p><strong>Semester:</strong> {$mahasiswa['SEMESTER']}</p>
                </div>

                <!-- Rekap Keterlambatan -->
                <div style='background-color: #fee2e2; padding: 15px; border-radius: 5px; margin: 15px 0; border: 1px solid #dc2626;'>
                    <h3 style='color: #dc2626; margin-top: 0;'>Rekap Keterlambatan:</h3>
                    <p><strong>Keterlambatan:</strong> {$terlambat} menit</p>
                    <p><strong>Alfa:</strong> {$alfa} menit</p>
                    <p><strong>Total:</strong> {$total} menit</p>
                </div>

                <div style='text-align: center; margin-top: 20px; padding: 15px; background-color: #f9fafb; border-radius: 5px;'>
                    <p style='color: #6b7280; font-size: 0.9em; margin: 0;'>
                        Email ini dikirim secara otomatis oleh sistem Sikompen PNJ.<br>
                        Jika membutuhkan bantuan, silakan hubungi admin. No telp: 0859-6065-2905 (Hafiz Rizqi Secario)
                    </p>
                </div>
            </div>
            ";

            $mail->Body = $body;
            $mail->send();
            $successCount++;

        } catch (Exception $e) {
            $errorCount++;
            $errorMessages[] = "Gagal mengirim ke {$mahasiswa['EMAIL']}: {$mail->ErrorInfo}";
            error_log("Email error: " . $mail->ErrorInfo);
        }
    }

    // Set pesan hasil
    if ($successCount > 0) {
        $_SESSION['success_message'] = "Berhasil mengirim email ke $successCount mahasiswa.";
    }
    if ($errorCount > 0) {
        $_SESSION['error_message'] = "Gagal mengirim ke $errorCount email.\n" . implode("\n", $errorMessages);
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

header('Location: Mahasiswa.php');
exit();
?>