<?php
// process_evidence.php

// First, clear any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Set error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/koneksi.php';

// Custom error handler for better error tracking
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['nim'])) {
        throw new Exception('User tidak teridentifikasi. Silakan login kembali.');
    }

    // Check if work_id is provided
    if (empty($_POST['work_id'])) {
        throw new Exception('Work ID tidak ditemukan');
    }

    $workId = $_POST['work_id'];
    $userNim = $_SESSION['nim']; // Get the correct user identifier
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    $pdo = $conn;
    $pdo->beginTransaction();

    $responseData = [
        'work_id' => $workId,
        'before_exists' => false,
        'after_exists' => false,
        'before_size' => 0,
        'after_size' => 0
    ];

    // Function to process image for Oracle BLOB
    function processImage($file, $type, $workId, $pdo, $userNim) {
        global $allowedTypes, $maxFileSize;
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("File {$type} harus berupa gambar (JPG, PNG, atau GIF)");
        }
        if ($file['size'] > $maxFileSize) {
            throw new Exception("Ukuran file {$type} maksimal 5MB");
        }

        $columnData = strtoupper($type) . '_PEKERJAAN';
        $columnMime = strtoupper($type) . '_MIME_TYPE';
        
        // Check if record exists for this user and work ID
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM TBL_PENGAJUAN_DETAIL 
                                   WHERE KODE_PEKERJAAN = :id 
                                   AND USER_CREATE = :user_nim");
        $checkStmt->execute([':id' => $workId, ':user_nim' => $userNim]);
        $exists = $checkStmt->fetchColumn();

        if (!$exists) {
            // Create new record if it doesn't exist
            $insertStmt = $pdo->prepare("INSERT INTO TBL_PENGAJUAN_DETAIL 
                                       (KODE_PEKERJAAN, USER_CREATE, USER_UPDATE)
                                       VALUES (:id, :user_nim, :user_update)");
            $insertStmt->execute([
                ':id' => $workId,
                ':user_nim' => $userNim,
                ':user_update' => $userNim
            ]);
        }
        
        // Update the BLOB data
        $stmt = $pdo->prepare("UPDATE TBL_PENGAJUAN_DETAIL 
                              SET {$columnData} = EMPTY_BLOB(),
                                  {$columnMime} = :mime_type,
                                  USER_UPDATE = :user_update
                              WHERE KODE_PEKERJAAN = :id
                              AND USER_CREATE = :user_nim
                              RETURNING {$columnData} INTO :blob_data");
        
        $blob = null;
        
        $stmt->bindParam(':mime_type', $file['type']);
        $stmt->bindParam(':user_update', $userNim);
        $stmt->bindParam(':user_nim', $userNim);
        $stmt->bindParam(':id', $workId);
        $stmt->bindParam(':blob_data', $blob, PDO::PARAM_LOB);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal inisialisasi BLOB untuk file {$type}");
        }

        // Write the file content to the BLOB
        if ($blob) {
            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                throw new Exception("Gagal membaca file {$type}");
            }
            
            if (is_resource($blob)) {
                fwrite($blob, $content);
                fclose($blob);
            } elseif (method_exists($blob, 'save')) {
                $blob->save($content);
            } else {
                throw new Exception("Tipe BLOB tidak didukung");
            }
            
            return strlen($content);
        }
        
        throw new Exception("Gagal membuat BLOB untuk file {$type}");
    }

    // Process before image
    if (!empty($_FILES['before']['tmp_name'])) {
        $beforeSize = processImage($_FILES['before'], 'before', $workId, $pdo, $userNim);
        $responseData['before_exists'] = true;
        $responseData['before_size'] = $beforeSize;
    }

    // Process after image
    if (!empty($_FILES['after']['tmp_name'])) {
        $afterSize = processImage($_FILES['after'], 'after', $workId, $pdo, $userNim);
        $responseData['after_exists'] = true;
        $responseData['after_size'] = $afterSize;
    }

    // Process additional evidence if provided
    if (isset($_POST['bukti_tambahan'])) {
        $stmt = $pdo->prepare("UPDATE TBL_PENGAJUAN_DETAIL 
                              SET BUKTI_TAMBAHAN = :bukti,
                                  USER_UPDATE = :user_update
                              WHERE KODE_PEKERJAAN = :id
                              AND USER_CREATE = :user_nim");
        
        $stmt->execute([
            ':bukti' => $_POST['bukti_tambahan'],
            ':user_update' => $userNim,
            ':id' => $workId,
            ':user_nim' => $userNim
        ]);
    }

    // Verify the update
    $stmt = $pdo->prepare("SELECT 
        CASE WHEN BEFORE_PEKERJAAN IS NOT NULL THEN 1 ELSE 0 END as BEFORE_EXISTS,
        CASE WHEN AFTER_PEKERJAAN IS NOT NULL THEN 1 ELSE 0 END as AFTER_EXISTS,
        DBMS_LOB.GETLENGTH(BEFORE_PEKERJAAN) as BEFORE_LENGTH,
        DBMS_LOB.GETLENGTH(AFTER_PEKERJAAN) as AFTER_LENGTH
        FROM TBL_PENGAJUAN_DETAIL 
        WHERE KODE_PEKERJAAN = :id
        AND USER_CREATE = :user_nim");
    
    $stmt->execute([
        ':id' => $workId,
        ':user_nim' => $userNim
    ]);
    
    $verifyResult = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verifyResult) {
        throw new Exception('Gagal memverifikasi data');
    }

    $pdo->commit();

    sendJsonResponse(true, 'Data berhasil disimpan', array_merge($responseData, [
        'before_length' => $verifyResult['BEFORE_LENGTH'],
        'after_length' => $verifyResult['AFTER_LENGTH']
    ]));

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Process Evidence Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendJsonResponse(false, $e->getMessage(), null, 500);
}

restore_error_handler();