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

// Walang session — try to restore from cookie first
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type'])) {
    // Need DB for restore — include early
    if (file_exists(__DIR__ . '/database.php')) {
        include_once __DIR__ . '/database.php';
        include_once __DIR__ . '/session_restore.php';
    }
}

// Still no session after restore attempt
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

// ── Force-kick old sessions that predate the session_token feature ────────────
if (!isset($_SESSION['session_token'])) {
    session_unset();
    session_destroy();
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

include_once __DIR__ . '/database.php';

// Ensure session_token and login_ip columns exist (safe to run every time, catch if already exists)
foreach (['admin','dentist_accounts','staff_accounts','patient_account'] as $_ct) {
    try { $pdo->exec("ALTER TABLE `$_ct` ADD COLUMN session_token VARCHAR(64) NULL DEFAULT NULL"); } catch (PDOException $_ce) { /* already exists */ }
    try { $pdo->exec("ALTER TABLE `$_ct` ADD COLUMN login_ip       VARCHAR(45) NULL DEFAULT NULL"); } catch (PDOException $_ce) { /* already exists */ }
}
unset($_ct, $_ce);

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
    // DB error — don't kick the user, just say valid to avoid false logouts
    echo json_encode(['valid' => true]);
    exit;
}

// Account no longer exists
if (!$exists) {
    session_unset();
    session_destroy();
    echo json_encode(['valid' => false, 'reason' => 'deleted']);
    exit;
}

// ── Token mismatch = logged in from another device (kicked) ──────────────────
// Only check if the DB actually has a token stored (null means single-session
// feature was not yet active when this user logged in — treat as valid).
if (!empty($dbToken) && !empty($myToken) && !hash_equals($dbToken, $myToken)) {
    session_unset();
    session_destroy();
    echo json_encode(['valid' => false, 'reason' => 'kicked']);
    exit;
}

echo json_encode(['valid' => true]);
