<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

try {
    $pdo->exec("ALTER TABLE staff_accounts ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE staff_accounts ADD COLUMN middle_name VARCHAR(50) DEFAULT NULL AFTER first_name");
} catch (PDOException $e) {}

// Fetch staff list with age
$active_staffs = $pdo->query("
  SELECT sa.*, TIMESTAMPDIFF(YEAR, sa.birthday, CURDATE()) AS age
  FROM staff_accounts sa
  WHERE COALESCE(sa.is_deleted, 0) = 0
  ORDER BY sa.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$deleted_staffs = $pdo->query("
  SELECT sa.*, TIMESTAMPDIFF(YEAR, sa.birthday, CURDATE()) AS age
  FROM staff_accounts sa
  WHERE COALESCE(sa.is_deleted, 0) = 1
  ORDER BY sa.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ADD
if (isset($_POST['add'])) {
    $first_name = strtoupper(trim($_POST['first_name']));
    $middle_name = strtoupper(trim($_POST['middle_name']));
    $last_name = strtoupper(trim($_POST['last_name']));
    $birthday = $_POST['birthday'];
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $is_active = 1;

    // Prevent duplicate emails across staff, dentist, admin
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT email FROM staff_accounts WHERE email = ?
            UNION ALL
            SELECT email FROM dentist_accounts WHERE email = ?
            UNION ALL
            SELECT email FROM admin WHERE email = ?
        ) AS all_emails
    ");
    $check->execute([$email, $email, $email]);

    if ($check->fetchColumn() > 0) {
        $_SESSION['error_message'] = "Email already exists in the system!";
        header("Location: staff_account.php");
        exit;
    }

    // Validate Name (No special characters, only letters and spaces allowed)
    if (!preg_match("/^[A-Z\s]+$/", $first_name) || 
        (!empty($middle_name) && !preg_match("/^[A-Z\s]*$/", $middle_name)) || 
        !preg_match("/^[A-Z\s]+$/", $last_name)) {
        echo "<script>alert('Names allowed only letters and spaces (No special characters).'); window.location='staff_account.php';</script>";
        exit;
    }

    // Validate Character Limit
    if (strlen($first_name) > 50 || strlen($middle_name) > 50 || strlen($last_name) > 50) {
        echo "<script>alert('Names must be 50 characters or less.'); window.location='staff_account.php';</script>";
        exit;
    }

    $emailParts = explode('@', $email);
    $domainPart = array_pop($emailParts);
    $localPart = implode('@', $emailParts);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
        echo "<script>alert('Invalid email address or exceeds length limits (64 chars before @, 255 after).'); window.location='staff_account.php';</script>";
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) || strtotime($birthday) > time()) {
        echo "<script>alert('Invalid birthday date format or future date.'); window.location='staff_account.php';</script>";
        exit;
    }

    // Validate Age (18+)
    $age = (new DateTime($birthday))->diff(new DateTime())->y;
    if ($age < 18 || $age >= 150) {
        echo "<script>alert('Staff must be between 18 and 149 years old.'); window.location='staff_account.php';</script>";
        exit;
    }

    // Validate Phone Number (Must start with 09 and be 11 digits)
    if (!preg_match("/^09\d{9}$/", $phone)) {
        echo "<script>alert('Phone number must start with 09 and contain 11 digits.'); window.location='staff_account.php';</script>";
        exit;
    }

    // Generate staff_id based on next id (S + zero-padded number)
    $pdo->beginTransaction();
    try {
        $stmtLast = $pdo->query("SELECT last_number FROM staff_id_tracker WHERE id = 1 FOR UPDATE");
        if (!$stmtLast) {
            throw new Exception("Staff ID tracker table not found or query failed.");
        }
        $lastNumber = $stmtLast->fetchColumn();
        
        if ($lastNumber === false) {
            throw new Exception("Staff ID tracker not initialized (Row 1 missing).");
        }

        $newNumber = $lastNumber + 1;
        $staff_id = 'PDS-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO staff_accounts 
            (staff_id, first_name, middle_name, last_name, birthday, address, phone, email, password_hash, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$staff_id, $first_name, ($middle_name === '' ? null : $middle_name), $last_name, $birthday, $address, $phone, $email, $password_hash, $is_active]);

        $pdo->prepare("UPDATE staff_id_tracker SET last_number = ? WHERE id = 1")->execute([$newNumber]);

        $pdo->commit();
        header("Location: staff_account.php");
        exit;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "<script>alert('Failed to add staff: " . $e->getMessage() . "'); window.location='staff_account.php';</script>";
        exit;
    }
}

