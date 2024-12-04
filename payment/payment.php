<?php
// payment.php
require_once '../config/koneksi.php';
require_once 'payment_ui_components.php';

function calculatePaymentAmount($totalMinutes) {
    $RATE_PER_HOUR = 10000; // Rate per hour in Rupiah
    $MAX_FREE_MINUTES = 1500; // Maximum free minutes threshold
    
    // Calculate exceeded minutes
    $payableMinutes = max(0, $totalMinutes - $MAX_FREE_MINUTES);
    
    // Convert to hours (round up)
    $payableHours = ceil($payableMinutes / 60);
    
    return $payableHours * $RATE_PER_HOUR;
}


// Function to download receipt
function downloadReceipt($paymentId) {
    try {
        $pdo = connectDB();
        
        $sql = "SELECT pr.*, p.ORDER_ID 
                FROM TBL_PAYMENT_REPORTS pr
                JOIN TBL_PAYMENTS p ON pr.PAYMENT_ID = p.PAYMENT_ID 
                WHERE p.PAYMENT_ID = :payment_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['payment_id' => $paymentId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            throw new Exception("Receipt not found");
        }
        
        // Clean output buffer
        while (ob_get_level()) ob_end_clean();
        
        // Set headers for file download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $report['FILENAME'] . '"');
        header('Cache-Control: private');
        header('Pragma: public');
        
        // Output file content
        echo $report['REPORT_FILE'];
        exit;
        
    } catch (Exception $e) {
        error_log("Error downloading receipt: " . $e->getMessage());
        header("HTTP/1.1 404 Not Found");
        echo "Error: Receipt not found";
    }
}

// Make sure to include the calculatePaymentAmount function in payment_ui_components.php
// or modify the code to access it from this file