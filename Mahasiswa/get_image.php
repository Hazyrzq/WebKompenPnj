<?php
// get_image.php
while (ob_get_level()) {
    ob_end_clean();
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/koneksi.php';

function sendErrorResponse($message, $statusCode = 404) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit();
}

try {
    // Check user authentication
    if (!isset($_SESSION['nim'])) {
        throw new Exception('User tidak teridentifikasi');
    }

    if (empty($_GET['work_id']) || empty($_GET['type'])) {
        throw new Exception('Parameter tidak lengkap');
    }

    $workId = $_GET['work_id'];
    $type = strtolower($_GET['type']);
    $userNim = $_SESSION['nim'];  // Get the user's NIM

    if (!in_array($type, ['before', 'after'])) {
        throw new Exception('Invalid image type');
    }

    $column = strtoupper($type) . '_PEKERJAAN';
    $mimeColumn = strtoupper($type) . '_MIME_TYPE';
    
    // Query with proper BLOB handling and user filtering
    $sql = "SELECT 
            " . $column . " as IMAGE_DATA,
            " . $mimeColumn . " as MIME_TYPE,
            DBMS_LOB.GETLENGTH(" . $column . ") as DATA_LENGTH
            FROM TBL_PENGAJUAN_DETAIL 
            WHERE KODE_PEKERJAAN = :id
            AND USER_CREATE = :user_nim";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $workId);
    $stmt->bindParam(':user_nim', $userNim);
    
    // Set fetch mode for BLOB
    $stmt->bindColumn('IMAGE_DATA', $lob, PDO::PARAM_LOB);
    $stmt->bindColumn('MIME_TYPE', $mimeType);
    $stmt->bindColumn('DATA_LENGTH', $length);
    
    $stmt->execute();
    
    if (!$stmt->fetch(PDO::FETCH_BOUND)) {
        throw new Exception('Image not found');
    }

    if (empty($length) || $length <= 0) {
        throw new Exception('Empty image data');
    }

    // Handle different LOB types
    if (is_resource($lob)) {
        $imageData = stream_get_contents($lob);
    } elseif (is_object($lob) && method_exists($lob, 'read')) {
        $imageData = $lob->read($length);
    } else {
        throw new Exception('Invalid BLOB data type');
    }

    if ($imageData === false || empty($imageData)) {
        throw new Exception('Failed to read image data');
    }

    // Clear buffers and set headers
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: public, max-age=3600');
    header('Pragma: cache');
    header('Content-Disposition: inline; filename="' . $type . '_image.' . pathinfo($mimeType, PATHINFO_EXTENSION) . '"');
    
    echo $imageData;
    exit();

} catch (Exception $e) {
    error_log("Get Image Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendErrorResponse($e->getMessage());
}