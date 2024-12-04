<?php
require_once '../config/koneksi.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_id']) || !isset($input['payment_type'])) {
        throw new Exception('Missing required parameters');
    }

    $pdo = connectDB();
    
    // First, check if the payment exists
    $stmt = $pdo->prepare("
        SELECT PAYMENT_ID 
        FROM TBL_PAYMENTS 
        WHERE ORDER_ID = ?
    ");
    $stmt->execute([$input['order_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Payment not found');
    }
    
    // Now update the payment method
    $stmt = $pdo->prepare("
        UPDATE TBL_PAYMENTS 
        SET 
            PAYMENT_METHOD = ?,
            PAYMENT_CHANNEL = ?
        WHERE ORDER_ID = ?
    ");
    
    $stmt->execute([
        $input['payment_type'],
        $input['payment_channel'] ?? $input['payment_type'],
        $input['order_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment method updated'
    ]);

} catch (Exception $e) {
    error_log('Payment method update error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}