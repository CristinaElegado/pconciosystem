<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mariategue Ortho-DentalClinic</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
</head>

<body>

   <div class="topbar">
    <div class="topbar-left">
          <i class="fa-solid fa-bars menu-btn" id="menu-btn"></i>
        <div class="logo">Mariategue Ortho-Dental<span>Clinic</span></div>
    </div>
    
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "admin"): ?>
        <div class="topbar-right">
          <i class="fa-solid fa-rotate" id="globalRefreshBtn" title="Refresh"></i>
          <h3>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>
        </div>

    <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "staff"): ?>
        <div class="topbar-right">
          <i class="fa-solid fa-rotate" id="globalRefreshBtn" title="Refresh"></i>
          <h3>Welcome, 
            <?php echo htmlspecialchars($_SESSION['username']); ?> 
            (STAFF ID: <?php echo htmlspecialchars($_SESSION['staff_id'] ?? ''); ?>)
          </h3>
        </div>

    <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "dentist"): ?>
        <div class="topbar-right">
          <i class="fa-solid fa-rotate" id="globalRefreshBtn" title="Refresh"></i>
          <h3>Welcome Dentist, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>
        </div>

    <?php else: ?>
        <div class="topbar-right">
          <i class="fa-solid fa-rotate" id="globalRefreshBtn" title="Refresh"></i>
          <h3>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h3>
        </div>
    <?php endif; ?>
  </div>

