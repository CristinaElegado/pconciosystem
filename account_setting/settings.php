<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../PHPMailer/src/Exception.php';
include __DIR__ . '/../PHPMailer/src/PHPMailer.php';
include __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include __DIR__ . '/../miscellaneous/auth_check.php';
if (!isset($_SESSION['verified']) || $_SESSION['verified'] !== true) {
    header("Location: /pconcio_dental/account_setting/index.php");
    exit;
}

$success = $error = "";

$user_type = $_SESSION['user_type'] ?? 'admin';

if ($user_type === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['id']]);
    $user = $stmt->fetch();
} elseif ($user_type === 'dentist') {
    $stmt = $pdo->prepare("SELECT * FROM dentist_accounts WHERE id = :id AND is_active = 1 AND is_deleted = 0");
    $stmt->execute(['id' => $_SESSION['id']]);
    $user = $stmt->fetch();
    $user['username'] = $user['first_name'] . ' ' . $user['last_name'];
    // Siguraduhing may fallback kung sakaling walang laman
    $user['employment_type'] = $user['employment_type'] ?? 'Full-Time';

    $stmt = $pdo->prepare("SELECT * FROM dentist_schedule WHERE dentist_id = :id");
    $stmt->execute(['id' => $_SESSION['id']]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($user_type === 'staff') {
    $stmt = $pdo->prepare("SELECT * FROM staff_accounts WHERE id = :id AND is_active = 1");
    $stmt->execute(['id' => $_SESSION['id']]);
    $user = $stmt->fetch();
    $user['username'] = $user['first_name'] . ' ' . $user['last_name'];
} elseif ($user_type === 'patient') {
    $stmt = $pdo->prepare("SELECT * FROM patient_account WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['id']]);
    $user = $stmt->fetch();
    $user['username'] = $user['first_name'] . ' ' . $user['last_name'];
    $user['email'] = $user['gmail']; // Map gmail to email for generic usage
} else {
    die("Unauthorized access.");
}

if ($user_type !== 'patient') {
    include __DIR__ . '/../miscellaneous/sidebar.php';
}

if (isset($_POST['save_admin_profile']) && $user_type === 'admin') {
    $username = trim($_POST['username']);
    $photo_sql = "";
    $params = [$username];
    
    // Check username uniqueness
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = ? AND id != ?");
    $stmt->execute([$username, $_SESSION['id']]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Username already taken.<br>";
    }

    if (empty($error)) {
        if (!empty($_FILES['profile_photo']['name'])) {
            $target_dir = "../uploads/profile_photos/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($ext, $allowed)) {
                $new_filename = "admin_" . $_SESSION['id'] . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_dir . $new_filename)) {
                    $photo_sql = ", profile_photo = ?";
                    $params[] = $new_filename;
                } else {
                    $error .= "Failed to upload photo.<br>";
                }
            } else {
                $error .= "Invalid file type. Only JPG, PNG, GIF allowed.<br>";
            }
        }
    }

    if (empty($error)) {
        $params[] = $_SESSION['id'];
        $stmt = $pdo->prepare("UPDATE admin SET username = ? $photo_sql WHERE id = ?");
        $stmt->execute($params);
        
        $_SESSION['username'] = $username;
        $_SESSION['user_name'] = $username;
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['id']]);
        $user = $stmt->fetch();
        $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
        
        $success .= "Profile updated successfully.<br>";
    }
}

if (isset($_POST['save_profile_photo']) && ($user_type === 'dentist' || $user_type === 'staff')) {
    $table = ($user_type === 'dentist') ? 'dentist_accounts' : 'staff_accounts';
    
    if (!empty($_FILES['profile_photo']['name'])) {
        $target_dir = "../uploads/profile_photos/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $new_filename = $user_type . "_" . $_SESSION['id'] . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_dir . $new_filename)) {
                $stmt = $pdo->prepare("UPDATE $table SET profile_photo = ? WHERE id = ?");
                $stmt->execute([$new_filename, $_SESSION['id']]);
                
                $_SESSION['profile_photo'] = $new_filename;
                $user['profile_photo'] = $new_filename;
                $success .= "Profile photo updated successfully.<br>";
            } else {
                $error .= "Failed to upload photo.<br>";
            }
        } else {
            $error .= "Invalid file type. Only JPG, PNG, GIF allowed.<br>";
        }
    }
}

