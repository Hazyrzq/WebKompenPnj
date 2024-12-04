<?php
require_once '../config/koneksi.php';

// Set JSON header
header('Content-Type: application/json');

// Configure error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set upload configuration
$uploadDir = '../uploads/bebas_kompen/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

function getFileExtension($mimeType) {
    $extensions = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
    ];
    return $extensions[$mimeType] ?? '';
}

// Function to clean filename - removes special characters and converts spaces to underscores
function cleanFileName($string) {
    // Replace special characters and spaces
    $string = preg_replace('/[^A-Za-z0-9\-]/', '_', $string);
    // Remove multiple underscores
    $string = preg_replace('/_+/', '_', $string);
    // Convert to lowercase
    return strtolower($string);
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get NIM from session or POST data
    session_start();
    $nim = $_SESSION['nim'] ?? null;
    if (!$nim) {
        throw new Exception('User not authenticated');
    }

    // Validate file upload
    if (!isset($_FILES['surat']) || $_FILES['surat']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File tidak ditemukan atau terjadi kesalahan saat upload');
    }

    $file = $_FILES['surat'];

    // Validate file size
    if ($file['size'] > $maxFileSize) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 5MB');
    }

    // Validate file type
    $fileMimeType = mime_content_type($file['tmp_name']);
    if (!in_array($fileMimeType, $allowedMimeTypes)) {
        throw new Exception('Format file tidak didukung. Hanya PDF, DOC, dan DOCX yang diperbolehkan');
    }

    // Connect to database
    $pdo = connectDB();

    // Get student's name from database
    $stmt = $pdo->prepare("SELECT NAMA FROM TBL_MAHASISWA WHERE NIM = :nim");
    $stmt->execute(['nim' => $nim]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Data mahasiswa tidak ditemukan');
    }
    
    // Generate filename using name_nim format
    $cleanName = cleanFileName($student['NAMA']); // Clean the name first
    $extension = getFileExtension($fileMimeType);
    $fileName = $cleanName . '_' . $nim . '_' . date('Ymd_His') . '.' . $extension;
    $destination = $uploadDir . $fileName;

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Gagal menyimpan file');
        }

        // Insert record into database
        $stmt = $pdo->prepare("
            INSERT INTO TBL_BEBAS_KOMPEN (
                submission_id,
                nim,
                file_path,
                original_filename,
                file_size,
                mime_type,
                submitted_at
            ) VALUES (
                seq_bebas_kompen_id.NEXTVAL,
                :nim,
                :file_path,
                :original_filename,
                :file_size,
                :mime_type,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            'nim' => $nim,
            'file_path' => $fileName,
            'original_filename' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $fileMimeType
        ]);

        $pdo->commit();
        $_SESSION['uploadSuccess'] = true;
        $_SESSION['uploadedFile'] = $fileName;

        echo json_encode([
            'success' => true,
            'message' => 'Pengajuan bebas kompen berhasil disubmit',
            'file_name' => $fileName
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        // Delete uploaded file if exists
        if (file_exists($destination)) {
            unlink($destination);
        }
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in process_bebas_kompen.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}