<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <i class="fa-solid fa-xmark close-btn" id="close-sidebar"></i>
  </div>

  <!-- Sidebar Profile Section -->
  <div class="sidebar-profile-section">
      <?php 
          $displayName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'User';
          $userRole = ucfirst($_SESSION['user_type'] ?? 'User');
          $userPhoto = $_SESSION['profile_photo'] ?? '';
          $avatarUrl = $userPhoto ? "../uploads/profile_photos/" . htmlspecialchars($userPhoto) : "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=0ea5e9&color=fff";
      ?>
      <div class="profile-wrapper" onclick="toggleSidebarProfile()">
          <img src="<?= $avatarUrl ?>" alt="Profile" class="user-icon">
          <div class="dropdown-arrow-badge">
             <i class="fa-solid fa-chevron-down"></i>
          </div>
      </div>
      <div class="profile-name" onclick="toggleSidebarProfile()"><?= htmlspecialchars($displayName) ?></div>
      <div id="sidebarProfileDropdown" class="sidebar-profile-dropdown">
          <div class="user-info">
              <span class="user-role"><?= htmlspecialchars($userRole) ?></span>
          </div>
          <a href="../account_setting/index.php" class="dropdown-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
          <a href="../main_page/logout.php" class="dropdown-item logout-item" id="sidebarDropdownLogout" onclick="event.preventDefault(); showConfirmDialog('Are you sure you want to logout?', function(){ window.location.href='../main_page/logout.php'; }, { title: 'Logout', icon: 'logout', okText: 'Logout' });"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
  </div>

  <ul>
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "admin"): ?>

      <!-- ✅ ADMIN SIDEBAR -->
      <li><a href="../dashboard/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Dashboard</a></li>
      <li><a href="../online_appointment/online_appointment.php" class="<?= $current_page == 'online_appointment.php' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> Online Appointments</a></li>
      <li><a href="../online_appointment/hmo_providers.php" class="<?= $current_page == 'hmo_providers.php' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> HMO Management</a></li>
      <li><a href="../walk_in/walk_in.php" class="<?= $current_page == 'walk_in.php' ? 'active' : '' ?>"><i class="fa-solid fa-door-open"></i> Walk In</a></li>
      <li><a href="../patient_list/patient_list.php" class="<?= $current_page == 'patient_list.php' ? 'active' : '' ?>"><i class="fa-solid fa-hospital-user"></i> Patient Queue</a></li>
      <li><a href="../transaction/transaction.php" class="<?= $current_page == 'transaction.php' ? 'active' : '' ?>"><i class="fa-solid fa-money-bill-wave"></i> Transaction</a></li>
      <li><a href="../inventory/inventory.php" class="<?= $current_page == 'inventory.php' ? 'active' : '' ?>"><i class="fa-solid fa-box"></i> Inventory</a></li>
      <li class="maintenance-group">
        <a href="../service/service.php" class="<?= $current_page == 'service.php' ? 'active' : '' ?>"><i class="fa-solid fa-stethoscope"></i> Services</a>
        <a href="../dentist/dentist_account.php" class="<?= $current_page == 'dentist_account.php' ? 'active' : '' ?>"><i class="fa-solid fa-tooth"></i> Dentist Accounts</a>
        <a href="../patient_account/patient_account.php" class="<?= $current_page == 'patient_account.php' ? 'active' : '' ?>"><i class="fa-solid fa-id-card"></i> Patient Accounts</a>
        <a href="../staff_account/staff_account.php" class="<?= $current_page == 'staff_account.php' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Staff Accounts</a>
      </li>
      <!-- Settings Dropdown -->
      <li class="has-submenu <?= in_array($current_page, ['audit_trail.php', 'admin_clear_data.php', 'testimonials_approval.php']) ? 'submenu-open' : '' ?>">
        <a href="#" class="submenu-toggle <?= in_array($current_page, ['audit_trail.php', 'admin_clear_data.php', 'testimonials_approval.php']) ? 'active' : '' ?>" onclick="toggleSubmenu(this); return false;">
          <i class="fa-solid fa-gear"></i> Settings
          <i class="fa-solid fa-chevron-down submenu-arrow"></i>
        </a>
        <ul class="submenu <?= in_array($current_page, ['audit_trail.php', 'admin_clear_data.php', 'testimonials_approval.php']) ? 'open' : '' ?>">
          <li><a href="../audit_trail/audit_trail.php" class="<?= $current_page == 'audit_trail.php' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Audit Trail</a></li>
          <li><a href="../admin_clear_data.php" class="<?= $current_page == 'admin_clear_data.php' ? 'active' : '' ?>"><i class="fa-solid fa-trash-can"></i> Truncate Data</a></li>
          <li><a href="../testimonials_approval.php" class="<?= $current_page == 'testimonials_approval.php' ? 'active' : '' ?>"><i class="fa-solid fa-star"></i> Testimonial Approvals</a></li>
        </ul>
      </li>
      <li><a href="../main_page/logout.php" class="logout-link" onclick="event.preventDefault(); showConfirmDialog('Are you sure you want to logout?', function(){ window.location.href='../main_page/logout.php'; }, { title: 'Logout', icon: 'logout', okText: 'Logout' });"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>

    <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "staff"): ?>

      <!-- ✅ STAFF SIDEBAR -->
      <li><a href="../online_appointment/online_appointment.php" class="<?= $current_page == 'online_appointment.php' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> Online Appointments</a></li>
      <li><a href="../online_appointment/hmo_providers.php" class="<?= $current_page == 'hmo_providers.php' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> HMO Management</a></li>
      <li><a href="../walk_in/walk_in.php" class="<?= $current_page == 'walk_in.php' ? 'active' : '' ?>"><i class="fa-solid fa-door-open"></i> Walk In</a></li>
      <li><a href="../patient_list/patient_list.php" class="<?= $current_page == 'patient_list.php' ? 'active' : '' ?>"><i class="fa-solid fa-hospital-user"></i> Patient Queue</a></li>
      <li><a href="../transaction/transaction.php" class="<?= $current_page == 'transaction.php' ? 'active' : '' ?>"><i class="fa-solid fa-money-bill-wave"></i> Transaction</a></li>
      <li><a href="../inventory/inventory.php" class="<?= $current_page == 'inventory.php' ? 'active' : '' ?>"><i class="fa-solid fa-box"></i> Inventory</a></li>
      <li class="maintenance-group">
        <a href="../service/service.php" class="<?= $current_page == 'service.php' ? 'active' : '' ?>"><i class="fa-solid fa-stethoscope"></i> Services</a>
      </li>
      <li><a href="../account_setting/index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-user-gear"></i> Account Setting</a></li>
      <li><a href="../main_page/logout.php" class="logout-link" onclick="event.preventDefault(); showConfirmDialog('Are you sure you want to logout?', function(){ window.location.href='../main_page/logout.php'; }, { title: 'Logout', icon: 'logout', okText: 'Logout' });"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>

    <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "dentist"): ?>

      <!-- ✅ DENTIST SIDEBAR -->
      <li><a href="../online_appointment/online_appointment.php" class="<?= $current_page == 'online_appointment.php' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> Online Appointments</a></li>
      <li><a href="../patient_list/patient_list.php" class="<?= $current_page == 'patient_list.php' ? 'active' : '' ?>"><i class="fa-solid fa-hospital-user"></i> Patient Queue</a></li>
      <li><a href="../main_page/dentist_dashboard.php" class="<?= $current_page == 'dentist_dashboard.php' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> Schedule</a></li>
      <li><a href="../account_setting/index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-user-gear"></i> Account Setting</a></li>
      <li><a href="../main_page/logout.php" class="logout-link" onclick="event.preventDefault(); showConfirmDialog('Are you sure you want to logout?', function(){ window.location.href='../main_page/logout.php'; }, { title: 'Logout', icon: 'logout', okText: 'Logout' });"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>

    <?php else: ?>
      <!-- FALLBACK -->
      <li><a href="../main_page/logout.php" class="logout-link" onclick="event.preventDefault(); showConfirmDialog('Are you sure you want to logout?', function(){ window.location.href='../main_page/logout.php'; }, { title: 'Logout', icon: 'logout', okText: 'Logout' });"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    <?php endif; ?>
  </ul>
