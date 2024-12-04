<?php
// notification_handler.php
require_once __DIR__ . '..\..\vendor\autoload.php';
require_once '/config/koneksi.php';

class PaymentNotificationHandler {
    private $pdo;
    private $notification;
    
    public function __construct() {
        $this->pdo = connectDB();
        \Midtrans\Config::$serverKey = 'SB-Mid-server-WURwTWswCZ2VeCzwCj5bHUGv';
        \Midtrans\Config::$isProduction = false;
    }
    
    public function handle() {
        try {
            // Log raw notification
            $rawInput = file_get_contents('php://input');
            error_log('Raw Midtrans Notification: ' . $rawInput);
            
            $this->notification = new \Midtrans\Notification();
            
            // Extract payment details
            $orderId = $this->notification->order_id;
            $transactionStatus = $this->notification->transaction_status;
            $paymentType = $this->notification->payment_type;
            $fraudStatus = $this->notification->fraud_status;
            
            // Get payment channel
            $paymentChannel = $this->determinePaymentChannel();
            
            // Begin transaction
            $this->pdo->beginTransaction();
            
            try {
                // Lock and get payment record
                $stmt = $this->pdo->prepare("
                    SELECT payment_id, status, amount 
                    FROM TBL_PAYMENTS 
                    WHERE order_id = :order_id 
                    FOR UPDATE
                ");
                $stmt->execute(['order_id' => $orderId]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$payment) {
                    throw new Exception('Payment not found: ' . $orderId);
                }
                
                // Map status
                $status = $this->mapTransactionStatus($transactionStatus, $fraudStatus);
                
                // Update payment status
                $this->updatePaymentStatus($payment['payment_id'], $status, $paymentType, $paymentChannel);
                
                // Create transaction record
                $this->createTransactionRecord($payment, $status, $paymentType, $paymentChannel);
                
                // Create log entry
                $this->createLogEntry($payment['payment_id'], $status, $paymentType, $paymentChannel);
                
                // Generate receipt for successful payments
                if ($status === 'success') {
                    require_once 'payment_receipt.php';
                    generatePaymentReceipt($orderId);
                }
                
                $this->pdo->commit();
                
                return [
                    'success' => true,
                    'message' => 'Payment notification processed successfully'
                ];
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log('Payment notification error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function determinePaymentChannel() {
        $paymentType = $this->notification->payment_type;
        
        // Check for bank transfer
        if (isset($this->notification->va_numbers[0]->bank)) {
            return strtolower($this->notification->va_numbers[0]->bank);
        }
        
        // Check for e-wallet
        if (in_array($paymentType, ['gopay', 'shopeepay'])) {
            return isset($this->notification->payment_option_type) && 
                   $this->notification->payment_option_type === 'QRIS' ? 'qris' : $paymentType;
        }
        
        // Check for convenience store
        if ($paymentType === 'cstore' && isset($this->notification->store)) {
            return strtolower($this->notification->store);
        }
        
        // Default to payment type if no specific channel found
        return $paymentType;
    }
    
    private function mapTransactionStatus($transactionStatus, $fraudStatus) {
        if ($transactionStatus == 'capture') {
            return ($fraudStatus == 'accept') ? 'success' : 'failed';
        }
        
        switch ($transactionStatus) {
            case 'settlement':
                return 'success';
            case 'pending':
                return 'pending';
            case 'deny':
            case 'cancel':
            case 'expire':
                return 'failed';
            default:
                return 'pending';
        }
    }
    
    private function updatePaymentStatus($paymentId, $status, $paymentType, $paymentChannel) {
        $stmt = $this->pdo->prepare("
            UPDATE TBL_PAYMENTS 
            SET status = :status,
                payment_method = :payment_method,
                payment_channel = :payment_channel,
                transaction_time = CURRENT_TIMESTAMP,
                settlement_time = CASE 
                    WHEN :status = 'success' THEN CURRENT_TIMESTAMP 
                    ELSE NULL 
                END,
                verified_at = CURRENT_TIMESTAMP
            WHERE payment_id = :payment_id
        ");
        
        return $stmt->execute([
            'status' => $status,
            'payment_method' => $paymentType,
            'payment_channel' => $paymentChannel,
            'payment_id' => $paymentId
        ]);
    }
    
    private function createTransactionRecord($payment, $status, $paymentType, $paymentChannel) {
        $stmt = $this->pdo->prepare("
            INSERT INTO TBL_MIDTRANS_TRANSACTIONS (
                transaction_id,
                payment_id,
                order_id,
                transaction_status,
                payment_type,
                payment_channel,
                gross_amount,
                transaction_time,
                settlement_time,
                status_message,
                created_at
            ) VALUES (
                SEQ_MIDTRANS_TRANSACTIONS.NEXTVAL,
                :payment_id,
                :order_id,
                :status,
                :payment_type,
                :payment_channel,
                :amount,
                CURRENT_TIMESTAMP,
                CASE WHEN :status = 'success' THEN CURRENT_TIMESTAMP ELSE NULL END,
                :message,
                CURRENT_TIMESTAMP
            )
        ");
        
        return $stmt->execute([
            'payment_id' => $payment['payment_id'],
            'order_id' => $this->notification->order_id,
            'status' => $status,
            'payment_type' => $paymentType,
            'payment_channel' => $paymentChannel,
            'amount' => $payment['amount'],
            'message' => $this->notification->status_message ?? 'Transaction processed'
        ]);
    }
    
    private function createLogEntry($paymentId, $status, $paymentType, $paymentChannel) {
        $stmt = $this->pdo->prepare("
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
                'SYSTEM',
                CURRENT_TIMESTAMP
            )
        ");
        
        $message = sprintf(
            'Payment %s via %s (%s)', 
            $status,
            $paymentType,
            $paymentChannel
        );
        
        return $stmt->execute([
            'payment_id' => $paymentId,
            'status' => $status,
            'message' => $message
        ]);
    }
}

// Handle the notification
$handler = new PaymentNotificationHandler();
$result = $handler->handle();

// Return response to Midtrans
header('Content-Type: application/json');
echo json_encode($result);