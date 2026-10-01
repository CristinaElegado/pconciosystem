<?php
/**
 * session_token_clear.php
 *
 * Called via navigator.sendBeacon() on beforeunload / visibilitychange (hidden)
 * to clear the session_token so the user can log in again on a new device.
 *
 * Also used by logout pages to ensure the token is wiped from the DB.
 */
session_start();

if (!isset($_SESSION['id'], $_SESSION['user_type'], $_SESSION['session_token'])) {
    http_response_code(204);
    exit;
}

include __DIR__ . '/database.php';

$userId    = $_SESSION['id'];
$userType  = $_SESSION['user_type'];
$token     = $_SESSION['session_token'];

try {
    // Only clear if the token in the DB still matches THIS session's token
    // (prevents a newly-logged-in device from getting wiped by the old device's close)
    switch ($userType) {
        case 'admin':
            $pdo->prepare("UPDATE admin SET session_token = NULL WHERE id = :id AND session_token = :token")
                ->execute(['id' => $userId, 'token' => $token]);
            break;
        case 'dentist':
            $pdo->prepare("UPDATE dentist_accounts SET session_token = NULL WHERE id = :id AND session_token = :token")
                ->execute(['id' => $userId, 'token' => $token]);
            break;
        case 'staff':
            $pdo->prepare("UPDATE staff_accounts SET session_token = NULL WHERE id = :id AND session_token = :token")
                ->execute(['id' => $userId, 'token' => $token]);
            break;
        case 'patient':
            $pdo->prepare("UPDATE patient_account SET session_token = NULL WHERE id = :id AND session_token = :token")
                ->execute(['id' => $userId, 'token' => $token]);
            break;
    }
} catch (PDOException $e) {
    // Silent fail — this is a beacon call
}

http_response_code(204);
exit;
