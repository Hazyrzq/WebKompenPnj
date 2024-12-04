<?php
// get_snap_token.php
require_once '../config/koneksi.php';
require_once '../vendor/autoload.php';

// Clear output buffer to prevent any unwanted output
ob_start();
while (ob_get_level()) {
    ob_end_clean();
}

// Configure error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_error.log');

header('Content-Type: application/json');
function calculatePaymentAmount($totalMinutes) {
    $MAX_FREE_MINUTES = 1500;
    $RATE_PER_HOUR = 10000;
    
    // Calculate exceeded minutes (anything over 1500)
    $exceededMinutes = max(0, $totalMinutes - $MAX_FREE_MINUTES);
    
    // Convert to hours - use floor instead of ceil to round down
    // This ensures 58.33 hours becomes 58 hours, not 59
    $payableHours = floor($exceededMinutes / 60);
    
    // Calculate final amount
    return $payableHours * $RATE_PER_HOUR;
}

try {
    // Log incoming request
    error_log("Payment request received: " . file_get_contents('php://input'));
    
    // Parse and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (empty($input['nim'])) {
        throw new Exception('NIM harus diisi');
    }

    // Verify Midtrans SDK
    if (!class_exists('\Midtrans\Config')) {
        throw new Exception('Midtrans SDK tidak ditemukan');
    }

    // Set Midtrans Configuration
    \Midtrans\Config::$serverKey = 'SB-Mid-server-WURwTWswCZ2VeCzwCj5bHUGv';
    \Midtrans\Config::$isProduction = false;
    \Midtrans\Config::$isSanitized = true;
    \Midtrans\Config::$is3ds = true;

    $nim = trim($input['nim']);
    $orderId = 'KOMPEN-' . $nim . '-' . time();

    // Connect to database
    $pdo = connectDB();
    
    // Get student data and total kompen
    $stmt = $pdo->prepare("
        SELECT m.NAMA, m.PRODI, m.EMAIL, m.TOTAL as TOTAL_KOMPEN
        FROM TBL_MAHASISWA m 
        WHERE m.NIM = :nim
    ");
    $stmt->execute(['nim' => $nim]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Data mahasiswa tidak ditemukan');
    }

    // Calculate payment amount using the consistent function
    $totalKompen = (int)$student['TOTAL_KOMPEN'];
    $amount = calculatePaymentAmount($totalKompen);

    // Log the calculation details for debugging
    error_log(sprintf(
        "Payment calculation: Total Kompen: %d minutes, Exceeded Minutes: %d, Payable Hours: %d, Amount: %d",
        $totalKompen,
        max(0, $totalKompen - 1500),
        floor(max(0, $totalKompen - 1500) / 60),
        $amount
    ));

    // Configure payment parameters...
    $params = [
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => $amount  // This will now match the UI calculation
        ],
        'customer_details' => [
            'first_name' => $student['NAMA'],
            'email' => $student['EMAIL'] ?: ($nim . '@student.example.com'),
            'billing_address' => [
                'first_name' => $student['NAMA'],
                'email' => $student['EMAIL'] ?: ($nim . '@student.example.com')
            ]
        ],
        'item_details' => [
            [
                'id' => 'KOMPEN-1',
                'price' => $amount,
                'quantity' => 1,
                'name' => 'Pembayaran Kompen ' . $student['PRODI']
            ]
        ],
        'enabled_payments' => [
            'credit_card',
            'mandiri_clickpay',
            'cimb_clicks',
            'bca_klikbca',
            'bca_klikpay',
            'bri_epay',
            'echannel',
            'permata_va',
            'bca_va',
            'bni_va',
            'bri_va',
            'other_va',
            'gopay',
            'shopeepay',
            'indomaret',
            'alfamart',
            'danamon_online',
            'akulaku'
        ],
        'credit_card' => [
            'secure' => true,
            'save_card' => true,
            'installment' => [
                'required' => false,
                'terms' => [
                    'bca' => [3, 6, 12],
                    'bni' => [3, 6, 12],
                    'mandiri' => [3, 6, 12],
                    'cimb' => [3, 6, 12],
                    'bri' => [3, 6, 12],
                    'maybank' => [3, 6, 12],
                    'mega' => [3, 6, 12]
                ]
            ]
        ]
    ];

    error_log("Payment parameters: " . json_encode($params));

    try {
        // Get Snap token
        $snapToken = \Midtrans\Snap::getSnapToken($params);
        error_log("Snap token received: " . $snapToken);
    } catch (Exception $e) {
        error_log("Midtrans Error: " . $e->getMessage());
        throw new Exception('Error from payment gateway: ' . $e->getMessage());
    }

    // Begin database transaction
    $pdo->beginTransaction();
    
    try {
        // Create payment record
        $stmt = $pdo->prepare("
            INSERT INTO TBL_PAYMENTS (
                PAYMENT_ID, NIM, AMOUNT, STATUS, 
                MIDTRANS_TOKEN, ORDER_ID, CREATED_AT
            ) VALUES (
                :order_id, :nim, :amount, 'pending',
                :token, :order_id, CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            'order_id' => $orderId,
            'nim' => $nim,
            'amount' => $amount,
            'token' => $snapToken
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'token' => $snapToken,
            'order_id' => $orderId,
            'amount' => $amount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error occurred: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}