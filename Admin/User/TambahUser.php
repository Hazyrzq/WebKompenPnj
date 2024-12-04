<?php
require_once('../../config/koneksi.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_user = $_POST['nama_user'];
    $nip = $_POST['nip'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'];
    $ttdBlob = null;

    if (!empty($_FILES['ttd']['name'])) {
        // Validate and read the file
        $check = getimagesize($_FILES["ttd"]["tmp_name"]);
        if ($check === false) {
            throw new Exception("File is not an image.");
        }

        if ($_FILES["ttd"]["size"] > 5000000) {
            throw new Exception("File is too large. Max size is 5MB.");
        }

        $allowedTypes = ['jpg', 'jpeg', 'png'];
        $imageFileType = strtolower(pathinfo($_FILES["ttd"]["name"], PATHINFO_EXTENSION));
        if (!in_array($imageFileType, $allowedTypes)) {
            throw new Exception("Only JPG, JPEG & PNG files are allowed.");
        }

        // Read the file as binary for storing in BLOB
        $ttdBlob = file_get_contents($_FILES["ttd"]["tmp_name"]);
    }

    if (strlen($nip) !== 18) {
        die("Error: NIP harus 18 karakter");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Error: Invalid email format");
    }

    $valid_roles = ['ADMIN', 'PLP', 'KALAB', 'PENGAWAS'];
    if (!in_array($role, $valid_roles)) {
        die("Error: Invalid role selected");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo = connectDB();
    
        // Mulai transaksi
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    
        $query = "INSERT INTO tbl_USER (
            NAMA_USER, 
            NIP, 
            EMAIL, 
            ROLE, 
            PASSWORD,
            TTD,
            CREATED_AT,
            UPDATED_AT,
            STATUS
        ) VALUES (
            :nama_user, 
            :nip, 
            :email, 
            :role, 
            :password,
            EMPTY_BLOB(),
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            'ACTIVE'
        ) RETURNING TTD INTO :ttd_blob";
    
        $stmt = $pdo->prepare($query);
    
        $stmt->bindParam(':nama_user', $nama_user);
        $stmt->bindParam(':nip', $nip);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':password', $hashed_password);
    
        $blobHandle = fopen('php://memory', 'wb');
        fwrite($blobHandle, $ttdBlob);
        rewind($blobHandle);
        $stmt->bindParam(':ttd_blob', $blobHandle, PDO::PARAM_LOB);
    
        $stmt->execute();
    
        // Komit transaksi
        $pdo->commit();
    
        fclose($blobHandle);
    
        header("Location: user.php");
        exit;
    } catch (Exception $e) {
        // Rollback jika transaksi aktif
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error: " . $e->getMessage());
    }
}    
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data User</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Poppins', sans-serif;
        }
    </style>
    <script>
        function validateNIP(input) {
            if (input.value.length !== 18) {
                input.setCustomValidity('NIP harus 18 karakter');
            } else {
                input.setCustomValidity('');
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="images/LogoPNJ.png" alt="Logo" class="h-12 w-12">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Tambah Data User</h1>
                        <p class="text-sm text-gray-600">Add a New User to the System</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <img src="images/profile.png" alt="Profile"
                        class="h-10 w-10 rounded-full border-2 border-emerald-500">
                </div>
            </div>
        </div>
    </nav>

    <!-- Form for Adding User -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <form method="POST" class="bg-white p-8 rounded-lg shadow-md" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="nama_user" class="block text-sm font-medium text-gray-700">Nama User</label>
                <input type="text" id="nama_user" name="nama_user" required maxlength="100"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div class="mb-4">
                <label for="nip" class="block text-sm font-medium text-gray-700">NIP</label>
                <input type="text" id="nip" name="nip" required minlength="18" maxlength="18" pattern="[0-9]{18}"
                    oninput="validateNIP(this)"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                <p class="mt-1 text-sm text-gray-500">NIP harus tepat 18 angka</p>
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" name="email" required maxlength="100"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div class="mb-4">
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <select id="role" name="role" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="ADMIN">Admin</option>
                    <option value="PLP">PLP</option>
                    <option value="KALAB">Kalab</option>
                    <option value="PENGAWAS">Pengawas</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" name="password" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div class="mb-4">
                <label for="ttd" class="block text-sm font-medium text-gray-700">TTD (Tanda Tangan)</label>
                <input type="file" id="ttd" name="ttd" accept="image/*" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                <p class="mt-1 text-sm text-gray-500">Upload file gambar tanda tangan (JPG, PNG, max 5MB)</p>
            </div>

            <div class="flex justify-end space-x-4">
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    Tambah Data
                </button>
                <a href="user.php" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>

</html>