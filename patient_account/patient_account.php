<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';
include __DIR__ . '/../miscellaneous/log_audit.php';

// Automatic database migration para masigurong may middle_name at is_deleted column
try {
    $pdo->exec("ALTER TABLE patient_account ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE patient_account ADD COLUMN middle_name VARCHAR(50) DEFAULT NULL AFTER first_name");
} catch (PDOException $e) {}

$active_patients = $pdo->query("SELECT * FROM patient_account WHERE COALESCE(is_deleted, 0) = 0 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$deleted_patients = $pdo->query("SELECT * FROM patient_account WHERE COALESCE(is_deleted, 0) = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

function computeAge($birthday) {
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age;
}

$error_message = "";
$reopen_modal = "";

if (isset($_POST['add'])) {
    $first_name = strtoupper(trim($_POST['first_name']));
    $middle_name = strtoupper(trim($_POST['middle_name']));
    $last_name = strtoupper(trim($_POST['last_name']));
    $birthday = $_POST['birthday'];
    $gender = strtoupper(trim($_POST['gender']));
    $gmail = trim($_POST['gmail']);
    $phone_number = trim($_POST['phone_number']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (strlen($first_name) > 50 || strlen($middle_name) > 50 || strlen($last_name) > 50) {
        $error_message = 'Names must be 50 characters or less.';
        $reopen_modal = 'addModal';
    } elseif (!preg_match("/^[A-Z\s]*$/", $middle_name) || !preg_match("/^[A-Z\s]+$/", $first_name) || !preg_match("/^[A-Z\s]+$/", $last_name)) {
        $error_message = 'Names can only contain letters and spaces.';
        $reopen_modal = 'addModal';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) || strtotime($birthday) > time()) {
        $error_message = 'Invalid birthday date format or future date.';
        $reopen_modal = 'addModal';
    } else {
        $age = computeAge($birthday);
        if ($age < 0 || $age >= 150) {
            $error_message = 'Invalid age computed from birthday (must be between 0 and 149).';
            $reopen_modal = 'addModal';
        } else {
            $emailParts = explode('@', $gmail);
            $domainPart = array_pop($emailParts);
            $localPart = implode('@', $emailParts);
            if (!filter_var($gmail, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
                $error_message = 'Invalid email address or exceeds length limits (64 chars before @, 255 after).';
                $reopen_modal = 'addModal';
            } elseif (strlen($phone_number) !== 11 || !preg_match('/^09\d{9}$/', $phone_number)) {
                $error_message = 'Phone number must be exactly 11 digits and start with 09.';
                $reopen_modal = 'addModal';
            } elseif ($password !== $confirm_password) {
                $error_message = 'Password and Confirm Password do not match!';
                $reopen_modal = 'addModal';
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM patient_account WHERE gmail = ?");
                $check->execute([$gmail]);
                if ($check->fetchColumn() > 0) {
                    $error_message = 'Email already exist!';
                    $reopen_modal = 'addModal';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO patient_account (first_name, middle_name, last_name, birthday, age, gender, gmail, phone_number, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$first_name, ($middle_name === '' ? null : $middle_name), $last_name, $birthday, $age, $gender, $gmail, $phone_number, $hashed_password]);
                    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Added Patient Account', "Patient: {$first_name} {$last_name} | Email: {$gmail}");
                    header("Location: patient_account.php");
                    exit;
                }
            }
        }
    }
}

if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $first_name = strtoupper(trim($_POST['first_name']));
    $middle_name = strtoupper(trim($_POST['middle_name']));
    $last_name = strtoupper(trim($_POST['last_name']));
    $birthday = $_POST['birthday'];
    $gender = strtoupper(trim($_POST['gender']));
    $gmail = trim($_POST['gmail']);
    $phone_number = trim($_POST['phone_number']);

    if (strlen($first_name) > 50 || strlen($middle_name) > 50 || strlen($last_name) > 50) {
        $error_message = 'Names must be 50 characters or less.';
        $reopen_modal = 'editModal';
    } elseif (!preg_match("/^[A-Z\s]*$/", $middle_name) || !preg_match("/^[A-Z\s]+$/", $first_name) || !preg_match("/^[A-Z\s]+$/", $last_name)) {
        $error_message = 'Names can only contain letters and spaces.';
        $reopen_modal = 'editModal';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) || strtotime($birthday) > time()) {
        $error_message = 'Invalid birthday date format or future date.';
        $reopen_modal = 'editModal';
    } else {
        $age = computeAge($birthday);
        if ($age < 0 || $age >= 150) {
            $error_message = 'Invalid age computed from birthday (must be between 0 and 149).';
            $reopen_modal = 'editModal';
        } else {
            $emailParts = explode('@', $gmail);
            $domainPart = array_pop($emailParts);
            $localPart = implode('@', $emailParts);
            if (!filter_var($gmail, FILTER_VALIDATE_EMAIL) || strlen($localPart) > 64 || strlen($domainPart) > 255) {
                $error_message = 'Invalid email address or exceeds length limits (64 chars before @, 255 after).';
                $reopen_modal = 'editModal';
            } elseif (strlen($phone_number) !== 11 || !preg_match('/^09\d{9}$/', $phone_number)) {
                $error_message = 'Phone number must be exactly 11 digits and start with 09.';
                $reopen_modal = 'editModal';
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM patient_account WHERE gmail = ? AND id != ?");
                $check->execute([$gmail, $id]);
                if ($check->fetchColumn() > 0) {
                    $error_message = 'Email already exist!';
                    $reopen_modal = 'editModal';
                } else {
                    $stmt = $pdo->prepare("UPDATE patient_account SET first_name=?, middle_name=?, last_name=?, birthday=?, age=?, gender=?, gmail=?, phone_number=? WHERE id=?");
                    $stmt->execute([$first_name, ($middle_name === '' ? null : $middle_name), $last_name, $birthday, $age, $gender, $gmail, $phone_number, $id]);
                    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Updated Patient Account', "Patient: {$first_name} {$last_name} | Email: {$gmail}");
                    header("Location: patient_account.php");
                    exit;
                }
            }
        }
    }
}

