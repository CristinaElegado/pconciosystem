<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/log_audit.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $Email = trim($_POST['email']);
    $password = trim($_POST['password']);

    header('Content-Type: application/json');

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $Email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if ($password === $admin['password']) {
            $_SESSION['id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['user_type'] = "admin";

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
            $_SESSION['id'] = $dentist['id'];
            $_SESSION['username'] = $dentist['first_name'];
            $_SESSION['user_type'] = "dentist";

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
            $_SESSION['id'] = $staff['id'];
            $_SESSION['username'] = $staff['first_name'];
            $_SESSION['staff_id'] = $staff['staff_id'];
            $_SESSION['user_type'] = "staff";

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