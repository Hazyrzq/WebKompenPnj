<?php
require_once(__DIR__ . '/../../Config.php');
require_once(__DIR__ . '/../../admin/mahasiswa/email_config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['role']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

function sendApprovalEmail($mahasiswaEmail, $mahasiswaNama, $idPengajuan, $kodeKegiatan, $kalabNama) {
    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($mahasiswaEmail, $mahasiswaNama);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Pengajuan Final (Kalab) Telah Disetujui - SIKOMPEN';
        
        // Email body
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #008751;'>Pemberitahuan Persetujuan Final Pengajuan</h2>
                
                <p>Halo <strong>{$mahasiswaNama}</strong>,</p>
                
                <p>Selamat! Pengajuan Anda telah mendapatkan persetujuan final dari Kepala Laboratorium.</p>
                
                <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>ID Pengajuan:</strong> {$idPengajuan}</p>
                    <p><strong>Kode Kegiatan:</strong> {$kodeKegiatan}</p>
                    <p><strong>Status:</strong> Disetujui oleh Kepala Laboratorium ({$kalabNama})</p>
                    <p><strong>Status Final:</strong> DISETUJUI</p>
                </div>

                <p style='background-color: #e8f5e9; padding: 10px; border-radius: 5px;'>
                    Pengajuan Anda telah selesai dan disetujui oleh semua pihak. 
                    Anda dapat melanjutkan dengan pelaksanaan kegiatan sesuai dengan pengajuan yang telah disetujui.
                </p>
                
                <p>Terima kasih telah menggunakan sistem SIKOMPEN.</p>
                
                <hr style='border: 1px solid #eee; margin: 20px 0;'>
                
                <p style='color: #666; font-size: 12px;'>
                    Email ini dikirim secara otomatis oleh sistem SIKOMPEN. 
                    Mohon tidak membalas email ini.
                </p>
            </div>
        </body>
        </html>";

        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send email: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
    try {
        $pdo = getDB();
        $selected_ids = $_POST['selected_ids'];
        $userNama = $_SESSION['nama_user'];

        // Begin transaction
        $pdo->beginTransaction();

        try {
            $success_count = 0;
            $email_success = 0;

            // Get mahasiswa info before updating
            $stmt = $pdo->prepare("
                SELECT p.id_pengajuan, m.email, m.nama, p.kode_kegiatan 
                FROM tbl_pengajuan p
                JOIN tbl_mahasiswa m ON p.kode_user = m.nim
                WHERE p.id_pengajuan = :id
            ");

            // Prepare statement untuk approval 3
            $updateStmt = $pdo->prepare("UPDATE tbl_pengajuan 
                                     SET status_approval3 = 'Approved',
                                         keterangan_approval3 = 'Disetujui oleh Kalab',
                                         approval3_by = :user_nama,
                                         updated_at = CURRENT_TIMESTAMP 
                                     WHERE id_pengajuan = :id 
                                     AND status_approval1 = 'Approved'
                                     AND status_approval2 = 'Approved'
                                     AND (status_approval3 IS NULL OR status_approval3 = 'Pending')");

            foreach ($selected_ids as $id) {
                // Get mahasiswa info first
                $stmt->execute(['id' => $id]);
                $mahasiswaInfo = $stmt->fetch(PDO::FETCH_ASSOC);

                // Update approval status
                $updateStmt->bindValue(':id', $id);
                $updateStmt->bindValue(':user_nama', $userNama);
                $updateStmt->execute();

                if ($updateStmt->rowCount() > 0) {
                    $success_count++;

                    // Send email if update was successful
                    if ($mahasiswaInfo && sendApprovalEmail(
                        $mahasiswaInfo['EMAIL'],
                        $mahasiswaInfo['NAMA'],
                        $mahasiswaInfo['ID_PENGAJUAN'],
                        $mahasiswaInfo['KODE_KEGIATAN'],
                        $userNama
                    )) {
                        $email_success++;
                    }
                }
            }

            if ($success_count > 0) {
                $pdo->commit();
                $_SESSION['success_message'] = "Berhasil menyetujui $success_count pengajuan dan mengirim $email_success email notifikasi final.";
            } else {
                throw new Exception("Tidak ada pengajuan yang dapat disetujui.");
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Terjadi kesalahan saat memproses data: " . $e->getMessage();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
    }
}

// Redirect back to the main page
header('Location: DaftarPengajuanKalab.php');
exit();
?>