if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $pName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patient_account WHERE id=?");
    $pName->execute([$id]);
    $patientName = $pName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("UPDATE patient_account SET is_deleted=1 WHERE id=?")->execute([$id]);
    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Deactivated Patient Account', "Patient: {$patientName}");
    header("Location: patient_account.php");
    exit;
}

if (isset($_POST['restore'])) {
    $id = $_POST['id'];
    $pName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patient_account WHERE id=?");
    $pName->execute([$id]);
    $patientName = $pName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("UPDATE patient_account SET is_deleted=0 WHERE id=?")->execute([$id]);
    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Restored Patient Account', "Patient: {$patientName}");
    header("Location: patient_account.php");
    exit;
}

if (isset($_POST['hard_delete'])) {
    $id = $_POST['id'];
    $pName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patient_account WHERE id=?");
    $pName->execute([$id]);
    $patientName = $pName->fetchColumn() ?: 'ID:'.$id;
    $stmt = $pdo->prepare("DELETE FROM patient_account WHERE id=?");
    $stmt->execute([$id]);
    log_audit($pdo, $_SESSION['user_type'] ?? 'admin', $_SESSION['username'] ?? 'Unknown', 'Permanently Deleted Patient Account', "Patient: {$patientName}");
    header("Location: patient_account.php");
    exit;
}

