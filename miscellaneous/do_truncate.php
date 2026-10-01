<?php
session_start();
include __DIR__ . '/database.php';
include __DIR__ . '/auth_check.php';
include __DIR__ . '/log_audit.php';

// Admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

if (!isset($_POST['confirm_truncate']) || $_POST['confirm_truncate'] !== '1') {
    header('Location: ../admin/settings.php');
    exit;
}

// Tables to truncate (data tables only — NOT admin, staff_accounts, dentist_accounts, patient_account, services, time_slots, service_items)
// We preserve accounts and config, clear transactional/operational data
$tables_to_truncate = [
    'patients_list',
    'patient_services',
    'online_appointment',
    'online_appointment_services',
    'transaction_history',
    'item_inventory',
    'dentist_schedule',
    'dentist_time_schedules',
    'dentist_accounts',
    'audit_log',
];

// Tables to back up before truncating (we snapshot everything that will be cleared)
$backup_data = [];
foreach ($tables_to_truncate as $table) {
    try {
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        $backup_data[$table] = $rows;
    } catch (Exception $e) {
        $backup_data[$table] = []; // Table may not exist yet
    }
}

// Save backup as JSON file
$backup_dir = __DIR__ . '/../admin/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$timestamp   = date('Y-m-d_H-i-s');
$backup_file = $backup_dir . 'backup_' . $timestamp . '.json';
file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Auto-create truncate_history table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS `truncate_history` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `truncated_at` DATETIME NOT NULL,
    `performed_by` VARCHAR(100) NOT NULL,
    `backup_file`  VARCHAR(255) NOT NULL,
    `tables_list`  TEXT NOT NULL,
    `restored`     TINYINT(1) DEFAULT 0,
    `restored_at`  DATETIME NULL
)");

// Perform truncate with FK checks disabled
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables_to_truncate as $table) {
        try {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        } catch (Exception $e) {
            // Skip tables that don't exist
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $_SESSION['settings_error'] = 'Truncate failed: ' . $e->getMessage();
    header('Location: ../admin/settings.php');
    exit;
}

// Log to truncate_history
$stmt = $pdo->prepare("INSERT INTO truncate_history (truncated_at, performed_by, backup_file, tables_list) VALUES (NOW(), ?, ?, ?)");
$stmt->execute([
    $_SESSION['username'] ?? 'admin',
    basename($backup_file),
    implode(', ', $tables_to_truncate),
]);

log_audit($pdo, 'admin', $_SESSION['username'] ?? 'admin', 'Database Truncated', 'Backup: ' . basename($backup_file));

$_SESSION['settings_success'] = 'Database truncated successfully. Backup saved as: ' . basename($backup_file);
header('Location: ../admin/settings.php');
exit;
