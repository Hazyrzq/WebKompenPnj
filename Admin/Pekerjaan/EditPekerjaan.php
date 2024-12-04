<?php
session_start();
require_once('../../Config.php');
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Check if ID is provided in URL
if (!isset($_GET['id'])) {
    header('Location: pekerjaan.php');
    exit;
}

$no_pekerjaan = $_GET['id'];

// Get pengawas data
$pengawasQuery = "SELECT ID, NAMA_USER FROM tbl_user WHERE ROLE = 'PENGAWAS' AND STATUS = 'ACTIVE'";
$stmt = executeQuery($pengawasQuery, []);
$pengawasList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing data
$query = "SELECT * FROM tbl_pekerjaan WHERE id_PEKERJAAN = :id_pekerjaan";
$stmt = executeQuery($query, ['id_pekerjaan' => $no_pekerjaan]);
$pekerjaan = $stmt->fetch(PDO::FETCH_ASSOC);

// If record not found, redirect back
if (!$pekerjaan) {
    header('Location: pekerjaan.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_pekerjaan = $_POST['kode_pekerjaan'];
    $nama_pekerjaan = $_POST['nama_pekerjaan'];
    $detail_pekerjaan = $_POST['detail_pekerjaan'];
    $jam_pekerjaan = $_POST['jam_pekerjaan'];
    $batas_pekerja = $_POST['batas_pekerja'];
    $id_penanggung_jawab = $_POST['id_penanggung_jawab'];
    
    // Get the selected pengawas name
    $pengawasName = '';
    foreach ($pengawasList as $pengawas) {
        if ($pengawas['ID'] == $id_penanggung_jawab) {
            $pengawasName = $pengawas['NAMA_USER'];
            break;
        }
    }

    $updateQuery = "UPDATE tbl_pekerjaan SET 
        KODE_PEKERJAAN = :KODE_PEKERJAAN,
        NAMA_PEKERJAAN = :NAMA_PEKERJAAN,
        DETAIL_PEKERJAAN = :DETAIL_PEKERJAAN,
        JAM_PEKERJAAN = :JAM_PEKERJAAN,
        BATAS_PEKERJA = :BATAS_PEKERJA,
        ID_PENANGGUNG_JAWAB = :ID_PENANGGUNG_JAWAB,
        PENANGGUNG_JAWAB = :PENANGGUNG_JAWAB
        WHERE id_PEKERJAAN = :id_pekerjaan";

    $params = [
        'KODE_PEKERJAAN' => $kode_pekerjaan,
        'NAMA_PEKERJAAN' => $nama_pekerjaan,
        'DETAIL_PEKERJAAN' => $detail_pekerjaan,
        'JAM_PEKERJAAN' => $jam_pekerjaan,
        'BATAS_PEKERJA' => $batas_pekerja,
        'ID_PENANGGUNG_JAWAB' => $id_penanggung_jawab,
        'PENANGGUNG_JAWAB' => $pengawasName,
        'id_pekerjaan' => $no_pekerjaan
    ];

    try {
        executeQuery($updateQuery, $params);
        header('Location: pekerjaan.php?success=1');
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pekerjaan</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Edit Data Pekerjaan</h1>
                        <p class="text-sm text-gray-600">Edit Details of Your Pekerjaan</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <img src="images/profile.png" alt="Profile" class="h-10 w-10 rounded-full border-2 border-emerald-500">
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
                        <label for="nama_pekerjaan" class="block text-sm font-medium text-gray-700">Nama Pekerjaan</label>
                        <input type="text" name="nama_pekerjaan" id="nama_pekerjaan" 
                            value="<?php echo htmlspecialchars($pekerjaan['NAMA_PEKERJAAN']); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label for="kode_pekerjaan" class="block text-sm font-medium text-gray-700">Kode Pekerjaan</label>
                        <input type="text" name="kode_pekerjaan" id="kode_pekerjaan" 
                            value="<?php echo htmlspecialchars($pekerjaan['KODE_PEKERJAAN']); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div>
    <label for="jam_pekerjaan_input" class="block text-sm font-medium text-gray-700">Jam Pekerjaan (jam)</label>
    <input type="number" 
           id="jam_pekerjaan_input" 
           step="0.01"
           min="0"
           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
    <p class="mt-1 text-sm text-gray-500">Hasil dalam menit: <span id="hasilMenit">0</span> menit</p>
    <input type="hidden" 
           name="jam_pekerjaan" 
           id="jam_pekerjaan" 
           value="<?php echo htmlspecialchars($pekerjaan['JAM_PEKERJAAN']); ?>">
</div>
                    <div>
                        <label for="batas_pekerja" class="block text-sm font-medium text-gray-700">Limit Pekerja</label>
                        <input type="number" name="batas_pekerja" id="batas_pekerja" 
                            value="<?php echo htmlspecialchars($pekerjaan['BATAS_PEKERJA']); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div class="col-span-2">
                        <label for="id_penanggung_jawab" class="block text-sm font-medium text-gray-700">Penanggung Jawab</label>
                        <select name="id_penanggung_jawab" id="id_penanggung_jawab" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Pilih Penanggung Jawab</option>
                            <?php foreach ($pengawasList as $pengawas): ?>
                                <option value="<?php echo htmlspecialchars($pengawas['ID']); ?>"
                                    <?php echo $pengawas['ID'] == $pekerjaan['ID_PENANGGUNG_JAWAB'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pengawas['NAMA_USER']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label for="detail_pekerjaan" class="block text-sm font-medium text-gray-700">Detail Pekerjaan</label>
                        <textarea name="detail_pekerjaan" id="detail_pekerjaan" rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><?php echo htmlspecialchars($pekerjaan['DETAIL_PEKERJAAN']); ?></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="pekerjaan.php" 
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" 
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
function toggleDropdown() {
    const dropdown = document.getElementById('dropdownMenu');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

function formatNumber(num) {
    // Jika bilangan bulat, tampilkan tanpa desimal
    if (Number.isInteger(num)) {
        return num.toString();
    }
    // Jika ada desimal, bulatkan ke 2 digit
    return num.toFixed(2);
}

function hitungMenit() {
    let jamInput = document.getElementById('jam_pekerjaan_input');
    let jamValue = parseFloat(jamInput.value) || 0;
    let menitValue = Math.round(jamValue * 60);

    // Update tampilan jam (format angka)
    jamInput.value = formatNumber(jamValue);
    
    // Update hidden input untuk menit dan tampilan hasil
    document.getElementById('jam_pekerjaan').value = jamValue;
    document.getElementById('hasilMenit').textContent = menitValue;
}

// Inisialisasi nilai awal dari menit ke jam
function initializeJamPekerjaan() {
    let menitInput = document.getElementById('jam_pekerjaan');
    let jamValue = parseFloat(menitInput.value);
    
    if (!isNaN(jamValue)) {
        // Set nilai jam
        document.getElementById('jam_pekerjaan_input').value = formatNumber(jamValue);
        // Set tampilan menit
        document.getElementById('hasilMenit').textContent = Math.round(jamValue * 60);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize jam pekerjaan value
    initializeJamPekerjaan();
    
    // Add event listeners
    const jamInput = document.getElementById('jam_pekerjaan_input');
    if (jamInput) {
        jamInput.addEventListener('input', hitungMenit);
        jamInput.addEventListener('change', hitungMenit);
    }
});
</script>
</body>
</html>