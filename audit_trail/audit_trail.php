<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';

// ── Guard: Admin only ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: /main_page/pconcio_main.php');
    exit;
}

// ── Filters ────────────────────────────────────────────────────────────────
$filter_type = trim($_GET['user_type'] ?? '');
$filter_date = trim($_GET['date'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 20;
$offset      = ($page - 1) * $per_page;

// ── Build WHERE ────────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($filter_type !== '') {
    $where[]             = 'user_type = :user_type';
    $params['user_type'] = $filter_type;
}

if ($filter_date !== '') {
    $where[]               = 'DATE(created_at) = :filter_date';
    $params['filter_date'] = $filter_date;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_trail $where_sql");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch rows
$data_stmt = $pdo->prepare("
    SELECT id, user_type, user_name, action, details, created_at
    FROM audit_trail
    $where_sql
    ORDER BY created_at DESC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) {
    $data_stmt->bindValue(':' . $k, $v);
}
$data_stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$data_stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
$data_stmt->execute();
$logs = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary counts
$counts_stmt = $pdo->query("SELECT user_type, COUNT(*) AS cnt FROM audit_trail GROUP BY user_type");
$counts_raw  = $counts_stmt->fetchAll(PDO::FETCH_ASSOC);
$counts      = ['admin' => 0, 'dentist' => 0, 'staff' => 0, 'patient' => 0];
$total_all   = 0;
foreach ($counts_raw as $row) {
    $t = strtolower($row['user_type']);
    if (isset($counts[$t])) $counts[$t] = (int)$row['cnt'];
    $total_all += (int)$row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Trail — Mariategue Ortho-DentalClinic</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <link rel="stylesheet" href="audit_trail_design.css">
</head>
<body>

<?php include __DIR__ . '/../miscellaneous/sidebar.php'; ?>

<div class="main-content">

  <!-- ── Page Header ── -->
  <div class="at-header">
    <h2><i class="fa-solid fa-clock-rotate-left"></i> Audit Trail</h2>

    <!-- Filters: role + date only -->
    <form method="GET" class="at-filters" id="filterForm">
      <select name="user_type" onchange="document.getElementById('filterForm').submit()">
        <option value="">All Roles</option>
        <option value="admin"   <?= $filter_type === 'admin'   ? 'selected' : '' ?>>Admin</option>
        <option value="dentist" <?= $filter_type === 'dentist' ? 'selected' : '' ?>>Dentist</option>
        <option value="staff"   <?= $filter_type === 'staff'   ? 'selected' : '' ?>>Staff</option>
        <option value="patient" <?= $filter_type === 'patient' ? 'selected' : '' ?>>Patient</option>
      </select>

      <input type="date"
             name="date"
             value="<?= htmlspecialchars($filter_date) ?>"
             onchange="document.getElementById('filterForm').submit()">

      <?php if ($filter_type || $filter_date): ?>
        <a href="audit_trail.php" class="btn-clear"><i class="fa-solid fa-xmark"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- ── Stats Cards ── -->
  <div class="at-stats">
    <div class="at-stat-card total-card">
      <span class="stat-label">Total Logs</span>
      <span class="stat-number"><?= number_format($total_all) ?></span>
    </div>
    <div class="at-stat-card admin-card">
      <span class="stat-label">Admin</span>
      <span class="stat-number"><?= number_format($counts['admin']) ?></span>
    </div>
    <div class="at-stat-card dentist-card">
      <span class="stat-label">Dentist</span>
      <span class="stat-number"><?= number_format($counts['dentist']) ?></span>
    </div>
    <div class="at-stat-card staff-card">
      <span class="stat-label">Staff</span>
      <span class="stat-number"><?= number_format($counts['staff']) ?></span>
    </div>
    <div class="at-stat-card patient-card">
      <span class="stat-label">Patient</span>
      <span class="stat-number"><?= number_format($counts['patient']) ?></span>
    </div>
  </div>

  <!-- ── Table ── -->
  <div class="table-wrapper">
    <?php if (empty($logs)): ?>
      <div class="at-empty">
        <i class="fa-solid fa-inbox"></i>
        No audit logs found.
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Time Logs</th>
            <th>User Type</th>
            <th>Actions</th>
            <th>Description</th>
            <th>Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <!-- TimeLogs: name of the user who acted -->
            <td class="col-timelogs"><?= htmlspecialchars($log['user_name']) ?></td>

            <!-- UserType: role badge -->
            <td>
              <span class="role-badge <?= htmlspecialchars(strtolower($log['user_type'])) ?>">
                <?= htmlspecialchars(ucfirst($log['user_type'])) ?>
              </span>
            </td>

            <!-- Actions: what was done -->
            <td class="col-action"><?= htmlspecialchars($log['action']) ?></td>

            <!-- Description: details -->
            <td class="col-details">
              <?= $log['details'] ? htmlspecialchars($log['details']) : '<span class="empty-cell">—</span>' ?>
            </td>

            <!-- CreatedAt: date & time -->
            <td class="col-date"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="at-pagination">
        <span>
          Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?>
          of <?= number_format($total_rows) ?> logs
        </span>
        <div class="page-btns">
          <?php
          $bp = array_filter(['user_type' => $filter_type, 'date' => $filter_date]);
          $base = 'audit_trail.php' . ($bp ? ('?' . http_build_query($bp)) : '');
          $sep  = $bp ? '&' : '?';
          ?>
          <button onclick="location='<?= $base.$sep ?>page=1'"
                  <?= $page<=1?'disabled':'' ?>><i class="fa-solid fa-angles-left"></i></button>
          <button onclick="location='<?= $base.$sep ?>page=<?= max(1,$page-1) ?>'"
                  <?= $page<=1?'disabled':'' ?>><i class="fa-solid fa-angle-left"></i></button>

          <?php for ($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
            <button class="<?= $p===$page?'active':'' ?>"
                    onclick="location='<?= $base.$sep ?>page=<?= $p ?>'">
              <?= $p ?>
            </button>
          <?php endfor; ?>

          <button onclick="location='<?= $base.$sep ?>page=<?= min($total_pages,$page+1) ?>'"
                  <?= $page>=$total_pages?'disabled':'' ?>><i class="fa-solid fa-angle-right"></i></button>
          <button onclick="location='<?= $base.$sep ?>page=<?= $total_pages ?>'"
                  <?= $page>=$total_pages?'disabled':'' ?>><i class="fa-solid fa-angles-right"></i></button>
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