// EDIT
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $first_name = strtoupper(trim($_POST['first_name']));
    $middle_name = strtoupper(trim($_POST['middle_name']));
    $last_name = strtoupper(trim($_POST['last_name']));
    $birthday = $_POST['birthday'];
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $is_active = ($_POST['is_active'] == "1") ? 1 : 0;

    // Check duplicate email excluding current staff and across dentist/admin
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT email FROM staff_accounts WHERE email = ? AND id != ?
            UNION ALL
            SELECT email FROM dentist_accounts WHERE email = ?
            UNION ALL
            SELECT email FROM admin WHERE email = ?
        ) AS all_emails
    ");
    $check->execute([$email, $id, $email, $email]);

    if ($check->fetchColumn() > 0) {
        $_SESSION['error_message'] = "Email already exists in the system!";
        header("Location: staff_account.php");
        exit;
    }

    // Validate Name (No special characters)
    if (!preg_match("/^[A-Z\s]+$/", $first_name) || 
        (!empty($middle_name) && !preg_match("/^[A-Z\s]*$/", $middle_name)) || 
        !preg_match("/^[A-Z\s]+$/", $last_name)) {
        echo "<script>alert('Names allowed only letters and spaces (No special characters).'); window.location='staff_account.php';</script>";
        exit;
    }

    // Validate Character Limit
    if (strlen($first_name) > 50 || strlen($middle_name) > 50 || strlen($last_name) > 50) {
        echo "<script>alert('Names must be 50 characters or less.'); window.location='staff_account.php';</script>";
        exit;
    }

    $emailParts = explode('@', $email);
    $domainPart = array_pop($emailParts);
    $localPart = implode('@', $emailParts);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
        echo "<script>alert('Invalid email address or exceeds length limits (64 chars before @, 255 after).'); window.location='staff_account.php';</script>";
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) || strtotime($birthday) > time()) {
        echo "<script>alert('Invalid birthday date format or future date.'); window.location='staff_account.php';</script>";
        exit;
    }

    // Validate Age (18+)
    $age = (new DateTime($birthday))->diff(new DateTime())->y;
    if ($age < 18 || $age >= 150) {
        echo "<script>alert('Staff must be between 18 and 149 years old.'); window.location='staff_account.php';</script>";
        exit;
    }

    // Validate Phone Number (Must start with 09 and be 11 digits)
    if (!preg_match("/^09\d{9}$/", $phone)) {
        echo "<script>alert('Phone number must start with 09 and contain 11 digits.'); window.location='staff_account.php';</script>";
        exit;
    }

    $stmt = $pdo->prepare("
      UPDATE staff_accounts
      SET first_name=?, middle_name=?, last_name=?, birthday=?, address=?, phone=?, email=?, is_active=?
      WHERE id=?
    ");
    $stmt->execute([$first_name, ($middle_name === '' ? null : $middle_name), $last_name, $birthday, $address, $phone, $email, $is_active, $id]);

    header("Location: staff_account.php");
    exit;
}

// DELETE
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $pdo->prepare("UPDATE staff_accounts SET is_deleted=1 WHERE id=?")->execute([$id]);
    echo "<script>window.location='staff_account.php';</script>";
    exit;
}

// RESTORE
if (isset($_POST['restore'])) {
    $id = $_POST['id'];
    $pdo->prepare("UPDATE staff_accounts SET is_deleted=0 WHERE id=?")->execute([$id]);
    echo "<script>window.location='staff_account.php';</script>";
    exit;
}

// HARD DELETE
if (isset($_POST['hard_delete'])) {
    $stmt = $pdo->prepare("DELETE FROM staff_accounts WHERE id=?");
    $stmt->execute([$_POST['id']]);
    echo "<script>window.location='staff_account.php';</script>";
    exit;
}

