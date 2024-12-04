<?php
require_once '../config/config.php';

// Fungsi untuk mengecek login attempts
function checkLoginAttempts($nim) {
    try {
        $query = "SELECT attempts, last_attempt FROM login_attempts WHERE nim = :nim";
        $stmt = executeQuery($query, [':nim' => $nim]);
        $row = $stmt->fetch();
        
        if (!$row) {
            $query = "INSERT INTO login_attempts (nim, attempts, last_attempt) VALUES (:nim, 0, SYSTIMESTAMP)";
            executeQuery($query, [':nim' => $nim]);
            return true;
        }
        
        // Reset attempts after 30 minutes
        $lastAttempt = strtotime($row['LAST_ATTEMPT']);
        if (time() - $lastAttempt > 1800) {
            resetLoginAttempts($nim);
            return true;
        }
        
        return $row['ATTEMPTS'] < 5;
    } catch (PDOException $e) {
        die("Error checking login attempts: " . $e->getMessage());
    }
}

// Reset login attempts
function resetLoginAttempts($nim) {
    try {
        $query = "UPDATE login_attempts SET attempts = 0, last_attempt = SYSTIMESTAMP WHERE nim = :nim";
        executeQuery($query, [':nim' => $nim]);
    } catch (PDOException $e) {
        die("Error resetting login attempts: " . $e->getMessage());
    }
}

// Update login attempts
function updateLoginAttempts($nim, $success = false) {
    try {
        if ($success) {
            resetLoginAttempts($nim);
        } else {
            $query = "UPDATE login_attempts SET attempts = attempts + 1, last_attempt = SYSTIMESTAMP WHERE nim = :nim";
            executeQuery($query, [':nim' => $nim]);
        }
    } catch (PDOException $e) {
        die("Error updating login attempts: " . $e->getMessage());
    }
}

// Verify user login
function verifyLogin($nim, $password) {
    try {
        $query = "SELECT * FROM tbl_mahasiswa WHERE nim = :nim";
        $stmt = executeQuery($query, [':nim' => $nim]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['PASSWORD'])) {
            return $user;
        }
        return false;
    } catch (PDOException $e) {
        die("Error verifying login: " . $e->getMessage());
    }
}

// Set remember me token
function setRememberToken($nim) {
    try {
        $token = generateToken();
        $query = "UPDATE tbl_mahasiswa SET remember_token = :token WHERE nim = :nim";
        executeQuery($query, [':token' => $token, ':nim' => $nim]);
        return $token;
    } catch (PDOException $e) {
        die("Error setting remember token: " . $e->getMessage());
    }
}

// Verify remember token
function verifyRememberToken($nim, $token) {
    try {
        $query = "SELECT * FROM tbl_mahasiswa WHERE nim = :nim AND remember_token = :token";
        $stmt = executeQuery($query, [':nim' => $nim, ':token' => $token]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        die("Error verifying remember token: " . $e->getMessage());
    }
}

// Set password reset token
function setPasswordResetToken($nim) {
    try {
        $token = generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $query = "UPDATE tbl_mahasiswa SET reset_token = :token, 
                  reset_expires = TO_TIMESTAMP(:expires, 'YYYY-MM-DD HH24:MI:SS') 
                  WHERE nim = :nim";
        
        executeQuery($query, [
            ':token' => $token,
            ':expires' => $expires,
            ':nim' => $nim
        ]);
        
        return $token;
    } catch (PDOException $e) {
        die("Error setting reset token: " . $e->getMessage());
    }
}

// Verify reset token
function verifyResetToken($nim, $token) {
    try {
        $query = "SELECT reset_expires FROM tbl_mahasiswa 
                  WHERE nim = :nim AND reset_token = :token 
                  AND reset_expires > SYSTIMESTAMP";
        
        $stmt = executeQuery($query, [':nim' => $nim, ':token' => $token]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        die("Error verifying reset token: " . $e->getMessage());
    }
}

// Update password
function updatePassword($nim, $password) {
    try {
        $hashedPassword = hashPassword($password);
        $query = "UPDATE tbl_mahasiswa 
                  SET password = :password, reset_token = NULL, reset_expires = NULL 
                  WHERE nim = :nim";
        
        executeQuery($query, [':password' => $hashedPassword, ':nim' => $nim]);
        return true;
    } catch (PDOException $e) {
        die("Error updating password: " . $e->getMessage());
    }
}

// Other utility functions remain the same
function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function sendResetEmail($email, $token, $nim) {
    $to = $email;
    $subject = "Reset Password";
    $reset_link = SITE_URL . "/reset_password.php?token=" . $token . "&nim=" . $nim;

    $message = "
    <html>
    <head>
        <title>Reset Password</title>
    </head>
    <body>
        <h2>Reset Password</h2>
        <p>Anda telah meminta untuk reset password akun Anda.</p>
        <p>Klik link berikut untuk melakukan reset password:</p>
        <p><a href='{$reset_link}'>{$reset_link}</a></p>
        <p>Link ini akan kadaluarsa dalam 1 jam.</p>
        <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . EMAIL_FROM . "\r\n";

    // SMTP configuration
    ini_set('SMTP', 'smtp.gmail.com');
    ini_set('smtp_port', 587);
    ini_set('smtp_secure', 'tls');
    ini_set('smtp_auth', true);
    ini_set('smtp_username', 'your_gmail_username@gmail.com');
    ini_set('smtp_password', 'your_gmail_password');

    // Send the email
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        return false;
    }
}

function registerUser($nama, $nim, $password, $email) {
    try {
        // Hash password before saving to the database
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // SQL query to insert user data into the database
        $query = "INSERT INTO tbl_mahasiswa (id_mhs, nama, nim, password, email) 
                  VALUES (seq_id_mhs.NEXTVAL, :nama, :nim, :password, :email)";
        
        // Execute query with user data
        executeQuery($query, [
            ':nama' => $nama,
            ':nim' => $nim,
            ':password' => $hashedPassword,  // Save the hashed password
            ':email' => $email
        ]);
    } catch (PDOException $e) {
        die("Error registering user: " . $e->getMessage());
    }
}
?>