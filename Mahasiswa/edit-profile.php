<?php
// edit-profile.php
require_once '../config/koneksi.php';

if (!isset($_SESSION['nim'])) {
    header("Location: ../loginmhs/login.php");
    exit();
}

$nim = $_SESSION['nim'];

try {
    // Fetch user data
    $stmt = executeQuery(
        "SELECT nim, nama, email, notelp FROM tbl_mahasiswa WHERE nim = :nim",
        ['nim' => $nim]
    );
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found");
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'];
        $notelp = $_POST['notelp'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        $error = null;
        
        // Validate inputs
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Password baru dan konfirmasi password tidak cocok";
        } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
            $error = "Password minimal 6 karakter";
        }
        
        if (!$error) {
            $updateFields = [];
            $params = [];
            
            // Build dynamic update query
            $updateFields[] = "email = :email";
            $params['email'] = $email;
            
            $updateFields[] = "notelp = :notelp";
            $params['notelp'] = $notelp;
            
            if (!empty($newPassword)) {
                $updateFields[] = "password = :password";
                $params['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            
            $params['nim'] = $nim;
            
            $query = "UPDATE tbl_mahasiswa SET " . implode(", ", $updateFields) . " WHERE nim = :nim";
            
            if (executeQuery($query, $params)) {
                $success = "Profile berhasil diperbarui!";
                // Refresh user data
                $stmt = executeQuery(
                    "SELECT nim, nama, email, notelp FROM tbl_mahasiswa WHERE nim = :nim",
                    ['nim' => $nim]
                );
                $user = $stmt->fetch();
            } else {
                $error = "Gagal memperbarui profile";
            }
        }
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="icon" type="image/x-icon" href="../images/LogoPNJ.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
    --primary-color: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #818cf8;
    --background: #f8fafc;
    --card-bg: #ffffff;
    --text-primary: #2c3e50;
    --text-secondary: #6b7280;
    --border-color: #e2e8f0;
    --error-color: #ef4444;
    --success-color: #10b981;
}

.profile-form-container {
    background: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    padding: 2rem;
    width: 100%;
    height: calc(100vh - 3rem);
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.section-title {
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

/* Form Layout */
.form-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    flex: 1;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.input-group {
    position: relative;
}

/* Label Styles */
.input-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.input-label i {
    color: var(--primary-color);
    font-size: 1rem;
}

/* Input Styles */
.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    border: 1.5px solid var(--border-color);
    border-radius: 8px;
    background-color: var(--background);
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

/* Disabled Input */
.form-control:disabled {
    background-color: #f1f5f9;
    border-color: var(--border-color);
    color: var(--text-secondary);
    cursor: not-allowed;
}

/* Valid State */
.form-control.is-valid {
    border-color: var(--success-color);
    padding-right: 2.5rem;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2310b981' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
}

/* Invalid State */
.form-control.is-invalid {
    border-color: var(--error-color);
    padding-right: 2.5rem;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23ef4444'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23ef4444' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
}

/* Password Fields */
.password-group {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0.25rem;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}

.password-toggle:hover {
    color: var(--primary-color);
}

/* Error Message */
.error-message {
    font-size: 0.85rem;
    color: var(--error-color);
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Button Container */
.btn-container {
    display: grid;
    grid-template-columns: repeat(2, minmax(120px, 1fr));
    gap: 1rem;
    margin-top: auto;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
    gap: 0.5rem;
    cursor: pointer;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-outline-secondary {
    background-color: transparent;
    border: 1.5px solid var(--border-color);
    color: var(--text-secondary);
}

.btn-outline-secondary:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-1px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .profile-form-container {
        padding: 1.5rem;
    }

    .btn-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .profile-form-container {
        padding: 1rem;
    }

    .section-title {
        font-size: 1.25rem;
    }
}
    </style>
</head>
<body class="bg-light">
    <div id="main-content">
        <div class="profile-form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <h2 class="section-title">Edit Profile</h2>

            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nim" placeholder="NIM"
                           value="<?php echo htmlspecialchars($user['NIM']); ?>" disabled>
                    <label for="nim"><i class="fas fa-id-card me-2"></i>NIM</label>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nama" placeholder="Nama"
                           value="<?php echo htmlspecialchars($user['NAMA']); ?>" disabled>
                    <label for="nama"><i class="fas fa-user me-2"></i>Nama</label>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email"
                           value="<?php echo htmlspecialchars($user['EMAIL']); ?>" required>
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                    <div class="invalid-feedback">Masukkan email yang valid</div>
                </div>

                <div class="form-floating mb-3">
                    <input type="tel" class="form-control" id="notelp" name="notelp" placeholder="Nomor Telepon"
                           value="<?php echo htmlspecialchars($user['NOTELP']); ?>" required pattern="[0-9]{10,13}">
                    <label for="notelp"><i class="fas fa-phone me-2"></i>Nomor Telepon</label>
                    <div class="invalid-feedback">Masukkan nomor telepon yang valid (10-13 digit)</div>
                </div>

                <div class="form-floating mb-3 position-relative">
                    <input type="password" class="form-control" id="new_password" name="new_password"
                           placeholder="Password Baru" minlength="6">
                    <label for="new_password"><i class="fas fa-lock me-2"></i>Password Baru (Opsional)</label>
                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                    <div class="invalid-feedback">Password minimal 6 karakter</div>
                </div>

                <div class="form-floating mb-3 position-relative">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                           placeholder="Konfirmasi Password">
                    <label for="confirm_password"><i class="fas fa-lock me-2"></i>Konfirmasi Password</label>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                    <div class="invalid-feedback">Password tidak cocok</div>
                </div>

                <div class="btn-container">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Simpan Perubahan
                    </button>
                    <a href="?page=beranda" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
            initializeSidebarResponsiveness();
        });

        function initializeForm() {
            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            // Password validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');

            if (confirmPassword && newPassword) {
                const validatePasswords = () => {
                    if (confirmPassword.value !== newPassword.value) {
                        confirmPassword.setCustomValidity('Password tidak cocok');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                };

                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }

            // Form input animations
            document.querySelectorAll('.form-control').forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', () => {
                    input.parentElement.classList.remove('focused');
                });
            });
        }

        function initializeSidebarResponsiveness() {
            const mainContent = document.getElementById('main-content');
            
            // Check initial sidebar state
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed') {
                mainContent.classList.add('expanded');
            }

            // Listen for sidebar toggle events
            document.addEventListener('sidebarToggle', function(e) {
                mainContent.classList.toggle('expanded');
            });

            // Handle window resize
            let timeout;
            window.addEventListener('resize', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (window.innerWidth <= 991) {
                        mainContent.classList.remove('expanded');
                    } else if (sidebarState === 'collapsed') {
                        mainContent.classList.add('expanded');
                    }
                }, 100);
            });
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }
    </script>
</body>
</html>