include __DIR__ . '/../miscellaneous/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Accounts</title>
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <link rel="stylesheet" href="staff_account_design.css?v=<?= time(); ?>">
  <style>
    body {
        background-image: none !important;
    }
    .btn-delete {
      background-color: #dc3545 !important;
      color: white !important;
      border: none !important;
    }
    .btn-delete:hover {
      background-color: #c82333 !important;
    }
    @media (max-width: 900px) {
  .main-content {
    margin-left: 0 !important;
    margin-top: 70px !important;
    padding: 15px !important;
    width: 100% !important;
  }

  /* 1. Isaayos ang buong topbar para magkasiya ang tatlo sa isang linya */
  .topbar {
    left: 0 !important;
    width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 0 10px !important;
    gap: 8px !important;
  }

  /* 2. Pagsamahin ang left elements (Menu button at Clinic Name) */
  .topbar-left {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    overflow: hidden !important;
    flex: 1 !important;
    min-width: 0 !important;
  }

  /* 3. Paliitin at lagyan ng ellipsis (...) ang pangalan para hindi sumobra */
  .topbar .logo, 
  .topbar h3,
  .topbar .clinic-title {
    display: inline-block !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    margin: 0 !important;
    max-width: 130px !important; /* Pwede mong taasan/babaan depende sa luwang ng screen */
  }

  /* 4. Paliitin nang kaunti ang Welcome text sa kanan para magkasya */
  .topbar div:last-child, 
  .topbar span {
    font-size: 11px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
  }

  /* --- SIDEBAR LOGIC --- */
  .sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 260px !important;
    height: 100vh !important;
    z-index: 999999 !important;
    transform: translateX(-100%) !important;
    transition: transform 0.3s ease-in-out !important;
  }

  .sidebar.active {
    transform: translateX(0) !important;
  }

  .table-wrapper {
    overflow-x: auto !important;
  }
}
  </style>
</head>
<body>

<div class="main-content">
  <h2>Staff Accounts</h2>
  <div style="display: flex; gap: 10px; margin-bottom: 15px;">
      <button type="button" class="btn-add" onclick="showTab('active_accounts')">Active Accounts</button>
      <button type="button" class="btn-add" onclick="showTab('deleted_accounts')" style="background-color: #64748b;">Deleted Accounts</button>
      <button type="button" class="btn-add" style="margin-left: auto;" onclick="document.getElementById('addModal').style.display='flex'">Add Staff</button>
  </div>

  <div id="active_accounts" class="tab-content" style="display:block;">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Staff ID</th>
          <th>First Name</th>
          <th>Middle Name</th>
          <th>Last Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Birthday</th>
          <th>Age</th>
          <th>Status</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($active_staffs)): ?>
          <tr><td colspan="12" style="text-align:center;">No active staff account found.</td></tr>
        <?php endif; ?>

        <?php foreach ($active_staffs as $i => $row): ?>
          <tr>
            <td data-label="#"><?= $i + 1 ?></td>
            <td data-label="Staff ID"><?= htmlspecialchars($row['staff_id']) ?></td>
            <td data-label="First Name"><?= htmlspecialchars($row['first_name']) ?></td>
            <td data-label="Middle Name"><?= htmlspecialchars($row['middle_name'] ?? '') ?></td>
            <td data-label="Last Name"><?= htmlspecialchars($row['last_name']) ?></td>
            <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
            <td data-label="Phone"><?= htmlspecialchars($row['phone']) ?></td>
            <td data-label="Birthday"><?= htmlspecialchars($row['birthday']) ?></td>
            <td data-label="Age"><?= htmlspecialchars($row['age']) ?></td>
            <td data-label="Status"><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td data-label="Created At"><?= htmlspecialchars($row['created_at']) ?></td>
            <td data-label="Actions">
              <button class="btn-edit" onclick='openEdit(<?= json_encode($row) ?>)'>Edit</button>
              <form method="post" style="display:inline;" id="staffDeleteForm_<?= $row['id'] ?>">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <input type="hidden" name="delete" value="1">
                <button type="button" class="btn-delete" onclick="showConfirmDialog('Move this staff to deleted accounts?', function(){ document.getElementById('staffDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Delete Staff', icon: 'delete', danger: true, okText: 'Delete' })">Delete</button>
              </form>
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
          <th>Staff ID</th>
          <th>First Name</th>
          <th>Middle Name</th>
          <th>Last Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Birthday</th>
          <th>Age</th>
          <th>Status</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($deleted_staffs)): ?>
          <tr><td colspan="12" style="text-align:center;">No deleted staff account found.</td></tr>
        <?php endif; ?>

        <?php foreach ($deleted_staffs as $i => $row): ?>
          <tr>
            <td data-label="#"><?= $i + 1 ?></td>
            <td data-label="Staff ID"><?= htmlspecialchars($row['staff_id']) ?></td>
            <td data-label="First Name"><?= htmlspecialchars($row['first_name']) ?></td>
            <td data-label="Middle Name"><?= htmlspecialchars($row['middle_name'] ?? '') ?></td>
            <td data-label="Last Name"><?= htmlspecialchars($row['last_name']) ?></td>
            <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
            <td data-label="Phone"><?= htmlspecialchars($row['phone']) ?></td>
            <td data-label="Birthday"><?= htmlspecialchars($row['birthday']) ?></td>
            <td data-label="Age"><?= htmlspecialchars($row['age']) ?></td>
            <td data-label="Status"><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td data-label="Created At"><?= htmlspecialchars($row['created_at']) ?></td>
            <td data-label="Actions">
              <form method="post" style="display:inline;" id="staffRestoreForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="restore" value="1">
                  <button type="button" class="btn-manage" style="background-color: #f59e0b; border:none; padding:5px 10px; border-radius:4px; color:white; cursor:pointer;" onclick="showConfirmDialog('Restore this staff?', function(){ document.getElementById('staffRestoreForm_<?= $row['id'] ?>').submit(); }, { title: 'Restore Staff', icon: 'restore', okText: 'Restore' })">Restore</button>
              </form>
              <form method="post" style="display:inline;" id="staffHardDeleteForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="hard_delete" value="1">
                  <button type="button" class="btn-delete" onclick="showConfirmDialog('Permanently delete this staff? This action cannot be undone.', function(){ document.getElementById('staffHardDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Permanent Delete', icon: 'danger', danger: true, okText: 'Delete Forever' })">Perm. Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  </div>
