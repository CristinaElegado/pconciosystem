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

$history_id  = isset($_POST['history_id'])  ? (int)$_POST['history_id']  : 0;
$backup_file = isset($_POST['backup_file']) ? trim($_POST['backup_file']) : '';

if (!$history_id || !$backup_file) {
    $_SESSION['settings_error'] = 'Invalid restore request.';
    header('Location: ../admin/settings.php');
    exit;
}

// Sanitize filename — only allow backup_YYYY-MM-DD_HH-II-SS.json
if (!preg_match('/^backup_[\d\-_]+\.json$/', $backup_file)) {
    $_SESSION['settings_error'] = 'Invalid backup filename.';
    header('Location: ../admin/settings.php');
    exit;
}

$backup_path = __DIR__ . '/../admin/backups/' . $backup_file;

if (!file_exists($backup_path)) {
    $_SESSION['settings_error'] = 'Backup file not found: ' . htmlspecialchars($backup_file);
    header('Location: ../admin/settings.php');
    exit;
}

$backup_data = json_decode(file_get_contents($backup_path), true);

if (!is_array($backup_data)) {
    $_SESSION['settings_error'] = 'Backup file is corrupted or unreadable.';
    header('Location: ../admin/settings.php');
    exit;
}

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($backup_data as $table => $rows) {
        // Sanitize table name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) continue;

        // Clear table first
        try {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        } catch (Exception $e) {
            continue; // Skip tables that don't exist
        }

        if (empty($rows)) continue;

        // Get columns from first row
        $columns  = array_keys($rows[0]);
        $colList  = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insertSql    = "INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders})";
        $insertStmt   = $pdo->prepare($insertSql);

        foreach ($rows as $row) {
            try {
                $insertStmt->execute(array_values($row));
            } catch (Exception $e) {
                // Skip individual row errors (e.g. FK violations)
            }
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $_SESSION['settings_error'] = 'Restore failed: ' . $e->getMessage();
    header('Location: ../admin/settings.php');
    exit;
}

// Mark as restored in truncate_history
$pdo->prepare("UPDATE truncate_history SET restored = 1, restored_at = NOW() WHERE id = ?")
    ->execute([$history_id]);

log_audit($pdo, 'admin', $_SESSION['username'] ?? 'admin', 'Database Restored', 'From backup: ' . $backup_file);

$_SESSION['settings_success'] = 'Database restored successfully from: ' . $backup_file;
header('Location: ../admin/settings.php');
exit;