if (isset($_POST['save_personal_info']) && $user_type === 'patient') {
    $first_name = strtoupper(trim($_POST['first_name']));
    $last_name = strtoupper(trim($_POST['last_name']));
    
    $photo_sql = "";
    $params = [$first_name, $last_name];

    if (!empty($_FILES['profile_photo']['name'])) {
        $target_dir = "../uploads/profile_photos/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $new_filename = "patient_" . $_SESSION['id'] . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_dir . $new_filename)) {
                $photo_sql = ", profile_photo = ?";
                $params[] = $new_filename;
            } else {
                $error .= "Failed to upload photo.<br>";
            }
        } else {
            $error .= "Invalid file type. Only JPG, PNG, GIF allowed.<br>";
        }
    }

    if (empty($error)) {
        $params[] = $_SESSION['id'];
        $stmt = $pdo->prepare("UPDATE patient_account SET first_name = ?, last_name = ? $photo_sql WHERE id = ?");
        $stmt->execute($params);
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $success .= "Personal information updated successfully.<br>";
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM patient_account WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['id']]);
        $user = $stmt->fetch();
        $user['username'] = $user['first_name'] . ' ' . $user['last_name'];
        $user['email'] = $user['gmail'];
        $_SESSION['profile_photo'] = $user['profile_photo'] ?? null; // Update session photo
    }
}

if (isset($_POST['save_email'])) {
    $newEmail = trim($_POST['email']);

    if ($newEmail === $user['email']) {
        $error .= "You entered your current email.<br>";
    } else {
        if ($user_type === 'admin') {
            $table = 'admin';
        } elseif ($user_type === 'dentist') {
            $table = 'dentist_accounts';
        } elseif ($user_type === 'staff') {
            $table = 'staff_accounts';
        } else {
            $table = 'patient_account';
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = :email");
        $stmt->execute(['email' => $newEmail]);
        $adminEmailExists = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dentist_accounts WHERE email = :email AND is_deleted = 0");
        $stmt->execute(['email' => $newEmail]);
        $dentistEmailExists = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_accounts WHERE email = :email");
        $stmt->execute(['email' => $newEmail]);
        $staffEmailExists = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM patient_account WHERE gmail = :email");
        $stmt->execute(['email' => $newEmail]);
        $patientEmailExists = $stmt->fetchColumn();

        if ($adminEmailExists > 0 || $dentistEmailExists > 0 || $staffEmailExists > 0 || $patientEmailExists > 0) {
            $error .= "This email is already in use.<br>";
        } else {
            $_SESSION['pending_email'] = $newEmail;
            $verificationCode = bin2hex(random_bytes(3));
            $_SESSION['email_code'] = $verificationCode;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'beanchcanonego1212@gmail.com';
                $mail->Password   = 'xksi pzdv avxp clby';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('beanchcanonego1212@gmail.com', 'Mariategue Ortho-DentalClinic Admin');
                $mail->addAddress($newEmail);
                $mail->isHTML(true);
                $mail->Subject = 'Your Verification Code';
                $mail->Body = "Your verification code is: <b>$verificationCode</b>";

                $mail->send();
                $success .= "Verification code sent to $newEmail.<br>";
            } catch (Exception $e) {
                $error .= "Failed to send verification code: {$mail->ErrorInfo}<br>";
            }
        }
    }
}

if (isset($_POST['verify_email'])) {
    if (!empty($_POST['email_code']) && isset($_SESSION['email_code'], $_SESSION['pending_email'])) {
        $enteredCode = trim($_POST['email_code']);

        $emailCol = 'email';
        if ($user_type === 'admin') {
            $table = 'admin';
        } elseif ($user_type === 'dentist') {
            $table = 'dentist_accounts';
        } elseif ($user_type === 'staff') {
            $table = 'staff_accounts';
        } else {
            $table = 'patient_account';
            $emailCol = 'gmail';
        }

        $stmt = $pdo->prepare("SELECT id FROM $table WHERE $emailCol = :email AND id != :id LIMIT 1");
        $stmt->execute([
            'email' => $_SESSION['pending_email'],
            'id'    => $_SESSION['id']
        ]);

        if ($stmt->fetch()) {
            $error .= "This email has just been taken by another account.<br>";
            unset($_SESSION['pending_email'], $_SESSION['email_code']);
        } else if ($enteredCode === $_SESSION['email_code']) {
            $stmt = $pdo->prepare("UPDATE $table SET $emailCol = :email WHERE id = :id");
            $stmt->execute(['email' => $_SESSION['pending_email'], 'id' => $_SESSION['id']]);
            $success .= "Email updated successfully!<br>";
            $user['email'] = $_SESSION['pending_email'];
            unset($_SESSION['email_code'], $_SESSION['pending_email']);

            $_POST['email'] = '';
            $_POST['email_code'] = '';
        } else {
            $error .= "Invalid verification code.<br>";
        }
    } else {
        $error .= "No verification code entered.<br>";
    }
}

