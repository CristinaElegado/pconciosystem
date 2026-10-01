<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/log_audit.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// If no admin account exists yet, redirect to setup page
try {
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
    if ((int)$adminCount === 0) {
        header("Location: ../setup.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: ../setup.php");
    exit;
}

// ── Already logged in? Restore from cookie if needed, then redirect away ──────
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    if (!isset($_SESSION['id']) || !isset($_SESSION['user_type'])) {
        include_once __DIR__ . '/../miscellaneous/session_restore.php';
    }
    if (isset($_SESSION['id'], $_SESSION['user_type'])) {
        // User is already authenticated — no need to see the login page
        header("Location: /patient_list/patient_list.php");
        exit;
    }
}
// ───────────────────────────────────────────────────────────────────────────────

// ── Run column migrations once per request (safe, catch if already exists) ───
$_loginMigTables = ['admin', 'dentist_accounts', 'staff_accounts'];
foreach ($_loginMigTables as $_t) {
    try { $pdo->exec("ALTER TABLE `$_t` ADD COLUMN session_token VARCHAR(64) NULL DEFAULT NULL"); } catch (PDOException $_e) { /* already exists */ }
    try { $pdo->exec("ALTER TABLE `$_t` ADD COLUMN login_ip       VARCHAR(45) NULL DEFAULT NULL"); } catch (PDOException $_e) { /* already exists */ }
}
unset($_loginMigTables, $_t, $_e);

