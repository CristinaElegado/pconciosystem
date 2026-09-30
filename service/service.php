<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';
include __DIR__ . '/../miscellaneous/log_audit.php';

// Helper: get display name of currently logged-in user
$_audit_user_type = $_SESSION['user_type'] ?? 'unknown';
$_audit_user_name = $_SESSION['username'] ?? 'Unknown User';

$services = $pdo->query("SELECT * FROM services")->fetchAll(PDO::FETCH_ASSOC);
$all_items = $pdo->query("SELECT * FROM item_inventory")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['add_service'])) {
  $service_name = strtoupper(trim($_POST['service_name']));
  $price = $_POST['price'];
  $xray_requirement = $_POST['xray_requirement'] ?? 'none';
  $item_ids = $_POST['item_id'] ?? [];
  $quantities = $_POST['quantity_needed'] ?? [];

  if (count(array_filter($item_ids)) === 0) {
    echo "<script>alert('Please add at least one item for the service.'); window.location='service.php';</script>";
    exit;
  }

  if (!preg_match("/^[A-Z\s]+$/", $service_name)) {
    echo "<script>alert('Service name can only contain letters and spaces.'); window.location='service.php';</script>";
    exit;
  }
  if (strlen($service_name) > 30) {
    echo "<script>alert('Service name must be 30 characters or less.'); window.location='service.php';</script>";
    exit;
  }
  if ($price < 0 || $price > 999999) {
    echo "<script>alert('Invalid price. It must be between 0 and 999,999.'); window.location='service.php';</script>";
    exit;
  }
  foreach ($quantities as $qty) {
    if ((int)$qty < 1 || (int)$qty > 999) {
        echo "<script>alert('Quantity must be between 1 and 999.'); window.location='service.php';</script>";
        exit;
    }
  }

  $check = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_name = ?");
  $check->execute([$service_name]);
  if ($check->fetchColumn() > 0) {
    echo "<script>alert('Service already exists!'); window.location='service.php';</script>";
    exit;
  }

  $stmt = $pdo->prepare("INSERT INTO services (service_name, price, xray_requirement) VALUES (?, ?, ?)");
  $stmt->execute([$service_name, $price, $xray_requirement]);
  $service_id = $pdo->lastInsertId();

  $insertItem = $pdo->prepare("INSERT INTO service_items (service_id, item_id, quantity_needed) VALUES (?, ?, ?)");
  for ($i = 0; $i < count($item_ids); $i++) {
    if (!empty($item_ids[$i]) && !empty($quantities[$i])) {
      $insertItem->execute([$service_id, $item_ids[$i], $quantities[$i]]);
    }
  }

  // Audit log: service added
  log_audit(
    $pdo,
    $_audit_user_type,
    $_audit_user_name,
    'Added Service',
    "Service '{$service_name}' added with price ₱{$price} (X-Ray: {$xray_requirement})."
  );

  header("Location: service.php");
  exit;
}

if (isset($_POST['update_service'])) {
  $id = $_POST['id'];
  $service_name = strtoupper(trim($_POST['service_name']));
  $price = $_POST['price'];
  $status = $_POST['status'];
  $xray_requirement = $_POST['xray_requirement'] ?? 'none';
  $item_ids = $_POST['item_id'] ?? [];
  $quantities = $_POST['quantity_needed'] ?? [];

  if (count(array_filter($item_ids)) === 0) {
    echo "<script>alert('Please add at least one item for the service.'); window.location='service.php';</script>";
    exit;
  }

  if (!preg_match("/^[A-Z\s]+$/", $service_name)) {
    echo "<script>alert('Service name can only contain letters and spaces.'); window.location='service.php';</script>";
    exit;
  }
  if (strlen($service_name) > 30) {
    echo "<script>alert('Service name must be 30 characters or less.'); window.location='service.php';</script>";
    exit;
  }
  if ($price < 0 || $price > 999999) {
    echo "<script>alert('Invalid price. It must be between 0 and 999,999.'); window.location='service.php';</script>";
    exit;
  }
  foreach ($quantities as $qty) {
    if ((int)$qty < 1 || (int)$qty > 999) {
        echo "<script>alert('Quantity must be between 1 and 999.'); window.location='service.php';</script>";
        exit;
    }
  }

  $check = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_name = ? AND id != ?");
  $check->execute([$service_name, $id]);
  if ($check->fetchColumn() > 0) {
    echo "<script>alert('Service name already exists! Please use a different name.'); window.location='service.php';</script>";
    exit;
  }

  // Fetch old values for audit detail
  $old = $pdo->prepare("SELECT service_name, price, status FROM services WHERE id = ?");
  $old->execute([$id]);
  $oldData = $old->fetch(PDO::FETCH_ASSOC);

  $pdo->prepare("UPDATE services SET service_name=?, price=?, status=?, xray_requirement=? WHERE id=?")
      ->execute([$service_name, $price, $status, $xray_requirement, $id]);

  $pdo->prepare("DELETE FROM service_items WHERE service_id=?")->execute([$id]);

  $insertItem = $pdo->prepare("INSERT INTO service_items (service_id, item_id, quantity_needed) VALUES (?, ?, ?)");
  for ($i = 0; $i < count($item_ids); $i++) {
    if (!empty($item_ids[$i]) && !empty($quantities[$i])) {
      $insertItem->execute([$id, $item_ids[$i], $quantities[$i]]);
    }
  }

  // Audit log: service updated
  $oldName  = $oldData['service_name'] ?? '?';
  $oldPrice = $oldData['price'] ?? '?';
  $oldStatus = $oldData['status'] ?? '?';
  log_audit(
    $pdo,
    $_audit_user_type,
    $_audit_user_name,
    'Updated Service',
    "Service ID {$id} updated. Name: '{$oldName}' → '{$service_name}', Price: ₱{$oldPrice} → ₱{$price}, Status: {$oldStatus} → {$status}, X-Ray: {$xray_requirement}."
  );

  header("Location: service.php");
  exit;
}

