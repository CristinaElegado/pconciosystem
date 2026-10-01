<?php
/**
 * session_check.php
 * 
 * Polling endpoint. Tinatawagan ng browser bawat ilang segundo para i-verify
 * kung valid pa ba ang session at kung nag-eexist pa ba ang user sa DB.
 * 
 * Returns JSON:
 *   { "valid": true }                         — session OK
 *   { "valid": false, "reason": "deleted" }   — user na-delete sa DB
 *   { "valid": false, "reason": "no_session"} — walang session
 *   { "valid": false, "reason": "no_admin",
 *     "register_url": "..." }                 — admin na-delete, walang ibang admin
 */

session_start();
header('Content-Type: application/json');

// Walang session
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

include __DIR__ . '/database.php';

$userId   = $_SESSION['id'];
$userType = $_SESSION['user_type'];
$exists   = false;

try {
    if ($userType === 'admin') {
        $stmt = $pdo->prepare("SELECT id FROM admin WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $exists = (bool) $stmt->fetch();

        // Kung na-delete yung admin, check kung may ibang admin pa sa table
        if (!$exists) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM admin");
            $adminCount = (int) $countStmt->fetchColumn();

            // Destroy the session so the user is fully logged out
            session_unset();
            session_destroy();

            if ($adminCount === 0) {
                // Walang admin — redirect sa registration
                echo json_encode([
                    'valid'        => false,
                    'reason'       => 'no_admin',
                    'register_url' => '/login_main/register_admin.php'
                ]);
            } else {
                // May ibang admin — redirect sa login lang
                echo json_encode(['valid' => false, 'reason' => 'deleted']);
            }
            exit;
        }

    } elseif ($userType === 'dentist') {
        $stmt = $pdo->prepare("SELECT id FROM dentist_accounts WHERE id = :id AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $exists = (bool) $stmt->fetch();

    } elseif ($userType === 'staff') {
        $stmt = $pdo->prepare("SELECT id FROM staff_accounts WHERE id = :id AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $exists = (bool) $stmt->fetch();
    }
} catch (PDOException $e) {
    // Sa case ng DB error, i-treat bilang invalid para safe
    echo json_encode(['valid' => false, 'reason' => 'error']);
    exit;
}

if (!$exists) {
    session_unset();
    session_destroy();
    echo json_encode(['valid' => false, 'reason' => 'deleted']);
    exit;
}

echo json_encode(['valid' => true]);
