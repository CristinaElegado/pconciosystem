<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

// Admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

// Auto-create truncate_history table
$pdo->exec("CREATE TABLE IF NOT EXISTS `truncate_history` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `truncated_at` DATETIME NOT NULL,
    `performed_by` VARCHAR(100) NOT NULL,
    `backup_file`  VARCHAR(255) NOT NULL,
    `tables_list`  TEXT NOT NULL,
    `restored`     TINYINT(1) DEFAULT 0,
    `restored_at`  DATETIME NULL
)");

// Fetch truncate history
$history = $pdo->query("SELECT * FROM truncate_history ORDER BY truncated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Flash messages
$success = $_SESSION['settings_success'] ?? '';
$error   = $_SESSION['settings_error']   ?? '';
unset($_SESSION['settings_success'], $_SESSION['settings_error']);

include __DIR__ . '/../miscellaneous/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings</title>
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { background-image: none !important; }

    .settings-wrapper {
      max-width: 860px;
    }

    .settings-section {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.07);
      padding: 28px 32px;
      margin-bottom: 28px;
    }

    .settings-section h3 {
      font-size: 1.05rem;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 6px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .settings-section p.section-desc {
      font-size: 0.87rem;
      color: #64748b;
      margin: 0 0 20px;
      line-height: 1.5;
    }

    /* Audit Trail shortcut card */
    .audit-link-card {
      display: flex;
      align-items: center;
      gap: 16px;
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      border-radius: 10px;
      padding: 16px 20px;
      text-decoration: none;
      color: #0369a1;
      transition: background 0.18s;
    }
    .audit-link-card:hover { background: #e0f2fe; }
    .audit-link-card .audit-icon {
      width: 44px; height: 44px;
      background: #0ea5e9;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.2rem; flex-shrink: 0;
    }
    .audit-link-card .audit-text strong { display: block; font-size: 0.95rem; font-weight: 700; }
    .audit-link-card .audit-text span   { font-size: 0.82rem; color: #64748b; }

    /* Truncate danger zone */
    .danger-zone {
      border: 1.5px solid #fca5a5;
      background: #fff7f7;
    }
    .danger-zone h3 { color: #dc2626; }

    .tables-badge-list {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 20px;
    }
    .tables-badge-list span {
      background: #fee2e2;
      color: #b91c1c;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 999px;
      font-family: monospace;
    }

    .btn-truncate {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #ef4444;
      color: #fff;
      border: none;
      padding: 11px 26px;
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      font-family: 'Poppins', sans-serif;
      transition: background 0.18s;
    }
    .btn-truncate:hover { background: #dc2626; }

    /* History table */
    .history-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .history-table th {
      background: #0ea5e9; color: #fff;
      padding: 10px 14px; text-align: left;
      font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .history-table td {
      padding: 10px 14px;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
      vertical-align: middle;
    }
    .history-table tr:last-child td { border-bottom: none; }
    .history-table tr:nth-child(even) td { background: #f8fafc; }

    .badge-restored { background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; }
    .badge-pending  { background: #fef3c7; color: #92400e; font-size: 0.72rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; }

    .btn-restore {
      display: inline-flex; align-items: center; gap: 5px;
      background: #0ea5e9; color: #fff; border: none;
      padding: 6px 14px; border-radius: 6px; font-size: 0.82rem;
      font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif;
      transition: background 0.18s;
    }
    .btn-restore:hover { background: #0284c7; }
    .btn-restore:disabled { background: #94a3b8; cursor: not-allowed; }

    /* Flash messages */
    .flash-success {
      background: #dcfce7; color: #166534; border: 1px solid #86efac;
      border-radius: 10px; padding: 12px 18px; margin-bottom: 20px;
      font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
    }
    .flash-error {
      background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
      border-radius: 10px; padding: 12px 18px; margin-bottom: 20px;
      font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
    }

    @media (max-width: 900px) {
      .main-content { margin-left: 0 !important; margin-top: 70px !important; padding: 15px !important; width: 100% !important; }
      .settings-section { padding: 18px 14px; }
      .history-table { font-size: 0.78rem; }
    }
  </style>
</head>
<body>
<div class="main-content">
  <div class="settings-wrapper">
    <h2><i class="fa-solid fa-gear" style="color:#0ea5e9; margin-right:10px;"></i>Settings</h2>

    <?php if ($success): ?>
      <div class="flash-success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="flash-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ===== AUDIT TRAIL SHORTCUT ===== -->
    <div class="settings-section">
      <h3><i class="fa-solid fa-clock-rotate-left" style="color:#0ea5e9;"></i> Audit Trail</h3>
      <p class="section-desc">View a full log of all admin, staff, and dentist actions performed in the system.</p>
      <a href="../audit_trail/audit_trail.php" class="audit-link-card">
        <div class="audit-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div class="audit-text">
          <strong>Open Audit Trail</strong>
          <span>View all system activity logs</span>
        </div>
        <i class="fa-solid fa-chevron-right" style="margin-left:auto; color:#94a3b8;"></i>
      </a>
    </div>

    <!-- ===== TRUNCATE DATABASE ===== -->
    <div class="settings-section danger-zone">
      <h3><i class="fa-solid fa-triangle-exclamation"></i> Truncate Database</h3>
      <p class="section-desc">
        This will <strong>permanently delete all operational data</strong> from the following tables.
        A <strong>backup JSON file</strong> will be saved automatically before truncating so you can restore later.
        Account data (admin, staff, dentist, patient accounts, services) will <strong>NOT</strong> be deleted.
      </p>

      <div class="tables-badge-list">
        <span>patients_list</span>
        <span>patient_services</span>
        <span>online_appointment</span>
        <span>online_appointment_services</span>
        <span>transaction_history</span>
        <span>item_inventory</span>
        <span>dentist_schedule</span>
        <span>dentist_time_schedules</span>
        <span>dentist_accounts</span>
        <span>audit_log</span>
      </div>

      <button class="btn-truncate" onclick="confirmTruncate()">
        <i class="fa-solid fa-trash-can"></i> Truncate Database
      </button>

      <!-- Hidden form submitted by JS after confirm -->
      <form id="truncateForm" method="post" action="../miscellaneous/do_truncate.php" style="display:none;">
        <input type="hidden" name="confirm_truncate" value="1">
      </form>
    </div>

    <!-- ===== TRUNCATE HISTORY ===== -->
    <div class="settings-section">
      <h3><i class="fa-solid fa-history" style="color:#0ea5e9;"></i> Truncate History</h3>
      <p class="section-desc">Previous truncations and their backup files. Click <strong>Restore</strong> to bring back the data from that backup.</p>

      <?php if (empty($history)): ?>
        <div style="text-align:center; padding:30px; color:#94a3b8; font-size:0.9rem;">
          <i class="fa-solid fa-database" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
          No truncate history yet.
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="history-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Date &amp; Time</th>
                <th>Performed By</th>
                <th>Backup File</th>
                <th>Status</th>
                <th>Restored At</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $i => $h): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= date('M d, Y g:i A', strtotime($h['truncated_at'])) ?></td>
                <td><?= htmlspecialchars($h['performed_by']) ?></td>
                <td style="font-family:monospace; font-size:0.8rem;"><?= htmlspecialchars($h['backup_file']) ?></td>
                <td>
                  <?php if ($h['restored']): ?>
                    <span class="badge-restored"><i class="fa-solid fa-check"></i> Restored</span>
                  <?php else: ?>
                    <span class="badge-pending"><i class="fa-solid fa-clock"></i> Not Restored</span>
                  <?php endif; ?>
                </td>
                <td><?= $h['restored_at'] ? date('M d, Y g:i A', strtotime($h['restored_at'])) : '—' ?></td>
                <td>
                  <?php if ($h['restored']): ?>
                    <button type="button" class="btn-restore" disabled>
                      <i class="fa-solid fa-check"></i> Restored
                    </button>
                  <?php else: ?>
                  <form method="post" action="../miscellaneous/do_restore.php" id="restoreForm_<?= $h['id'] ?>">
                    <input type="hidden" name="history_id"  value="<?= $h['id'] ?>">
                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($h['backup_file']) ?>">
                    <button type="button" class="btn-restore"
                      onclick="confirmRestore(<?= $h['id'] ?>, '<?= htmlspecialchars($h['backup_file'], ENT_QUOTES) ?>')">
                      <i class="fa-solid fa-rotate-left"></i> Restore
                    </button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /.settings-wrapper -->
</div><!-- /.main-content -->

<script>
  function confirmTruncate() {
    showConfirmDialog(
      'This will permanently delete all operational data and cannot be undone without restoring a backup. A backup will be saved automatically. Proceed?',
      function() {
        document.getElementById('truncateForm').submit();
      },
      { title: 'Truncate Database', icon: 'danger', danger: true, okText: 'Yes, Truncate' }
    );
  }

  function confirmRestore(id, file) {
    showConfirmDialog(
      'This will restore all data from the backup: ' + file + '. Current data will be overwritten. Proceed?',
      function() {
        document.getElementById('restoreForm_' + id).submit();
      },
      { title: 'Restore Backup', icon: 'restore', okText: 'Yes, Restore' }
    );
  }
</script>
</body>
</html>
