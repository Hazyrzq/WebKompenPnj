<?php
require_once('../../config/koneksi.php');
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

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

        // Set headers sesuai struktur database
        $headers = [
            'NIM',
            'Nama',
            'Email',
            'Prodi',
            'Kelas',
            'Semester',
            'No. Telp',
            'Password',
            'Jumlah Terlambat',
            'Jumlah Alfa',
            'Total'
        ];

        foreach ($headers as $key => $header) {
            $column = chr(65 + $key);
            $sheet->setCellValue($column . '1', $header);
        }

        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="template_mahasiswa.xlsx"');
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

        $pdo = connectDB();
        $pdo->beginTransaction();

        // Prepare statement untuk cek duplikasi
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_mahasiswa WHERE nim = ?");

        // Prepare statement untuk insert dengan semua kolom yang diperlukan
        $insertStmt = $pdo->prepare("INSERT INTO tbl_mahasiswa (
            id_mhs, nim, nama, email, prodi, kelas, semester, notelp, password,
            edit_password, user_role, jumlah_terlambat, jumlah_alfa, total,
            user_create, created_at
        ) VALUES (
            seq_id_mhs.NEXTVAL, :nim, :nama, :email, :prodi, :kelas, :semester, :notelp, :password,
            '0', 'Mahasiswa', :jumlah_terlambat, :jumlah_alfa, :total,
            :user_create, CURRENT_TIMESTAMP
        )");

        $insertedRows = 0;
        $errors = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $nim = sanitizeInput($worksheet->getCell('A' . $row)->getValue());
                $nama = sanitizeInput($worksheet->getCell('B' . $row)->getValue());
                $email = sanitizeInput($worksheet->getCell('C' . $row)->getValue());
                $prodi = sanitizeInput($worksheet->getCell('D' . $row)->getValue());
                $kelas = sanitizeInput($worksheet->getCell('E' . $row)->getValue());
                $semester = sanitizeInput($worksheet->getCell('F' . $row)->getValue());
                $notelp = sanitizeInput($worksheet->getCell('G' . $row)->getValue());
                $password = sanitizeInput($worksheet->getCell('H' . $row)->getValue());
                $jumlah_terlambat = sanitizeInput($worksheet->getCell('I' . $row)->getValue());
                $jumlah_alfa = sanitizeInput($worksheet->getCell('J' . $row)->getValue());
                
                // Skip empty rows
                if (empty($nim) && empty($nama)) {
                    continue;
                }
        
                // Validasi data wajib
                if (empty($nim) || empty($nama)) {
                    throw new Exception("Baris $row: NIM dan Nama Mahasiswa tidak boleh kosong");
                }
        
                // Konversi ke angka dan validasi
                $jumlah_terlambat = is_numeric($jumlah_terlambat) ? (int)$jumlah_terlambat : 0;
                $jumlah_alfa = is_numeric($jumlah_alfa) ? (int)$jumlah_alfa : 0;
        
                // Hitung total menit:
                // - Terlambat: menit x 2
                // - Alfa: jam x 60 (konversi ke menit)
                $total_menit_terlambat = $jumlah_terlambat * 2;
                $total_menit_alfa = $jumlah_alfa * 60;
                $total = $total_menit_terlambat + $total_menit_alfa;
        
                // Cek duplikasi
                $checkStmt->execute([$nim]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Baris $row: NIM '$nim' sudah ada dalam database");
                }
        
                // Hash password jika ada
                $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : password_hash($nim, PASSWORD_DEFAULT);
        
                // Insert data dengan total yang sudah dihitung
                $insertStmt->execute([
                    ':nim' => $nim,
                    ':nama' => $nama,
                    ':email' => $email,
                    ':prodi' => $prodi,
                    ':kelas' => $kelas,
                    ':semester' => $semester,
                    ':notelp' => $notelp,
                    ':password' => $hashedPassword,
                    ':jumlah_terlambat' => $jumlah_terlambat,
                    ':jumlah_alfa' => $jumlah_alfa,
                    ':total' => $total,
                    ':user_create' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'system'
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

        // Redirect ke mahasiswa.php setelah berhasil
        header('Location: mahasiswa.php?message=success&count=' . $insertedRows);
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
    <title>Import Data Mahasiswa</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * {
            font-family: 'Poppins', sans-serif;
        }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .drop-zone:hover {
            border-color: #10B981;
            background-color: #F0FDF4;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h2 class="h4 mb-1 fw-bold">Import Data Mahasiswa</h2>
                                <p class="text-muted mb-0 small">Upload your Excel file to import student data</p>
                            </div>
                            <a href="mahasiswa.php" class="btn btn-light rounded-circle p-2">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>

                        <!-- Alert Messages -->
                        <?php if (!empty($message)): ?>
                            <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-danger'; ?> d-flex align-items-center" role="alert">
                                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                                <div><?php echo htmlspecialchars($message); ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Upload Form -->
                        <form method="POST" enctype="multipart/form-data" class="mt-4">
                            <div class="drop-zone mb-4">
                                <input type="file" name="file" accept=".xls,.xlsx,.csv" class="d-none" id="fileInput">
                                <label for="fileInput" class="mb-3 d-block">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-emerald-500 mb-3"></i>
                                    <h5 class="mb-2">Drag & Drop your file here</h5>
                                    <p class="text-muted small mb-0">or click to browse</p>
                                </label>
                                <div class="text-muted small mt-2">
                                    Supported formats: XLS, XLSX, CSV
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="submit" name="submit" class="btn btn-success px-4 py-2">
                                    <i class="fas fa-file-import me-2"></i>
                                    Import Data
                                </button>
                                <a href="?download_template" class="btn btn-primary px-4 py-2">
                                    <i class="fas fa-download me-2"></i>
                                    Download Template
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and drop functionality
        const dropZone = document.querySelector('.drop-zone');
        const fileInput = document.getElementById('fileInput');

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-emerald-500', 'bg-emerald-50');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-emerald-500', 'bg-emerald-50');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-emerald-500', 'bg-emerald-50');
            fileInput.files = e.dataTransfer.files;
        });

        // Show selected filename
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                const fileName = e.target.files[0].name;
                dropZone.querySelector('p').textContent = `Selected: ${fileName}`;
            }
        });
    </script>
</body>
</html>