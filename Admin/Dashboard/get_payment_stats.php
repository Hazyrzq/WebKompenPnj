<?php
require_once('../../Config.php');
header('Content-Type: application/json');

try {
    $db = getDB();

    // Get payment counts dengan LOWER untuk case insensitive comparison
    $countQuery = "SELECT 
    COUNT(CASE WHEN LOWER(STATUS) = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN LOWER(STATUS) = 'verified' THEN 1 END) as verified,
    COUNT(CASE WHEN LOWER(STATUS) = 'failed' THEN 1 END) as failed
FROM TBL_PAYMENTS";
    $countStmt = $db->query($countQuery);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    // Get recent transactions
    $recentQuery = "SELECT 
        p.PAYMENT_ID,
        m.NAMA,
        p.AMOUNT,
        p.STATUS,
        p.NIM,
        p.PAYMENT_METHOD,
        p.PAYMENT_CHANNEL,
        TO_CHAR(p.CREATED_AT, 'DD-MM-YYYY HH24:MI') as CREATED_AT
    FROM TBL_PAYMENTS p
    LEFT JOIN TBL_MAHASISWA m ON p.NIM = m.NIM
    ORDER BY p.CREATED_AT DESC
    FETCH FIRST 5 ROWS ONLY";
    $recentStmt = $db->query($recentQuery);
    $recentTx = [];

    while ($row = $recentStmt->fetch(PDO::FETCH_ASSOC)) {
        $recentTx[] = [
            'payment_id' => $row['PAYMENT_ID'],
            'nama' => $row['NAMA'],
            'amount' => (int) $row['AMOUNT'],
            'status' => strtolower($row['STATUS']), // Pastikan status lowercase
            'created_at' => $row['CREATED_AT']
        ];
    }

    // Prepare response
    $response = [
        'pending' => (int) ($counts['pending'] ?? 0),
        'verified' => (int) ($counts['verified'] ?? 0),
        'failed' => (int) ($counts['failed'] ?? 0),
        'recent' => $recentTx
    ];

    // Debug info
    error_log("Payment Stats Response: " . json_encode($response));

    echo json_encode($response);
} catch (PDOException $e) {
    error_log("Error in get_payment_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>