</div>

<div class="overlay" id="overlay"></div>

<style>
  .topbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  #globalRefreshBtn {
    font-size: 1.1rem;
    color: #0ea5e9;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    transition: background 0.2s;
    line-height: 1;
    animation: spinRefresh 1.5s linear infinite;
  }
  #globalRefreshBtn:hover {
    background: #e0f2fe;
  }
  @keyframes spinRefresh {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
  }
</style>

<script>
  function toggleSubmenu(el) {
    const li = el.closest('.has-submenu');
    const submenu = li.querySelector('.submenu');
    const isOpen = li.classList.contains('submenu-open');
    // Close all other submenus
    document.querySelectorAll('.has-submenu.submenu-open').forEach(item => {
      item.classList.remove('submenu-open');
      item.querySelector('.submenu').classList.remove('open');
    });
    if (!isOpen) {
      li.classList.add('submenu-open');
      submenu.classList.add('open');
    }
  }

  const menuBtn = document.getElementById("menu-btn");
  const closeBtn = document.getElementById("close-sidebar");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("overlay");
  
  const toggleSidebar = () => {
    sidebar.classList.toggle("active");
    overlay.classList.toggle("active");
    menuBtn.classList.toggle("active");
  };

  const toggleSidebarProfile = () => {
    const dropdown = document.getElementById("sidebarProfileDropdown");
    dropdown.classList.toggle("show");
  };

  menuBtn?.addEventListener("click", toggleSidebar);
  closeBtn?.addEventListener("click", toggleSidebar);
  overlay?.addEventListener("click", toggleSidebar);

  // ===== GLOBAL AUTO-REFRESH (No blink) =====
  const refreshBtn = document.getElementById('globalRefreshBtn');

  // Always spin the icon
  if (refreshBtn) {
    refreshBtn.classList.add('spinning');
  }

  function doSilentRefresh() {
    // Never touch DOM if any expandable row is currently open anywhere on the page
    const hasOpenRow = document.querySelector('tr.expandable.row-open') ||
                       document.querySelector('tr.expandable[style*="table-row"]');
    if (hasOpenRow) return;

    fetch(window.location.href, { cache: 'no-store' })
      .then(res => res.text())
      .then(html => {
        // Double-check after fetch completes
        const stillOpen = document.querySelector('tr.expandable.row-open') ||
                          document.querySelector('tr.expandable[style*="table-row"]');
        if (stillOpen) return;

        const parser = new DOMParser();
        const newDoc = parser.parseFromString(html, 'text/html');

        const newMain = newDoc.querySelector('.main-content');
        const curMain = document.querySelector('.main-content');

        if (!newMain || !curMain) return;

        // Update top-level table bodies only
        const newTables = newMain.querySelectorAll('.table-wrapper > table');
        const curTables = curMain.querySelectorAll('.table-wrapper > table');

        newTables.forEach((newTable, i) => {
          if (!curTables[i]) return;
          const newTbody = newTable.querySelector(':scope > tbody');
          const curTbody = curTables[i].querySelector(':scope > tbody');
          if (!newTbody || !curTbody) return;
          if (newTbody.innerHTML === curTbody.innerHTML) return;
          curTbody.innerHTML = newTbody.innerHTML;
        });

        // Update card numbers only
        const newCards = newMain.querySelectorAll('.card p');
        const curCards = curMain.querySelectorAll('.card p');
        newCards.forEach((el, i) => {
          if (curCards[i] && curCards[i].textContent !== el.textContent) {
            curCards[i].textContent = el.textContent;
          }
        });
      })
      .catch(() => {});
  }

  // Auto-refresh every 5 seconds silently
  document.addEventListener('DOMContentLoaded', function() {
    setInterval(doSilentRefresh, 5000);
  });

  // Manual click also triggers refresh
  if (refreshBtn) {
    refreshBtn.addEventListener('click', doSilentRefresh);
  }
