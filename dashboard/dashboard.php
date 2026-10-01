<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

function getData($pdo, $query, $params = []) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$totalPatients = getData($pdo, "SELECT COUNT(DISTINCT CONCAT(first_name, ' ', last_name)) AS count FROM patients_list")['count'] ?? 0;
$totalOngoing = getData($pdo, "SELECT COUNT(*) AS count FROM patients_list WHERE status = 'ONGOING'")['count'] ?? 0;
$totalPending = getData($pdo, "SELECT COUNT(*) AS count FROM patients_list WHERE status = 'WAITING'")['count'] ?? 0;
$totalRevenue = getData($pdo, "SELECT SUM(price) AS sum FROM transaction_history WHERE MONTH(date_completed) = MONTH(CURDATE()) AND YEAR(date_completed) = YEAR(CURDATE())")['sum'] ?? 0;

$totalSales = getData($pdo, "SELECT SUM(price) AS sum FROM transaction_history")['sum'] ?? 0;

$totalDentists = getData($pdo, "SELECT COUNT(*) AS count FROM dentist_accounts WHERE is_active = TRUE AND is_deleted = 0")['count'] ?? 0;
$totalStaff = getData($pdo, "SELECT COUNT(*) AS count FROM staff_accounts WHERE is_active = TRUE AND is_deleted = 0")['count'] ?? 0;
$lowStockItems = getData($pdo, "SELECT COUNT(*) AS count FROM item_inventory WHERE quantity <= 20")['count'] ?? 0;
$totalAppointmentsToday = getData($pdo, "SELECT COUNT(*) AS count FROM patients_list WHERE date_visit = CURDATE()")['count'] ?? 0;

$stmt = $pdo->prepare("SELECT p.first_name, p.last_name, p.time_visit, p.status, d.first_name AS dentist_first, d.last_name AS dentist_last FROM patients_list p JOIN dentist_accounts d ON p.dentist_id = d.id WHERE p.date_visit = CURDATE() ORDER BY p.time_visit ASC LIMIT 10");
$stmt->execute();
$todaysAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT patient_name, service_name, price, appointment_type, date_completed FROM transaction_history ORDER BY date_completed DESC LIMIT 5");
$stmt->execute();
$recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT service_name, COUNT(*) AS count
    FROM transaction_history
    WHERE MONTH(date_completed) = MONTH(CURDATE())
      AND YEAR(date_completed) = YEAR(CURDATE())
    GROUP BY service_name
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute();
$topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$servicesLabels = json_encode(array_column($topServices, 'service_name'));
$servicesCounts = json_encode(array_column($topServices, 'count'));

$stmt = $pdo->prepare("SELECT appointment_type, SUM(price) AS total FROM transaction_history WHERE MONTH(date_completed) = MONTH(CURDATE()) AND YEAR(date_completed) = YEAR(CURDATE()) GROUP BY appointment_type");
$stmt->execute();
$revenueByType = $stmt->fetchAll(PDO::FETCH_ASSOC);
$revenueLabels = json_encode(array_column($revenueByType, 'appointment_type'));
$revenueData = json_encode(array_column($revenueByType, 'total'));

