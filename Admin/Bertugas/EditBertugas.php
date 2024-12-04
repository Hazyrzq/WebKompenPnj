<?php
session_start();
require_once('../../Config.php');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: Bertugas.php');
    exit();
}

$id = $_GET['id'];

// Get current data
$query = "SELECT b.*, u.NAMA_USER, u.ROLE 
          FROM SETUP_BERTUGAS b
          JOIN TBL_USER u ON b.NIP = u.NIP
          WHERE b.ID = :id";
$stmt = executeQuery($query, ['id' => $id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header('Location: Bertugas.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Ambil tanggal dan jam dari form
        $tanggal = $_POST['tanggal'];
        $jam = $_POST['jam'];

        // Gabungkan tanggal dan jam
        $tanggal_bertugas = date('Y-m-d H:i:s', strtotime("$tanggal $jam"));

        // Update query
        $updateQuery = "UPDATE SETUP_BERTUGAS 
                       SET TANGGAL_BERTUGAS = TO_DATE(:tanggal_bertugas, 'YYYY-MM-DD HH24:MI:SS')
                       WHERE ID = :id";

        $params = [
            'id' => $id,
            'tanggal_bertugas' => $tanggal_bertugas
        ];

        executeQuery($updateQuery, $params);

        header('Location: Bertugas.php');
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Format tanggal dan jam untuk ditampilkan di form
$tanggal_value = date('Y-m-d', strtotime($data['TANGGAL_BERTUGAS']));
$jam_value = date('H:i', strtotime($data['TANGGAL_BERTUGAS']));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Bertugas</title>
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
            <h2 class="text-2xl font-bold mb-4">Edit Data Bertugas</h2>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">User</label>
                    <input type="text"
                        value="<?php echo htmlspecialchars($data['NAMA_USER']); ?> - <?php echo htmlspecialchars($data['ROLE']); ?>"
                        disabled class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-100 p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">NIP</label>
                    <input type="text" value="<?php echo htmlspecialchars($data['NIP']); ?>" disabled
                        class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-100 p-2">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                        <input type="date" name="tanggal" value="<?php echo $tanggal_value; ?>" required
                            class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jam</label>
                        <input type="time" name="jam" value="<?php echo $jam_value; ?>" required
                            class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="Bertugas.php"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>