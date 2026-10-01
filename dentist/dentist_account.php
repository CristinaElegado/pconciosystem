<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include __DIR__ . '/../miscellaneous/database.php';

// --- AJAX CHECK FOR EXISTING EMAIL ---
if (isset($_GET['check_email'])) {
    $email = trim($_GET['check_email']);
    $exclude_id = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : 0;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT email FROM dentist_accounts WHERE email = ? AND id != ?
            UNION ALL
            SELECT email FROM admin WHERE email = ?
            UNION ALL
            SELECT email FROM staff_accounts WHERE email = ?
        ) AS all_emails
    ");
    $stmt->execute([$email, $exclude_id, $email, $email]);
    $exists = $stmt->fetchColumn() > 0;

    header('Content-Type: application/json');
    echo json_encode(['exists' => $exists]);
    exit;
}

try {
    // Auto-patch the table if columns don't exist
    $pdo->exec("ALTER TABLE dentist_schedule 
        ADD COLUMN monday_start TIME NULL, ADD COLUMN monday_end TIME NULL,
        ADD COLUMN tuesday_start TIME NULL, ADD COLUMN tuesday_end TIME NULL,
        ADD COLUMN wednesday_start TIME NULL, ADD COLUMN wednesday_end TIME NULL,
        ADD COLUMN thursday_start TIME NULL, ADD COLUMN thursday_end TIME NULL,
        ADD COLUMN friday_start TIME NULL, ADD COLUMN friday_end TIME NULL,
        ADD COLUMN saturday_start TIME NULL, ADD COLUMN saturday_end TIME NULL,
        ADD COLUMN sunday_start TIME NULL, ADD COLUMN sunday_end TIME NULL
    ");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE dentist_accounts ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {}

include __DIR__ . '/../miscellaneous/auth_check.php';
include __DIR__ . '/../miscellaneous/log_audit.php';

$active_dentists = $pdo->query("
  SELECT 
    da.*, 
    TIMESTAMPDIFF(YEAR, da.birthday, CURDATE()) AS age,
    ds.monday, ds.tuesday, ds.wednesday, ds.thursday, ds.friday, ds.saturday, ds.sunday,
    ds.monday_start, ds.monday_end, ds.tuesday_start, ds.tuesday_end, ds.wednesday_start, ds.wednesday_end,
    ds.thursday_start, ds.thursday_end, ds.friday_start, ds.friday_end, ds.saturday_start, ds.saturday_end,
    ds.sunday_start, ds.sunday_end
  FROM dentist_accounts da
  LEFT JOIN dentist_schedule ds ON da.id = ds.dentist_id
  WHERE da.is_deleted = 0
  ORDER BY da.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$deleted_dentists = $pdo->query("
  SELECT 
    da.*, 
    TIMESTAMPDIFF(YEAR, da.birthday, CURDATE()) AS age,
    ds.monday, ds.tuesday, ds.wednesday, ds.thursday, ds.friday, ds.saturday, ds.sunday,
    ds.monday_start, ds.monday_end, ds.tuesday_start, ds.tuesday_end, ds.wednesday_start, ds.wednesday_end,
    ds.thursday_start, ds.thursday_end, ds.friday_start, ds.friday_end, ds.saturday_start, ds.saturday_end,
    ds.sunday_start, ds.sunday_end
  FROM dentist_accounts da
  LEFT JOIN dentist_schedule ds ON da.id = ds.dentist_id
  WHERE da.is_deleted = 1
  ORDER BY da.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['add'])) {
  $first_name = strtoupper(trim($_POST['first_name']));
  $middle_name = isset($_POST['middle_name']) && $_POST['middle_name'] !== ''
  ? strtoupper(trim($_POST['middle_name']))
  : 'NONE';
  $last_name = strtoupper(trim($_POST['last_name']));
  $birthday = $_POST['birthday'];
  $address = trim($_POST['address']);
  $phone = trim($_POST['phone']);
  $employment_type = $_POST['employment_type'];
  $email = trim($_POST['email']);
  $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $is_active = 1;

  // Prevent duplicate emails server-side backup
  $check = $pdo->prepare("
      SELECT COUNT(*) FROM (
          SELECT email FROM dentist_accounts WHERE email = ?
          UNION ALL
          SELECT email FROM admin WHERE email = ?
          UNION ALL
          SELECT email FROM staff_accounts WHERE email = ?
      ) AS all_emails
  ");
  $check->execute([$email, $email, $email]);

  if ($check->fetchColumn() > 0) {
      echo "<script>alert('Email already exists in the system!'); window.location='dentist_account.php';</script>";
      exit;
  }

  if (!preg_match("/^[A-Z\s]+$/", $first_name) || 
      (!empty($middle_name) && $middle_name !== 'NONE' && !preg_match("/^[A-Z\s]+$/", $middle_name)) || 
      !preg_match("/^[A-Z\s]+$/", $last_name)) {
      echo "<script>alert('Names allowed only letters and spaces (No special characters).'); window.location='dentist_account.php';</script>";
      exit;
  }

  if (strlen($first_name) > 100 || strlen($middle_name) > 100 || strlen($last_name) > 100) {
      echo "<script>alert('Names must be 100 characters or less.'); window.location='dentist_account.php';</script>";
      exit;
  }

  $emailParts = explode('@', $email);
  $domainPart = array_pop($emailParts);
  $localPart = implode('@', $emailParts);

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
      echo "<script>alert('Invalid email address or exceeds length limits.'); window.location='dentist_account.php';</script>";
      exit;
  }

  $age = (new DateTime($birthday))->diff(new DateTime())->y;
  if ($age < 18) {
      echo "<script>alert('Dentist must be at least 18 years old.'); window.location='dentist_account.php';</script>";
      exit;
  }

  if (!preg_match("/^09\d{9}$/", $phone)) {
      echo "<script>alert('Phone number must start with 09 and contain 11 digits.'); window.location='dentist_account.php';</script>";
      exit;
  }

  $stmt = $pdo->prepare("
    INSERT INTO dentist_accounts (first_name, middle_name, last_name, birthday, address, phone, email, password_hash, is_active, employment_type)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([$first_name, $middle_name, $last_name, $birthday, $address, $phone, $email, $password_hash, $is_active, $employment_type]);
  $dentist_id = $pdo->lastInsertId();

  $days_list = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  $schedule_flags = [];
  $schedule_times = [];
  foreach ($days_list as $day) {
      if (isset($_POST[$day . '_active']) && $_POST[$day . '_active'] == '1') {
          $start = (isset($_POST[$day . '_start']) && $_POST[$day . '_start'] !== '') ? $_POST[$day . '_start'] : null;
          $end   = (isset($_POST[$day . '_end'])   && $_POST[$day . '_end']   !== '') ? $_POST[$day . '_end']   : null;

          // PHP backend validation: 07:00–17:00
          if ($start !== null && ($start < '07:00' || $start > '17:00')) {
              echo "<script>alert('Time In for " . ucfirst($day) . " must be between 7:00 AM and 5:00 PM.'); window.history.back();</script>";
              exit;
          }
          if ($end !== null && ($end < '07:00' || $end > '17:00')) {
              echo "<script>alert('Time Out for " . ucfirst($day) . " must be between 7:00 AM and 5:00 PM.'); window.history.back();</script>";
              exit;
          }
          if ($start !== null && $end !== null && $end <= $start) {
              echo "<script>alert('Time Out for " . ucfirst($day) . " must be later than Time In.'); window.history.back();</script>";
              exit;
          }

          $schedule_flags[$day] = 1;
          $schedule_times[$day . '_start'] = $start;
          $schedule_times[$day . '_end']   = $end;
      } else {
          $schedule_flags[$day] = 0;
          $schedule_times[$day . '_start'] = null;
          $schedule_times[$day . '_end'] = null;
      }
  }

  $stmt = $pdo->prepare("
    INSERT INTO dentist_schedule 
    (dentist_id, monday, tuesday, wednesday, thursday, friday, saturday, sunday,
     monday_start, monday_end, tuesday_start, tuesday_end, wednesday_start, wednesday_end,
     thursday_start, thursday_end, friday_start, friday_end, saturday_start, saturday_end,
     sunday_start, sunday_end)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute(array_merge([$dentist_id], array_values($schedule_flags), array_values($schedule_times)));

  log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Added Dentist Account', "Dentist: {$first_name} {$last_name} | Email: {$email}");
  header("Location: dentist_account.php");
  exit;
}

// EDIT
if (isset($_POST['edit'])) {
  $id = $_POST['id'];
  $first_name = strtoupper(trim($_POST['first_name']));
  $middle_name = isset($_POST['middle_name']) && $_POST['middle_name'] !== ''
  ? strtoupper(trim($_POST['middle_name']))
  : 'NONE';
  $last_name = strtoupper(trim($_POST['last_name']));
  $birthday = $_POST['birthday'];
  $address = trim($_POST['address']);
  $phone = trim($_POST['phone']);
  $employment_type = $_POST['employment_type'];
  $email = trim($_POST['email']);
  $is_active = ($_POST['is_active'] == "1") ? 1 : 0;

  $check = $pdo->prepare("
      SELECT COUNT(*) FROM (
          SELECT email FROM dentist_accounts WHERE email = ? AND id != ?
          UNION ALL
          SELECT email FROM admin WHERE email = ?
          UNION ALL
          SELECT email FROM staff_accounts WHERE email = ?
      ) AS all_emails
  ");
  $check->execute([$email, $id, $email, $email]);

  if ($check->fetchColumn() > 0) {
      echo "<script>alert('Email already exists in the system!'); window.location='dentist_account.php';</script>";
      exit;
  }

  if (!preg_match("/^[A-Z\s]+$/", $first_name) || 
      (!empty($middle_name) && $middle_name !== 'NONE' && !preg_match("/^[A-Z\s]+$/", $middle_name)) || 
      !preg_match("/^[A-Z\s]+$/", $last_name)) {
      echo "<script>alert('Names allowed only letters and spaces (No special characters).'); window.location='dentist_account.php';</script>";
      exit;
  }

  if (strlen($first_name) > 100 || strlen($middle_name) > 100 || strlen($last_name) > 100) {
      echo "<script>alert('Names must be 100 characters or less.'); window.location='dentist_account.php';</script>";
      exit;
  }

  $emailParts = explode('@', $email);
  $domainPart = array_pop($emailParts);
  $localPart = implode('@', $emailParts);

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
      echo "<script>alert('Invalid email address or exceeds length limits.'); window.location='dentist_account.php';</script>";
      exit;
  }

  $age = (new DateTime($birthday))->diff(new DateTime())->y;
  if ($age < 18) {
      echo "<script>alert('Dentist must be at least 18 years old.'); window.location='dentist_account.php';</script>";
      exit;
  }

  if (!preg_match("/^09\d{9}$/", $phone)) {
      echo "<script>alert('Phone number must start with 09 and contain 11 digits.'); window.location='dentist_account.php';</script>";
      exit;
  }

  $stmt = $pdo->prepare("
    UPDATE dentist_accounts 
    SET first_name=?, middle_name=?, last_name=?, birthday=?, address=?, phone=?, email=?, is_active=?, employment_type=?
    WHERE id=?
  ");
  $stmt->execute([$first_name, $middle_name, $last_name, $birthday, $address, $phone, $email, $is_active, $employment_type, $id]);

  log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Updated Dentist Account', "Dentist: {$first_name} {$last_name} | Email: {$email}");
  header("Location: dentist_account.php");
  exit;
}

// UPDATE AVAILABILITY
if (isset($_POST['update_availability'])) {
    $dentist_id = $_POST['dentist_id'];
    $days_list = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  
    $schedule_flags = [];
    $schedule_times = [];
    foreach ($days_list as $day) {
        if (isset($_POST[$day . '_active']) && $_POST[$day . '_active'] == '1') {
            $start = (isset($_POST[$day . '_start']) && $_POST[$day . '_start'] !== '') ? $_POST[$day . '_start'] : null;
            $end   = (isset($_POST[$day . '_end'])   && $_POST[$day . '_end']   !== '') ? $_POST[$day . '_end']   : null;

            // PHP backend validation: 07:00–17:00
            if ($start !== null && ($start < '07:00' || $start > '17:00')) {
                echo "<script>alert('Time In for " . ucfirst($day) . " must be between 7:00 AM and 5:00 PM.'); window.history.back();</script>";
                exit;
            }
            if ($end !== null && ($end < '07:00' || $end > '17:00')) {
                echo "<script>alert('Time Out for " . ucfirst($day) . " must be between 7:00 AM and 5:00 PM.'); window.history.back();</script>";
                exit;
            }
            if ($start !== null && $end !== null && $end <= $start) {
                echo "<script>alert('Time Out for " . ucfirst($day) . " must be later than Time In.'); window.history.back();</script>";
                exit;
            }

            $schedule_flags[$day] = 1;
            $schedule_times[$day . '_start'] = $start;
            $schedule_times[$day . '_end']   = $end;
        } else {
            $schedule_flags[$day] = 0;
            $schedule_times[$day . '_start'] = null;
            $schedule_times[$day . '_end'] = null;
        }
    }

    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM dentist_schedule WHERE dentist_id = ?");
    $check_stmt->execute([$dentist_id]);
    
    if ($check_stmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare("
            UPDATE dentist_schedule SET 
            monday=?, tuesday=?, wednesday=?, thursday=?, friday=?, saturday=?, sunday=?,
            monday_start=?, monday_end=?, tuesday_start=?, tuesday_end=?, wednesday_start=?, wednesday_end=?,
            thursday_start=?, thursday_end=?, friday_start=?, friday_end=?, saturday_start=?, saturday_end=?,
            sunday_start=?, sunday_end=?
            WHERE dentist_id=?
        ");
        $stmt->execute(array_merge(array_values($schedule_flags), array_values($schedule_times), [$dentist_id]));
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO dentist_schedule 
            (dentist_id, monday, tuesday, wednesday, thursday, friday, saturday, sunday,
            monday_start, monday_end, tuesday_start, tuesday_end, wednesday_start, wednesday_end,
            thursday_start, thursday_end, friday_start, friday_end, saturday_start, saturday_end,
            sunday_start, sunday_end)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array_merge([$dentist_id], array_values($schedule_flags), array_values($schedule_times)));
    }

    header("Location: dentist_account.php");
    exit;
}

// DELETE
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $dName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM dentist_accounts WHERE id=?");
    $dName->execute([$id]);
    $dentistName = $dName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("UPDATE dentist_accounts SET is_deleted=1 WHERE id=?")->execute([$id]);
    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Deactivated Dentist Account', "Dentist: {$dentistName}");
    header("Location: dentist_account.php");
    exit;
}

// RESTORE
if (isset($_POST['restore'])) {
    $id = $_POST['id'];
    $dName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM dentist_accounts WHERE id=?");
    $dName->execute([$id]);
    $dentistName = $dName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("UPDATE dentist_accounts SET is_deleted=0 WHERE id=?")->execute([$id]);
    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Restored Dentist Account', "Dentist: {$dentistName}");
    header("Location: dentist_account.php");
    exit;
}

// HARD DELETE
if (isset($_POST['hard_delete'])) {
    $id = $_POST['id'];
    $dName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM dentist_accounts WHERE id=?");
    $dName->execute([$id]);
    $dentistName = $dName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("DELETE FROM dentist_schedule WHERE dentist_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM dentist_time_schedules WHERE dentist_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM dentist_accounts WHERE id=?")->execute([$id]);
    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Permanently Deleted Dentist Account', "Dentist: {$dentistName}");
    header("Location: dentist_account.php");
    exit;
}

include __DIR__ . '/../miscellaneous/sidebar.php';
$maxDate = date('Y-m-d', strtotime('-18 years'));
?>

  <link rel="stylesheet" href="dentist_account_design.css?v=<?= time(); ?>">
  <style>
    body { background-image: none !important; }
    .availability-modal h4,
    .availability-modal .availability-label,
    .availability-modal .slot-checkbox-item,
    .availability-modal .info-text { color: black !important; }
    .btn-delete, .btn-cancel { background-color: #0ea5e9 !important; color: white !important; }
    .btn-delete:hover, .btn-cancel:hover { background-color: #0284c7 !important; }
    .day-schedule-row { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    .time-input { padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; width: 100%; max-width: 120px; }

    /* Custom Notification Modal for Email Exist */
    #emailExistModal {
        display: none;
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        justify-content: center;
        align-items: center;
    }
    .custom-alert-box {
        background: #fff;
        padding: 25px;
        border-radius: 8px;
        text-align: center;
        max-width: 350px;
        width: 90%;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    .custom-alert-box h3 { color: #dc3545; margin-bottom: 10px; }
    .custom-alert-box p { color: #333; margin-bottom: 20px; font-size: 14px; }
    .custom-alert-box button {
        background: #0ea5e9;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
    }

    @media (max-width: 900px) {
      .main-content { margin-left: 0 !important; margin-top: 70px !important; padding: 15px !important; width: 100% !important; }
      .topbar { left: 0 !important; width: 100% !important; display: flex !important; justify-content: space-between !important; align-items: center !important; padding: 0 10px !important; gap: 8px !important; }
      .topbar-left { display: flex !important; align-items: center !important; gap: 8px !important; overflow: hidden !important; flex: 1 !important; min-width: 0 !important; }
      .topbar .logo, .topbar h3, .topbar .clinic-title { display: inline-block !important; font-size: 12px !important; font-weight: 600 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; margin: 0 !important; max-width: 130px !important; }
      .topbar div:last-child, .topbar span { font-size: 11px !important; white-space: nowrap !important; flex-shrink: 0 !important; }
      .sidebar { position: fixed !important; top: 0 !important; left: 0 !important; width: 260px !important; height: 100vh !important; z-index: 999999 !important; transform: translateX(-100%) !important; transition: transform 0.3s ease-in-out !important; }
      .sidebar.active { transform: translateX(0) !important; }
      .table-wrapper { overflow-x: auto !important; }
    }
  </style>

<!-- Email Already Exist Custom Modal -->
<div id="emailExistModal">
    <div class="custom-alert-box">
        <h3>Notice</h3>
        <p>Email already exists in the system!</p>
        <button onclick="closeEmailModal()">OK</button>
    </div>
</div>

<div class="main-content">
  <h2>Dentist Accounts</h2>
  <div style="display: flex; gap: 10px; margin-bottom: 15px;">
      <button class="btn-add" onclick="showTab('active_accounts')">Active Accounts</button>
      <button class="btn-add" onclick="showTab('deleted_accounts')" style="background-color: #64748b;">Deleted Accounts</button>
      <button class="btn-add" style="margin-left: auto;" onclick="document.getElementById('addModal').style.display='flex'">Add Dentist</button>
  </div>

<div id="active_accounts" class="tab-content" style="display:block;">
<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>First Name</th>
        <th>Middle Name</th>
        <th>Last Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Birthday</th>
        <th>Age</th>
        <th>Employment</th>
        <th>Status</th>
        <th>Schedule</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($active_dentists)): ?>
      <tr><td colspan="12" style="text-align:center;">No active dentist account found.</td></tr>
    <?php endif; ?>
      <?php foreach ($active_dentists as $i => $row): ?>
        <tr>
          <td data-label="#"> <?= $i + 1 ?> </td>
          <td data-label="First Name"><?= htmlspecialchars($row['first_name']) ?></td>
          <td data-label="Middle Name"><?= htmlspecialchars($row['middle_name']) ?></td>
          <td data-label="Last Name"><?= htmlspecialchars($row['last_name']) ?></td>
          <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
          <td data-label="Phone"><?= htmlspecialchars($row['phone']) ?></td>
          <td data-label="Birthday"><?= htmlspecialchars($row['birthday']) ?></td>
          <td data-label="Age"><?= htmlspecialchars($row['age']) ?></td>
          <td data-label="Employment"><?= htmlspecialchars($row['employment_type'] ?? 'Full-Time') ?></td>
          <td data-label="Status"><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td data-label="Schedule">
            <?php
              $days = [];
              foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                if ($row[$day]) $days[] = ucfirst($day);
              echo $days ? implode(', ', $days) : 'None';
            ?>
          </td>
          <td data-label="Actions">
            <div style="display:flex; flex-direction:column; gap:6px; width:140px;">
              <button class="btn-edit" style="width:100% !important;" onclick='openEdit(<?= json_encode($row) ?>)'>Edit</button>
              <button class="btn-manage" style="width:100% !important;" onclick='openAvailability(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>Manage Availability</button>
              <form method="post" id="dentistDeleteForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="delete" value="1">
                  <button type="button" class="btn-delete" style="width:100% !important;" onclick="showConfirmDialog('Move this dentist to deleted accounts?', function(){ document.getElementById('dentistDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Delete Dentist', icon: 'delete', danger: true, okText: 'Delete' })">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>

<div id="deleted_accounts" class="tab-content" style="display:none;">
<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>First Name</th>
        <th>Middle Name</th>
        <th>Last Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Birthday</th>
        <th>Age</th>
        <th>Employment</th>
        <th>Status</th>
        <th>Schedule</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($deleted_dentists)): ?>
      <tr><td colspan="12" style="text-align:center;">No deleted dentist account found.</td></tr>
    <?php endif; ?>
      <?php foreach ($deleted_dentists as $i => $row): ?>
        <tr>
          <td data-label="#"> <?= $i + 1 ?> </td>
          <td data-label="First Name"><?= htmlspecialchars($row['first_name']) ?></td>
          <td data-label="Middle Name"><?= htmlspecialchars($row['middle_name']) ?></td>
          <td data-label="Last Name"><?= htmlspecialchars($row['last_name']) ?></td>
          <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
          <td data-label="Phone"><?= htmlspecialchars($row['phone']) ?></td>
          <td data-label="Birthday"><?= htmlspecialchars($row['birthday']) ?></td>
          <td data-label="Age"><?= htmlspecialchars($row['age']) ?></td>
          <td data-label="Employment"><?= htmlspecialchars($row['employment_type'] ?? 'Full-Time') ?></td>
          <td data-label="Status"><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td data-label="Schedule">
            <?php
              $days = [];
              foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                if ($row[$day]) $days[] = ucfirst($day);
              echo $days ? implode(', ', $days) : 'None';
            ?>
          </td>
          <td data-label="Actions">
              <form method="post" style="display:inline;" id="dentistRestoreForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="restore" value="1">
                  <button type="button" class="btn-manage" onclick="showConfirmDialog('Restore this dentist?', function(){ document.getElementById('dentistRestoreForm_<?= $row['id'] ?>').submit(); }, { title: 'Restore Dentist', icon: 'restore', okText: 'Restore' })">Restore</button>
              </form>
              <form method="post" style="display:inline;" id="dentistHardDeleteForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="hard_delete" value="1">
                  <button type="button" class="btn-delete" onclick="showConfirmDialog('Permanently delete this dentist? This action cannot be undone.', function(){ document.getElementById('dentistHardDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Permanent Delete', icon: 'danger', danger: true, okText: 'Delete Forever' })">Perm. Delete</button>
              </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
  <form id="addDentistForm" method="post" class="modal-content" style="max-width: 1000px;">
    <div class="modal-header">
      <h3>Add New Dentist</h3>
    </div>
    
    <div class="modal-body">
      <h4 class="section-title">Personal Information</h4>
      <div class="form-grid">
        <div class="form-group">
            <label>First Name:</label>
            <input name="first_name" placeholder="First Name" required maxlength="100">
        </div>
        <div class="form-group">
            <label>Middle Name:</label>
            <div class="input-with-checkbox">
              <input type="text" name="middle_name" id="middle_name" placeholder="Middle Name" maxlength="100">
              <label class="inside-checkbox">
                <input type="checkbox" id="noneMiddle" onclick="toggleMiddleName('middle_name', this)">
                <span style="color: #333;">None</span>
              </label>
            </div>
        </div>
        <div class="form-group">
            <label>Last Name:</label>
            <input name="last_name" placeholder="Last Name" required maxlength="100">
        </div>
        <div class="form-group">
            <label>Birthdate:</label>
            <input name="birthday" type="date" required max="<?= $maxDate ?>">
        </div>
        <div class="form-group">
            <label>Phone Number:</label>
            <input name="phone" placeholder="Phone Number (09xxxxxxxxx)" maxlength="11" oninput="enforcePhoneLimit(this)">
        </div>
        <div class="form-group">
            <label>Email:</label>
            <input name="email" id="addEmailInput" type="email" placeholder="Email" required maxlength="320">
        </div>
        <div class="form-group full-width">
            <label>Address:</label>
            <textarea name="address" placeholder="Address" rows="2"></textarea>
        </div>
        <div class="form-group">
            <label>Password:</label>
            <div class="password-wrapper">
              <input type="password" placeholder="Password" id="addPassword" name="password" oninput="enforcePasswordLimit(this)" required>
              <span class="toggle-password" onclick="togglePassword(this, 'addPassword')">SHOW</span>
            </div>
        </div>
        <div class="form-group">
            <label>Confirm Password:</label>
            <div class="password-wrapper">
              <input type="password" placeholder="Confirm Password" id="addConfirmPassword" name="confirm_password" oninput="enforcePasswordLimit(this)" required>
              <span class="toggle-password" onclick="togglePassword(this, 'addConfirmPassword')">SHOW</span>
            </div>
        </div>
      </div>
      <div>
        <label>Employment Type:</label>
        <select name="employment_type">
          <option value="Full-Time">Full-Time</option>
          <option value="Part-Time">Part-Time</option>
        </select>
      </div>
      <h4 class="section-title" style="margin-top: 20px;">Weekly Schedule</h4>
      <div class="schedule-wrapper">
        <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday'] as $day): ?>
            <div class="day-schedule-row" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div style="flex: 1; display: flex; align-items: center;">
                    <span style="font-weight: bold; font-size: 1.1em; text-transform: capitalize; display: inline-block; width: 100px;"><?= ucfirst($day) ?></span>
                    <input type="checkbox" name="<?= $day ?>_active" value="1" onchange="toggleAddInputs('<?= $day ?>', this.checked)" style="transform: scale(1.2); cursor: pointer; margin: 0;">
                </div>
                <div style="flex: 2; display: flex; gap: 10px; align-items: center;">
                    <label>In:</label>
                    <input type="time" name="<?= $day ?>_start" id="add_<?= $day ?>_start" class="time-input" min="07:00" max="17:00" disabled>
                    <label>Out:</label>
                    <input type="time" name="<?= $day ?>_end" id="add_<?= $day ?>_end" class="time-input" min="07:00" max="17:00" disabled>
                </div>
            </div>
        <?php endforeach; ?>
        <!-- Sunday is always closed — hidden field submits 0 -->
        <input type="hidden" name="sunday_active" value="0">
      </div>
    </div>

    <div class="modal-actions">
      <button type="submit" name="add" value="1">Save</button>
      <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
    </div>
  </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <form id="editDentistForm" method="post" class="modal-content">
    <h3>Edit Dentist</h3>
    <input type="hidden" name="id" id="editId">
    
    <div class="form-grid">
      <div>
        <label>First Name:</label>
        <input name="first_name" id="editFirst" placeholder="First Name" required maxlength="100">
      </div>
      <div>
        <label>Middle Name:</label>
        <div class="input-with-checkbox">
          <input type="text" name="middle_name" id="editMiddle" placeholder="Middle Name" maxlength="100">
          <label class="inside-checkbox">
            <input type="checkbox" id="editNoneMiddle" onclick="toggleMiddleName('editMiddle', this)">
            <span>None</span>
          </label>
        </div>
      </div>
      <div>
        <label>Last Name:</label>
        <input name="last_name" id="editLast" placeholder="Last Name" required maxlength="100">
      </div>
      <div>
        <label>Birthdate:</label>
        <input name="birthday" type="date" id="editBirthday" required max="<?= $maxDate ?>">
      </div>
      <div>
        <label>Phone Number:</label>
        <input name="phone" id="editPhone" placeholder="Phone Number (09xxxxxxxxx)" maxlength="11" oninput="enforcePhoneLimit(this)">
      </div>
      <div>
        <label>Email:</label>
        <input name="email" type="email" id="editEmailInput" placeholder="Email" required maxlength="320">
      </div>

     <div>
        <label>Employment Type:</label>
        <select name="employment_type" id="editEmploymentType">
          <option value="Full-Time">Full-Time</option>
          <option value="Part-Time">Part-Time</option>
        </select>
      </div>

      <div>
        <label>Status:</label>
        <select name="is_active" id="editActiveSelect">
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>
      <div class="full-width">
        <label>Address:</label>
        <textarea name="address" id="editAddress" placeholder="Address" rows="2"></textarea>
      </div>
    </div>

    <div class="modal-actions">
      <button type="submit" name="edit" value="1">Update</button>
      <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
    </div>
  </form>
</div>

<!-- Availability Modal -->
<div id="availabilityModal" class="modal">
  <form method="post" class="modal-content availability-modal" style="max-width: 600px; width: 95%;">
    <h3>Manage Availability</h3>
    <h4 id="dentistNameDisplay" style="margin-bottom: 20px; opacity: 0.8;"></h4>
    <input type="hidden" name="dentist_id" id="availDentistId">
    
    <div id="weeklyScheduleContainer" style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">
        <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday'] as $day): ?>
            <div class="day-schedule-row" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div style="flex: 1; display: flex; align-items: center;">
                    <span style="font-weight: bold; font-size: 1.1em; text-transform: capitalize; display: inline-block; width: 100px;"><?= ucfirst($day) ?></span>
                    <input type="checkbox" name="<?= $day ?>_active" id="avail_<?= $day ?>_active" value="1" onchange="toggleTimeInputs('<?= $day ?>', this.checked)" style="transform: scale(1.2); cursor: pointer; margin: 0;">
                </div>
                <div style="flex: 2; display: flex; gap: 10px; align-items: center;">
                    <label>In:</label>
                    <input type="time" name="<?= $day ?>_start" id="avail_<?= $day ?>_start" class="time-input" min="07:00" max="17:00" disabled>
                    <label>Out:</label>
                    <input type="time" name="<?= $day ?>_end" id="avail_<?= $day ?>_end" class="time-input" min="07:00" max="17:00" disabled>
                </div>
            </div>
        <?php endforeach; ?>
        <!-- Sunday is always closed — hidden field submits 0 -->
        <input type="hidden" name="sunday_active" value="0">
    </div>

    <div class="availability-actions" style="margin-top: 20px; text-align: right;">
      <button type="submit" name="update_availability" value="1" class="btn-save" onclick="return validateTimeInputsInForm(this.closest('form'))">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="document.getElementById('availabilityModal').style.display='none'">Close</button>
    </div>
  </form>
</div>

<script>
  window.addEventListener('storage', function(e) {
      if (e.key === 'logoutEvent') {
          showAlert('You have been logged out from another tab.');
          window.location.href = '../main_page/pconcio_main.php';
      }
  });

  function showTab(tabId) {
      document.querySelectorAll('.tab-content').forEach(div => {
          div.style.display = 'none';
      });
      document.getElementById(tabId).style.display = 'block';
  }

  // Strictly enforce 07:00–17:00; called on change and blur
  function enforceTimeRange(input) {
      if (!input.value) return;
      if (input.value < '07:00') {
          input.value = '07:00';
          showAlert('Minimum time is 7:00 AM.');
      } else if (input.value > '17:00') {
          input.value = '17:00';
          showAlert('Maximum time is 5:00 PM.');
      }
  }

  function attachTimeEnforcer(inp) {
      inp.addEventListener('change', function() { enforceTimeRange(this); });
      inp.addEventListener('blur',   function() { enforceTimeRange(this); });
  }

  // Attach enforcer to all time inputs on page load
  document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('input[type="time"]').forEach(attachTimeEnforcer);
  });

  // Validate all active (checked) days in a form before submitting
  function validateTimeInputsInForm(formEl) {
      const days = ['monday','tuesday','wednesday','thursday','friday','saturday'];
      for (const day of days) {
          const cbEl    = formEl.querySelector('[name="' + day + '_active"]');
          const startEl = formEl.querySelector('[name="' + day + '_start"]');
          const endEl   = formEl.querySelector('[name="' + day + '_end"]');

          // Only validate if the day is checked/active
          if (!cbEl || !cbEl.checked) continue;

          const start = startEl ? startEl.value : '';
          const end   = endEl   ? endEl.value   : '';
          const dayLabel = day.charAt(0).toUpperCase() + day.slice(1);

          if (!start) {
              showAlert('Please enter Time In for ' + dayLabel + '.');
              if (startEl) startEl.focus();
              return false;
          }
          if (!end) {
              showAlert('Please enter Time Out for ' + dayLabel + '.');
              if (endEl) endEl.focus();
              return false;
          }
          if (start < '07:00' || start > '17:00') {
              showAlert('Time In for ' + dayLabel + ' must be between 7:00 AM and 5:00 PM.');
              if (startEl) { startEl.value = ''; startEl.focus(); }
              return false;
          }
          if (end < '07:00' || end > '17:00') {
              showAlert('Time Out for ' + dayLabel + ' must be between 7:00 AM and 5:00 PM.');
              if (endEl) { endEl.value = ''; endEl.focus(); }
              return false;
          }
          if (end <= start) {
              showAlert('Time Out for ' + dayLabel + ' must be later than Time In.');
              if (endEl) { endEl.value = ''; endEl.focus(); }
              return false;
          }
      }
      return true;
  }

  function toggleAddInputs(day, isChecked) {
      document.getElementById('add_' + day + '_start').disabled = !isChecked;
      document.getElementById('add_' + day + '_end').disabled = !isChecked;
  }

  function toggleTimeInputs(day, isChecked) {
      document.getElementById('avail_' + day + '_start').disabled = !isChecked;
      document.getElementById('avail_' + day + '_end').disabled = !isChecked;
  }

  function openAvailability(data) {
    document.getElementById('availDentistId').value = data.id;
    document.getElementById('dentistNameDisplay').textContent = 'Dentist: ' + data.first_name + ' ' + data.last_name;
    
    // Monday to Saturday only — Sunday is not editable
    const days = ['monday','tuesday','wednesday','thursday','friday','saturday'];
    
    days.forEach(day => {
        const isActive = data[day] == 1;
        const cb = document.getElementById('avail_' + day + '_active');
        cb.checked = isActive;
        
        const startInput = document.getElementById('avail_' + day + '_start');
        const endInput = document.getElementById('avail_' + day + '_end');
        
        startInput.value = data[day + '_start'] ? data[day + '_start'].substring(0, 5) : '';
        endInput.value = data[day + '_end'] ? data[day + '_end'].substring(0, 5) : '';
        
        startInput.disabled = !isActive;
        endInput.disabled = !isActive;
    });

    document.getElementById('availabilityModal').style.display = 'flex';
  }

  function openEdit(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editFirst').value = data.first_name;
    document.getElementById('editMiddle').value = data.middle_name;
    document.getElementById('editLast').value = data.last_name;
    document.getElementById('editBirthday').value = data.birthday;
    document.getElementById('editAddress').value = data.address;
    document.getElementById('editPhone').value = data.phone;
    document.getElementById('editEmailInput').value = data.email;
    document.getElementById('editActiveSelect').value = data.is_active == 1 ? "1" : "0";
    document.getElementById('editEmploymentType').value = data.employment_type || 'Full-Time';

    document.getElementById('editModal').style.display = 'flex';
  }

  function toggleMiddleName(inputId, checkbox) {
    const input = document.getElementById(inputId);
    if (checkbox.checked) {
      input.value = '';
      input.disabled = true;
      input.style.background = '#f1f1f1';
    } else {
      input.disabled = false;
      input.style.background = 'white';
    }
  }

  document.addEventListener("input", function (e) {
    const upperFields = ["first_name", "middle_name", "last_name", "address", 
                         "editFirst", "editMiddle", "editLast", "editAddress"];
    if (upperFields.includes(e.target.name) || upperFields.includes(e.target.id)) {
      e.target.value = e.target.value.toUpperCase();
      if (["first_name", "middle_name", "last_name", "editFirst", "editMiddle", "editLast"].includes(e.target.name) || 
          ["first_name", "middle_name", "last_name", "editFirst", "editMiddle", "editLast"].includes(e.target.id)) {
          e.target.value = e.target.value.replace(/[^A-Z\s]/g, '');
      }
    }
  });

  function enforcePhoneLimit(input) {
    input.value = input.value.replace(/\D/g, "");
    if (input.value.length > 11) {
      input.value = input.value.slice(0, 11);
    }
  }

  function enforcePasswordLimit(input) {
    if (input.value.length > 30) {
      input.value = input.value.slice(0, 30);
    }
  }

  function togglePassword(span, inputId) {
    const pass = document.getElementById(inputId);
    if (pass.type === 'password') {
      pass.type = 'text';
      span.textContent = 'HIDE';
    } else {
      pass.type = 'password';
      span.textContent = 'SHOW';
    }
  }

  function showEmailModal() {
      document.getElementById('emailExistModal').style.display = 'flex';
  }

  function closeEmailModal() {
      document.getElementById('emailExistModal').style.display = 'none';
  }

  let isCheckingAddEmail = false;

  // AJAX Email Validation for Add Form (Fixed submission)
  document.getElementById("addDentistForm").addEventListener("submit", function(e) {
    if (isCheckingAddEmail) return;

    e.preventDefault();
    
    let p = document.getElementById("addPassword").value;
    let c = document.getElementById("addConfirmPassword").value;
    if (p !== c) {
      showAlert("Password and Confirm Password do not match!");
      return false;
    }

    // Validate time range 7AM–5PM before submitting
    if (!validateTimeInputsInForm(this)) return false;

    let email = document.getElementById("addEmailInput").value;
    let formElement = this;

    fetch('dentist_account.php?check_email=' + encodeURIComponent(email))
      .then(response => response.json())
      .then(data => {
          if (data.exists) {
              showEmailModal();
          } else {
              isCheckingAddEmail = true;

              // I-apend ang hidden input para mabasa ng PHP ang `isset($_POST['add'])`
              let hiddenAdd = document.createElement('input');
              hiddenAdd.type = 'hidden';
              hiddenAdd.name = 'add';
              hiddenAdd.value = '1';
              formElement.appendChild(hiddenAdd);

              formElement.submit();
          }
      }).catch(error => {
          console.error('Error:', error);
          isCheckingAddEmail = true;
          
          let hiddenAdd = document.createElement('input');
          hiddenAdd.type = 'hidden';
          hiddenAdd.name = 'add';
          hiddenAdd.value = '1';
          formElement.appendChild(hiddenAdd);

          formElement.submit();
      });
  });

  let isCheckingEditEmail = false;

  // AJAX Email Validation for Edit Form (Fixed submission)
  document.getElementById("editDentistForm").addEventListener("submit", function(e) {
    if (isCheckingEditEmail) return;

    e.preventDefault();

    let id = document.getElementById("editId").value;
    let email = document.getElementById("editEmailInput").value;
    let formElement = this;

    fetch('dentist_account.php?check_email=' + encodeURIComponent(email) + '&exclude_id=' + encodeURIComponent(id))
      .then(response => response.json())
      .then(data => {
          if (data.exists) {
              showEmailModal();
          } else {
              isCheckingEditEmail = true;

              // I-apend ang hidden input para mabasa ng PHP ang `isset($_POST['edit'])`
              let hiddenEdit = document.createElement('input');
              hiddenEdit.type = 'hidden';
              hiddenEdit.name = 'edit';
              hiddenEdit.value = '1';
              formElement.appendChild(hiddenEdit);

              formElement.submit();
          }
      }).catch(error => {
          console.error('Error:', error);
          isCheckingEditEmail = true;

          let hiddenEdit = document.createElement('input');
          hiddenEdit.type = 'hidden';
          hiddenEdit.name = 'edit';
          hiddenEdit.value = '1';
          formElement.appendChild(hiddenEdit);

          formElement.submit();
      });
  });
</script>



