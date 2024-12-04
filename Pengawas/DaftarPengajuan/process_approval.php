<?php
require_once(__DIR__ . '/../../Config.php');
require_once(__DIR__ . '/../../Admin/mahasiswa/email_config.php');

// Check session
session_start();
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['role']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

function sendApprovalEmail($mahasiswaEmail, $mahasiswaNama, $idPengajuan, $kodeKegiatan) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->Port = SMTP_PORT;
        
        // Set sender
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        
        // Add recipient
        $mail->addAddress($mahasiswaEmail, $mahasiswaNama);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Notifikasi Persetujuan Pengajuan - SIKOMPEN';
        
        // Create email body
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #008751;'>Pemberitahuan Persetujuan Pengajuan</h2>
                <p>Halo <strong>{$mahasiswaNama}</strong>,</p>
                
                <p>Pengajuan Anda telah disetujui oleh Pengawas dengan detail sebagai berikut:</p>
                
                <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>ID Pengajuan:</strong> {$idPengajuan}</p>
                    <p><strong>Kode Kegiatan:</strong> {$kodeKegiatan}</p>
                    <p><strong>Status:</strong> Disetujui oleh Pengawas</p>
                </div>

                <p>Pengajuan Anda akan diteruskan ke PLP untuk persetujuan selanjutnya.</p>
                
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

try {
    $pdo = getDB();
    
    // Function to get mahasiswa email
    function getMahasiswaInfo($pdo, $idPengajuan) {
        $stmt = $pdo->prepare("
            SELECT m.email, m.nama, p.kode_kegiatan 
            FROM tbl_pengajuan p
            JOIN tbl_mahasiswa m ON p.kode_user = m.nim
            WHERE p.id_pengajuan = :id
        ");
        $stmt->execute(['id' => $idPengajuan]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Process single approval
    if (isset($_POST['pengajuan_id'])) {
        $id = $_POST['pengajuan_id'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("UPDATE tbl_pengajuan 
                                 SET status_approval1 = 'Approved', 
                                     updated_at = CURRENT_TIMESTAMP 
                                 WHERE id_pengajuan = :id 
                                 AND status_approval1 <> 'Approved'");

            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() > 0) {
                // Get mahasiswa info for email
                $mahasiswaInfo = getMahasiswaInfo($pdo, $id);
                
                if ($mahasiswaInfo && sendApprovalEmail(
                    $mahasiswaInfo['EMAIL'],
                    $mahasiswaInfo['NAMA'],
                    $id,
                    $mahasiswaInfo['KODE_KEGIATAN']
                )) {
                    $pdo->commit();
                    $_SESSION['success_message'] = "Pengajuan berhasil disetujui dan email notifikasi telah dikirim.";
                } else {
                    throw new Exception("Gagal mengirim email notifikasi.");
                }
            } else {
                throw new Exception("Tidak dapat menyetujui pengajuan.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
    // Process multiple approval
    elseif (isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
        $pdo->beginTransaction();
        
        try {
            $success_count = 0;
            $email_success = 0;
            $selected_ids = $_POST['selected_ids'];

            $stmt = $pdo->prepare("UPDATE tbl_pengajuan 
                                 SET status_approval1 = 'Approved', 
                                     updated_at = CURRENT_TIMESTAMP 
                                 WHERE id_pengajuan = :id 
                                 AND status_approval1 <> 'Approved'");

            foreach ($selected_ids as $id) {
                $stmt->bindValue(':id', $id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $success_count++;
                    
                    // Send email for each approved submission
                    $mahasiswaInfo = getMahasiswaInfo($pdo, $id);
                    if ($mahasiswaInfo && sendApprovalEmail(
                        $mahasiswaInfo['EMAIL'],
                        $mahasiswaInfo['NAMA'],
                        $id,
                        $mahasiswaInfo['KODE_KEGIATAN']
                    )) {
                        $email_success++;
                    }
                }
            }

            if ($success_count > 0) {
                $pdo->commit();
                $_SESSION['success_message'] = "Berhasil menyetujui $success_count pengajuan dan mengirim $email_success email notifikasi.";
            } else {
                throw new Exception("Tidak ada pengajuan yang dapat disetujui.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Silahkan pilih pengajuan yang akan disetujui.";
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
}

// Redirect back to main page
header('Location: DaftarPengajuanPengawas.php');
exit();
?>