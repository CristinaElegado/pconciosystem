<head>
    <!-- ... other lines ... -->
    <link rel="stylesheet" href="transaction_design.css">
    <style>
        /* Add this line to remove the background */
        body {
            background-image: none !important;
        }

        /* Existing styles... */
    </style>
</head>
<head>
    <!-- ... other lines ... -->
    <link rel="stylesheet" href="transaction_design.css">
    <style>
        /* Add this line to remove the background */
        body {
            background-image: none !important;
        }

        /* Existing styles... */
    </style>
</head>
<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

// ✅ Pagination
$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

// ✅ Filters
$search = $_GET['q'] ?? "";
$type_filter = $_GET['type'] ?? "ALL";

// ✅ Base query
$query = "FROM transaction_history WHERE 1";
$params = [];

// ✅ Search filter
if (!empty($search)) {
    $query .= " AND patient_name LIKE ?";
    $params[] = "%$search%";
}

// ✅ Appointment type filter
if ($type_filter !== "ALL") {
    $query .= " AND appointment_type = ?";
    $params[] = $type_filter;
}

// ✅ Date range filter (moved here and validated)
$date_from = $_GET['date_from'] ?? "";
$date_to = $_GET['date_to'] ?? "";

if (!empty($date_from)) {
    $d = DateTime::createFromFormat('Y-m-d', $date_from);
    if ($d) {
        $query .= " AND date_completed >= ?";
        $params[] = $d->format('Y-m-d') . " 00:00:00";
    }
}

if (!empty($date_to)) {
    $d = DateTime::createFromFormat('Y-m-d', $date_to);
    if ($d) {
        $query .= " AND date_completed <= ?";
        $params[] = $d->format('Y-m-d') . " 23:59:59";
    }
}

// ✅ Count total rows
$stmt = $pdo->prepare("SELECT COUNT(*) " . $query);
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();

// ✅ Compute total pages
$totalPages = max(1, ceil($totalRows / $rowsPerPage));

// ✅ Fetch paginated results
$stmt = $pdo->prepare("SELECT * " . $query . " ORDER BY date_completed DESC LIMIT $rowsPerPage OFFSET $offset");
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Helper to keep filters when switching pages
function buildQueryPreserve($extra = []) {
    $base = $_GET;
    foreach ($extra as $k => $v) {
        $base[$k] = $v;
    }
    return http_build_query($base);
}

include __DIR__ . '/../miscellaneous/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaction History</title>
<link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
<link rel="stylesheet" href="transaction_design.css">
</head>
<style>
  /* Make sure panel doesn't cover small screens awkwardly */
    @media (max-width: 900px) {
        .prescription-panel { right: 10px; width: 220px; top: 70px; }
        .prescription-toggle { right: 10px; top: 40px; }
    }
    /* --- CLEAN & WORKING MOBILE SIDEBAR FIX --- */
@media (max-width: 768px) {
  /* 1. Sidebar Hiding & Slide-out Logic */
  .sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 260px !important;
    height: 100vh !important;
    z-index: 999999 !important;
    transform: translateX(-100%) !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
    transition: transform 0.3s ease-in-out, visibility 0.3s ease-in-out, opacity 0.3s ease-in-out !important;
  }

  /* Kapag pinindot ang menu at pumasok ang .active class */
  .sidebar.active {
    transform: translateX(0) !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
  }

  /* 2. Top Header Adjustments */
  .topbar {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 8px 10px !important;
    flex-wrap: nowrap !important;
    gap: 5px !important;
    height: auto !important;
    min-height: 60px;
  }

  .topbar-left {
    gap: 8px !important;
    flex: 1;
    min-width: 0;
  }

  .topbar .logo, 
  .clinic-title-top,
  .logo {
    font-size: 11px !important;
    max-width: 55% !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
  }

  .topbar div:last-child, 
  .topbar span, 
  .topbar h3 {
    font-size: 10px !important;
    white-space: nowrap !important;
  }

  /* 3. Main Content Spacing */
  .main-content {
    margin-top: 65px !important;
    margin-left: 0 !important;
    width: 100% !important;
    padding: 8px !important;
  }
}
</style>
<body>

<div class="main-content">
  <h2>Transaction History</h2>

  <div class="filters">
    <form method="get" id="filterForm" class="filters-form">
      <input type="search" name="q" placeholder="Search patient" value="<?= htmlspecialchars($search) ?>" class="filter-input" oninput="debounceSubmit()">

      <select name="type" class="filter-select" onchange="document.getElementById('filterForm').submit()">
        <option value="ALL">All Types</option>
        <option value="ONLINE"  <?= $type_filter === 'ONLINE'  ? 'selected' : '' ?>>Online</option>
        <option value="WALK-IN" <?= $type_filter === 'WALK-IN' ? 'selected' : '' ?>>Walk-in</option>
      </select>
    </form>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Patient</th>
          <th>Dentist</th>
          <th>Service</th>
          <th>Price</th>
          <th>Appointment Type</th>
          <th>Date Completed</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr><td colspan="7" style="text-align:center;">No transactions found.</td></tr>
        <?php else: ?>
          <?php foreach ($transactions as $index => $row): ?>
            <tr>
              <td data-label="Row"><?= $offset + $index + 1 ?></td>
              <td data-label="Patient"><?= htmlspecialchars($row['patient_name']) ?></td>
              <td data-label="Dentist"><?= htmlspecialchars($row['dentist_name']) ?></td>
              <td data-label="Service"><?= htmlspecialchars($row['service_name']) ?></td>
              <td data-label="Price"><?= number_format($row['price'], 2) ?></td>
              <td data-label="Type"><?= $row['appointment_type'] ?></td>
              <td data-label="Date Completed"><?= date("M d, Y H:i", strtotime($row['date_completed'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pagination">
    <?php 
      $prevPage = max(1, $page-1); 
      $nextPage = min($totalPages, $page+1);
    ?>
    <a href="transaction.php?<?= buildQueryPreserve(['page'=>$prevPage]) ?>" class="page-link">&laquo; Prev</a>
    <span class="current-page"><?= $page ?></span>
    <a href="transaction.php?<?= buildQueryPreserve(['page'=>$nextPage]) ?>" class="page-link">Next &raquo;</a>
    <div class="pagination-info">
      Page <?= $page ?> of <?= $totalPages ?> — <?= $totalRows ?> total
    </div>
  </div>

</div>

<script>
// Auto-submit search after user stops typing (300ms debounce)
let _searchTimer = null;
function debounceSubmit() {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(function() {
        document.getElementById('filterForm').submit();
    }, 300);
}

// ✅ DAPAT NASA LABAS ITO PARA MAGING GLOBAL FUNCTION NA MATAWAG NG BUTTON
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar') || document.querySelector('#sidebar') || document.querySelector('aside');
    if (sidebar) {
        // Ginagamit natin ang classList toggle para sumabay sa CSS (.active)
        sidebar.classList.toggle('active');
        
        // Pwede rin nating i-handle ang display kung kinakailangan
        if (sidebar.classList.contains('active')) {
            sidebar.style.display = 'block';
        } else {
            // Opsyonal: ibabalik sa default o display none depende sa CSS mo
        }
    }
}
</script>
</body>
</html>
