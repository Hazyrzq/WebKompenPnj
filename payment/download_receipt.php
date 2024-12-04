<?php
require_once '../config/koneksi.php';
require_once 'payment_receipt_helper.php';

try {
    if (empty($_GET['payment_id'])) {
        throw new Exception('Payment ID is required');
    }

    $paymentId = $_GET['payment_id'];
    $pdo = connectDB();

    // Get payment data with proper BLOB handling
    $stmt = $pdo->prepare("
        SELECT p.STATUS, pr.REPORT_FILE, pr.FILENAME, pr.FILE_SIZE 
        FROM TBL_PAYMENTS p
        LEFT JOIN TBL_PAYMENT_REPORTS pr ON p.PAYMENT_ID = pr.PAYMENT_ID
        WHERE p.PAYMENT_ID = :payment_id
    ");
    
    // Execute with explicit fetch mode for LOB handling
    $stmt->bindParam(':payment_id', $paymentId);
    $stmt->execute();
    
    // Set fetch mode for proper BLOB handling
    $stmt->bindColumn('REPORT_FILE', $blobData, PDO::PARAM_LOB);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    if ($payment['STATUS'] !== 'success') {
        throw new Exception('Receipt only available for successful payments');
    }

    // Ensure receipt exists
    if (empty($blobData)) {
        ensureReceiptExists($paymentId, $pdo);
        
        // Fetch again after generation
        $stmt->execute(['payment_id' => $paymentId]);
        $stmt->bindColumn('REPORT_FILE', $blobData, PDO::PARAM_LOB);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment || empty($blobData)) {
            throw new Exception('Failed to generate receipt');
        }
    }

    // Get BLOB content
    $pdfContent = stream_get_contents($blobData);

    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $payment['FILENAME'] . '"');
    header('Content-Length: ' . $payment['FILE_SIZE']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    // Output PDF content
    echo $pdfContent;
    exit;

} catch (Exception $e) {
    error_log("Receipt download error: " . $e->getMessage());
    header("HTTP/1.1 404 Not Found");
    echo "Error: " . $e->getMessage();
}