</div>

<?php 
    $maxDate = date('Y-m-d', strtotime('-18 years'));
?>

<!-- Add Modal -->
<div id="addModal" class="modal">
  <form method="post" class="modal-content">
    <h3>Add Staff</h3>

    <label>First Name:</label>
    <input name="first_name" placeholder="First Name" required maxlength="50">

    <label>Middle Name (Optional):</label>
    <input name="middle_name" placeholder="Middle Name" maxlength="50">

    <label>Last Name:</label>
    <input name="last_name" placeholder="Last Name" required maxlength="50">

    <label>Birthdate:</label>
    <input name="birthday" id="addBirthday" type="date" required max="<?= $maxDate ?>" onchange="validateBirthday(this)">

    <label>Address:</label>
    <textarea name="address" placeholder="Address"></textarea>

    <label>Phone Number:</label>
    <input name="phone" placeholder="Phone Number (09xxxxxxxxx)" maxlength="11" oninput="enforcePhoneLimit(this)">

    <label>Email:</label>
    <input name="email" type="email" placeholder="Email" required maxlength="320">

    <label>Password:</label>
    <div class="password-wrapper">
      <input type="password" placeholder="Password" id="addPassword" name="password" oninput="enforcePasswordLimit(this)" required>
      <span class="toggle-password" onclick="togglePassword(this, 'addPassword')">SHOW</span>
    </div>

    <label>Confirm Password:</label>
    <div class="password-wrapper">
      <input type="password" placeholder="Confirm Password" id="addConfirmPassword" name="confirm_password" oninput="enforcePasswordLimit(this)" required>
      <span class="toggle-password" onclick="togglePassword(this, 'addConfirmPassword')">SHOW</span>
    </div>

    <button name="add">Save</button>
    <button type="button" onclick="document.getElementById('addModal').style.display='none'; this.form.reset();">Cancel</button>
  </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <form method="post" class="modal-content">
    <h3>Edit Staff</h3>

    <input type="hidden" name="id" id="editId">

    <label>First Name:</label>
    <input name="first_name" id="editFirst" placeholder="First Name" required maxlength="50">

    <label>Middle Name (Optional):</label>
    <input name="middle_name" id="editMiddle" placeholder="Middle Name" maxlength="50">

    <label>Last Name:</label>
    <input name="last_name" id="editLast" placeholder="Last Name" required maxlength="50">

    <label>Birthdate:</label>
    <input name="birthday" type="date" id="editBirthday" required max="<?= $maxDate ?>" onchange="validateBirthday(this)">

    <label>Address:</label>
    <textarea name="address" id="editAddress" placeholder="Address"></textarea>

    <label>Phone Number:</label>
    <input name="phone" id="editPhone" placeholder="Phone Number (09xxxxxxxxx)" maxlength="11" oninput="enforcePhoneLimit(this)">

    <label>Email:</label>
    <input name="email" type="email" id="editEmail" placeholder="Email" required maxlength="320">

    <label>Status:</label>
    <select name="is_active" id="editActiveSelect">
      <option value="1">Active</option>
      <option value="0">Inactive</option>
    </select>

    <button name="edit">Update</button>
    <button type="button" onclick="document.getElementById('editModal').style.display='none'; this.form.reset();">Cancel</button>
  </form>