if (isset($_POST['save_password'])) {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($new_password && $confirm_password) {
        if ($new_password === $confirm_password) {
            if ($user_type === 'admin') {
                $table = 'admin';
                $password_column = 'password';
                $password_value = $new_password;
            } elseif ($user_type === 'patient') {
                $table = 'patient_account';
                $password_column = 'password';
                $password_value = password_hash($new_password, PASSWORD_DEFAULT);
            } elseif ($user_type === 'dentist') {
                $table = 'dentist_accounts';
                $password_column = 'password_hash';
                $password_value = password_hash($new_password, PASSWORD_DEFAULT);
            } elseif ($user_type === 'staff') {
                $table = 'staff_accounts';
                $password_column = 'password_hash';
                $password_value = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            if (isset($table)) {
                try {
                    $pdo->exec("ALTER TABLE $table MODIFY $password_column VARCHAR(255)");
                } catch (PDOException $e) { }
                
                $stmt = $pdo->prepare("UPDATE $table SET $password_column = :password WHERE id = :id");
                $stmt->execute(['password' => $password_value, 'id' => $_SESSION['id']]);
                $success .= "Password updated successfully.<br>";
            }
        } else {
            $error .= "Passwords do not match.<br>";
        }
    } else {
        $error .= "Please enter both password fields.<br>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <?php if ($user_type === 'patient'): ?>
        <link rel="stylesheet" href="../main_page/pconcio_main_design.css">
        <link rel="stylesheet" href="setting_design.css">
        <style>
            body {
                background: radial-gradient(circle at top left, rgba(180, 220, 255, 0.6), transparent 60%),
                            radial-gradient(circle at bottom right, rgba(200, 235, 255, 0.6), transparent 60%),
                            linear-gradient(to bottom, #e8f8ff 0%, #ffffff 100%);
                background-attachment: fixed;
            }
            .settings-container {
                max-width: 1200px;
                margin: 40px auto 50px;
                padding: 30px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            .user-menu-container { position: relative; display: flex; align-items: center; }
            .user-icon { width: 45px; height: 45px; border-radius: 50%; cursor: pointer; border: 2px solid #0ea5e9; transition: transform 0.2s; object-fit: cover; }
            .profile-wrapper { position: relative; cursor: pointer; }
            .dropdown-arrow-badge { position: absolute; bottom: 0; right: 0; background-color: #e2e8f0; color: #334155; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; border: 2px solid #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
            .user-icon:hover { transform: scale(1.05); }
            .user-dropdown { display: none; position: absolute; top: 60px; right: 0; background: white; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); width: 250px; z-index: 10000; overflow: hidden; border: 1px solid #f0f0f0; }
            .user-dropdown.show { display: block; animation: fadeIn 0.2s ease-out; }
            .user-info { padding: 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; }
            .user-name { display: block; font-weight: 600; color: #334155; font-size: 0.95rem; }
            .user-email { display: block; font-size: 0.8rem; color: #64748b; margin-top: 2px; word-break: break-all; }
            .dropdown-item { display: block; padding: 12px 15px; color: #334155; text-decoration: none; font-size: 0.9rem; transition: background 0.2s; text-align: left; }
            .dropdown-item:hover { background: #f1f5f9; color: #0ea5e9; }
            .logout-item { color: #ef4444; border-top: 1px solid #f1f5f9; }
            .logout-item:hover { background: #fef2f2; color: #dc2626; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
            .settings-btn { width: 100%; margin-top: 10px; }
            .patient-settings-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 25px;
                align-items: start;
            }
            .settings-section {
                background: #f8fafc;
                padding: 25px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
            }
            .settings-alert-wrapper { grid-column: 1 / -1; }
        </style>
    <?php else: ?>
        <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
        <link rel="stylesheet" href="setting_design.css">
    <?php endif; ?>
    <style>
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="loader"></div>
</div>

<?php if ($user_type === 'patient'): ?>
    <header>
        <h1 class="logo"><a href="../main_page/pconcio_main.php">Pconcio<span>Dental</span></a></h1>
        <nav>
            <ul>
                <li><a href="../main_page/pconcio_main.php">Home</a></li>
                <li><a href="../main_page/online_appointment_form.php">Book Appointment</a></li>
            </ul>
            <div class="login-btn">
                <div class="user-menu-container">
                    <?php 
                        $userPhoto = $_SESSION['profile_photo'] ?? '';
                        $avatarUrl = $userPhoto ? "../uploads/profile_photos/" . htmlspecialchars($userPhoto) : "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['user_name']) . "&background=0ea5e9&color=fff";
                    ?>
                    <div class="profile-wrapper" onclick="toggleUserMenu()">
                        <img src="<?= $avatarUrl ?>" alt="User Menu" class="user-icon">
                        <div class="dropdown-arrow-badge">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </div>
                    </div>
                    <div id="userDropdown" class="user-dropdown">
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            <span class="user-email"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                        </div>
                        <a href="index.php" class="dropdown-item">Account Settings</a>
                        <a href="../main_page/logout.php" class="dropdown-item logout-item" id="settingsLogoutLink">Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>
<?php endif; ?>

<div class="<?= ($user_type === 'patient') ? 'settings-container' : 'main-content' ?>">

      <h2>Account Settings</h2>
        <div class="<?= ($user_type === 'patient') ? 'patient-settings-grid' : 'table-wrapper' ?>">

        <?php if (!empty($success) || !empty($error)): ?>
            <div class="settings-alert-wrapper">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
            </div>
        <?php endif; ?>

<?php if($user_type === 'admin'): ?>
<div class="settings-section">
<h3 class="settings-section-title">Profile Settings</h3>
<form method="POST" enctype="multipart/form-data">
    <label>Username</label>
    <input type="text" name="username" class="settings-input" value="<?= htmlspecialchars($user['username']) ?>">

    <label>Profile Photo</label>
    <div style="margin-bottom: 10px;">
        <?php 
            $photoSrc = !empty($user['profile_photo']) ? "../uploads/profile_photos/" . htmlspecialchars($user['profile_photo']) : "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=0ea5e9&color=fff&size=128";
        ?>
        <img src="<?= $photoSrc ?>" alt="Profile Photo" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #0ea5e9;">
    </div>
    <input type="file" name="profile_photo" class="settings-input" accept="image/*">

    <button class="settings-btn" name="save_admin_profile">Save Changes</button>
</form>
</div>
<?php endif; ?>

<?php if($user_type === 'dentist' || $user_type === 'staff'): ?>
<div class="settings-section">
<h3 class="settings-section-title">Profile Photo</h3>
<form method="POST" enctype="multipart/form-data">
    <label>Profile Photo</label>
    <div style="margin-bottom: 10px;">
        <?php 
            $photoSrc = !empty($user['profile_photo']) ? "../uploads/profile_photos/" . htmlspecialchars($user['profile_photo']) : "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=0ea5e9&color=fff&size=128";
        ?>
        <img src="<?= $photoSrc ?>" alt="Profile Photo" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #0ea5e9;">
    </div>
    <input type="file" name="profile_photo" class="settings-input" accept="image/*">

    <button class="settings-btn" name="save_profile_photo">Upload Photo</button>
</form>
</div>
<?php endif; ?>

<?php if($user_type === 'dentist'): ?>
<div class="settings-section">
    <h3 class="settings-section-title">Employment Status</h3>
    <div style="padding: 5px 0;">
        <p style="font-size: 0.95rem; color: #334155; margin-bottom: 8px;">
            Your current employment type is: 
            <strong style="color: #0ea5e9; font-size: 1.1rem; text-transform: uppercase; margin-left: 5px;">
                <?= htmlspecialchars($user['employment_type'] ?? 'Full-Time') ?>
            </strong>
        </p>
        <p style="font-size: 0.85rem; color: #64748b; margin: 0;">
            <em>Note: Please contact the clinic administrator if you need to request a change to your employment type or status.</em>
        </p>
    </div>
</div>

<div class="settings-section">
<h3 class="settings-section-title">Your Availability (Min & Max Time)</h3>
<p style="font-size: 0.9rem; color: #64748b; margin-bottom: 15px;">Please contact the clinic admin if you need to change your schedule.</p>
<div>
    <div style="max-height: 350px; overflow-y: auto; padding-right: 10px;">
        <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day): ?>
            <?php 
                $isActive = !empty($schedule[$day]) ? 1 : 0; 
                $start = $isActive && !empty($schedule[$day.'_start']) ? substr($schedule[$day.'_start'], 0, 5) : '';
                $end = $isActive && !empty($schedule[$day.'_end']) ? substr($schedule[$day.'_end'], 0, 5) : '';
            ?>
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <div style="flex: 1; display: flex; align-items: center;">
                    <span style="font-weight: bold; text-transform: capitalize; display: inline-block; width: 100px;"><?= ucfirst($day) ?></span>
                    <input type="checkbox" disabled <?= $isActive ? 'checked' : '' ?> style="transform: scale(1.2); margin: 0;">
                </div>
                <div style="flex: 2; display: flex; gap: 10px; align-items: center;">
                    <label style="margin:0; font-size: 0.85rem;">Min (In):</label>
                    <input type="time" class="settings-input" style="margin:0; padding:5px; background: #f1f5f9; color: #64748b;" value="<?= $start ?>" disabled>
                    <label style="margin:0; font-size: 0.85rem;">Max (Out):</label>
                    <input type="time" class="settings-input" style="margin:0; padding:5px; background: #f1f5f9; color: #64748b;" value="<?= $end ?>" disabled>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</div>
<?php endif; ?>

<?php if($user_type === 'patient'): ?>
<div class="settings-section">
<h3 class="settings-section-title">Personal Information</h3>
<form method="POST" enctype="multipart/form-data">
    <label>First Name</label>
    <input type="text" name="first_name" class="settings-input" value="<?= htmlspecialchars($user['first_name']) ?>" required>

    <label>Last Name</label>
    <input type="text" name="last_name" class="settings-input" value="<?= htmlspecialchars($user['last_name']) ?>" required>

    <label>Profile Photo</label>
    <div style="margin-bottom: 10px;">
        <?php 
            $photoSrc = !empty($user['profile_photo']) ? "../uploads/profile_photos/" . htmlspecialchars($user['profile_photo']) : "https://ui-avatars.com/api/?name=" . urlencode($user['first_name'] . ' ' . $user['last_name']) . "&background=0ea5e9&color=fff&size=128";
        ?>
        <img src="<?= $photoSrc ?>" alt="Profile Photo" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #0ea5e9;">
    </div>

    <input type="file" name="profile_photo" class="settings-input" accept="image/*">

    <button class="settings-btn" name="save_personal_info">Save Changes</button>
</form>
</div>
<?php endif; ?>

<?php
$newEmailInput = $_POST['email'] ?? '';
?>

<?php if($user_type !== 'admin'): ?>
<div class="settings-section">
<h3 class="settings-section-title">Update Email</h3>
<form method="POST">
    <label>Current Email</label>
    <input type="email" class="settings-input" value="<?= htmlspecialchars($user['email']) ?>" disabled>

    <label>New Email</label>
    <input type="email" name="email" class="settings-input" value="<?= htmlspecialchars($newEmailInput) ?>">

    <label>Verification Code</label>
    <input type="text" name="email_code" class="settings-input" value="<?= htmlspecialchars($_POST['email_code'] ?? '') ?>">

    <button class="settings-btn" name="save_email">Send Code</button>
    <button class="settings-btn" name="verify_email">Verify & Save</button>
</form>
</div>
<?php endif; ?>

<div class="settings-section">
      <h3 class="settings-section-title">Change Password</h3>
      <form method="POST">
          <label>New Password</label>
          <div class="password-wrapper">
              <input type="password" name="new_password" id="new_password" class="settings-input">
              <span class="toggle-password" onclick="togglePassword('new_password')">Show</span>
          </div>

          <label>Confirm Password</label>
          <div class="password-wrapper">
              <input type="password" name="confirm_password" id="confirm_password" class="settings-input">
              <span class="toggle-password" onclick="togglePassword('confirm_password')">Show</span>
          </div>

          <button class="settings-btn" name="save_password">Save Password</button>
      </form>
</div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('loadingOverlay');

    if (overlay) {
        overlay.style.display = 'none';
    }

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function () {
            if (overlay) {
                overlay.style.display = 'flex';
                document.body.classList.add('disable-clicks');
            }
        });
    });

    window.addEventListener('load', function () {
        if (overlay) {
            overlay.style.display = 'none';
            document.body.classList.remove('disable-clicks');
        }
    });
});

function toggleUserMenu() {
    const dropdown = document.getElementById("userDropdown");
    dropdown.classList.toggle("show");
}

window.addEventListener('click', function(e) {
    if (!e.target.closest('.profile-wrapper')) {
        const dropdown = document.getElementById("userDropdown");
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
});

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const toggleText = event.target;

    if (input.type === "password") {
        input.type = "text";
        toggleText.textContent = "Hide";
    } else {
        input.type = "password";
        toggleText.textContent = "Show";
    }
}

const settingsLogoutLink = document.getElementById('settingsLogoutLink');
if (settingsLogoutLink) {
    settingsLogoutLink.addEventListener('click', function(e) {
        e.preventDefault();
        const href = this.href;
        showConfirmDialog('Are you sure you want to logout?', function() {
            window.location.href = href;
        }, { title: 'Logout', icon: 'logout', okText: 'Logout' });
    });
}
</script>

</body>
</html>