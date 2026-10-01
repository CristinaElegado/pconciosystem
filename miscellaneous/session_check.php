<?php
/**
 * session_check.php
 *
 * Polling endpoint. Tinatawagan ng browser bawat ilang segundo para i-verify
 * kung valid pa ba ang session at kung nag-eexist pa ba ang user sa DB.
 *
 * Returns JSON:
 *   { "valid": true }                          — session OK
 *   { "valid": false, "reason": "kicked" }     — logged in sa ibang device
 *   { "valid": false, "reason": "deleted" }    — user na-delete sa DB
 *   { "valid": false, "reason": "no_session" } — walang session
 *   { "valid": false, "reason": "no_admin",
 *     "register_url": "..." }                  — admin na-delete, walang ibang admin
 */

session_start();
header('Content-Type: application/json');

// Walang session
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

// ── Force-kick old sessions that predate the session_token feature ────────────
// If no token in session, it's a stale pre-deploy session — log them out
if (!isset($_SESSION['session_token'])) {
    session_unset();
    session_destroy();
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

include __DIR__ . '/database.php';

// Ensure session_token columns exist (safe to run every time, IF NOT EXISTS is cheap)
try {
    $pdo->exec("ALTER TABLE admin              ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE dentist_accounts   ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE staff_accounts     ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE patient_account    ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL DEFAULT NULL");
} catch (PDOException $e) { /* silent */ }

$userId    = $_SESSION['id'];
$userType  = $_SESSION['user_type'];
$myToken   = $_SESSION['session_token'] ?? null;
$exists    = false;
$dbToken   = null;

try {
    if ($userType === 'admin') {
        $stmt = $pdo->prepare("SELECT id, session_token FROM admin WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            // Admin account deleted — check if any admin left
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
            session_unset();
            session_destroy();

            if ($adminCount === 0) {
                echo json_encode(['valid' => false, 'reason' => 'no_admin', 'register_url' => '/setup.php']);
            } else {
                echo json_encode(['valid' => false, 'reason' => 'deleted']);
            }
            exit;
        }

        $exists  = true;
        $dbToken = $row['session_token'];

    } elseif ($userType === 'dentist') {
        $stmt = $pdo->prepare("SELECT id, session_token FROM dentist_accounts WHERE id = :id AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $row     = $stmt->fetch();
        $exists  = (bool) $row;
        $dbToken = $row['session_token'] ?? null;

    } elseif ($userType === 'staff') {
        $stmt = $pdo->prepare("SELECT id, session_token FROM staff_accounts WHERE id = :id AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $row     = $stmt->fetch();
        $exists  = (bool) $row;
        $dbToken = $row['session_token'] ?? null;

    } elseif ($userType === 'patient') {
        $stmt = $pdo->prepare("SELECT id, session_token FROM patient_account WHERE id = :id AND is_deleted = 0 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $row     = $stmt->fetch();
        $exists  = (bool) $row;
        $dbToken = $row['session_token'] ?? null;
    }

} catch (PDOException $e) {
    echo json_encode(['valid' => false, 'reason' => 'error']);
    exit;
}

// Account no longer exists
if (!$exists) {
    session_unset();
    session_destroy();
    echo json_encode(['valid' => false, 'reason' => 'deleted']);
    exit;
}

echo json_encode(['valid' => true]);
