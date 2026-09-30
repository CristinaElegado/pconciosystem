<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

// Time slots disabled for now
header("Location: ../main_page/pconcio_main.php");
exit;

$time_slots = $pdo->query("SELECT * FROM time_slots ORDER BY STR_TO_DATE(time_slot, '%l:%i %p') ASC")->fetchAll(PDO::FETCH_ASSOC);

function generateTimeOptions() {
  $times = [];
  $start = strtotime('1:00 AM');
  $end = strtotime('11:00 PM');

  while ($start <= $end) {
    $times[] = date('g:i A', $start);
    $start = strtotime('+30 minutes', $start);
  }
  return $times;
}
$timeOptions = generateTimeOptions();

if (isset($_POST['add'])) {
  $time_slot = trim($_POST['time_slot']);
  $is_active = 1;

  $check = $pdo->prepare("SELECT COUNT(*) FROM time_slots WHERE time_slot = ?");
  $check->execute([$time_slot]);
  if ($check->fetchColumn() > 0) {
    echo "<script>alert('Duplicate time slot already exists!'); window.location='time_slots.php';</script>";
    exit;
  }

  $stmt = $pdo->prepare("INSERT INTO time_slots (time_slot, is_active) VALUES (?, ?)");
  $stmt->execute([$time_slot, $is_active]);
  header("Location: time_slots.php");
  exit;
}

if (isset($_POST['edit'])) {
  $time_slot = trim($_POST['time_slot']);
  $is_active = $_POST['is_active'];
  $id = $_POST['id'];

  $check = $pdo->prepare("SELECT COUNT(*) FROM time_slots WHERE time_slot = ? AND id != ?");
  $check->execute([$time_slot, $id]);
  if ($check->fetchColumn() > 0) {
    echo "<script>alert('Duplicate time slot already exists!'); window.location='time_slots.php';</script>";
    exit;
  }

  $stmt = $pdo->prepare("UPDATE time_slots SET time_slot=?, is_active=? WHERE id=?");
  $stmt->execute([$time_slot, $is_active, $id]);
  header("Location: time_slots.php");
  exit;
}

if (isset($_POST['delete'])) {
  $stmt = $pdo->prepare("DELETE FROM time_slots WHERE id=?");
  $stmt->execute([$_POST['id']]);
  header("Location: time_slots.php");
  exit;
}

$existing_times = array_column($time_slots, 'time_slot');

include __DIR__ . '/../miscellaneous/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Time Slots</title>
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <link rel="stylesheet" href="time_slots_design.css">
  <style>
    /* Red color for delete buttons */
    .btn-delete {
      background-color: #dc3545 !important;
      color: white !important;
      border: none !important;
    }
    .btn-delete:hover {
      background-color: #c82333 !important;
    }
  </style>
</head>
<body>
  <div class="main-content">
    <h2>Time Slots</h2>
    <button class="btn-add" onclick="document.getElementById('addModal').style.display='flex'">Add Time Slot</button>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Time Slot</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
    <?php if (empty($time_slots)): ?>
      <tr><td colspan="8" style="text-align:center;">No time slot found.</td></tr>
    <?php endif; ?>
        
          <?php foreach ($time_slots as $index => $row): ?>
          <tr>
            <td data-label="#"><?= $index + 1 ?></td>
            <td data-label="Time Slot"><?= htmlspecialchars($row['time_slot']) ?></td>
            <td data-label="Status"><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td data-label="Actions">
              <button class="btn-edit" onclick='openEdit(<?= json_encode($row) ?>)'>Edit</button>
              <form method="post" style="display:inline;" id="tsDeleteForm_<?= $row['id'] ?>">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <input type="hidden" name="delete" value="1">
                <button type="button" class="btn-delete" onclick="showConfirmDialog('Are you sure you want to delete this time slot?', function(){ document.getElementById('tsDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Delete Time Slot', icon: 'delete', danger: true, okText: 'Delete' })">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="addModal" class="modal">
    <form method="post" class="modal-content">
      <h3>Add</h3>
      <label>Time Visit:</label>
      <select name="time_slot" required>
        <option value="">-- Select Time Slot --</option>
        <?php foreach ($timeOptions as $t): ?>
          <?php $disabled = in_array($t, $existing_times) ? 'disabled' : ''; ?>
          <option value="<?= $t ?>" <?= $disabled ?>><?= $t ?><?= $disabled ? ' (Taken)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <button name="add">Save</button>
      <button type="button" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
    </form>
  </div>

  <div id="editModal" class="modal">
    <form method="post" class="modal-content">
      <h3>Edit</h3>
      <input type="hidden" name="id" id="editId">
<label>Time Visit:</label>
      <select name="time_slot" id="editTimeSlot" required>
        <option value="">-- Select Time Slot --</option>
        <?php foreach ($timeOptions as $t): ?>
          <?php $disabled = in_array($t, $existing_times) ? 'disabled' : ''; ?>
          <option value="<?= $t ?>" <?= $disabled ?>><?= $t ?><?= $disabled ? ' (Taken)' : '' ?></option>
        <?php endforeach; ?>
      </select>
<label>Status:</label>
      <select name="is_active" id="editActive" required>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>

      <button name="edit">Update</button>
      <button type="button" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
    </form>
  </div>

  <script>
    function openEdit(data) {
      document.getElementById('editId').value = data.id;
      document.getElementById('editModal').style.display = 'flex';

      const selectTime = document.getElementById('editTimeSlot');

      for (let opt of selectTime.options) {
        if (opt.text.includes('(Taken)')) {
          opt.disabled = true;
        } else {
          opt.disabled = false;
        }
      }

      for (let opt of selectTime.options) {
        if (opt.value === data.time_slot) {
          opt.disabled = false;
          opt.selected = true;
        }
      }

      const selectStatus = document.getElementById('editActive');
      for (let opt of selectStatus.options) {
        opt.selected = (opt.value == data.is_active);
      }
    }
  </script>
</body>
</html>
