<?php
session_start();
require_once('../../Config.php');
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get pengawas data
$pengawasQuery = "SELECT ID, NAMA_USER FROM tbl_user WHERE ROLE = 'PENGAWAS' AND STATUS = 'ACTIVE'";
$stmt = executeQuery($pengawasQuery, []);
$pengawasList = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pengawasList)) {
    die("Tidak ada user dengan role Pengawas yang aktif");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo = getDB();

        // Start transaction
        $pdo->beginTransaction();

        // Get next sequence value
        $seqQuery = "SELECT seq_id_pekerjaan.NEXTVAL FROM DUAL";
        $stmt = $pdo->query($seqQuery);
        $id_pekerjaan = $stmt->fetchColumn();

        // Prepare data
        $kode_pekerjaan = $_POST['kode_pekerjaan'];
        $nama_pekerjaan = $_POST['nama_pekerjaan'];
        $detail_pekerjaan = $_POST['detail_pekerjaan'];
        $jam_pekerjaan = $_POST['jam_pekerjaan'];
        $batas_pekerja = $_POST['batas_pekerja'];
        $id_penanggung_jawab = $_POST['id_penanggung_jawab'];

        // Get pengawas name from the selected ID
        $pengawasName = '';
        foreach ($pengawasList as $pengawas) {
            if ($pengawas['ID'] == $id_penanggung_jawab) {
                $pengawasName = $pengawas['NAMA_USER'];
                break;
            }
        }

        // Check if kode_pekerjaan already exists
        $checkKodeQuery = "SELECT COUNT(*) FROM tbl_pekerjaan WHERE kode_pekerjaan = :kode_pekerjaan";
        $stmt = $pdo->prepare($checkKodeQuery);
        $stmt->execute([':kode_pekerjaan' => $kode_pekerjaan]);
        $result = $stmt->fetchColumn();

        if ($result > 0) {
            throw new Exception("Kode pekerjaan sudah digunakan. Silakan gunakan kode lain.");
        }

        // Generate UUID for user_uid
        $user_uid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        // Insert query
        $insertQuery = "INSERT INTO tbl_pekerjaan (
            id_pekerjaan,
            kode_pekerjaan,
            nama_pekerjaan,
            detail_pekerjaan,
            jam_pekerjaan,
            batas_pekerja,
            id_penanggung_jawab,
            penanggung_jawab,
            user_create,
            user_uid,
            created_at
        ) VALUES (
            :id_pekerjaan,
            :kode_pekerjaan,
            :nama_pekerjaan,
            :detail_pekerjaan,
            :jam_pekerjaan,
            :batas_pekerja,
            :id_penanggung_jawab,
            :penanggung_jawab,
            :user_create,
            :user_uid,
            CURRENT_TIMESTAMP
        )";

        $params = [
            ':id_pekerjaan' => $id_pekerjaan,
            ':kode_pekerjaan' => $kode_pekerjaan,
            ':nama_pekerjaan' => $nama_pekerjaan,
            ':detail_pekerjaan' => $detail_pekerjaan,
            ':jam_pekerjaan' => $jam_pekerjaan,
            ':batas_pekerja' => $batas_pekerja,
            ':id_penanggung_jawab' => $id_penanggung_jawab,
            ':penanggung_jawab' => $pengawasName,
            ':user_create' => $_SESSION['nama_user'],
            ':user_uid' => $user_uid
        ];

        // Execute insert query
        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute($params);

        // Commit transaction
        $pdo->commit();

        // Redirect on success
        header('Location: pekerjaan.php?status=success&message=' . urlencode('Pekerjaan berhasil ditambahkan'));
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pekerjaan</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Poppins', sans-serif;
        }

        .bg-gray-100 {
            background-color: #f3f4f6;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="images/LogoPNJ.png" alt="Logo" class="h-12 w-12">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Tambah Data Pekerjaan</h1>
                        <p class="text-sm text-gray-600">Add New Pekerjaan Details</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <img src="images/profile.png" alt="Profile"
                        class="h-10 w-10 rounded-full border-2 border-emerald-500">
                </div>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" onsubmit="return validateForm()">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="nama_pekerjaan" class="block text-sm font-medium text-gray-700">Nama
                            Pekerjaan</label>
                        <input type="text" name="nama_pekerjaan" id="nama_pekerjaan" required maxlength="255"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Masukkan nama pekerjaan">
                    </div>

                    <div>
                        <label for="kode_pekerjaan" class="block text-sm font-medium text-gray-700">Kode
                            Pekerjaan</label>
                        <input type="text" name="kode_pekerjaan" id="kode_pekerjaan" required maxlength="50"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Masukkan kode pekerjaan">
                    </div>

                    <div>
                        <label for="jam_pekerjaan" class="block text-sm font-medium text-gray-700">Jam Pekerjaan
                            (jam)</label>
                        <input type="number" name="jam_pekerjaan" id="jam_pekerjaan" step="0.01" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Masukkan jam pekerjaan" oninput="hitungMenit()">
                        <p class="mt-1 text-sm text-gray-500">Hasil dalam menit: <span id="hasilMenit">0</span> menit
                        </p>
                    </div>

                    <div>
                        <label for="batas_pekerja" class="block text-sm font-medium text-gray-700">Limit Pekerja</label>
                        <input type="number" name="batas_pekerja" id="batas_pekerja" required min="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Masukkan batas pekerja">
                    </div>

                    <div class="col-span-2">
                        <label for="id_penanggung_jawab" class="block text-sm font-medium text-gray-700">Penanggung
                            Jawab</label>
                        <select name="id_penanggung_jawab" id="id_penanggung_jawab" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Pilih Penanggung Jawab</option>
                            <?php foreach ($pengawasList as $pengawas): ?>
                                <option value="<?php echo htmlspecialchars($pengawas['ID']); ?>">
                                    <?php echo htmlspecialchars($pengawas['NAMA_USER']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label for="detail_pekerjaan" class="block text-sm font-medium text-gray-700">Detail
                            Pekerjaan</label>
                        <textarea name="detail_pekerjaan" id="detail_pekerjaan" required rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Masukkan detail pekerjaan"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="pekerjaan.php"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        Tambah Pekerjaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function validateForm() {
            const requiredFields = [
                'nama_pekerjaan',
                'kode_pekerjaan',
                'jam_pekerjaan',
                'batas_pekerja',
                'id_penanggung_jawab',
                'detail_pekerjaan'
            ];

            let isValid = true;

            requiredFields.forEach(field => {
                const element = document.getElementById(field);
                const value = element.value.trim();

                if (!value) {
                    isValid = false;
                    element.classList.add('border-red-500');
                    showError(element, 'Field ini harus diisi');
                } else {
                    element.classList.remove('border-red-500');
                    clearError(element);

                    if (field === 'jam_pekerjaan' || field === 'batas_pekerja') {
                        const num = parseInt(value);
                        if (num < 1) {
                            isValid = false;
                            element.classList.add('border-red-500');
                            showError(element, 'Nilai harus lebih besar dari 0');
                        }
                    }
                }
            });

            if (!isValid) {
                event.preventDefault();
                alert('Mohon periksa kembali input Anda');
                return false;
            }

            return true;
        }

        function showError(element, message) {
            let errorDiv = element.parentNode.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message text-red-500 text-sm mt-1';
                element.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        }

        function hitungMenit() {
            let jamInput = document.getElementById('jam_pekerjaan');
            let jamValue = parseFloat(jamInput.value) || 0;
            let menitValue = Math.round(jamValue * 60);

            jamInput.value = formatNumber(jamValue);
            document.getElementById('hasilMenit').textContent = menitValue;
        }

        function formatNumber(num) {
            return Number.isInteger(num) ? num.toString() : num.toFixed(2);
        }

        // Jalankan fungsi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', hitungMenit);
    </script>
</body>

</html>