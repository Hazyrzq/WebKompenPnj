<?php
// First, let's fix the cancel payment functionality
// cancel_payment.php
require_once '../config/koneksi.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    if (empty($_POST['payment_id'])) {
        throw new Exception('Payment ID is required');
    }

    $pdo = connectDB();
    $payment_id = $_POST['payment_id'];
    
    $pdo->beginTransaction();
    
    // Verify payment exists and is pending
    $stmt = $pdo->prepare("
        SELECT PAYMENT_ID, STATUS 
        FROM TBL_PAYMENTS 
        WHERE PAYMENT_ID = :payment_id
        FOR UPDATE
    ");
    $stmt->execute(['payment_id' => $payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception('Payment not found');
    }
    
    if ($payment['STATUS'] !== 'pending') {
        throw new Exception('Only pending payments can be cancelled');
    }
    
    // Update payment status to canceled
    $stmt = $pdo->prepare("
        UPDATE TBL_PAYMENTS 
        SET STATUS = 'canceled',
            VERIFIED_AT = CURRENT_TIMESTAMP
        WHERE PAYMENT_ID = :payment_id
    ");
    $stmt->execute(['payment_id' => $payment_id]);
    
    // Create log entry
    $stmt = $pdo->prepare("
        INSERT INTO TBL_PAYMENT_LOGS (
            log_id, payment_id, status, message, verified_by, created_at
        ) VALUES (
            seq_payment_logs_id.NEXTVAL,
            :payment_id,
            'canceled',
            'Payment cancelled by user',
            'USER',
            CURRENT_TIMESTAMP
        )
    ");
    $stmt->execute(['payment_id' => $payment_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pembayaran berhasil dibatalkan'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}