if (!function_exists('get_client_ip')) {
    function get_client_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if ($val === '') continue;
            $ip = trim(explode(',', $val)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return '0.0.0.0';
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $Email = trim($_POST['email']);
    $password = trim($_POST['password']);

    header('Content-Type: application/json');

    $clientIp = get_client_ip();

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $Email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if ($password === $admin['password']) {
            // ── Single-session block ──────────────────────────────────────────
            $existingToken = $pdo->prepare("SELECT session_token, login_ip FROM admin WHERE id = :id LIMIT 1");
            $existingToken->execute(['id' => $admin['id']]);
            $tokenRow = $existingToken->fetch();
            if (!empty($tokenRow['session_token'])) {
                $loggedInIp = $tokenRow['login_ip'] ?? '';
                if ($loggedInIp !== $clientIp) {
                    echo json_encode(['success' => false, 'message' => "⚠️ This account is already logged in from IP $loggedInIp. Please log out from that device first."]);
                    exit;
                }
            }
            // ─────────────────────────────────────────────────────────────────
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE admin SET session_token = :token, login_ip = :ip WHERE id = :id")->execute(['token' => $token, 'ip' => $clientIp, 'id' => $admin['id']]);

            $_SESSION['id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['user_type'] = "admin";
            $_SESSION['session_token'] = $token;
            $_SESSION['login_ip'] = $clientIp;
            setcookie('__st', $token, ['expires'=>time()+28800,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);

            log_audit($pdo, 'admin', $admin['username'], 'Logged In', 'Admin successfully logged in.');

            echo json_encode(['success' => true, 'redirect' => '/patient_list/patient_list.php']);
            exit;
        } else {
            log_audit($pdo, 'admin', $admin['username'], 'Failed Login Attempt', 'Wrong password for admin email: ' . $Email);
        }
    }

    // ✅ TRY DENTIST LOGIN
    $stmt = $pdo->prepare("SELECT * FROM dentist_accounts WHERE email = :email AND is_active = 1 LIMIT 1");
    $stmt->execute(['email' => $Email]);
    $dentist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dentist) {
        if (password_verify($password, $dentist['password_hash'])) {
            // ── Single-session block ──────────────────────────────────────────
            $existingToken = $pdo->prepare("SELECT session_token, login_ip FROM dentist_accounts WHERE id = :id LIMIT 1");
            $existingToken->execute(['id' => $dentist['id']]);
            $tokenRow = $existingToken->fetch();
            if (!empty($tokenRow['session_token'])) {
                $loggedInIp = $tokenRow['login_ip'] ?? '';
                if ($loggedInIp !== $clientIp) {
                    echo json_encode(['success' => false, 'message' => "⚠️ This account is already logged in from IP $loggedInIp. Please log out from that device first."]);
                    exit;
                }
            }
            // ─────────────────────────────────────────────────────────────────
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE dentist_accounts SET session_token = :token, login_ip = :ip WHERE id = :id")->execute(['token' => $token, 'ip' => $clientIp, 'id' => $dentist['id']]);

            $_SESSION['id'] = $dentist['id'];
            $_SESSION['username'] = $dentist['first_name'];
            $_SESSION['user_type'] = "dentist";
            $_SESSION['session_token'] = $token;
            $_SESSION['login_ip'] = $clientIp;
            setcookie('__st', $token, ['expires'=>time()+28800,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);

            log_audit($pdo, 'dentist', $dentist['first_name'] . ' ' . $dentist['last_name'], 'Logged In', 'Dentist successfully logged in.');

            echo json_encode(['success' => true, 'redirect' => '/patient_list/patient_list.php']);
            exit;
        } else {
            log_audit($pdo, 'dentist', $dentist['first_name'] . ' ' . $dentist['last_name'], 'Failed Login Attempt', 'Wrong password for dentist email: ' . $Email);
        }
    }

    // ✅ TRY STAFF LOGIN
    $stmt = $pdo->prepare("SELECT * FROM staff_accounts WHERE email = :email AND is_active = 1 LIMIT 1");
    $stmt->execute(['email' => $Email]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staff) {
        if (password_verify($password, $staff['password_hash'])) {
            // ── Single-session block ──────────────────────────────────────────
            $existingToken = $pdo->prepare("SELECT session_token, login_ip FROM staff_accounts WHERE id = :id LIMIT 1");
            $existingToken->execute(['id' => $staff['id']]);
            $tokenRow = $existingToken->fetch();
            if (!empty($tokenRow['session_token'])) {
                $loggedInIp = $tokenRow['login_ip'] ?? '';
                if ($loggedInIp !== $clientIp) {
                    echo json_encode(['success' => false, 'message' => "⚠️ This account is already logged in from IP $loggedInIp. Please log out from that device first."]);
                    exit;
                }
            }
            // ─────────────────────────────────────────────────────────────────
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE staff_accounts SET session_token = :token, login_ip = :ip WHERE id = :id")->execute(['token' => $token, 'ip' => $clientIp, 'id' => $staff['id']]);

            $_SESSION['id'] = $staff['id'];
            $_SESSION['username'] = $staff['first_name'];
            $_SESSION['staff_id'] = $staff['staff_id'];
            $_SESSION['user_type'] = "staff";
            $_SESSION['session_token'] = $token;
            $_SESSION['login_ip'] = $clientIp;
            setcookie('__st', $token, ['expires'=>time()+28800,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);

            log_audit($pdo, 'staff', $staff['first_name'] . ' ' . $staff['last_name'], 'Logged In', 'Staff successfully logged in.');

            echo json_encode(['success' => true, 'redirect' => '/patient_list/patient_list.php']);
            exit;
        } else {
            log_audit($pdo, 'staff', $staff['first_name'] . ' ' . $staff['last_name'], 'Failed Login Attempt', 'Wrong password for staff email: ' . $Email);
        }
    }

    // ❌ INVALID CREDENTIALS — walang nahanap na account
    log_audit($pdo, 'unknown', $Email, 'Failed Login Attempt', 'No matching account found.');
    echo json_encode(['success' => false, 'message' => 'Invalid email or password!']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PconcioClinic</title>
    <link rel="stylesheet" href="login_design.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .form-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: none;
        }
        .form-message.error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #f87171;
        }
        .form-message.success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
    </style>
</head>
<body>
       
    <div class="register-container">
    <h2>Login</h2>
    <div id="loginMessage" class="form-message"></div>

    <form method="POST" id="loginForm">
        <label>Email:</label>
        <input type="text" name="email" id="email" required>

        <label>Password:</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" required>
            <span class="toggle-password" onclick="togglePassword(this, 'password')"><i class="fa fa-eye"></i></span>
        </div>

        <button type="submit">Login</button>
    </form>

    <div class="small-text">
        <a href="">Forgot Password?</a>
    </div>
    </div>

    <script>
        document.getElementById('loginForm').onsubmit = async function(e) {
            e.preventDefault();
            const messageDiv = document.getElementById('loginMessage');
            messageDiv.style.display = 'none';
            messageDiv.className = 'form-message';

            const formData = new FormData(this);
            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    messageDiv.textContent = result.message;
                    messageDiv.classList.add('error');
                    messageDiv.style.display = 'block';
                }
            } catch (err) {
                messageDiv.textContent = "An unexpected error occurred. Please try again.";
                messageDiv.classList.add('error');
                messageDiv.style.display = 'block';
            }
        };

        function togglePassword(span, inputId) {
            const pass = document.getElementById(inputId);
            const icon = span.querySelector('i');
            if (pass.type === 'password') {
                pass.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pass.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>

</body>
</html>