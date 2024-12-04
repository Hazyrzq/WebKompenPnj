<?php
session_start();
require_once('../../Config.php');
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

$userNama = $_SESSION['nama_user'];
$userRole = $_SESSION['role'];

// Get available users
$userQuery = "SELECT NIP, NAMA_USER, ROLE FROM TBL_USER WHERE STATUS = 'ACTIVE' ORDER BY NAMA_USER";
$userStmt = executeQuery($userQuery);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nip = $_POST['nip'];
        $tanggal = $_POST['tanggal'];
        $jam = $_POST['jam'];

        // Gabungkan tanggal dan jam
        $tanggal_bertugas = date('Y-m-d H:i:s', strtotime("$tanggal $jam"));

        // Get user data first
        $getUserQuery = "SELECT NAMA_USER, ROLE FROM TBL_USER WHERE NIP = :nip";
        $userDataStmt = executeQuery($getUserQuery, ['nip' => $nip]);
        $userData = $userDataStmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            // Insert query dengan semua kolom yang diperlukan
            $insertQuery = "INSERT INTO SETUP_BERTUGAS (NIP, NAMA_USER, ROLE, TANGGAL_BERTUGAS) 
                          VALUES (:nip, :nama_user, :role, TO_DATE(:tanggal_bertugas, 'YYYY-MM-DD HH24:MI:SS'))";

            $params = [
                'nip' => $nip,
                'nama_user' => $userData['NAMA_USER'],
                'role' => $userData['ROLE'],
                'tanggal_bertugas' => $tanggal_bertugas
            ];

            executeQuery($insertQuery, $params);

            header('Location: Bertugas.php');
            exit();
        } else {
            $error = "User not found!";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Set default datetime value untuk input (current time)
$default_datetime = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data Bertugas</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
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
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-4">Tambah Data Bertugas</h2>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">User</label>
                    <select name="nip" required
                        class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['NIP']); ?>">
                                <?php echo htmlspecialchars($user['NAMA_USER']); ?> -
                                <?php echo htmlspecialchars($user['ROLE']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                        <input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required
                            class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jam</label>
                        <input type="time" name="jam" value="<?php echo date('H:i'); ?>" required
                            class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>


                <div class="flex justify-end space-x-3">
                    <a href="Bertugas.php"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>