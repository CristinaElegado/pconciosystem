<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include __DIR__ . '/../PHPMailer/src/Exception.php';
include __DIR__ . '/../PHPMailer/src/PHPMailer.php';
include __DIR__ . '/../PHPMailer/src/SMTP.php';

// Restrict access for logged-in users
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'staff') {
        header("Location: ../online_appointment/online_appointment.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'dentist') {
        header("Location: ../patient_list/patient_list.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'admin') {
        header("Location: ../dashboard/dashboard.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'patient') {
        header("Location: pconcio_main.php");
        exit;
    }
}

$message = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $userFound = false;
    $table = '';
    $passCol = '';
    $id = '';
    $name = '';

    $emailParts = explode('@', $email);
    $domainPart = array_pop($emailParts);
    $localPart = implode('@', $emailParts);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
        $message = "Invalid email address or exceeds length limits (64 chars before @, 255 after).";
        $msgClass = "error";
    } else {
        // 1. Check Patient
        $stmt = $pdo->prepare("SELECT id, first_name FROM patient_account WHERE gmail = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$email]);
        if ($row = $stmt->fetch()) {
            $userFound = true; $table = 'patient_account'; $passCol = 'password'; $id = $row['id']; $name = $row['first_name'];
        } else {
            // 2. Check Dentist
            $stmt = $pdo->prepare("SELECT id, first_name FROM dentist_accounts WHERE email = ? AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$email]);
            if ($row = $stmt->fetch()) {
                $userFound = true; $table = 'dentist_accounts'; $passCol = 'password_hash'; $id = $row['id']; $name = $row['first_name'];
            } else {
                // 3. Check Staff
                $stmt = $pdo->prepare("SELECT id, first_name FROM staff_accounts WHERE email = ? AND is_deleted = 0 LIMIT 1");
                $stmt->execute([$email]);
                if ($row = $stmt->fetch()) {
                    $userFound = true; $table = 'staff_accounts'; $passCol = 'password_hash'; $id = $row['id']; $name = $row['first_name'];
                }
            }
        }

        if ($userFound) {
            // Generate a strong temporary password: e.g. Reset@495813
            $newPassword = 'Reset@' . rand(100000, 999999);
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

            try {
                // Ensure column supports bcrypt length seamlessly
                try { $pdo->exec("ALTER TABLE $table MODIFY $passCol VARCHAR(255)"); } catch (PDOException $e) {}

                // Update database
                $update = $pdo->prepare("UPDATE $table SET $passCol = ? WHERE id = ?");
                $update->execute([$hashed, $id]);

                // Send Email
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'beanchcanonego1212@gmail.com';
                $mail->Password   = 'xksi pzdv avxp clby';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('beanchcanonego1212@gmail.com', 'Mariategue Ortho-DentalClinic');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset MariategueOrtho-DentalClinic';
                $mail->Body    = "Hello <b>$name</b>,<br><br>Your password has been successfully reset.<br>Your new temporary password is: <b>$newPassword</b><br><br>Please log in and change your password immediately in your Account Settings.<br><br>Thank you!";

                $mail->send();
                $message = "A new temporary password has been sent to your email.";
                $msgClass = "success";
            } catch (Exception $e) {
                $message = "Failed to send email. Error: {$mail->ErrorInfo}";
                $msgClass = "error";
            }
        } else {
            // The Admin table is ignored above, so an admin email will hit this block.
            $message = "Email not found in our records (Admin accounts cannot be reset here).";
            $msgClass = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password MariategueOrtho-DentalClinic</title>
    <link rel="stylesheet" href="pconcio_main_design.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; padding: 0; background: radial-gradient(circle at top left, rgba(180, 220, 255, 0.6), transparent 60%), radial-gradient(circle at bottom right, rgba(200, 235, 255, 0.6), transparent 60%), linear-gradient(to bottom, #e8f8ff 0%, #ffffff 100%); background-attachment: fixed; }
        .page-content { flex: 1; display: flex; justify-content: center; align-items: center; padding: 40px 20px; }
        .forgot-container { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 40px; border-radius: 24px; box-shadow: 0 20px 60px -10px rgba(0, 0, 0, 0.1); width: 100%; max-width: 500px; text-align: center; }
        .back-button { text-decoration: none; color: #0ea5e9; background: rgba(14, 165, 233, 0.1); padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px; }
        .back-button:hover { background: #0ea5e9; color: white; }
        .form-message { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; }
        .form-message.error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #f87171; }
        .form-message.success { background-color: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        input { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; margin-bottom: 20px; box-sizing: border-box; font-size: 0.95rem; }
        input:focus { border-color: #0ea5e9; outline: none; box-shadow: 0 0 0 3px rgba(14,165,233,0.2); }
        button { width: 100%; background: #0ea5e9; color: white; border: none; padding: 16px; border-radius: 50px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; }
        button:hover { background: #0284c7; transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(14, 165, 233, 0.4); }
        
        /* Loader logic */
        #loadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 9999; justify-content: center; align-items: center; }
        .loader { border: 8px solid #f3f3f3; border-top: 8px solid #0ea5e9; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <div class="page-content">
        <div class="forgot-container">
            <a href="pconcio_main.php" class="back-button"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
            
            <h2 style="color: #0f172a; margin-bottom: 15px; font-size: 2rem;">Forgot Password</h2>
            <p style="color: #64748b; margin-bottom: 25px;">Enter your registered email address and we'll send you a new temporary password.</p>
            
            <?php if ($message): ?>
                <div class="form-message <?= $msgClass ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" id="forgotForm">
                <input type="email" name="email" required maxlength="320" placeholder="Enter your email address">
                <button type="submit" id="resetBtn">Reset Password</button>
            </form>
        </div>
    </div>

    <script>
        // Disable button on submit to prevent spamming the email gateway
        document.getElementById('forgotForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            const btn = document.getElementById('resetBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
        });
    </script>
</body>
</html>