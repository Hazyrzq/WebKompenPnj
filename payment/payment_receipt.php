<?php
require_once '../config/koneksi.php';
require_once '../vendor/autoload.php';

function generatePaymentReceipt($orderId) {
    try {
        $pdo = connectDB();
        
        // Get payment data
        $stmt = $pdo->prepare("
            SELECT p.*, m.NAMA, m.KELAS, m.PRODI, m.SEMESTER, m.TOTAL as TOTAL_KOMPEN,
                   TO_CHAR(p.CREATED_AT, 'DD/MM/YYYY HH24:MI') as PAYMENT_DATE,
                   TO_CHAR(p.VERIFIED_AT, 'DD/MM/YYYY HH24:MI') as VERIFIED_DATE
            FROM TBL_PAYMENTS p 
            INNER JOIN TBL_MAHASISWA m ON p.NIM = m.NIM 
            WHERE p.ORDER_ID = :order_id
        ");
        $stmt->execute(['order_id' => $orderId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            throw new Exception("Payment data not found");
        }

        // Initialize PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Sistem Kompen PNJ');
        $pdf->SetAuthor('Politeknik Negeri Jakarta');
        $pdf->SetTitle('Bukti Pembayaran Kompen - ' . $data['NIM']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 10, 15);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        // Header with logo
        $logoPath = '..\images\Logo_PNJ.png';
        $header = '
        <table cellpadding="2" cellspacing="0" style="width: 100%; border: 1px solid black;">
            <tr>
                <td style="width: 15%; text-align: center; border-right: 1px solid black;">
                    <img src="' . $logoPath . '" style="width: 60px; height: 60px;">
                </td>
                <td style="width: 70%; text-align: center;">
                    <span style="font-size: 14pt; font-weight: bold;">POLITEKNIK NEGERI JAKARTA</span><br>
                    <span style="font-size: 11pt;">JURUSAN TEKNIK INFORMATIKA DAN KOMPUTER</span><br>
                    <span style="font-size: 12pt; font-weight: bold;">BUKTI PEMBAYARAN KOMPENSASI</span>
                </td>
                <td style="width: 15%; text-align: center; border-left: 1px solid black;">
                    <span style="font-size: 14pt; font-weight: bold;">K-02</span>
                </td>
            </tr>
        </table>';
        
        $pdf->writeHTML($header, true, false, true, false, '');
        $pdf->Ln(5);

        // Student Information
        $studentInfo = '
        <table cellpadding="3" cellspacing="0" border="1" style="width: 100%;">
            <tr bgcolor="#f2f2f2">
                <td colspan="2" style="font-weight: bold;">INFORMASI MAHASISWA</td>
            </tr>
            <tr>
                <td width="30%">NIM</td>
                <td width="70%">' . htmlspecialchars($data['NIM']) . '</td>
            </tr>
            <tr>
                <td>Nama Lengkap</td>
                <td>' . htmlspecialchars(strtoupper($data['NAMA'])) . '</td>
            </tr>
            <tr>
                <td>Program Studi</td>
                <td>' . htmlspecialchars($data['PRODI']) . '</td>
            </tr>
            <tr>
                <td>Kelas</td>
                <td>' . htmlspecialchars($data['KELAS']) . '</td>
            </tr>
            <tr>
                <td>Semester</td>
                <td>' . htmlspecialchars($data['SEMESTER']) . '</td>
            </tr>
            <tr>
                <td>Total Kompen</td>
                <td>' . number_format($data['TOTAL_KOMPEN'], 0, ',', '.') . ' menit</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($studentInfo, true, false, true, false, '');
        $pdf->Ln(3);

        // Payment Information
        $paymentInfo = '
        <table cellpadding="3" cellspacing="0" border="1" style="width: 100%;">
            <tr bgcolor="#f2f2f2">
                <td colspan="2" style="font-weight: bold;">DETAIL PEMBAYARAN</td>
            </tr>
            <tr>
                <td>Payment ID</td>
                <td>' . htmlspecialchars($data['PAYMENT_ID']) . '</td>
            </tr>
            <tr>
                <td>Jumlah Pembayaran</td>
                <td>Rp ' . number_format($data['AMOUNT'], 0, ',', '.') . '</td>
            </tr>
            <tr>
                <td>Metode Pembayaran</td>
                <td>' . htmlspecialchars(strtoupper($data['PAYMENT_METHOD'] ?? 'MANUAL')) . '</td>
            </tr>
            <tr>
                <td>Channel Pembayaran</td>
                <td>' . htmlspecialchars(strtoupper($data['PAYMENT_CHANNEL'] ?? '-')) . '</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>' . htmlspecialchars(strtoupper($data['STATUS'])) . '</td>
            </tr>
            <tr>
                <td>Tanggal Pembayaran</td>
                <td>' . htmlspecialchars($data['PAYMENT_DATE']) . '</td>
            </tr>
            <tr>
                <td>Tanggal Verifikasi</td>
                <td>' . htmlspecialchars($data['VERIFIED_DATE']) . '</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($paymentInfo, true, false, true, false, '');
        $pdf->Ln(3);

        // Prepare QR Code content
        $qrContent = "ORDER_ID: {$data['ORDER_ID']}\n";
        $qrContent .= "NIM: {$data['NIM']}\n";
        $qrContent .= "AMOUNT: Rp " . number_format($data['AMOUNT'], 0, ',', '.') . "\n";
        $qrContent .= "DATE: {$data['VERIFIED_DATE']}\n";
        $qrContent .= "STATUS: {$data['STATUS']}";

        // Verification note with QR Code
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->writeHTML('
        <table cellpadding="3" style="width: 100%;">
            <tr>
                <td style="width: 70%;">
                    <i>Catatan:</i><br>
                    1. Dokumen ini adalah bukti pembayaran yang sah dan telah terverifikasi secara elektronik<br>
                    2. Pembayaran yang sudah dilakukan tidak dapat dikembalikan<br>
                    3. Simpan bukti pembayaran ini sebagai arsip
                </td>
                <td style="width: 30%; text-align: right;">
                    ' . $pdf->write2DBarcode($qrContent, 'QRCODE,L', '', '', 30, 30, ['border' => false], 'N') . '
                </td>
            </tr>
        </table>');

        // Get PDF content
        $pdfContent = $pdf->Output('', 'S');
        $filename = 'BuktiPembayaran_' . $data['NAMA'] . '_' . $data['NIM'] . '.pdf';
        $fileSize = strlen($pdfContent);

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Get next report ID
            $stmt = $pdo->query("SELECT seq_payment_reports_id.NEXTVAL as next_id FROM DUAL");
            $sequence = $stmt->fetch(PDO::FETCH_ASSOC);
            $reportId = $sequence['NEXT_ID'];

            // Prepare SQL with RETURNING clause for BLOB
            $sql = "INSERT INTO TBL_PAYMENT_REPORTS (
                REPORT_ID,
                PAYMENT_ID,
                REPORT_FILE,
                FILENAME,
                FILE_SIZE,
                FILE_TYPE,
                CREATED_AT
            ) VALUES (
                :report_id,
                :payment_id,
                EMPTY_BLOB(),
                :filename,
                :file_size,
                'application/pdf',
                CURRENT_TIMESTAMP
            ) RETURNING REPORT_FILE INTO :blob_data";

            $stmt = $pdo->prepare($sql);

            // Create temporary stream
            $blob = fopen('php://memory', 'r+');
            fwrite($blob, $pdfContent);
            rewind($blob);

            // Bind parameters
            $stmt->bindParam(':report_id', $reportId);
            $stmt->bindParam(':payment_id', $data['PAYMENT_ID']);
            $stmt->bindParam(':filename', $filename);
            $stmt->bindParam(':file_size', $fileSize);
            $stmt->bindParam(':blob_data', $blob, PDO::PARAM_LOB);

            // Execute and handle BLOB
            $stmt->execute();
            fclose($blob);

            // Add log entry
            $logSql = "INSERT INTO TBL_PAYMENT_LOGS (
                log_id,
                payment_id,
                status,
                message,
                verified_by,
                created_at
            ) VALUES (
                seq_payment_logs_id.NEXTVAL,
                :payment_id,
                'receipt_generated',
                'Bukti pembayaran berhasil dibuat',
                'SYSTEM',
                CURRENT_TIMESTAMP
            )";
            
            $stmt = $pdo->prepare($logSql);
            $stmt->execute(['payment_id' => $data['PAYMENT_ID']]);
            
            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Transaction error: " . $e->getMessage());
            throw new Exception("Database error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Receipt generation error: " . $e->getMessage());
        throw new Exception("Gagal membuat bukti pembayaran: " . $e->getMessage());
    }
}