include __DIR__ . '/../miscellaneous/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Accounts</title>
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <link rel="stylesheet" href="patient_account_design.css?v=<?= time(); ?>">
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
      .topbar {
        left: 0 !important;
        width: 100% !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 0 10px !important;
        gap: 8px !important;
      }
      .topbar-left {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        overflow: hidden !important;
        flex: 1 !important;
        min-width: 0 !important;
      }
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
        max-width: 130px !important;
      }
      .topbar div:last-child, 
      .topbar span {
        font-size: 11px !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
      }
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
    <h2>Patient Accounts</h2>
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
      <button type="button" class="btn-add" onclick="showTab('active_accounts')">Active Accounts</button>
      <button type="button" class="btn-add" onclick="showTab('deleted_accounts')" style="background-color: #64748b;">Deleted Accounts</button>
      <button type="button" class="btn-add" style="margin-left: auto;" onclick="document.getElementById('addModal').style.display='flex'">Add Patient</button>
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
              <th>Birthday</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Gmail</th>
              <th>Phone Number</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($active_patients)): ?>
              <tr><td colspan="11" style="text-align:center;">No active patients account found.</td></tr>
            <?php endif; ?>

            <?php foreach ($active_patients as $index => $row): ?>
            <tr>
              <td data-label="#"><?= $index + 1 ?></td>
              <td data-label="First Name"><?= htmlspecialchars($row['first_name']) ?></td>
              <td data-label="Middle Name"><?= htmlspecialchars($row['middle_name'] ?? '') ?></td>
              <td data-label="Last Name"><?= htmlspecialchars($row['last_name']) ?></td>
              <td data-label="Birthday"><?= htmlspecialchars($row['birthday']) ?></td>
              <td data-label="Age"><?= htmlspecialchars($row['age']) ?></td>
              <td data-label="Gender"><?= htmlspecialchars($row['gender']) ?></td>
              <td data-label="Gmail"><?= htmlspecialchars($row['gmail']) ?></td>
              <td data-label="Phone Number"><?= htmlspecialchars($row['phone_number']) ?></td>
              <td data-label="Created"><?= htmlspecialchars($row['created_at']) ?></td>
              <td data-label="Actions">
                <button class="btn-edit" onclick='openEdit(<?= json_encode($row) ?>)'>Edit</button>
                <form method="post" style="display:inline;" id="patientDeleteForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="delete" value="1">
                  <button type="button" class="btn-delete" onclick="showConfirmDialog('Move this account to deleted accounts?', function(){ document.getElementById('patientDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Delete Account', icon: 'delete', danger: true, okText: 'Delete' })">Delete</button>
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
              <th>First Name</th>
              <th>Middle Name</th>
              <th>Last Name</th>
              <th>Birthday</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Gmail</th>
              <th>Phone Number</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($deleted_patients)): ?>
              <tr><td colspan="11" style="text-align:center;">No deleted patients account found.</td></tr>
            <?php endif; ?>

            <?php foreach ($deleted_patients as $index => $row): ?>
            <tr>
              <td data-label="#"><?= $index + 1 ?></td>
              <td data-label="First Name"><?= htmlspecialchars($row['first_name']) ?></td>
              <td data-label="Middle Name"><?= htmlspecialchars($row['middle_name'] ?? '') ?></td>
              <td data-label="Last Name"><?= htmlspecialchars($row['last_name']) ?></td>
              <td data-label="Birthday"><?= htmlspecialchars($row['birthday']) ?></td>
              <td data-label="Age"><?= htmlspecialchars($row['age']) ?></td>
              <td data-label="Gender"><?= htmlspecialchars($row['gender']) ?></td>
              <td data-label="Gmail"><?= htmlspecialchars($row['gmail']) ?></td>
              <td data-label="Phone Number"><?= htmlspecialchars($row['phone_number']) ?></td>
              <td data-label="Created"><?= htmlspecialchars($row['created_at']) ?></td>
              <td data-label="Actions">
                <form method="post" style="display:inline;" id="patientRestoreForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="restore" value="1">
                  <button type="button" class="btn-manage" style="background-color: #f59e0b; border:none; padding:5px 10px; border-radius:4px; color:white; cursor:pointer;" onclick="showConfirmDialog('Restore this account?', function(){ document.getElementById('patientRestoreForm_<?= $row['id'] ?>').submit(); }, { title: 'Restore Account', icon: 'restore', okText: 'Restore' })">Restore</button>
                </form>
                <form method="post" style="display:inline;" id="patientHardDeleteForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="hard_delete" value="1">
                  <button type="button" class="btn-delete" onclick="showConfirmDialog('Permanently delete this account? This action cannot be undone.', function(){ document.getElementById('patientHardDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Permanent Delete', icon: 'danger', danger: true, okText: 'Delete Forever' })">Perm. Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<!-- Add Modal -->