if (isset($_POST['delete'])) {
  $id = $_POST['id'];

  // Fetch service name before deleting (for audit trail)
  $svcRow = $pdo->prepare("SELECT service_name, price FROM services WHERE id = ?");
  $svcRow->execute([$id]);
  $svcData = $svcRow->fetch(PDO::FETCH_ASSOC);
  $deleted_name  = $svcData['service_name'] ?? "ID {$id}";
  $deleted_price = $svcData['price'] ?? '?';

  $pdo->prepare("DELETE FROM service_items WHERE service_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$id]);

  // Audit log: service deleted
  log_audit(
    $pdo,
    $_audit_user_type,
    $_audit_user_name,
    'Deleted Service',
    "Service '{$deleted_name}' (ID: {$id}, Price: ₱{$deleted_price}) was permanently deleted."
  );

  header("Location: service.php");
  exit;
}
include __DIR__ . '/../miscellaneous/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Service</title>
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <link rel="stylesheet" href="service_design.css">
  <style>
    body {
      background-image: none !important;
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
  <div class="main-content" data-no-tbody-refresh="true">

    <h2>Services</h2>
    <button class="btn-add" style="margin-bottom:15px;" onclick="document.getElementById('addServiceModal').style.display='flex'">Add Service</button>

    <div class="table-wrapper">

      <table>

        <thead>
          <tr>
            <th>#</th>
            <th>Service Name</th>
            <th>Price</th>
            <th>X-Ray Attachment</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>

    <?php if (empty($services)): ?>
      <tr><td colspan="6" style="text-align:center;">No service found.</td></tr>
    <?php endif; ?>
        
          <?php foreach ($services as $index => $service): ?>
            <tr>
              <td data-label="#"><?= $index + 1 ?></td>
              <td data-label="Service Name"><?= htmlspecialchars($service['service_name']) ?></td>
              <td data-label="Price">₱<?= number_format($service['price'], 2) ?></td>
              <td data-label="X-Ray Attachment">
                <?php 
                  $xray = $service['xray_requirement'] ?? 'none';
                  if ($xray === 'required') echo '<span style="color:red; font-weight:bold;">Required</span>';
                  elseif ($xray === 'optional') echo '<span style="color:orange; font-weight:bold;">Optional</span>';
                  else echo '<span style="color:gray;">Not Needed</span>';
                ?>
              </td>
              <td data-label="Status"><?= ucfirst($service['status']) ?></td>
              <td data-label="Actions">
                <button class="btn-view" onclick="toggleItems(<?= $service['id'] ?>)">View Items</button>
                <button class="btn-edit" onclick="openEditModal(<?= $service['id'] ?>)">Edit</button>

                <form method="post" style="display:inline;" id="svcDeleteForm_<?= $service['id'] ?>">
                  <input type="hidden" name="id" value="<?= $service['id'] ?>">
                  <input type="hidden" name="delete" value="1">
                  <button type="button" class="btn-delete" onclick="showConfirmDialog('Delete this service?', function(){ document.getElementById('svcDeleteForm_<?= $service['id'] ?>').submit(); }, { title: 'Delete Service', icon: 'delete', danger: true, okText: 'Delete' })">Delete</button>
                </form>
              </td>
            </tr>

            <tr id="items-<?= $service['id'] ?>" class="expandable">
              <td colspan="6">
                <strong>Items Used:</strong>
                <table style="width:100%; margin-top:8px;">
                  <thead>
                    <tr style="background: linear-gradient(180deg, #0ea5e9, #0284c7)">
                      <th>Item Name</th>
                      <th>Type</th>
                      <th>Quantity Needed</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php
                      $stmt = $pdo->prepare("SELECT i.item_name, i.item_type, si.quantity_needed 
                                            FROM service_items si
                                            JOIN item_inventory i ON si.item_id=i.id
                                            WHERE si.service_id=?");
                      $stmt->execute([$service['id']]);
                      $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                      if (empty($items)) echo "<tr><td colspan='3' style='text-align:center;'>No items added.</td></tr>";
                      else foreach ($items as $it) echo "<tr><td>{$it['item_name']}</td><td>{$it['item_type']}</td><td>{$it['quantity_needed']}</td></tr>";
                    ?>
                  </tbody>
                </table>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>

      </table>
    </div>
  </div>

  <!-- Add Service Modal -->
  <div id="addServiceModal" class="modal">
    <form method="post" class="modal-content" onsubmit="return validateItems(this)">
      <h3>Add Service</h3>
      <label>Service Name:</label>
      <input type="text" name="service_name" placeholder="Service Name" maxlength="30" required>
      
      <label>Price:</label>
      <input type="number" name="price" placeholder="Price" step="0.01" min="0" max="999999" required>

      <label>X-Ray Attachment Option:</label>
      <select name="xray_requirement" required>
        <option value="none">Not Needed (No X-Ray required)</option>
        <option value="optional">Optional (Patient can choose to upload)</option>
        <option value="required">Required (Patient must upload X-Ray)</option>
      </select>

      <div class="items-header">
        <h4>Items Needed:</h4>
        <div class="items-actions">
          <button type="button" class="add-item" onclick="addItemRow(this.form)">Add Item</button>
        </div>
      </div>

      <div id="itemListContainer" class="item-list">
        <div class="item-content">
          <select name="item_id[]" required>
            <option value="">Select Item</option>
            <?php foreach ($all_items as $item): ?>
              <option value="<?= $item['id'] ?>">
                <?= htmlspecialchars($item['item_name']) ?> (<?= $item['item_type'] ?>)
              </option>
            <?php endforeach; ?>
          </select>

          <input type="number" name="quantity_needed[]" placeholder="Quantity" min="1" max="999" required>
          <button type="button" class="remove-item" onclick="removeRow(this)">Remove</button>
        </div>
      </div>

      <button name="add_service">Save Service</button>
      <button type="button" class="btn-cancel" onclick="document.getElementById('addServiceModal').style.display='none'">Cancel</button>
    </form>
  </div>

  <!-- Edit Service Modal -->
  <div id="editServiceModal" class="modal">
    <form method="post" class="modal-content" id="editForm" onsubmit="return validateItems(this)">
      <h3>Edit Service</h3>

      <input type="hidden" name="id" id="edit_id">
      <label>Service Name:</label>
      <input type="text" name="service_name" id="edit_service_name" placeholder="Service Name" maxlength="30" required>
      
      <label>Price:</label>
      <input type="number" name="price" id="edit_price" placeholder="Price" step="0.01" min="0" max="999999" required>

      <label>X-Ray Attachment Option:</label>
      <select name="xray_requirement" id="edit_xray_requirement" required>
        <option value="none">Not Needed (No X-Ray required)</option>
        <option value="optional">Optional (Patient can choose to upload)</option>
        <option value="required">Required (Patient must upload X-Ray)</option>
      </select>

      <label>Status:</label>
      <select name="status" id="edit_status" required>
        <option value="enable">Enable</option>
        <option value="disable">Disable</option>
      </select>

      <div class="items-header">
        <h4>Items Needed:</h4>
        <div class="items-actions">
          <button type="button" class="add-item" onclick="addItemRow('itemContainerEdit')">Add Item</button>
        </div>
      </div>

      <div id="itemContainerEdit" class="item-list">
      </div>

      <button name="update_service">Update Service</button>
      <button type="button" class="btn-cancel" onclick="document.getElementById('editServiceModal').style.display='none'">Cancel</button>
    </form>
  </div>

<script>
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
function toggleItems(id) {
  const allRows = document.querySelectorAll('.expandable');
  allRows.forEach(row => {
    if (row.id !== 'items-' + id) {
      row.classList.remove('row-open');
      row.style.display = 'none';
    }
  });

  const row = document.getElementById('items-' + id);
  if (row.classList.contains('row-open')) {
    row.classList.remove('row-open');
    row.style.display = 'none';
  } else {
    row.classList.add('row-open');
    row.style.display = 'table-row';
  }
}

function addItemRow(containerId) {
  const form = (typeof containerId === 'string')
    ? document.getElementById(containerId).closest('form')
    : containerId.closest('form');

  const itemList = form.querySelector('.item-list') || form;

  const existingRows = itemList.querySelectorAll('select[name="item_id[]"]');
  const referenceRow = existingRows.length > 0
    ? existingRows[0].closest('.item-content')
    : null;

  const newRow = document.createElement('div');
  newRow.className = 'item-content';
  newRow.innerHTML = `
    <select name="item_id[]" required onchange="updateDisabledOptions(this)">
      <option value="">Select Item</option>
      <?php foreach ($all_items as $item): ?>
        <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['item_name']) ?> (<?= $item['item_type'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <input type="number" name="quantity_needed[]" placeholder="Quantity" min="1" max="999" required>
    <button type="button" class="remove-item" onclick="removeRow(this)">Remove</button>
  `;

  if (referenceRow && referenceRow.parentNode) {
    referenceRow.parentNode.insertBefore(newRow, referenceRow);
  } else {
    itemList.appendChild(newRow);
  }

  updateDisabledOptions(newRow);
}

function removeRow(btn){
  const row = btn.parentElement;
  const form = btn.closest('form');
  row.remove();
  if (form) {
    updateDisabledOptions(form);
  }
}

function updateDisabledOptions(element){
  const form = element.closest ? element.closest('form') : element;
  if (!form) return;
  const selects = form.querySelectorAll('select[name="item_id[]"]');
  const selectedValues = Array.from(selects).map(s => s.value).filter(v => v !== '');
  selects.forEach(select => {
    const current = select.value;
    Array.from(select.options).forEach(opt => {
      opt.disabled = false;
      if(opt.value !== '' && opt.value !== current && selectedValues.includes(opt.value)){
        opt.disabled = true;
      }
    });
  });
}

function validateItems(form){
  const selects = form.querySelectorAll('select[name="item_id[]"]');
  if(selects.length === 0){ alert('Please add at least one item.'); return false; }
  for(let s of selects){ if(s.value===''){ alert('Please select an item.'); return false; } }
  return true;
}

function openEditModal(id){
  fetch('get_service.php?id=' + id)
    .then(res => res.json())
    .then(data => {
      document.getElementById('edit_id').value = data.id;
      document.getElementById('edit_service_name').value = data.service_name;
      document.getElementById('edit_price').value = data.price;
      document.getElementById('edit_status').value = data.status || 'enable';
      document.getElementById('edit_xray_requirement').value = data.xray_requirement || 'none';

      const container = document.getElementById('itemContainerEdit');
      container.innerHTML = '';

      if (data.items) {
        data.items.forEach(it => {
          const newRow = document.createElement('div');
          newRow.className = 'item-content';
          newRow.innerHTML = `
            <select name="item_id[]" required onchange="updateDisabledOptions(this)">
              <option value="">Select Item</option>
              <?php foreach ($all_items as $item): ?>
                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['item_name']) ?> (<?= $item['item_type'] ?>)</option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="quantity_needed[]" placeholder="Quantity" min="1" max="999" required value="${it.quantity_needed}">
            <button type="button" class="remove-item" onclick="removeRow(this)">Remove</button>
          `;
          container.appendChild(newRow);
          newRow.querySelector('select').value = it.item_id;
        });
      }

      updateDisabledOptions(container);
      document.getElementById('editServiceModal').style.display = 'flex';
    })
    .catch(err => console.error('Error loading service data:', err));
}

document.addEventListener('input', function(e) {
  if (e.target.name === 'service_name') {
    e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '').toUpperCase();
  }
  if (e.target.name === 'price') {
    e.target.value = e.target.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
    if (e.target.value !== '' && parseFloat(e.target.value) < 0) {
      e.target.value = 0;
    }
    if (e.target.value !== '' && parseFloat(e.target.value) > 999999) {
      e.target.value = 999999;
    }
  }
  if (e.target.name === 'quantity_needed[]') {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
    if (e.target.value !== '' && parseInt(e.target.value) > 999) {
      e.target.value = 999;
    }
  }
});
</script>
</body>
</html>