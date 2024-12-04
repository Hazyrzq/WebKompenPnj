<?php
// verify_payment.php
require_once '../config/koneksi.php';
require_once 'payment_receipt.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Debug: Log incoming request data
    error_log('Received POST data: ' . print_r($_POST, true));

    // Get payment ID and status, with better validation
    $paymentId = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : null;

    // Validate input more strictly
    if (empty($paymentId)) {
        throw new Exception('Payment ID is required');
    }

    if (empty($status) || !in_array($status, ['success', 'failed'])) {
        throw new Exception('Invalid payment status');
    }

    $pdo = connectDB();
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get payment details with additional validation
    $stmt = $pdo->prepare("
        SELECT p.PAYMENT_ID, p.STATUS, p.NIM, p.ORDER_ID, p.AMOUNT 
        FROM TBL_PAYMENTS p
        WHERE p.PAYMENT_ID = :payment_id
        FOR UPDATE
    ");
    $stmt->execute(['payment_id' => $paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception('Payment not found: ' . $paymentId);
    }
    
    if ($payment['STATUS'] !== 'pending') {
        throw new Exception('Payment cannot be verified: current status is ' . $payment['STATUS']);
    }
    
    // Update payment status with additional fields
    $stmt = $pdo->prepare("
        UPDATE TBL_PAYMENTS 
        SET STATUS = :status,
            VERIFIED_AT = CURRENT_TIMESTAMP,
            SETTLEMENT_TIME = CASE 
                WHEN :status = 'success' THEN CURRENT_TIMESTAMP 
                ELSE NULL 
            END,
            PAYMENT_METHOD = COALESCE(PAYMENT_METHOD, 'Manual'),
            PAYMENT_CHANNEL = COALESCE(PAYMENT_CHANNEL, 'Admin')
        WHERE PAYMENT_ID = :payment_id
    ");
    
    $stmt->execute([
        'status' => $status,
        'payment_id' => $paymentId
    ]);

    // Verify the update was successful
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to update payment status');
    }
    
    // Create verification log with more detailed message
    $stmt = $pdo->prepare("
        INSERT INTO TBL_PAYMENT_LOGS (
            log_id,
            payment_id,
            status,
            message,
            verified_by,
            created_at
        ) VALUES (
            seq_payment_logs_id.NEXTVAL,
            :payment_id,
            :status,
            :message,
            :verified_by,
            CURRENT_TIMESTAMP
        )
    ");
    
    $message = $status === 'success' 
        ? sprintf('Payment verified successfully (Order ID: %s, Amount: %s)', $payment['ORDER_ID'], $payment['AMOUNT'])
        : sprintf('Payment verification failed (Order ID: %s)', $payment['ORDER_ID']);

    $stmt->execute([
        'payment_id' => $paymentId,
        'status' => $status,
        'message' => $message,
        'verified_by' => 'ADMIN'
    ]);
    
    // If payment is successful, generate receipt
    if ($status === 'success') {
        try {
            generatePaymentReceipt($paymentId);
        } catch (Exception $e) {
            error_log('Error generating receipt: ' . $e->getMessage());
            // Don't throw the exception - we still want to complete the verification
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response with additional data
    echo json_encode([
        'success' => true,
        'message' => $status === 'success' 
            ? 'Pembayaran berhasil diverifikasi' 
            : 'Pembayaran ditandai sebagai gagal',
        'payment_id' => $paymentId,
        'status' => $status,
        'order_id' => $payment['ORDER_ID']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log('Payment verification error: ' . $e->getMessage());
    
    // Return error response without debug trace
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}