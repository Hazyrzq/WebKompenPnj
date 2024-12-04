<?php
require_once('../../Config.php');
$message = '';
$messageType = '';

// Check if ID is set
if (!isset($_GET['id'])) {
    header('Location: user.php');
    exit;
}

$id = $_GET['id'];

// Define valid roles
$validRoles = ['Admin', 'Kalab', 'Pengawas', 'PLP'];

// Fetch user data
try {
    $query = "SELECT * FROM tbl_USER WHERE ID = :id";
    $stmt = executeQuery($query, [':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }
} catch (Exception $e) {
    die("Error fetching user: " . $e->getMessage());
}

// Handle form submission for updating user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ROLE - with validation
        if (!empty($_POST['role'])) {
            if (!in_array($_POST['role'], $validRoles)) {
                throw new Exception("Invalid role selected. Valid roles are: " . implode(", ", $validRoles));
            }
            $updateQuery = "UPDATE tbl_USER SET ROLE = :role WHERE ID = :id";
            $params = [
                ':role' => strtoupper($_POST['role']),
                ':id' => $id
            ];
            executeQuery($updateQuery, $params);

            $message = "User role berhasil diupdate.";
            $messageType = "success";
            header('Location: user.php');
            exit;
        }
    } catch (Exception $e) {
        $message = "Error updating user role: " . $e->getMessage();
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User Role</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <div class="max-w-md mx-auto mt-10">
        <h1 class="text-2xl font-bold mb-4">Edit User Role</h1>
        <?php if ($message): ?>
            <div class="mb-4 <?php echo $messageType === 'success' ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form action="" method="POST">
            <div class="mb-4">
                <label class="block text-gray-700">Role</label>
                <select name="role" required class="border rounded-lg w-full p-2">
                    <option value="Admin" <?php echo $user['ROLE'] === 'ADMIN' ? 'selected' : ''; ?>>Admin</option>
                    <option value="Kalab" <?php echo $user['ROLE'] === 'KALAB' ? 'selected' : ''; ?>>Kalab</option>
                    <option value="PLP" <?php echo $user['ROLE'] === 'PLP' ? 'selected' : ''; ?>>PLP</option>
                    <option value="Pengawas" <?php echo $user['ROLE'] === 'PENGAWAS' ? 'selected' : ''; ?>>Pengawas
                    </option>
                </select>
            </div>
            <div class="flex justify-between">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Update
                    Role</button>
                <button type="button" onclick="window.location.href='user.php'"
                    class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">Cancel</button>
            </div>
        </form>
    </div>
</body>

</html>