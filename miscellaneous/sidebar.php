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
          <!-- Expiry Notification Bell (Admin) -->
          <div class="notif-bell-wrapper" id="notifBellWrapper">
            <i class="fa-solid fa-bell notif-bell-icon" id="notifBellIcon" onclick="toggleNotifDropdown()" title="Inventory Expiry Alerts"></i>
            <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
            <div class="notif-dropdown" id="notifDropdown">
              <div class="notif-dropdown-header">
                <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i>
                Inventory Expiry Alerts
              </div>
              <div id="notifDropdownBody" class="notif-dropdown-body">
                <div class="notif-loading">Loading...</div>
              </div>
              <div class="notif-dropdown-footer">
                <a href="../inventory/inventory.php">View Inventory</a>
              </div>
            </div>
          </div>
          <i class="fa-solid fa-rotate" id="globalRefreshBtn" title="Refresh"></i>
          <h3>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>
        </div>

    <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === "staff"): ?>
        <div class="topbar-right">
          <!-- Expiry Notification Bell (Staff) -->
          <div class="notif-bell-wrapper" id="notifBellWrapper">
            <i class="fa-solid fa-bell notif-bell-icon" id="notifBellIcon" onclick="toggleNotifDropdown()" title="Inventory Expiry Alerts"></i>
            <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
            <div class="notif-dropdown" id="notifDropdown">
              <div class="notif-dropdown-header">
                <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i>
                Inventory Expiry Alerts
              </div>
              <div id="notifDropdownBody" class="notif-dropdown-body">
                <div class="notif-loading">Loading...</div>
              </div>
              <div class="notif-dropdown-footer">
                <a href="../inventory/inventory.php">View Inventory</a>
              </div>
            </div>
          </div>
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
      <li class="accordion-item <?= in_array($current_page, ['settings.php','audit_trail.php','testimonials_approval.php']) ? 'accordion-open' : '' ?>">
        <div class="accordion-toggle" onclick="toggleAccordion(this)">
          <span><i class="fa-solid fa-gear"></i> Settings</span>
          <i class="fa-solid fa-chevron-up accordion-arrow"></i>
        </div>
        <ul class="accordion-sub">
          <li><a href="../audit_trail/audit_trail.php" class="<?= $current_page == 'audit_trail.php' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Audit Trail</a></li>
          <li><a href="../admin/settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>"><i class="fa-solid fa-database"></i> Truncate / Restore</a></li>
          <li><a href="../testimonials_approval.php" class="<?= $current_page == 'testimonials_approval.php' ? 'active' : '' ?>"><i class="fa-solid fa-star"></i> Testimonial Approval</a></li>
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
  /* ====== SETTINGS ACCORDION ====== */
  .accordion-item { list-style: none; }
  .accordion-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 20px;
    cursor: pointer;
    color: #334155;
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: 8px;
    transition: background 0.18s;
    user-select: none;
  }
  .accordion-toggle:hover { background: rgba(14,165,233,0.08); color: #0ea5e9; }
  .accordion-toggle span { display: flex; align-items: center; gap: 10px; }
  .accordion-arrow {
    font-size: 0.75rem;
    transition: transform 0.25s ease;
    transform: rotate(180deg); /* points up = closed */
  }
  .accordion-item.accordion-open .accordion-arrow {
    transform: rotate(0deg); /* points up = open */
  }
  .accordion-sub {
    list-style: none;
    padding: 0 0 0 18px;
    margin: 0;
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease;
  }
  .accordion-item.accordion-open .accordion-sub {
    max-height: 280px;
  }
  .accordion-sub li a {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 14px;
    font-size: 0.88rem;
    color: #475569;
    border-radius: 7px;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
  }
  .accordion-sub li a:hover, .accordion-sub li a.active {
    background: rgba(14,165,233,0.12);
    color: #0ea5e9;
    font-weight: 600;
  }

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

  /* ====== NOTIFICATION BELL ====== */
  .notif-bell-wrapper {
    position: relative;
    display: flex;
    align-items: center;
  }
  .notif-bell-icon {
    font-size: 1.2rem;
    color: #0ea5e9;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    transition: background 0.2s, color 0.2s;
    line-height: 1;
  }
  .notif-bell-icon:hover {
    background: #e0f2fe;
  }
  .notif-bell-icon.notif-has-alerts {
    color: #f59e0b;
    animation: bellRing 1.2s ease infinite;
  }
  @keyframes bellRing {
    0%,100% { transform: rotate(0deg); }
    15%      { transform: rotate(18deg); }
    30%      { transform: rotate(-18deg); }
    45%      { transform: rotate(12deg); }
    60%      { transform: rotate(-12deg); }
    75%      { transform: rotate(6deg); }
    90%      { transform: rotate(-6deg); }
  }
  .notif-badge {
    position: absolute;
    top: 0px;
    right: 0px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    min-width: 17px;
    height: 17px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0 4px;
    pointer-events: none;
    box-shadow: 0 1px 4px rgba(0,0,0,0.18);
    z-index: 10;
  }
  /* Dropdown panel */
  .notif-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 320px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    z-index: 99999;
    overflow: hidden;
    animation: notifFadeIn 0.18s ease;
  }
  .notif-dropdown.open {
    display: block;
  }
  @keyframes notifFadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .notif-dropdown-header {
    background: #0ea5e9;
    color: #fff;
    font-size: 0.88rem;
    font-weight: 600;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: Poppins, sans-serif;
  }
  .notif-dropdown-body {
    max-height: 320px;
    overflow-y: auto;
    padding: 6px 0;
  }
  .notif-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 16px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
    cursor: default;
  }
  .notif-item:last-child { border-bottom: none; }
  .notif-item:hover { background: #f8fafc; }
  .notif-item-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    margin-top: 2px;
  }
  .notif-item-icon.expired   { background: #fee2e2; color: #ef4444; }
  .notif-item-icon.expiring  { background: #fef3c7; color: #f59e0b; }
  .notif-item-text { flex: 1; }
  .notif-item-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1e293b;
    font-family: Poppins, sans-serif;
    line-height: 1.3;
  }
  .notif-item-detail {
    font-size: 0.78rem;
    color: #64748b;
    margin-top: 2px;
    font-family: Poppins, sans-serif;
  }
  .notif-item-status {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    margin-top: 4px;
    display: inline-block;
  }
  .notif-item-status.expired  { background: #fee2e2; color: #ef4444; }
  .notif-item-status.expiring { background: #fef3c7; color: #b45309; }
  .notif-empty {
    text-align: center;
    padding: 24px 16px;
    color: #94a3b8;
    font-size: 0.85rem;
    font-family: Poppins, sans-serif;
  }
  .notif-empty i { font-size: 1.8rem; display: block; margin-bottom: 8px; color: #cbd5e1; }
  .notif-loading {
    text-align: center;
    padding: 20px;
    color: #94a3b8;
    font-size: 0.85rem;
    font-family: Poppins, sans-serif;
  }
  .notif-dropdown-footer {
    padding: 10px 16px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    text-align: center;
  }
  .notif-dropdown-footer a {
    color: #0ea5e9;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    font-family: Poppins, sans-serif;
  }
  .notif-dropdown-footer a:hover { text-decoration: underline; }
</style>

<script>
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

  function toggleAccordion(el) {
    const item = el.closest('.accordion-item');
    item.classList.toggle('accordion-open');
  }

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

  // ====== EXPIRY NOTIFICATION BELL ======
  function fetchExpiryNotifications() {
    const badge   = document.getElementById('notifBadge');
    const bell    = document.getElementById('notifBellIcon');
    const body    = document.getElementById('notifDropdownBody');
    if (!badge || !bell || !body) return;

    fetch('/miscellaneous/get_expiry_notifications.php', { cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        const count = data.count || 0;

        // Update badge
        if (count > 0) {
          badge.textContent = count > 99 ? '99+' : count;
          badge.style.display = 'flex';
          bell.classList.add('notif-has-alerts');
        } else {
          badge.style.display = 'none';
          bell.classList.remove('notif-has-alerts');
        }

        // Build dropdown items
        if (!data.items || data.items.length === 0) {
          body.innerHTML = '<div class="notif-empty"><i class="fa-solid fa-circle-check"></i>No expiry alerts. All items are good!</div>';
          return;
        }

        let html = '';
        data.items.forEach(function(item) {
          const isExpired  = item.status === 'EXPIRED';
          const iconClass  = isExpired ? 'expired'  : 'expiring';
          const icon       = isExpired ? 'fa-skull-crossbones' : 'fa-clock';
          html += `
            <div class="notif-item">
              <div class="notif-item-icon ${iconClass}">
                <i class="fa-solid ${icon}"></i>
              </div>
              <div class="notif-item-text">
                <div class="notif-item-name">${item.item_name}</div>
                <div class="notif-item-detail">Exp: ${item.expiration_date} &bull; Qty: ${item.quantity}</div>
                <span class="notif-item-status ${iconClass}">${item.status}</span>
              </div>
            </div>`;
        });
        body.innerHTML = html;
      })
      .catch(function() {
        const b = document.getElementById('notifBadge');
        if (b) b.style.display = 'none';
      });
  }

  function toggleNotifDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    if (!dropdown) return;
    const isOpen = dropdown.classList.contains('open');
    dropdown.classList.toggle('open', !isOpen);
    if (!isOpen) fetchExpiryNotifications(); // Always refresh on open
  }

  // Close dropdown when clicking outside the bell wrapper
  document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notifBellWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      const dropdown = document.getElementById('notifDropdown');
      if (dropdown) dropdown.classList.remove('open');
    }
  });

  // Initial fetch on page load + re-poll every 60 seconds
  document.addEventListener('DOMContentLoaded', function() {
    fetchExpiryNotifications();
    setInterval(fetchExpiryNotifications, 60000);
  });
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
  let _sessionModalRedirectUrl = '/login_main/login.php';
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
      const res  = await fetch('/miscellaneous/session_check.php', { cache: 'no-store' });
      const data = await res.json();

      if (!data.valid) {
        _sessionPollingActive = false;

        if (data.reason === 'no_admin') {
          // Admin deleted — redirect to register page immediately
          window.location.href = data.register_url || '/setup.php';

        } else if (data.reason === 'deleted') {
          // Current user account deleted
          const modal = document.getElementById('sessionModal');
          if (modal) {
            document.getElementById('sessionModalTitle').textContent = 'Account Deleted';
            document.getElementById('sessionModalMessage').textContent = 'Your account has been removed from the system. You will be redirected to the login page.';
            _sessionModalRedirectUrl = '/login_main/login.php';
            modal.classList.add('show');
          } else {
            window.location.href = '/login_main/login.php';
          }

        } else if (data.reason === 'kicked') {
          // Logged in from another device
          const modal = document.getElementById('sessionModal');
          if (modal) {
            document.getElementById('sessionModalTitle').textContent = 'Logged In Elsewhere';
            document.getElementById('sessionModalMessage').textContent = 'Your account was logged in from another device or browser. You have been signed out of this session.';
            _sessionModalRedirectUrl = '/login_main/login.php';
            modal.classList.add('show');
          } else {
            window.location.href = '/login_main/login.php';
          }

        } else if (data.reason === 'no_session') {
          window.location.href = '/login_main/login.php';
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