</div>

<!-- Warning / Already Exist Modal -->
<div id="errorModal" class="modal" style="display: <?= isset($_SESSION['error_message']) ? 'flex' : 'none' ?>;">
  <div class="modal-content" style="
      max-width: 400px;
      text-align: center;
      background: #ffffff !important;
      background-image: none !important;
      color: #1e293b;
      border-radius: 16px;
      box-shadow: 0 24px 64px rgba(0,0,0,0.22);
      padding: 36px 32px 28px;
      gap: 0;
  ">
    <div style="font-size: 2.4rem; margin-bottom: 12px; line-height: 1;">
      <i class="fa-solid fa-circle-exclamation" style="color: #ef4444;"></i>
    </div>
    <h3 style="color: #ef4444; margin: 0 0 10px; font-size: 1.15rem; font-weight: 700;">Notice</h3>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 16px;">
    <p id="errorMessageText" style="color: #475569; font-size: 0.95rem; margin: 0 0 24px; line-height: 1.55;">
      <?= htmlspecialchars($_SESSION['error_message'] ?? '') ?>
    </p>
    <button type="button"
      onclick="document.getElementById('errorModal').style.display='none'"
      style="padding: 10px 32px; background-color: #0ea5e9; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600;">
      OK
    </button>
  </div>
</div>

<?php 
// Clear session error after rendering
unset($_SESSION['error_message']);
?>

<script>
  window.addEventListener('storage', function(e) {
      if (e.key === 'logoutEvent') {
          alert('You have been logged out from another tab.');
          window.location.href = '../main_page/pconcio_main.php';
      }
  });

  function showTab(tabId) {
      document.querySelectorAll('.tab-content').forEach(div => {
          div.style.display = 'none';
      });
      document.getElementById(tabId).style.display = 'block';
  }

  function openEdit(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editFirst').value = data.first_name;
    document.getElementById('editMiddle').value = data.middle_name || '';
    document.getElementById('editLast').value = data.last_name;
    document.getElementById('editBirthday').value = data.birthday;
    document.getElementById('editAddress').value = data.address;
    document.getElementById('editPhone').value = data.phone;
    document.getElementById('editEmail').value = data.email;
    document.getElementById('editActiveSelect').value = data.is_active == 1 ? "1" : "0";

    document.getElementById('editModal').style.display = 'flex';
  }

  function computeAge(birthday) {
      const birthDate = new Date(birthday);
      const today = new Date();
      let age = today.getFullYear() - birthDate.getFullYear();
      const m = today.getMonth() - birthDate.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
      return age;
  }

  function validateBirthday(input) {
      const dateValue = input.value;
      if (!dateValue) return;

      const parts = dateValue.split('-');
      if (parts.length === 3) {
          const year = parseInt(parts[0], 10);
          if (year < 1000 || year > 9999 || parts[0].length !== 4) {
              alert("Please enter a valid 4-digit year.");
              input.value = '';
              return;
          }
      }

      const age = computeAge(dateValue);
      if (age < 18 || age >= 150) {
          alert("Invalid birthday. Staff must be between 18 and 149 years old.");
          input.value = '';
      }
  }

  document.addEventListener("input", function (e) {
    const upperFields = ["first_name", "middle_name", "last_name", "address", 
                         "editFirst", "editMiddle", "editLast", "editAddress"];

    if (upperFields.includes(e.target.name) || upperFields.includes(e.target.id)) {
      if (e.target.name === "address" || e.target.id === "editAddress") {
          e.target.value = e.target.value.toUpperCase();
      } else {
          e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '').toUpperCase();
      }
    }

    if (e.target.name === "email" || e.target.id === "editEmail") {
        let val = e.target.value;
        if (val.includes('@')) {
            let parts = val.split('@');
            if (parts[0].length > 64) parts[0] = parts[0].substring(0, 64);
            if (parts.length > 1) {
                let domain = parts.slice(1).join('@');
                if (domain.length > 255) domain = domain.substring(0, 255);
                e.target.value = parts[0] + '@' + domain;
            }
        } else {
            if (val.length > 64) e.target.value = val.substring(0, 64);
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

  document.querySelector("#addModal form").addEventListener("submit", function(e) {
    let p = document.getElementById("addPassword").value;
    let c = document.getElementById("addConfirmPassword").value;

    if (p !== c) {
      e.preventDefault();
      alert("Password and Confirm Password do not match!");
      return false;
    }
  });
</script>

</body>
</html>