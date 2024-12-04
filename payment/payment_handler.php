<?php
// payment_handler.php
require_once __DIR__ . '..\..\vendor\autoload.php';
require_once '../config/koneksi.php';
require_once '../config/midtrans_config.php';

class PaymentHandler {
    private $pdo;
    private $config;
    
    public function __construct() {
        $this->pdo = connectDB();
        $this->config = MidtransConfig::getInstance()->getConfig();
        
        // Set Midtrans configuration
        \Midtrans\Config::$serverKey = $this->config['server_key'];
        \Midtrans\Config::$isProduction = $this->config['is_production'];
        \Midtrans\Config::$is3ds = true;
    }

    public function createPayment($nim, $amount) {
        try {
            // Get student data
            $stmt = $this->pdo->prepare("SELECT NAMA, EMAIL FROM TBL_MAHASISWA WHERE NIM = ?");
            $stmt->execute([$nim]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                throw new Exception("Student data not found");
            }

            // Generate order ID
            $orderId = 'KOMPEN-' . $nim . '-' . time();

            // Prepare transaction parameters
            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int)$amount
                ],
                'customer_details' => [
                    'first_name' => $student['NAMA'],
                    'email' => $student['EMAIL']
                ],
                'enabled_payments' => [
                    'credit_card', 'bca_va', 'bni_va', 'bri_va', 'mandiri_va',
                    'gopay', 'shopeepay'
                ]
            ];

            // Get snap token
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            // Begin transaction
            $this->pdo->beginTransaction();

            // Insert payment record
            $sql = "INSERT INTO TBL_PAYMENTS (
                        payment_id, nim, amount, status, midtrans_token,
                        order_id, created_at
                    ) VALUES (
                        :order_id, :nim, :amount, 'pending',
                        :token, :order_id, CURRENT_TIMESTAMP
                    )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'order_id' => $orderId,
                'nim' => $nim,
                'amount' => $amount,
                'token' => $snapToken
            ]);

            // Insert initial transaction record
            $sql = "INSERT INTO TBL_MIDTRANS_TRANSACTIONS (
                        transaction_id,
                        payment_id,
                        order_id,
                        transaction_status,
                        gross_amount,
                        created_at
                    ) VALUES (
                        SEQ_MIDTRANS_TRANSACTIONS.NEXTVAL,
                        :payment_id,
                        :order_id,
                        'pending',
                        :amount,
                        CURRENT_TIMESTAMP
                    )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'payment_id' => $orderId,
                'order_id' => $orderId,
                'amount' => $amount
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'token' => $snapToken,
                'order_id' => $orderId
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function handleNotification() {
        try {
            $notification = new \Midtrans\Notification();

            $orderId = $notification->order_id;
            $transactionStatus = $notification->transaction_status;
            $paymentType = $notification->payment_type;
            $fraudStatus = $notification->fraud_status;

            // Map Midtrans status to our status
            $status = $this->getMappedStatus($transactionStatus, $fraudStatus);

            // Begin transaction
            $this->pdo->beginTransaction();

            // Update payment status
            $sql = "UPDATE TBL_PAYMENTS SET 
                    status = :status,
                    payment_method = :payment_type,
                    transaction_time = CURRENT_TIMESTAMP,
                    settlement_time = CASE 
                        WHEN :status = 'success' THEN CURRENT_TIMESTAMP 
                        ELSE NULL 
                    END
                    WHERE order_id = :order_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'status' => $status,
                'payment_type' => $paymentType,
                'order_id' => $orderId
            ]);

            // Update transaction record
            $sql = "UPDATE TBL_MIDTRANS_TRANSACTIONS SET 
                    transaction_status = :status,
                    payment_type = :payment_type,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE order_id = :order_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'status' => $status,
                'payment_type' => $paymentType,
                'order_id' => $orderId
            ]);

            // If payment is successful, generate receipt
            if ($status === 'success') {
                $this->generateReceipt($orderId);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Payment notification handled successfully'
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function getMappedStatus($transactionStatus, $fraudStatus = null) {
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

    private function generateReceipt($orderId) {
        // Implementation moved to separate file
        require_once 'payment_receipt.php';
        return generatePaymentReceipt($orderId);
    }
}