include __DIR__ . '/../miscellaneous/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="dashboard_design.css">
        <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<style>
 @media (max-width: 900px) {
  .main-content {
    margin-left: 0 !important;
    margin-top: 70px !important;
    width: 100% !important;
    position: relative !important;
    left: 0 !important;
    padding: 15px !important;
  }

  .sidebar {
    position: fixed !important;
    top: 0 !important;
    left: -260px !important;
    width: 260px !important;
    height: 100vh !important;
    z-index: 999999 !important;
    transition: left 0.3s ease-in-out !important;
  }

  .sidebar.active {
    left: 0 !important;
  }

  /* --- TAMANG PAG-AYOS SA TOPBAR PARA SA MAHAHABANG PANGALAN --- */
  .topbar {
    left: 0 !important;
    width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 0 10px !important;
    position: fixed !important;
    top: 0 !important;
    z-index: 9998 !important;
    background: #ffffff !important;
    height: 60px !important;
    box-sizing: border-box !important;
  }

  /* Hatiin ang kaliwang side para magkasya ang hamburger at pangalan */
  .topbar-left, .topbar > div:first-child {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    flex: 1 !important;
    min-width: 0 !important;
    overflow: hidden !important;
  }

  /* I-truncate (lagyan ng ...) ang pangalan ng clinic kapag humahaba */
  .topbar .logo, 
  .topbar h3,
  .topbar .clinic-title {
    font-size: 13px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    max-width: 160px !important; /* Limitasyon sa haba para di maipit ang welcome text */
    margin: 0 !important;
  }

  /* Siguraduhing may space ang Welcome text sa kanan at hindi masisiksik */
  .topbar div:last-child, 
  .topbar span {
    font-size: 11px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
  }

  .table-wrapper {
    overflow-x: auto !important;
  }
}
</style>
<body>
      <div class="main-content">
    <h2>Dashboard</h2>
    <p class="dashboard-subtitle">Welcome back! Here's an overview of your clinic today.</p>

    <!-- ROW 1: 5 cards -->
    <div class="dashboard-cards dashboard-cards-row1">
      <div class="card card-blue" onclick="openCardModal('total-patients')">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <h5>Total Patients</h5>
        <p><?php echo $totalPatients; ?></p>
      </div>
      <div class="card card-teal" onclick="openCardModal('total-ongoing')">
        <div class="card-icon"><i class="fas fa-clock"></i></div>
        <h5>Total Ongoing</h5>
        <p><?php echo $totalOngoing; ?></p>
      </div>
      <div class="card card-violet" onclick="openCardModal('waiting-appointments')">
        <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
        <h5>Waiting Appointments</h5>
        <p><?php echo $totalPending; ?></p>
      </div>
      <div class="card card-cyan" onclick="openCardModal('total-dentists')">
        <div class="card-icon"><i class="fas fa-user-md"></i></div>
        <h5>Total Dentists</h5>
        <p><?php echo $totalDentists; ?></p>
      </div>
      <div class="card card-indigo" onclick="openCardModal('total-staff')">
        <div class="card-icon"><i class="fas fa-user-tie"></i></div>
        <h5>Total Staff</h5>
        <p><?php echo $totalStaff; ?></p>
      </div>
    </div>

    <!-- ROW 2: 4 cards -->
    <div class="dashboard-cards dashboard-cards-row2">
      <div class="card card-rose" onclick="openCardModal('low-stock')">
        <div class="card-icon"><i class="fas fa-box-open"></i></div>
        <h5>Low Stock Items</h5>
        <p><?php echo $lowStockItems; ?></p>
      </div>
      <div class="card card-orange" onclick="openCardModal('appointments-today')">
        <div class="card-icon"><i class="fas fa-calendar-check"></i></div>
        <h5>Appointments Today</h5>
        <p><?php echo $totalAppointmentsToday; ?></p>
      </div>
      <div class="card card-emerald" onclick="openCardModal('monthly-revenue')">
        <div class="card-icon"><i class="fas fa-peso-sign"></i></div>
        <h5>Monthly Revenue</h5>
        <p>₱<?php echo number_format($totalRevenue, 2); ?></p>
      </div>
      <div class="card card-amber" onclick="openCardModal('total-sales')">
        <div class="card-icon"><i class="fas fa-chart-line"></i></div>
        <h5>Total Sales</h5>
        <p>₱<?php echo number_format($totalSales, 2); ?></p>
      </div>
    </div>

    <!-- Today's Appointments + Top Services side by side -->
    <div class="dashboard-row-split">
      <div class="table-wrapper">
        <h3><i class="fas fa-calendar-day"></i> Today's Appointments</h3>
        <table>
          <thead>
            <tr>
              <th>Patient Name</th>
              <th>Time</th>
              <th>Dentist</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($todaysAppointments)): ?>
              <tr><td colspan="4" style="text-align:center;">No appointments today.</td></tr>
            <?php else: ?>
              <?php foreach ($todaysAppointments as $row): ?>
                <tr>
                  <td data-label="Patient Name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                  <td data-label="Time"><?php echo date('g:i A', strtotime($row['time_visit'])); ?></td>
                  <td data-label="Dentist"><?php echo htmlspecialchars($row['dentist_first'] . ' ' . $row['dentist_last']); ?></td>
                  <td data-label="Status"><?php echo htmlspecialchars($row['status']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="chart-wrapper">
        <h3><i class="fas fa-tooth"></i> Top Services (This Month)</h3>
        <canvas id="servicesChart" width="400" height="200"></canvas>
      </div>
    </div>

    <!-- Recent Transactions + Revenue side by side -->
    <div class="dashboard-row-split">
      <div class="table-wrapper">
        <h3><i class="fas fa-receipt"></i> Recent Transactions</h3>
        <table>
          <thead>
            <tr>
              <th>Patient Name</th>
              <th>Service</th>
              <th>Price</th>
              <th>Type</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentTransactions)): ?>
              <tr><td colspan="5" style="text-align:center;">No recent transactions.</td></tr>
            <?php else: ?>
              <?php foreach ($recentTransactions as $row): ?>
                <tr>
                  <td data-label="Patient Name"><?php echo htmlspecialchars($row['patient_name']); ?></td>
                  <td data-label="Service"><?php echo htmlspecialchars($row['service_name']); ?></td>
                  <td data-label="Price">₱<?php echo number_format($row['price'], 2); ?></td>
                  <td data-label="Type"><?php echo htmlspecialchars($row['appointment_type']); ?></td>
                  <td data-label="Date"><?php echo htmlspecialchars(date('M d, Y', strtotime($row['date_completed']))); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="chart-wrapper">
        <h3><i class="fas fa-chart-pie"></i> Revenue by Appointment Type</h3>
        <canvas id="revenueChart" width="400" height="200"></canvas>
      </div>
    </div>
  </div>

  <!-- ===== CARD MODAL ===== -->
  <div id="cardModalOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:99998; justify-content:center; align-items:center;">
    <div style="background:#fff; border-radius:16px; padding:28px; width:90%; max-width:750px; max-height:85vh; overflow-y:auto; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
      <div onclick="closeCardModal()" style="position:absolute; top:10px; right:12px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; border-radius:8px; font-size:1.3rem; color:#64748b; background:transparent; z-index:1; user-select:none;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">&times;</div>
      <h3 id="cardModalTitle" style="margin:0 0 14px; font-size:1.1rem; color:#1e293b; font-family:Poppins,sans-serif;"></h3>
      <input type="text" id="cardModalSearch" placeholder="Search..." oninput="filterCardTable()" style="width:100%; padding:9px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.9rem; margin-bottom:14px; box-sizing:border-box; font-family:Poppins,sans-serif;">
      <div style="overflow-x:auto;">
        <table id="cardModalTable" style="width:100%; border-collapse:collapse; font-size:14px;">
          <thead id="cardModalThead" style="background:#0ea5e9;"></thead>
          <tbody id="cardModalTbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  const ctx1 = document.getElementById('servicesChart').getContext('2d');
  const serviceBarColors = [
    'rgba(14, 165, 233, 0.8)',   // blue      - 1st bar
    'rgba(34, 197, 94, 0.8)',    // green     - 2nd bar
    'rgba(249, 115, 22, 0.8)',   // orange    - 3rd bar
    'rgba(168, 85, 247, 0.8)',   // purple    - 4th bar
    'rgba(239, 68, 68, 0.8)',    // red       - 5th bar
    'rgba(234, 179, 8, 0.8)',    // yellow    - 6th bar
  ];
  const serviceBarBorders = [
    'rgba(2, 132, 199, 1)',
    'rgba(22, 163, 74, 1)',
    'rgba(234, 88, 12, 1)',
    'rgba(147, 51, 234, 1)',
    'rgba(220, 38, 38, 1)',
    'rgba(202, 138, 4, 1)',
  ];
  const servicesData = <?php echo $servicesCounts; ?>;
  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: <?php echo $servicesLabels; ?>,
      datasets: [{
        label: 'Usage Count',
        data: servicesData,
        backgroundColor: servicesData.map((_, i) => serviceBarColors[i % serviceBarColors.length]),
        borderColor: servicesData.map((_, i) => serviceBarBorders[i % serviceBarBorders.length]),
        borderWidth: 2,
        borderRadius: 4,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: true, position: 'top' },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: 'white',
          bodyColor: 'white',
        }
      },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0, 0, 0, 0.1)' } },
        x: { grid: { color: 'rgba(0, 0, 0, 0.1)' } }
      }
    }
  });

  const ctx2 = document.getElementById('revenueChart').getContext('2d');
  new Chart(ctx2, {
    type: 'pie',
    data: {
      labels: <?php echo $revenueLabels; ?>,
      datasets: [{
        data: <?php echo $revenueData; ?>,
        backgroundColor: ['rgba(14, 165, 233, 0.7)', 'rgba(255, 99, 132, 0.7)'],
        borderColor: ['rgba(2, 132, 199, 1)', 'rgba(255, 99, 132, 1)'],
        borderWidth: 2,
        hoverOffset: 10,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: true, position: 'bottom' },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: 'white',
          bodyColor: 'white',
          callbacks: {
            label: function(context) {
              return '₱' + context.parsed.toLocaleString();
            }
          }
        }
      }
    }
  });

  
  // ===== CARD MODAL =====
  const cardModalData = {
    'total-patients':      { title: 'Total Patients',         url: 'dashboard_data.php?type=total_patients' },
    'total-ongoing':       { title: 'Ongoing Patients',       url: 'dashboard_data.php?type=ongoing' },
    'waiting-appointments':{ title: 'Waiting Appointments',   url: 'dashboard_data.php?type=waiting' },
    'total-dentists':      { title: 'Dentist Accounts',       url: 'dashboard_data.php?type=dentists' },
    'total-staff':         { title: 'Staff Accounts',         url: 'dashboard_data.php?type=staff' },
    'low-stock':           { title: 'Low Stock Items',        url: 'dashboard_data.php?type=low_stock' },
    'appointments-today':  { title: "Today's Appointments",   url: 'dashboard_data.php?type=appointments_today' },
    'monthly-revenue':     { title: 'Monthly Revenue',        url: 'dashboard_data.php?type=monthly_revenue' },
    'total-sales':         { title: 'Total Sales',            url: 'dashboard_data.php?type=total_sales' },
  };

  let _allCardRows = [];

  function openCardModal(key) {
    const cfg = cardModalData[key];
    if (!cfg) return;
    document.getElementById('cardModalTitle').textContent = cfg.title;
    document.getElementById('cardModalSearch').value = '';
    document.getElementById('cardModalThead').innerHTML = '';
    document.getElementById('cardModalTbody').innerHTML = '<tr><td style="padding:16px;text-align:center;color:#64748b;">Loading...</td></tr>';
    document.getElementById('cardModalOverlay').style.display = 'flex';

    fetch(cfg.url)
      .then(r => r.json())
      .then(data => {
        if (!data.columns || !data.rows) {
          document.getElementById('cardModalTbody').innerHTML = '<tr><td style="padding:16px;text-align:center;color:#64748b;">No data found.</td></tr>';
          return;
        }
        // Build header
        let thead = '<tr>' + data.columns.map(c => `<th style="padding:10px 14px;color:#fff;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">${c}</th>`).join('') + '</tr>';
        document.getElementById('cardModalThead').innerHTML = thead;

        _allCardRows = data.rows;
        renderCardRows(_allCardRows);
      })
      .catch(() => {
        document.getElementById('cardModalTbody').innerHTML = '<tr><td style="padding:16px;text-align:center;color:#ef4444;">Failed to load data.</td></tr>';
      });
  }

  function renderCardRows(rows) {
    if (rows.length === 0) {
      document.getElementById('cardModalTbody').innerHTML = '<tr><td colspan="99" style="padding:16px;text-align:center;color:#64748b;">No records found.</td></tr>';
      return;
    }
    let html = rows.map((row, i) =>
      '<tr style="background:' + (i % 2 === 0 ? '#fff' : '#f8fafc') + ';">' +
      Object.values(row).map(v => `<td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#334155;">${v ?? ''}</td>`).join('') +
      '</tr>'
    ).join('');
    document.getElementById('cardModalTbody').innerHTML = html;
  }

  function filterCardTable() {
    const q = document.getElementById('cardModalSearch').value.toLowerCase();
    const filtered = _allCardRows.filter(row =>
      Object.values(row).some(v => String(v ?? '').toLowerCase().includes(q))
    );
    renderCardRows(filtered);
  }

  function closeCardModal() {
    document.getElementById('cardModalOverlay').style.display = 'none';
  }

  document.getElementById('cardModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeCardModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCardModal();
  });

  // Scroll reveal for dashboard
  document.addEventListener('DOMContentLoaded', function () {
    try {
      const targets = document.querySelectorAll('.card, .table-wrapper, .chart-wrapper, h2, h3');
      targets.forEach(el => el.classList.add('reveal'));

      const obs = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });

      targets.forEach(t => obs.observe(t));
    } catch (e) {
      console.error('Reveal init error:', e);
    }
  });
</script>
</body>
</html>