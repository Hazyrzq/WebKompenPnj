<?php
require_once('../../Config.php');require_once dirname(__FILE__) . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Inisialisasi variabel pesan
$message = '';
$messageType = '';

// Fungsi untuk membersihkan input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Handle template download
if (isset($_GET['download_template'])) {
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'KODE_PEKERJAAN',
            'NAMA_PEKERJAAN',
            'JAM_PEKERJAAN',
            'DETAIL_PEKERJAAN',
            'BATAS_PEKERJA',
            'ID_PENANGGUNG_JAWAB',
            'PENANGGUNG_JAWAB'
        ];

        foreach ($headers as $key => $header) {
            $column = chr(65 + $key);
            $sheet->setCellValue($column . '1', $header);
        }

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="template_pekerjaan.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        $message = "Error saat mengunduh template: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle file import
if (isset($_POST["submit"])) {
    try {
        if (!isset($_FILES["file"]) || empty($_FILES["file"]["name"])) {
            throw new Exception("Silakan pilih file untuk diimport");
        }

        $allowed_ext = ['xls', 'csv', 'xlsx'];
        $fileName = $_FILES["file"]["name"];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowed_ext)) {
            throw new Exception("Format file tidak valid. Hanya file XLS, XLSX, dan CSV yang diizinkan");
        }

        $inputFileName = $_FILES["file"]["tmp_name"];

        if (!is_uploaded_file($inputFileName)) {
            throw new Exception("File tidak berhasil diunggah");
        }

        $spreadsheet = IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        if ($highestRow <= 1) {
            throw new Exception("File Excel kosong atau tidak memiliki data");
        }

        $pdo = getDB();
        $pdo->beginTransaction();

        // Prepare statement untuk cek duplikasi
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_pekerjaan WHERE KODE_PEKERJAAN = ?");

        // Prepare statement untuk insert
        $insertStmt = $pdo->prepare("INSERT INTO tbl_pekerjaan (KODE_PEKERJAAN, NAMA_PEKERJAAN, JAM_PEKERJAAN, DETAIL_PEKERJAAN, BATAS_PEKERJA, ID_PENANGGUNG_JAWAB, PENANGGUNG_JAWAB) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");

        $insertedRows = 0;
        $errors = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $kode_pekerjaan = sanitizeInput($worksheet->getCell('A' . $row)->getValue());
                $nama_pekerjaan = sanitizeInput($worksheet->getCell('B' . $row)->getValue());
                $jam_pekerjaan = sanitizeInput($worksheet->getCell('C' . $row)->getValue());
                $detail_pekerjaan = sanitizeInput($worksheet->getCell('D' . $row)->getValue());
                $batas_pekerja = sanitizeInput($worksheet->getCell('E' . $row)->getValue());
                $id_penanggung_jawab = sanitizeInput($worksheet->getCell('F' . $row)->getValue());
                $penanggung_jawab = sanitizeInput($worksheet->getCell('G' . $row)->getValue());

                // Skip empty rows
                if (empty($kode_pekerjaan) && empty($nama_pekerjaan)) {
                    continue;
                }

                // Validasi data wajib
                if (empty($kode_pekerjaan) || empty($nama_pekerjaan)) {
                    throw new Exception("Baris $row: Kode Pekerjaan dan Nama Pekerjaan tidak boleh kosong");
                }

                // Cek duplikasi
                $checkStmt->execute([$kode_pekerjaan]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Baris $row: Kode Pekerjaan '$kode_pekerjaan' sudah ada dalam database");
                }

                // Insert data
                $insertStmt->execute([
                    $kode_pekerjaan,
                    $nama_pekerjaan,
                    $jam_pekerjaan,
                    $detail_pekerjaan,
                    $batas_pekerja,
                    $id_penanggung_jawab,
                    $penanggung_jawab
                ]);

                $insertedRows++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new Exception("Terjadi error:<br>" . implode("<br>", $errors));
        }

        $pdo->commit();

        // Redirect ke pekerjaan.php setelah berhasil
        header('Location: pekerjaan.php?message=success&count=' . $insertedRows);
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Pekerjaan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-6">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Import Data Pekerjaan</h2>
            <a href="pekerjaan.php" class="text-gray-600 hover:text-gray-800 transition duration-200">
                <i class="fas fa-times text-xl hover:scale-110"></i>
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Pilih File Excel</label>
                <input type="file" name="file" accept=".xls,.xlsx,.csv" class="w-full p-2 border rounded">
                <p class="text-sm text-gray-600 mt-1">Format yang diterima: XLS, XLSX, CSV</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" name="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition flex items-center">
                    <i class="fas fa-file-import mr-2"></i>
                    Import Data
                </button>
                <a href="?download_template" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition inline-flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    Download Template
                </a>
            </div>
        </form>
    </div>
</body>
</html>
