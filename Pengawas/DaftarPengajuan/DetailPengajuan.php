<?php
// File: DetailPengajuan.php
require_once(__DIR__ . '/../../Config.php');

// Cek session dan role
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['role']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Ambil ID pengajuan dari URL
$id_pengajuan = isset($_GET['id']) ? $_GET['id'] : null;

// Log untuk debug
error_log("ID Pengajuan yang diterima: " . $id_pengajuan);

if (!$id_pengajuan) {
    die('ID Pengajuan tidak ditemukan');
}

try {
    $pdo = getDB();

    // Perbaiki query sesuai struktur tabel yang benar
    $stmt = $pdo->prepare("
    SELECT 
        TBL_PENGAJUAN_DETAIL.ID_PENGAJUAN_DETAIL,
        TBL_PENGAJUAN_DETAIL.KODE_KEGIATAN,
        TBL_PENGAJUAN_DETAIL.NAMA_PEKERJAAN,
        TBL_PENGAJUAN_DETAIL.JAM_PEKERJAAN,
        TBL_PENGAJUAN_DETAIL.BEFORE_PEKERJAAN,
        TBL_PENGAJUAN_DETAIL.AFTER_PEKERJAAN,
        TBL_PENGAJUAN_DETAIL.BEFORE_MIME_TYPE,
        TBL_PENGAJUAN_DETAIL.AFTER_MIME_TYPE,
        TBL_MAHASISWA.NAMA as NAMA_MAHASISWA
    FROM TBL_PENGAJUAN_DETAIL
    LEFT JOIN TBL_PENGAJUAN ON TBL_PENGAJUAN_DETAIL.KODE_KEGIATAN = TBL_PENGAJUAN.KODE_KEGIATAN
    LEFT JOIN TBL_MAHASISWA ON TBL_PENGAJUAN.KODE_USER = TBL_MAHASISWA.NIM
    WHERE TBL_PENGAJUAN.ID_PENGAJUAN = :id
");

    // Debug untuk tracking
    error_log("Executing query for ID: " . $id_pengajuan);

    $stmt->execute([':id' => $id_pengajuan]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug hasil query
    error_log("Query result: " . print_r($detail, true));

    if (!$detail) {
        error_log("No data found for ID: " . $id_pengajuan);
        die('Detail pengajuan tidak ditemukan');
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die('Error koneksi database: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengajuan</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <div class="max-w-5xl mx-auto px-4 py-8"> <!-- Mengubah max-w dan padding -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden"> <!-- Mengubah rounded dan shadow -->
            <!-- Header dengan styling yang lebih baik -->
            <div class="border-b bg-gray-50 px-6 py-4">
                <h1 class="text-xl font-semibold text-gray-800">Detail Pengajuan</h1>
                <p class="text-sm text-gray-600 mt-1">ID Pengajuan: <?php echo htmlspecialchars($id_pengajuan); ?></p>
            </div>

            <!-- Card content dengan padding yang lebih baik -->
            <div class="p-6">
                <!-- Informasi Pengajuan -->
                <div class="mb-8"> <!-- Menambah margin bottom -->
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Informasi Pengajuan</h2>
                    <div class="bg-gray-50 rounded-lg p-4"> <!-- Menambah background dan padding -->
                        <div class="grid gap-4">
                            <div class="space-y-2"> <!-- Mengatur spacing -->
                                <p class="text-sm">
                                    <span class="font-medium text-gray-600">Kode Kegiatan:</span>
                                    <span
                                        class="ml-2 text-gray-800"><?php echo isset($detail['KODE_KEGIATAN']) ? htmlspecialchars($detail['KODE_KEGIATAN']) : 'Tidak ada data'; ?></span>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium text-gray-600">Nama Mahasiswa:</span>
                                    <span
                                        class="ml-2 text-gray-800"><?php echo isset($detail['NAMA_MAHASISWA']) ? htmlspecialchars($detail['NAMA_MAHASISWA']) : 'Tidak ada data'; ?></span>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium text-gray-600">Nama Pekerjaan:</span>
                                    <span
                                        class="ml-2 text-gray-800"><?php echo isset($detail['NAMA_PEKERJAAN']) ? htmlspecialchars($detail['NAMA_PEKERJAAN']) : 'Tidak ada data'; ?></span>
                                </p>
                                <p class="text-sm">
                                    <span class="font-medium text-gray-600">Jam Pekerjaan:</span>
                                    <span
                                        class="ml-2 text-gray-800"><?php echo isset($detail['JAM_PEKERJAAN']) ? htmlspecialchars($detail['JAM_PEKERJAAN']) : 'Tidak ada data'; ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Foto Bukti dengan Grid Layout -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Foto Sebelum -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-md font-medium text-gray-800 mb-3">Foto Sebelum Pengerjaan</h3>
                        <div class="bg-white rounded-lg overflow-hidden border border-gray-200">
                            <?php if (isset($detail['BEFORE_PEKERJAAN']) && $detail['BEFORE_PEKERJAAN'] !== null): ?>
                                <img src="data:<?php echo htmlspecialchars($detail['BEFORE_MIME_TYPE']); ?>;base64,<?php echo base64_encode(stream_get_contents($detail['BEFORE_PEKERJAAN'])); ?>"
                                    alt="Foto sebelum pengerjaan" class="w-full h-48 object-contain" />
                                <!-- Fixed height -->
                            <?php else: ?>
                                <div class="h-48 flex items-center justify-center">
                                    <p class="text-gray-500 text-sm">Tidak ada foto</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Foto Sesudah -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-md font-medium text-gray-800 mb-3">Foto Setelah Pengerjaan</h3>
                        <div class="bg-white rounded-lg overflow-hidden border border-gray-200">
                            <?php if (isset($detail['AFTER_PEKERJAAN']) && $detail['AFTER_PEKERJAAN'] !== null): ?>
                                <img src="data:<?php echo htmlspecialchars($detail['AFTER_MIME_TYPE']); ?>;base64,<?php echo base64_encode(stream_get_contents($detail['AFTER_PEKERJAAN'])); ?>"
                                    alt="Foto setelah pengerjaan" class="w-full h-48 object-contain" />
                                <!-- Fixed height -->
                            <?php else: ?>
                                <div class="h-48 flex items-center justify-center">
                                    <p class="text-gray-500 text-sm">Tidak ada foto</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tombol Kembali -->
                <div class="mt-8 flex justify-end">
                    <a href="DaftarPengajuanPengawas.php"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 text-sm text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>