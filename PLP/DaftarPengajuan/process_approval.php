<?php
require_once(__DIR__ . '/../../Config.php');
require_once(__DIR__ . '/../../admin/mahasiswa/email_config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

function sendApprovalEmail($mahasiswaEmail, $mahasiswaNama, $idPengajuan, $kodeKegiatan, $plpNama) {
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
        $mail->Subject = 'Pengajuan Tahap 2 (PLP) Telah Disetujui - SIKOMPEN';
        
        // Email body
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #008751;'>Pemberitahuan Persetujuan Pengajuan Tahap 2</h2>
                
                <p>Halo <strong>{$mahasiswaNama}</strong>,</p>
                
                <p>Pengajuan Anda telah disetujui oleh PLP dengan detail sebagai berikut:</p>
                
                <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>ID Pengajuan:</strong> {$idPengajuan}</p>
                    <p><strong>Kode Kegiatan:</strong> {$kodeKegiatan}</p>
                    <p><strong>Status:</strong> Disetujui oleh PLP ({$plpNama})</p>
                </div>

                <p>Pengajuan Anda akan diteruskan ke Kepala Laboratorium untuk persetujuan final.</p>
                
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pengajuan_id'])) {
    try {
        $pdo = getDB();
        $pengajuan_id = $_POST['pengajuan_id'];
        $userNama = $_SESSION['nama_user'];

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Get mahasiswa info before updating
            $stmt = $pdo->prepare("
                SELECT m.email, m.nama, p.kode_kegiatan 
                FROM tbl_pengajuan p
                JOIN tbl_mahasiswa m ON p.kode_user = m.nim
                WHERE p.id_pengajuan = :id
            ");
            $stmt->execute(['id' => $pengajuan_id]);
            $mahasiswaInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            // Prepare statement untuk single approval
            $stmt = $pdo->prepare("UPDATE tbl_pengajuan 
                                 SET status_approval2 = 'Approved',
                                     keterangan_approval2 = 'Disetujui oleh PLP',
                                     approval2_by = :user_nama,
                                     updated_at = CURRENT_TIMESTAMP 
                                 WHERE id_pengajuan = :id 
                                 AND status_approval1 = 'Approved'
                                 AND (status_approval2 IS NULL OR status_approval2 = 'Pending')");

            $stmt->bindValue(':id', $pengajuan_id);
            $stmt->bindValue(':user_nama', $userNama);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // Send email notification
                if ($mahasiswaInfo && sendApprovalEmail(
                    $mahasiswaInfo['EMAIL'],
                    $mahasiswaInfo['NAMA'],
                    $pengajuan_id,
                    $mahasiswaInfo['KODE_KEGIATAN'],
                    $userNama
                )) {
                    $_SESSION['success_message'] = "Berhasil menyetujui pengajuan dan mengirim email notifikasi.";
                    $pdo->commit();
                } else {
                    throw new Exception("Berhasil menyetujui pengajuan tetapi gagal mengirim email notifikasi.");
                }
            } else {
                throw new Exception("Gagal menyetujui pengajuan.");
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
    }
}

// Redirect kembali ke halaman utama
header('Location: DaftarPengajuanPLP.php');
exit();
?>