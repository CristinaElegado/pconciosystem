<?php
/**
 * log_audit.php
 * 
 * Helper function to save an audit log entry to the database.
 * 
 * Usage:
 *   include __DIR__ . '/../miscellaneous/log_audit.php';
 *   log_audit($pdo, 'admin', 'Admin', 'Logged In');
 *   log_audit($pdo, 'dentist', 'Dr. Reyes', 'Viewed Patient Record');
 */

function log_audit(PDO $pdo, string $user_type, string $user_name, string $action, string $details = ''): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("
            INSERT INTO audit_trail (user_type, user_name, action, details, ip_address, created_at)
            VALUES (:user_type, :user_name, :action, :details, :ip_address, NOW())
        ");
        $stmt->execute([
            'user_type'  => $user_type,
            'user_name'  => $user_name,
            'action'     => $action,
            'details'    => $details,
            'ip_address' => $ip,
        ]);
    } catch (PDOException $e) {
        // Silent fail — audit logging should never break the main app
        error_log('Audit log error: ' . $e->getMessage());
    }
}
