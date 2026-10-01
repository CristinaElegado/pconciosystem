<?php
session_start();
include __DIR__ . '/miscellaneous/database.php';
include __DIR__ . '/miscellaneous/log_audit.php';

// Admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: main_page/pconcio_main.php");
    exit;
}

// Tables involved in Transactions & Appointments clear
$transaction_tables = [
    'transaction_history',
    'patients_list',
    'patient_services',
    'online_appointment',
    'online_appointment_services',
];

// Tables involved in Factory Reset (all except admin)
$all_tables = [
    'transaction_history',
    'patients_list',
    'patient_services',
    'online_appointment',
    'online_appointment_services',
    'patient_account',
    'staff_accounts',
    'dentist_accounts',
    'dentist_schedule',
    'dentist_time_schedules',
    'services',
    'service_items',
    'item_inventory',
    'password_change_requests',
    'time_slots',
];

// ── Helper: dump table rows as array ──────────────────────────────────────────
function dumpTable(PDO $pdo, string $table): array {
    try {
        return $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// ── Helper: ensure truncate_history table exists ──────────────────────────────
function ensureHistoryTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `truncate_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `backup_name` VARCHAR(100) NOT NULL,
        `truncate_type` VARCHAR(50) NOT NULL,
        `backup_data` LONGTEXT NOT NULL,
        `status` ENUM('truncated','restored') NOT NULL DEFAULT 'truncated',
        `truncated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `restored_at` DATETIME DEFAULT NULL,
        `admin_id` INT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

ensureHistoryTable($pdo);

// ── POST: Truncate ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $action = $_POST['action'];

    if ($action === 'clear_transactions' || $action === 'clear_all') {

        $tables = ($action === 'clear_all') ? $all_tables : $transaction_tables;
        $label  = ($action === 'clear_all') ? 'Factory Reset' : 'Clear Transactions';

        // 1. Backup data
        $backup = [];
        foreach ($tables as $t) {
            $backup[$t] = dumpTable($pdo, $t);
        }
        $backupJson = json_encode($backup, JSON_UNESCAPED_UNICODE);
        $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.json';

        // 2. Save backup record
        $stmt = $pdo->prepare("INSERT INTO truncate_history
            (backup_name, truncate_type, backup_data, status, admin_id)
            VALUES (:bn, :tt, :bd, 'truncated', :aid)");
        $stmt->execute([
            'bn'  => $backupName,
            'tt'  => $label,
            'bd'  => $backupJson,
            'aid' => $_SESSION['id'] ?? null,
        ]);

        // 3. Truncate
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        foreach ($tables as $t) {
            try { $pdo->exec("TRUNCATE TABLE `$t`;"); } catch (Exception $e) {}
        }
        if ($action === 'clear_all') {
            try { $pdo->exec("UPDATE staff_id_tracker SET last_number = 0 WHERE id = 1;"); } catch (Exception $e) {}
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // 4. Audit log
        logAudit($pdo, $_SESSION['id'] ?? 0, $_SESSION['username'] ?? 'Admin', 'Database Truncated', "Backup: $backupName");

        echo "<script>alert('$label completed. Backup saved as $backupName.'); window.location='admin_clear_data.php';</script>";
        exit;
    }

    // ── Restore ───────────────────────────────────────────────────────────────
    if ($action === 'restore' && isset($_POST['history_id'])) {
        $histId = (int)$_POST['history_id'];

        $stmt = $pdo->prepare("SELECT * FROM truncate_history WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $histId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo "<script>alert('Backup record not found.'); window.location='admin_clear_data.php';</script>";
            exit;
        }

        $backup = json_decode($row['backup_data'], true);

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        foreach ($backup as $table => $rows) {
            try {
                $pdo->exec("TRUNCATE TABLE `$table`;");
                if (!empty($rows)) {
                    $cols    = array_keys($rows[0]);
                    $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
                    $ph      = implode(',', array_map(fn($c) => ":$c", $cols));
                    $ins     = $pdo->prepare("INSERT INTO `$table` ($colList) VALUES ($ph)");
                    foreach ($rows as $r) {
                        $ins->execute($r);
                    }
                }
            } catch (Exception $e) {}
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Mark as restored
        $pdo->prepare("UPDATE truncate_history SET status='restored', restored_at=NOW() WHERE id=:id")
            ->execute(['id' => $histId]);

        logAudit($pdo, $_SESSION['id'] ?? 0, $_SESSION['username'] ?? 'Admin', 'Database Restored', "From backup: {$row['backup_name']}");

        echo "<script>alert('Data restored from backup: {$row['backup_name']}.'); window.location='admin_clear_data.php';</script>";
        exit;
    }
}

// ── Fetch history ─────────────────────────────────────────────────────────────
$history = $pdo->query("SELECT * FROM truncate_history ORDER BY truncated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truncate & Backup — Mariategue</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { background:#f8fafc; min-height:100vh; }

        .page-wrapper { max-width:960px; margin:40px auto; padding:0 20px 60px; }

        /* ── Header ── */
        .page-header { display:flex; align-items:center; gap:14px; margin-bottom:32px; }
        .page-header .back-btn {
            display:inline-flex; align-items:center; gap:8px;
            padding:9px 18px; background:#fff; border:1px solid #e2e8f0;
            border-radius:10px; font-size:.875rem; font-weight:500; color:#475569;
            text-decoration:none; transition:.2s;
        }
        .page-header .back-btn:hover { background:#f1f5f9; }
        .page-header h1 { font-size:1.6rem; color:#1e293b; font-weight:700; }
        .page-header p  { font-size:.875rem; color:#64748b; margin-top:2px; }

        /* ── Cards ── */
        .card {
            background:#fff; border-radius:14px; padding:28px;
            box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:28px;
        }
        .card-title { font-size:1rem; font-weight:600; color:#1e293b; margin-bottom:18px;
                      display:flex; align-items:center; gap:10px; }

        /* ── Action buttons ── */
        .actions { display:flex; gap:14px; flex-wrap:wrap; }
        .btn {
            display:inline-flex; align-items:center; gap:8px;
            padding:12px 24px; border:none; border-radius:10px;
            font-size:.9rem; font-weight:600; cursor:pointer; transition:.2s; color:#fff;
        }
        .btn-warning { background:#f59e0b; }
        .btn-warning:hover { background:#d97706; }
        .btn-danger  { background:#ef4444; }
        .btn-danger:hover  { background:#dc2626; }
        .btn-restore { background:#10b981; font-size:.8rem; padding:7px 14px; border-radius:8px; }
        .btn-restore:hover { background:#059669; }
        .btn-restore:disabled { background:#94a3b8; cursor:not-allowed; }

        /* ── Table ── */
        .tbl-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:.875rem; }
        thead th {
            background:#f1f5f9; color:#475569; font-weight:600;
            padding:12px 14px; text-align:left; white-space:nowrap;
        }
        tbody tr { border-bottom:1px solid #f1f5f9; transition:.15s; }
        tbody tr:hover { background:#fafafa; }
        tbody td { padding:12px 14px; color:#334155; vertical-align:middle; }

        .badge {
            display:inline-block; padding:3px 10px; border-radius:20px;
            font-size:.75rem; font-weight:600;
        }
        .badge-truncated { background:#fee2e2; color:#dc2626; }
        .badge-restored  { background:#dcfce7; color:#16a34a; }

        .empty-row td { text-align:center; color:#94a3b8; padding:28px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/miscellaneous/confirm_dialog.php'; ?>

<div class="page-wrapper">

    <!-- Header -->
    <div class="page-header">
        <a href="dashboard/dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <div>
            <h1><i class="fa-solid fa-database" style="color:#ef4444"></i> Truncate &amp; Backup</h1>
            <p>Clear data with automatic backup. Restore anytime from history.</p>
        </div>
    </div>

    <!-- Action Card -->
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b"></i> Truncate Actions</div>
        <p style="font-size:.875rem;color:#64748b;margin-bottom:20px;">
            A backup is automatically saved before any truncation. You can restore it from the history below.
        </p>
        <div class="actions">
            <form method="POST">
                <input type="hidden" name="action" value="clear_transactions">
                <button type="button" class="btn btn-warning"
                    onclick="showConfirmDialog('Clear ALL transactions and appointments? A backup will be saved first.', function(){ this.closest('form').submit(); }.bind(document.querySelector('[name=action][value=clear_transactions]').closest('form')), { title:'Clear Transactions', icon:'warning', okText:'Yes, Clear' })">
                    <i class="fa-solid fa-broom"></i> Clear Transactions &amp; Appointments
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="clear_all">
                <button type="button" class="btn btn-danger"
                    onclick="showConfirmDialog('FACTORY RESET: Wipes everything except the Admin account. A backup will be saved first. Proceed?', function(){ document.querySelectorAll('[name=action][value=clear_all]')[0].closest(\'form\').submit(); }, { title:\'Factory Reset\', icon:\'danger\', danger:true, okText:\'Yes, Wipe Everything\' })">
                    <i class="fa-solid fa-rotate-left"></i> Factory Reset (Wipe All Data)
                </button>
            </form>
        </div>
    </div>

    <!-- History Table -->
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color:#0ea5e9"></i> Backup &amp; Restore History</div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Backup File</th>
                        <th>Type</th>
                        <th>Truncated At</th>
                        <th>Restored At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($history)): ?>
                    <tr class="empty-row"><td colspan="7">No backup history yet. Truncate actions will appear here.</td></tr>
                <?php else: foreach ($history as $i => $h): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td style="font-family:monospace;font-size:.8rem"><?= htmlspecialchars($h['backup_name']) ?></td>
                        <td><?= htmlspecialchars($h['truncate_type']) ?></td>
                        <td><?= htmlspecialchars($h['truncated_at']) ?></td>
                        <td><?= $h['restored_at'] ? htmlspecialchars($h['restored_at']) : '<span style="color:#94a3b8">—</span>' ?></td>
                        <td>
                            <?php if ($h['status'] === 'restored'): ?>
                                <span class="badge badge-restored">Restored</span>
                            <?php else: ?>
                                <span class="badge badge-truncated">Truncated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($h['status'] !== 'restored'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="history_id" value="<?= $h['id'] ?>">
                                <button type="button" class="btn btn-restore"
                                    onclick="showConfirmDialog('Restore data from backup: <?= htmlspecialchars(addslashes($h['backup_name'])) ?>? Current data will be overwritten.', function(){ document.querySelectorAll('.restore-submit-<?= $h['id'] ?>')[0].click(); }, { title:'Restore Backup', icon:'warning', okText:'Yes, Restore' })">
                                    <i class="fa-solid fa-rotate"></i> Restore
                                </button>
                                <input type="submit" class="restore-submit-<?= $h['id'] ?>" style="display:none">
                            </form>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:.8rem">Already restored</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
