<?php
session_start();
include __DIR__ . '/miscellaneous/database.php';

// Check if admin account exists
$stmt = $pdo->query("SELECT COUNT(*) FROM admin");
$adminCount = $stmt->fetchColumn();

// If admin already exists, redirect to login
if ($adminCount > 0) {
    header("Location: login_main/login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Insert admin — plain password to match existing login logic
        $stmt = $pdo->prepare("INSERT INTO admin (username, email, password) VALUES (:username, :email, :password)");
        $stmt->execute([
            'username' => $username,
            'email'    => $email,
            'password' => $password,
        ]);
        $success = 'Admin account created! Redirecting to login...';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Clinic Setup — Create Admin Account</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 60%, #e8f5e9 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
      padding: 20px;
    }

    .setup-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(14, 165, 233, 0.13);
      padding: 40px 36px;
      width: 100%;
      max-width: 460px;
    }

    .setup-header {
      text-align: center;
      margin-bottom: 28px;
    }

    .setup-header .icon {
      width: 60px;
      height: 60px;
      background: #eff6ff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 14px;
      font-size: 26px;
      color: #0ea5e9;
    }

    .setup-header h1 {
      font-size: 22px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 6px;
    }

    .setup-header p {
      font-size: 13.5px;
      color: #64748b;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #fef3c7;
      color: #92400e;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 20px;
      margin-top: 10px;
      border: 1px solid #fde68a;
    }

    .alert {
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 13.5px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .alert.error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
    .alert.success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #475569;
      margin-bottom: 6px;
    }

    .input-wrap {
      position: relative;
    }

    .input-wrap i.fa-lock,
    .input-wrap i.fa-user,
    .input-wrap i.fa-envelope {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 14px;
      pointer-events: none;
    }

    input {
      width: 100%;
      padding: 11px 40px 11px 38px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      color: #0f172a;
      background: #f8fafc;
      transition: border-color 0.2s, box-shadow 0.2s;
      outline: none;
      box-sizing: border-box;
    }

    input:focus {
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
      background: #fff;
    }

    .toggle-pw {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 15px;
      color: #94a3b8;
      cursor: pointer;
      user-select: none;
      transition: color 0.2s;
    }

    .toggle-pw:hover {
      color: #0ea5e9;
    }

    .btn-submit {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #0ea5e9, #0369a1);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 6px;
      transition: opacity 0.2s, transform 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-submit:hover {
      opacity: 0.92;
      transform: translateY(-1px);
    }

    .divider {
      border: none;
      border-top: 1px solid #e2e8f0;
      margin: 22px 0;
    }

    .footnote {
      text-align: center;
      font-size: 12px;
      color: #94a3b8;
    }
  </style>
</head>
<body>

  <div class="setup-card">

    <div class="setup-header">
      <div class="icon"><i class="fas fa-tooth"></i></div>
      <h1>Mariategue Ortho-DentalClinic</h1>
      <p>First-time setup — no admin account detected.</p>
      <span class="badge"><i class="fas fa-shield-halved"></i> Initial Setup Mode</span>
    </div>

    <?php if ($error): ?>
      <div class="alert error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert success" id="successAlert"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
      <script>
        setTimeout(() => { window.location.href = 'login_main/login.php'; }, 2000);
      </script>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" autocomplete="off">

      <div class="form-group">
        <label for="username">Username</label>
        <div class="input-wrap">
          <i class="fas fa-user"></i>
          <input type="text" id="username" name="username" placeholder="Enter admin username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required />
        </div>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email" placeholder="admin@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required />
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" id="password" name="password" placeholder="At least 6 characters" required />
          <span class="toggle-pw" onclick="togglePw('password', this)"><i class="fas fa-eye"></i></span>
        </div>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required />
          <span class="toggle-pw" onclick="togglePw('confirm_password', this)"><i class="fas fa-eye"></i></span>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <i class="fas fa-user-shield"></i> Create Admin Account
      </button>

    </form>
    <?php endif; ?>

    <hr class="divider"/>
    <p class="footnote">This page is only accessible when no admin account exists.<br>After setup, this page will redirect to login automatically.</p>

  </div>

  <script>
    function togglePw(id, span) {
      const input = document.getElementById(id);
      const icon = span.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
  </script>

</body>
</html>