<div id="addModal" class="modal" style="<?= ($reopen_modal === 'addModal') ? 'display:flex;' : 'display:none;' ?>">
  <form method="post" class="modal-content">
    <h3>Add Patient</h3>

    <label>First Name:</label>
    <input type="text" name="first_name" placeholder="First Name" maxlength="50" value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>" required>
    
    <label>Middle Name (Optional):</label>
    <input type="text" name="middle_name" placeholder="Middle Name" maxlength="50" value="<?= isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : '' ?>">
    
    <label>Last Name:</label>
    <input type="text" name="last_name" placeholder="Last Name" maxlength="50" value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>" required>
    
    <label>Birth Date:</label>
    <input name="birthday" type="date" max="<?= date('Y-m-d') ?>" value="<?= isset($_POST['birthday']) ? htmlspecialchars($_POST['birthday']) : '' ?>" required onchange="validateBirthday(this, 'ageAdd')">
    
    <label>Age:</label>
    <input name="age" id="ageAdd" type="text" inputmode="numeric" placeholder="Age" value="<?= isset($_POST['birthday']) ? computeAge($_POST['birthday']) : '' ?>" readonly>
    
    <label>Gender:</label>
    <select name="gender" required>
      <option <?= (isset($_POST['gender']) && $_POST['gender'] === 'MALE') ? 'selected' : '' ?>>MALE</option>
      <option <?= (isset($_POST['gender']) && $_POST['gender'] === 'FEMALE') ? 'selected' : '' ?>>FEMALE</option>
    </select>
    
    <label>Gmail:</label>
    <input name="gmail" type="email" placeholder="Gmail" maxlength="320" value="<?= isset($_POST['gmail']) ? htmlspecialchars($_POST['gmail']) : '' ?>" required>
    
    <label>Phone Number:</label>
    <input type="tel" name="phone_number" pattern="^09\d{9}$" placeholder="Phone Number" value="<?= isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '' ?>" required>
    
    <label>Password:</label>
    <div class="password-wrapper">
      <input type="password" placeholder="Password" id="addPassword" name="password" required>
      <span class="toggle-password" onclick="togglePassword(this, 'addPassword')">SHOW</span>
    </div>
    
    <label>Confirm Password:</label>
    <div class="password-wrapper">
      <input type="password" placeholder="Confirm Password" id="addConfirmPassword" name="confirm_password" required>
      <span class="toggle-password" onclick="togglePassword(this, 'addConfirmPassword')">SHOW</span>
    </div>
    
    <button name="add">Save</button>
    <button type="button" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
  </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal" style="<?= ($reopen_modal === 'editModal') ? 'display:flex;' : 'display:none;' ?>">
  <form method="post" class="modal-content">
    <h3>Edit Patient</h3>

    <input type="hidden" name="id" id="editId" value="<?= isset($_POST['id']) ? htmlspecialchars($_POST['id']) : '' ?>">
    
    <label>First Name:</label>
    <input type="text" name="first_name" id="editFirst" maxlength="50" required value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>">
    
    <label>Middle Name (Optional):</label>
    <input type="text" name="middle_name" id="editMiddle" maxlength="50" value="<?= isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : '' ?>">
    
    <label>Last Name:</label>
    <input type="text" name="last_name" id="editLast" maxlength="50" required value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>">
    
    <label>Birth Date:</label>
    <input name="birthday" type="date" id="editBirthday" max="<?= date('Y-m-d') ?>" required onchange="validateBirthday(this, 'ageEdit')" value="<?= isset($_POST['birthday']) ? htmlspecialchars($_POST['birthday']) : '' ?>">
    
    <label>Age:</label>
    <input name="age" id="ageEdit" type="text" inputmode="numeric" readonly value="<?= isset($_POST['birthday']) ? computeAge($_POST['birthday']) : '' ?>">
    
    <label>Gender:</label>
    <select name="gender" id="editGender" required>
      <option <?= (isset($_POST['gender']) && $_POST['gender'] === 'MALE') ? 'selected' : '' ?>>MALE</option>
      <option <?= (isset($_POST['gender']) && $_POST['gender'] === 'FEMALE') ? 'selected' : '' ?>>FEMALE</option>
    </select>
    
    <label>Gmail:</label>
    <input name="gmail" type="email" id="editGmail" maxlength="320" required value="<?= isset($_POST['gmail']) ? htmlspecialchars($_POST['gmail']) : '' ?>">
    
    <label>Phone Number:</label>
    <input type="tel" name="phone_number" id="editPhone" pattern="^09\d{9}$" placeholder="Phone Number" required value="<?= isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '' ?>">
    
    <button name="edit">Update</button>
    <button type="button" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
  </form>
