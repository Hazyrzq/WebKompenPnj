<?php
require_once '../Config.php';

// Inisialisasi variabel pesan dan referrer
$message = '';
$messageType = '';
$referrer = isset($_GET['ref']) ? $_GET['ref'] : 'daftarpengajuan/daftarpengajuanpengawas.php';
$referrer = filter_var($referrer, FILTER_SANITIZE_URL);

// Validasi referrer yang diizinkan
// Validasi referrer yang diizinkan
$allowedPaths = [
    '/Sikompen/pengawas/daftarpengajuan/daftarpengjuanpengawas.php',
    '/Sikompen/pengawas/daftardisetujui/daftardisetujuipengawas.php',
    '/Sikompen/pengawas/pekerjaan/pekerjaanpengawas.php'

];

if (!in_array($referrer, $allowedPaths)) {
    $referrer = 'daftarpengajuan/daftarpengajuanpengawas.php';
}

// Fungsi untuk mendapatkan data pengguna
function getUserData($nip)
{
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT NAMA_USER, NIP, EMAIL, ROLE, TTD, STATUS FROM tbl_USER WHERE NIP = :nip");
        $stmt->bindParam(':nip', $nip);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data && isset($data['TTD']) && $data['TTD'] !== null) {
            // Handle TTD data
            if (is_resource($data['TTD'])) {
                $data['TTD'] = stream_get_contents($data['TTD']);
            }
        }

        return $data;
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        return false;
    }
}

// Ambil data user
$userData = getUserData($_SESSION['nip']);
if (!$userData) {
    header("Location: login.php");
    exit;
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo = getDB();
        $nip = $_POST['nip'];
        $nama_user = $_POST['nama_user'];
        $email = $_POST['email'];
        $role = $_POST['role'];

        // Validasi input
        if (strlen($nip) !== 18) {
            throw new Exception("NIP harus 18 karakter");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid");
        }

        $valid_roles = ['ADMIN', 'PLP', 'KALAB', 'PENGAWAS'];
        if (!in_array($role, $valid_roles)) {
            throw new Exception("Role tidak valid");
        }

        // Mulai transaksi
        $pdo->beginTransaction();

        try {
            // Update data dasar
            $query = "UPDATE tbl_USER SET 
                     NAMA_USER = :nama_user,
                     EMAIL = :email,
                     ROLE = :role,
                     UPDATED_AT = CURRENT_TIMESTAMP";

            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query .= ", PASSWORD = :password";
            }

            $query .= " WHERE NIP = :nip";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':nama_user', $nama_user, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->bindParam(':nip', $nip, PDO::PARAM_STR);

            if (!empty($_POST['password'])) {
                $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            }

            $stmt->execute();

            // Handle TTD upload
            if (!empty($_FILES['ttd']['tmp_name'])) {
                try {
                    // Validate image
                    $check = getimagesize($_FILES["ttd"]["tmp_name"]);
                    if ($check === false) {
                        throw new Exception("File bukan gambar yang valid");
                    }

                    if ($_FILES["ttd"]["size"] > 5000000) {
                        throw new Exception("Ukuran file terlalu besar. Maksimal 5MB");
                    }

                    $allowedTypes = ['image/jpeg', 'image/png'];
                    if (!in_array($check['mime'], $allowedTypes)) {
                        throw new Exception("Hanya file JPG & PNG yang diizinkan");
                    }

                    // Baca file sebagai binary
                    $ttdBlob = file_get_contents($_FILES["ttd"]["tmp_name"]);

                    // Update TTD menggunakan EMPTY_BLOB dan RETURNING
                    $queryTTD = "UPDATE tbl_USER SET TTD = EMPTY_BLOB() WHERE NIP = :nip RETURNING TTD INTO :ttd_blob";
                    $stmtTTD = $pdo->prepare($queryTTD);

                    $stmtTTD->bindParam(':nip', $nip);

                    // Persiapkan BLOB handle
                    $blobHandle = fopen('php://memory', 'wb');
                    fwrite($blobHandle, $ttdBlob);
                    rewind($blobHandle);
                    $stmtTTD->bindParam(':ttd_blob', $blobHandle, PDO::PARAM_LOB);

                    $stmtTTD->execute();
                    fclose($blobHandle);
                } catch (Exception $e) {
                    throw new Exception("Error saat upload TTD: " . $e->getMessage());
                }
            }

            $pdo->commit();
            $message = "Profile berhasil diperbarui!";
            $messageType = "success";

            // Refresh data
            $userData = getUserData($_SESSION['nip']);
            

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}
// HTML code remains the same as your original
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    <link rel="icon" type="image/x-icon" href="daftardisetujui/images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Poppins', sans-serif;
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
                        <h1 class="text-2xl font-bold text-gray-800">Update Profile</h1>
                        <p class="text-sm text-gray-600">Edit Your Profile Information</p>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Message Display -->
    <?php if ($message): ?>
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div
                class="<?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> p-4 rounded-lg">
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Update Form -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <form method="POST" class="bg-white p-8 rounded-lg shadow-md" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="nama_user" class="block text-sm font-medium text-gray-700">Nama User</label>
                <input type="text" id="nama_user" name="nama_user"
                    value="<?php echo htmlspecialchars($userData['NAMA_USER']); ?>" required maxlength="100"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div class="mb-4">
                <label for="nip" class="block text-sm font-medium text-gray-700">NIP</label>
                <input type="text" id="nip" name="nip" value="<?php echo htmlspecialchars($userData['NIP']); ?>"
                    readonly class="mt-1 block w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-md">
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['EMAIL']); ?>"
                    required maxlength="100"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div class="mb-4">
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <select id="role" name="role" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                    <?php foreach (['ADMIN', 'PLP', 'KALAB', 'PENGAWAS'] as $role): ?>
                        <option value="<?php echo $role; ?>" <?php echo $userData['ROLE'] === $role ? 'selected' : ''; ?>>
                            <?php echo $role; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">Password Baru (kosongkan jika
                    tidak ingin mengubah)</label>
                <input type="password" id="password" name="password"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div class="mb-4">
                <label for="ttd" class="block text-sm font-medium text-gray-700">TTD Baru (Tanda Tangan)</label>
                <input type="file" id="ttd" name="ttd" accept="image/*"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                <p class="mt-1 text-sm text-gray-500">Upload file gambar tanda tangan baru (JPG, PNG, max 5MB)</p>
                <?php if (isset($userData['TTD']) && $userData['TTD'] !== null): ?>
                    <div class="mt-2">
                        <p class="text-sm text-gray-600">TTD Saat Ini:</p>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($userData['TTD']); ?>" alt="Tanda Tangan"
                            class="mt-1 max-h-32 border border-gray-300 rounded">
                    </div>
                <?php else: ?>
                    <p class="mt-2 text-sm text-gray-500">Belum ada TTD yang tersimpan</p>
                <?php endif; ?>
            </div>

            <div class="flex justify-end space-x-4">
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    Update Profile
                </button>
                <a href="<?php echo htmlspecialchars($referrer); ?>"
                    class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>

</html>