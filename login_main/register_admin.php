<?php
// Redirect to setup.php which has the proper UI
header("Location: /Mariategue-DentalClinic/setup.php");
exit;

// Kung may admin na, hindi dapat ma-access ang page na ito
$countStmt = $pdo->query("SELECT COUNT(*) FROM admin");
$adminCount = (int) $countStmt->fetchColumn();

if ($adminCount > 0) {
    header("Location: /Mariategue-DentalClinic/login_main/login.php");
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Lahat ng fields ay required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid na email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Double-check na wala pang admin (race condition protection)
            $recheck = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
            if ($recheck > 0) {
                header("Location: /Mariategue-DentalClinic/login_main/login.php");
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO admin (username, email, password) VALUES (:username, :email, :password)");
            $stmt->execute([
                'username' => $username,
                'email'    => $email,
                'password' => $password   // Stored as plaintext para consistent sa existing login logic
            ]);

            $success = 'Admin account created! Redirecting to login...';
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account — Mariategue Ortho-DentalClinic</title>
    <link rel="stylesheet" href="login_design.css">
    <style>
        .form-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            text-align: left;
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
        .badge-warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: left;
        }
        .badge-warning i { margin-right: 6px; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Create Admin Account</h2>

        <div class="badge-warning">
            ⚠️ No admin account found. Please create one to continue.
        </div>

        <?php if ($error): ?>
            <div class="form-message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="form-message success" id="successMsg"><?= htmlspecialchars($success) ?></div>
            <script>
                setTimeout(() => {
                    window.location.href = '/Mariategue-DentalClinic/login_main/login.php';
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" id="registerForm">
            <label>Username:</label>
            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            <label>Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            <label>Password:</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" required>
                <span class="toggle-password" onclick="togglePassword(this, 'password')">SHOW</span>
            </div>

            <label>Confirm Password:</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" required>
                <span class="toggle-password" onclick="togglePassword(this, 'confirm_password')">SHOW</span>
            </div>

            <button type="submit">Create Admin Account</button>
        </form>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(span, inputId) {
            const pass = document.getElementById(inputId);
            if (pass.type === 'password') {
                pass.type = 'text';
                span.textContent = 'HIDE';
            } else {
                pass.type = 'password';
                span.textContent = 'SHOW';
            }
        }
    </script>
</body>
</html>
