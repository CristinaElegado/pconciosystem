<?php
/**
 * auth_check.php
 * 
 * Centralized session validation.
 * I-include ito sa lahat ng protected admin pages PAGKATAPOS ng session_start()
 * at include ng database.php.
 * 
 * Kung hindi valid ang session (wala sa DB, na-delete, etc.), auto-logout agad.
 */

if (!isset($_SESSION['id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_type'])) {
    session_unset();
    session_destroy();
    header("Location: /login_main/login.php");
    exit;
}

// I-verify na nag-eexist pa ba ang user sa database base sa user_type
$userExists = false;
$userId = $_SESSION['id'];
$userType = $_SESSION['user_type'];

try {
    if ($userType === 'admin') {
        $stmt = $pdo->prepare("SELECT id FROM admin WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $userExists = (bool) $stmt->fetch();

    } elseif ($userType === 'dentist') {
        $stmt = $pdo->prepare("SELECT id FROM dentist_accounts WHERE id = :id AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $userExists = (bool) $stmt->fetch();

    } elseif ($userType === 'staff') {
        $stmt = $pdo->prepare("SELECT id FROM staff_accounts WHERE id = :id AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $userExists = (bool) $stmt->fetch();
    }
} catch (PDOException $e) {
    // Kung may DB error, i-logout na rin para safe
    $userExists = false;
}

if (!$userExists) {
    // I-clear ang session at i-redirect sa login
    session_unset();
    session_destroy();
    header("Location: /login_main/login.php");
    exit;
}