</script>

<!-- ============================================================
     SESSION POLLING — Auto-detect kung na-deleted ang account
     ============================================================ -->
<style>
  #sessionModal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 99999;
    justify-content: center;
    align-items: center;
  }
  #sessionModal.show {
    display: flex;
  }
  #sessionModalBox {
    background: #fff;
    border-radius: 14px;
    padding: 36px 32px;
    max-width: 420px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    animation: popIn 0.25s ease;
  }
  @keyframes popIn {
    from { transform: scale(0.85); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
  }
  #sessionModalBox .modal-icon {
    font-size: 3rem;
    margin-bottom: 12px;
  }
  #sessionModalBox h3 {
    margin: 0 0 10px;
    color: #1e293b;
    font-size: 1.2rem;
  }
  #sessionModalBox p {
    color: #64748b;
    font-size: 0.92rem;
    margin-bottom: 24px;
    line-height: 1.5;
  }
  #sessionModalBtn {
    background: #0099ff;
    color: #fff;
    border: none;
    padding: 11px 32px;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
  }
  #sessionModalBtn:hover { background: #0072cc; }
</style>

<!-- Modal Dialog -->
<div id="sessionModal">
  <div id="sessionModalBox">
    <div class="modal-icon">⚠️</div>
    <h3 id="sessionModalTitle">Session Expired</h3>
    <p id="sessionModalMessage">Your account no longer exists. Please log in again.</p>
    <button id="sessionModalBtn" onclick="sessionModalRedirect()">OK</button>
  </div>
</div>

<script>
  let _sessionModalRedirectUrl = '/Mariategue-DentalClinic/login_main/login.php';
  let _sessionPollingActive = true;

  function sessionModalRedirect() {
    _sessionPollingActive = false;
    window.location.href = _sessionModalRedirectUrl;
  }

  function showSessionModal(title, message, redirectUrl) {
    _sessionPollingActive = false; // Stop further polls once modal is shown
    _sessionModalRedirectUrl = redirectUrl;
    document.getElementById('sessionModalTitle').textContent = title;
    document.getElementById('sessionModalMessage').textContent = message;
    document.getElementById('sessionModal').classList.add('show');
  }

  async function pollSession() {
    if (!_sessionPollingActive) return;
    try {
      const res  = await fetch('/Mariategue-DentalClinic/miscellaneous/session_check.php', { cache: 'no-store' });
      const data = await res.json();

      if (!data.valid) {
        _sessionPollingActive = false;

        if (data.reason === 'no_admin') {
          // Admin deleted — redirect to register page immediately
          window.location.href = data.register_url || '/Mariategue-DentalClinic/setup.php';

        } else if (data.reason === 'deleted') {
          // Current user account deleted
          const modal = document.getElementById('sessionModal');
          if (modal) {
            document.getElementById('sessionModalTitle').textContent = 'Account Deleted';
            document.getElementById('sessionModalMessage').textContent = 'Your account has been removed from the system. You will be redirected to the login page.';
            _sessionModalRedirectUrl = '/Mariategue-DentalClinic/login_main/login.php';
            modal.classList.add('show');
          } else {
            window.location.href = '/Mariategue-DentalClinic/login_main/login.php';
          }

        } else if (data.reason === 'no_session') {
          window.location.href = '/Mariategue-DentalClinic/login_main/login.php';
        }
      }
    } catch (e) {
      // Network error — ignore
    }
  }

  // Poll every 5 seconds
  setInterval(pollSession, 5000);
</script>

</body>
</html>

<?php include __DIR__ . '/confirm_dialog.php'; ?>
