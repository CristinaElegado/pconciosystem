<?php
session_start();

// Clear session_token from DB before destroying session
if (isset($_SESSION['id'], $_SESSION['user_type'], $_SESSION['session_token'])) {
    include __DIR__ . '/../miscellaneous/database.php';
    $userId   = $_SESSION['id'];
    $userType = $_SESSION['user_type'];
    $token    = $_SESSION['session_token'];
    try {
        switch ($userType) {
            case 'admin':
                $pdo->prepare("UPDATE admin SET session_token = NULL WHERE id = :id AND session_token = :token")->execute(['id' => $userId, 'token' => $token]); break;
            case 'dentist':
                $pdo->prepare("UPDATE dentist_accounts SET session_token = NULL WHERE id = :id AND session_token = :token")->execute(['id' => $userId, 'token' => $token]); break;
            case 'staff':
                $pdo->prepare("UPDATE staff_accounts SET session_token = NULL WHERE id = :id AND session_token = :token")->execute(['id' => $userId, 'token' => $token]); break;
            case 'patient':
                $pdo->prepare("UPDATE patient_account SET session_token = NULL WHERE id = :id AND session_token = :token")->execute(['id' => $userId, 'token' => $token]); break;
        }
    } catch (PDOException $e) { /* silent */ }
}

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Prevent browser from caching protected pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging out...</title>
</head>
<body>
    <script>
        localStorage.setItem('logoutEvent', Date.now());
        window.location.href = 'pconcio_main.php';
    </script>
</body>
</html>