</div>

<!-- Notice / Error Dialog -->
<?php if (!empty($error_message)): ?>
<div id="noticeModal" style="
    display: flex;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.50);
    z-index: 999999;
    justify-content: center;
    align-items: center;
">
  <div style="
      background: #ffffff;
      border-radius: 16px;
      padding: 36px 32px 28px;
      max-width: 420px;
      width: 90%;
      text-align: center;
      box-shadow: 0 24px 64px rgba(0,0,0,0.22);
      animation: confirmPopIn 0.22s cubic-bezier(0.34,1.56,0.64,1);
  ">
    <div style="font-size: 2.4rem; margin-bottom: 12px; line-height: 1;">
      <i class="fa-solid fa-circle-exclamation" style="color: #ef4444;"></i>
    </div>
    <h3 style="color: #ef4444; margin: 0 0 10px; font-size: 1.15rem; font-weight: 700;">Notice</h3>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 16px;">
    <p style="color: #475569; font-size: 0.95rem; margin: 0 0 24px; line-height: 1.55;">
      <?= htmlspecialchars($error_message) ?>
    </p>
    <button type="button"
      onclick="document.getElementById('noticeModal').style.display='none'"
      style="padding: 10px 32px; background-color: #0ea5e9; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600;">
      OK
    </button>
  </div>
</div>
<?php endif; ?>

<script>
function computeAge(birthday) {
    const birthDate = new Date(birthday);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
    return age;
}

function validateBirthday(input, ageInputId) {
    const dateValue = input.value;
    const ageField = document.getElementById(ageInputId);

    if (!dateValue) {
        ageField.value = '';
        return;
    }

    const parts = dateValue.split('-');
    if (parts.length === 3) {
        const year = parseInt(parts[0], 10);
        if (year < 1000 || year > 9999 || parts[0].length !== 4) {
            showAlert("Please enter a valid 4-digit year.");
            input.value = '';
            ageField.value = '';
            return;
        }
    }

    const age = computeAge(dateValue);
    if (age < 0 || age >= 150) {
        showAlert("Invalid birthday. Age must be between 0 and 149.");
        input.value = '';
        ageField.value = '';
        return;
    }

    ageField.value = age;
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

function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(div => {
        div.style.display = 'none';
    });
    document.getElementById(tabId).style.display = 'block';
}

function openEdit(data) {
    // Kung walang naganap na error POST, i-load ang data mula sa table row
    <?php if (empty($error_message) || $reopen_modal !== 'editModal'): ?>
    document.getElementById('editId').value = data.id;
    document.getElementById('editFirst').value = data.first_name;
    document.getElementById('editMiddle').value = data.middle_name || '';
    document.getElementById('editLast').value = data.last_name;
    document.getElementById('editBirthday').value = data.birthday;
    document.getElementById('ageEdit').value = computeAge(data.birthday);
    document.getElementById('editGender').value = data.gender;
    document.getElementById('editGmail').value = data.gmail;
    document.getElementById('editPhone').value = data.phone_number;
    <?php endif; ?>
    document.getElementById('editModal').style.display = 'flex';
}

function enforcePhoneLimit(input) {
  input.value = input.value.replace(/\D/g, "");
  if (input.value.length > 11) {
    input.value = input.value.slice(0, 11);
  }
}

document.addEventListener("input", function (e) {
    const upperFields = [
        "first_name",
        "middle_name",
        "last_name",
        "editFirst",
        "editMiddle",
        "editLast"
    ];

    if (upperFields.includes(e.target.name) || upperFields.includes(e.target.id)) {
        e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '').toUpperCase();
    }

    if (e.target.name === "phone_number") {
        enforcePhoneLimit(e.target);
    }

    if (e.target.name === "gmail" || e.target.id === "editGmail") {
        if (e.target.value.includes('@')) {
            let parts = e.target.value.split('@');
            if (parts[0].length > 64) parts[0] = parts[0].substring(0, 64);
            if (parts.length > 1) {
                let domain = parts.slice(1).join('@');
                if (domain.length > 255) domain = domain.substring(0, 255);
                e.target.value = parts[0] + '@' + domain;
            }
        }
    }
});
</script>
</body>
</html>

