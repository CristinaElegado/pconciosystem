<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';
include __DIR__ . '/../miscellaneous/log_audit.php';

// ✅ FUNCTION: Generate next Delivery ID for a given date
// Format: DEL-YYYYMMDD-001, DEL-YYYYMMDD-002, ...
function generateDeliveryId($pdo, $delivery_date) {
    $dateStr = date('Ymd', strtotime($delivery_date));
    $prefix  = 'DEL-' . $dateStr . '-';
    $stmt = $pdo->prepare("SELECT delivery_id FROM item_inventory WHERE delivery_id LIKE ? ORDER BY delivery_id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    if ($last) {
        $lastNum = (int) substr($last, strrpos($last, '-') + 1);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// ✅ AJAX: Return next delivery ID preview for a given date
if (isset($_GET['ajax_next_delivery_id']) && isset($_GET['date'])) {
    header('Content-Type: application/json');
    $date = $_GET['date'];
    if (!strtotime($date)) {
        echo json_encode(['id' => '']);
        exit;
    }
    echo json_encode(['id' => generateDeliveryId($pdo, $date)]);
    exit;
}

// âœ… Fetch Staff List (First Name, Middle Name, Last Name) mula sa staff_accounts
$staff_stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) AS full_name 
                            FROM staff_accounts 
                            WHERE is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL) 
                            ORDER BY first_name ASC");
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ ADD ITEM LOGIC
if (isset($_POST['add'])) {
  $item_name = strtoupper(trim(preg_replace('/\s+/', ' ', $_POST['item_name'])));
  $item_type = strtoupper(trim($_POST['item_type']));
  $quantity = (int)$_POST['quantity'];
  $unit = trim($_POST['unit']);
  $price = floatval($_POST['price']);
  $supplier = trim(preg_replace('/\s+/', ' ', $_POST['supplier']));
  $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : NULL;
  $batch_lot_number = trim($_POST['batch_lot_number']);
  $received_by = !empty($_POST['received_by']) ? $_POST['received_by'] : NULL;
  $expiration_date = ($item_type === 'MEDICINE' && !empty($_POST['expiration_date'])) ? $_POST['expiration_date'] : NULL;

  // Auto-generate Delivery ID based on delivery date (or today if no date)
  $id_date = $delivery_date ?? date('Y-m-d');
  $delivery_id = generateDeliveryId($pdo, $id_date);

  // Backend Validations
  if (empty($item_name) || !preg_match("/^(?=.*[A-Z])[A-Z0-9\s]{1,50}$/", $item_name)) {
    echo "<script>alert('Item name must be up to 50 characters, can contain numbers and letters, but cannot be numbers-only or empty.'); window.location='inventory.php';</script>";
    exit;
  }
  if (empty($supplier) || !preg_match("/^[a-zA-Z\s]{1,50}$/", $supplier)) {
    echo "<script>alert('Supplier must only contain letters, cannot be empty or spaces only, and up to 50 characters.'); window.location='inventory.php';</script>";
    exit;
  }
  if (!preg_match("/^\d{0,50}$/", $batch_lot_number)) {
    echo "<script>alert('Batch/Lot Number must be numbers only and up to 50 characters.'); window.location='inventory.php';</script>";
    exit;
  }
  if ($quantity < 0 || $price < 0) {
    echo "<script>alert('Quantity and price cannot be negative.'); window.location='inventory.php';</script>";
    exit;
  }

  $check = $pdo->prepare("SELECT COUNT(*) FROM item_inventory WHERE item_name = ? AND item_type = ?");
  $check->execute([$item_name, $item_type]);
  if ($check->fetchColumn() > 0) {
    echo "<script>alert('Duplicate item already exists!'); window.location='inventory.php';</script>";
    exit;
  }

  $stmt = $pdo->prepare("INSERT INTO item_inventory (item_name, item_type, quantity, unit, price, delivery_id, supplier, delivery_date, batch_lot_number, received_by, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([$item_name, $item_type, $quantity, $unit, $price, $delivery_id, $supplier, $delivery_date, $batch_lot_number, $received_by, $expiration_date]);
  log_audit($pdo, $_SESSION['user_type'] ?? 'staff', $_SESSION['username'] ?? 'Unknown', 'Added Inventory Item', "Item: {$item_name} | Type: {$item_type} | Qty: {$quantity} | Delivery ID: {$delivery_id}");
  header("Location: inventory.php");
  exit;
}

// ✅ EDIT ITEM LOGIC
if (isset($_POST['edit'])) {
  $item_name = strtoupper(trim(preg_replace('/\s+/', ' ', $_POST['item_name'])));
  $item_type = strtoupper(trim($_POST['item_type']));
  $quantity = (int)$_POST['quantity'];
  $unit = trim($_POST['unit']);
  $price = floatval($_POST['price']);
  $id = $_POST['id'];
  $delivery_id = trim($_POST['delivery_id']);
  $supplier = trim(preg_replace('/\s+/', ' ', $_POST['supplier']));
  $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : NULL;
  $batch_lot_number = trim($_POST['batch_lot_number']);
  $received_by = !empty($_POST['received_by']) ? $_POST['received_by'] : NULL;
  $expiration_date = ($item_type === 'MEDICINE' && !empty($_POST['expiration_date'])) ? $_POST['expiration_date'] : NULL;

  // Backend Validations
  if (empty($item_name) || !preg_match("/^(?=.*[A-Z])[A-Z0-9\s]{1,50}$/", $item_name)) {
    echo "<script>alert('Item name must be up to 50 characters, can contain numbers and letters, but cannot be numbers-only or empty.'); window.location='inventory.php';</script>";
    exit;
  }
  // Accept both legacy numeric IDs and new DEL-YYYYMMDD-XXX format
  if (!preg_match("/^(\d{1,50}|DEL-\d{8}-\d{3})$/", $delivery_id)) {
    echo "<script>alert('Invalid Delivery ID format.'); window.location='inventory.php';</script>";
    exit;
  }
  if (empty($supplier) || !preg_match("/^[a-zA-Z\s]{1,50}$/", $supplier)) {
    echo "<script>alert('Supplier must only contain letters, cannot be empty or spaces only, and up to 50 characters.'); window.location='inventory.php';</script>";
    exit;
  }
  if (!preg_match("/^\d{0,50}$/", $batch_lot_number)) {
    echo "<script>alert('Batch/Lot Number must be numbers only and up to 50 characters.'); window.location='inventory.php';</script>";
    exit;
  }
  if ($quantity < 0 || $price < 0) {
    echo "<script>alert('Quantity and price cannot be negative.'); window.location='inventory.php';</script>";
    exit;
  }

  $stmt = $pdo->prepare("UPDATE item_inventory SET item_name=?, item_type=?, quantity=?, unit=?, price=?, delivery_id=?, supplier=?, delivery_date=?, batch_lot_number=?, received_by=?, expiration_date=? WHERE id=?");
  $stmt->execute([$item_name, $item_type, $quantity, $unit, $price, $delivery_id, $supplier, $delivery_date, $batch_lot_number, $received_by, $expiration_date, $id]);
  log_audit($pdo, $_SESSION['user_type'] ?? 'staff', $_SESSION['username'] ?? 'Unknown', 'Updated Inventory Item', "Item: {$item_name} | Type: {$item_type} | Qty: {$quantity}");
  header("Location: inventory.php");
  exit;
}

if (isset($_POST['delete'])) {
  // Fetch name before deleting
  $fetchItem = $pdo->prepare("SELECT item_name FROM item_inventory WHERE id=?");
  $fetchItem->execute([$_POST['id']]);
  $deletedItem = $fetchItem->fetchColumn() ?: 'ID:'.$_POST['id'];
  $stmt = $pdo->prepare("DELETE FROM item_inventory WHERE id=?");
  $stmt->execute([$_POST['id']]);
  log_audit($pdo, $_SESSION['user_type'] ?? 'staff', $_SESSION['username'] ?? 'Unknown', 'Deleted Inventory Item', "Item: {$deletedItem}");
  header("Location: inventory.php");
  exit;
}

include __DIR__ . '/../miscellaneous/sidebar.php';

$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

// âœ… Filters
$search = $_GET['q'] ?? "";
$type_filter = $_GET['type'] ?? "ALL";
$stock_filter = $_GET['stock'] ?? "ALL";

// âœ… Base query with JOIN for staff full name
$query = "FROM item_inventory i 
          LEFT JOIN staff_accounts s ON i.received_by = s.id 
          WHERE 1";
$params = [];

// âœ… Search filter
if (!empty($search)) {
    $query .= " AND i.item_name LIKE ?";
    $params[] = "%$search%";
}

// âœ… Type filter
if ($type_filter !== "ALL") {
    $query .= " AND i.item_type = ?";
    $params[] = $type_filter;
}

// âœ… Stock filter
if ($stock_filter === "zero") {
    $query .= " AND i.quantity = 0";
} elseif ($stock_filter === "low") {
    $query .= " AND i.quantity <= 20 AND i.quantity > 0";
}

// âœ… Count total rows
$stmt = $pdo->prepare("SELECT COUNT(*) " . $query);
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();

// âœ… Compute total pages
$totalPages = max(1, ceil($totalRows / $rowsPerPage));

// âœ… Fetch paginated results
$stmt = $pdo->prepare("SELECT i.*, CONCAT(s.first_name, ' ', IFNULL(s.middle_name, ''), ' ', s.last_name) AS receiver_name " . $query . " ORDER BY i.id DESC LIMIT $rowsPerPage OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// âœ… Helper to keep filters when switching pages
function buildQueryPreserve($extra = []) {
    $base = $_GET;
    foreach ($extra as $k => $v) {
        $base[$k] = $v;
    }
    return http_build_query($base);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory</title>
  <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
  <link rel="stylesheet" href="inventory_design.css">
  <style>
  body {
      background-image: none !important;
    }
    .btn-delete, .btn-cancel {
      background-color: #0ea5e9 !important;
      color: white !important;
      border: none !important;
    }
    .btn-delete:hover, .btn-cancel:hover {
      background-color: #0284c7 !important;
    }
    .exp-field {
      display: none; /* Hidden by default */
    }
    @media (max-width: 900px) {
        .prescription-panel { right: 10px; width: 220px; top: 70px; }
        .prescription-toggle { right: 10px; top: 40px; }
    }
@media (max-width: 768px) {
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

  .sidebar.active {
    transform: translateX(0) !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
  }

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

  .main-content {
    margin-top: 65px !important;
    margin-left: 0 !important;
    width: 100% !important;
    padding: 8px !important;
  }
}
  </style>
</head>
<body>

  <div class="main-content">
    <h2>Inventory</h2>
    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:15px;">
      <button class="btn-add" onclick="openAddModal()">Add Item</button>

      <form method="get" id="invFilterForm" style="display:contents;">
        <input type="search"
               name="q"
               placeholder="Search item"
               value="<?= htmlspecialchars($search) ?>"
               style="height:32px !important; padding:0 10px !important; border:1px solid #cbd5e1 !important; border-radius:6px !important; font-size:13px !important; box-sizing:border-box !important; width:180px !important; margin-bottom:0 !important;"
               oninput="invDebounce()">

        <select name="type"
                style="height:32px !important; padding:0 10px !important; border:1px solid #cbd5e1 !important; border-radius:6px !important; font-size:13px !important; box-sizing:border-box !important; width:140px !important; margin-bottom:0 !important; background:#fff !important; color:#000 !important;"
                onchange="document.getElementById('invFilterForm').submit()">
          <option value="ALL">All Types</option>
          <option value="Medicine" <?= $type_filter === 'Medicine' ? 'selected' : '' ?>>Medicine</option>
          <option value="Supply"   <?= $type_filter === 'Supply'   ? 'selected' : '' ?>>Supply</option>
        </select>
      </form>
    </div>

    <div class="table-wrapper">

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Delivery ID</th>
            <th>Supplier</th>
            <th>Delivery Date</th>
            <th>Batch / Lot No.</th>
            <th>Name</th>
            <th>Type</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Price</th>
            <th>Expiration Date</th>
            <th>Received By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>

          <?php if (empty($items)): ?>
            <tr><td colspan="13" style="text-align:center;">No item found.</td></tr>
          <?php endif; ?>

          <?php foreach ($items as $index => $row): ?>
          <tr>
            <td data-label="Row"><?= $offset + $index + 1 ?></td>
            <td data-label="Delivery ID"><?= htmlspecialchars($row['delivery_id'] ?? '-') ?></td>
            <td data-label="Supplier"><?= htmlspecialchars($row['supplier'] ?? '-') ?></td>
            <td data-label="Delivery Date"><?= (!empty($row['delivery_date'])) ? date("M d, Y", strtotime($row['delivery_date'])) : '-' ?></td>
            <td data-label="Batch Lot No."><?= htmlspecialchars($row['batch_lot_number'] ?? '-') ?></td>
            <td data-label="Name"><?= htmlspecialchars($row['item_name']) ?></td>
            <td data-label="Type"><?= htmlspecialchars($row['item_type']) ?></td>
            <td data-label="Qty"><?= $row['quantity'] ?></td>
            <td data-label="Unit"><?= htmlspecialchars($row['unit'] ?? 'pcs') ?></td>
            <td data-label="Price">₱<?= number_format(isset($row['price']) && $row['price'] !== null ? $row['price'] : 0, 2) ?></td>
            <td data-label="Expiration">
              <?= (!empty($row['expiration_date'])) ? date("M d, Y", strtotime($row['expiration_date'])) : '-' ?>
            </td>
            <td data-label="Received By"><?= htmlspecialchars($row['receiver_name'] ?? '-') ?></td>
            <td data-label="Actions">
              <div style="display:flex; flex-direction:column; gap:6px; width:74px;">
                <button class="btn-edit" style="width:100% !important;" onclick='openEdit(<?= json_encode($row) ?>)'>Edit</button>
                <form method="post" id="invDeleteForm_<?= $row['id'] ?>">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="delete" value="1">
                  <button type="button" class="btn-delete" style="width:100% !important;" onclick="showConfirmDialog('Are you sure you want to delete this item?', function(){ document.getElementById('invDeleteForm_<?= $row['id'] ?>').submit(); }, { title: 'Delete Item', icon: 'delete', danger: true, okText: 'Delete' })">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <?php 
        $prevPage = max(1, $page-1); 
        $nextPage = min($totalPages, $page+1);
      ?>
      
      <a href="inventory.php?<?= buildQueryPreserve(['page'=>$prevPage]) ?>" class="page-link">&laquo; Prev</a>
      <span class="current-page"><?= $page ?></span>
      <a href="inventory.php?<?= buildQueryPreserve(['page'=>$nextPage]) ?>" class="page-link">Next &raquo;</a>
      
      <div class="pagination-info">
        Page <?= $page ?> of <?= $totalPages ?> â€” <?= $totalRows ?> total
      </div>
    </div>
  </div>

  <!-- ADD MODAL -->
  <div id="addModal" class="modal">
    <form method="post" class="modal-content">
      <h3>Add Item</h3>

      <label>Delivery ID: <small style="color:#64748b; font-weight:normal;">(auto-generated)</small></label>
      <input type="text" id="addDeliveryIdDisplay" value="Will be generated after selecting a delivery date"
             style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; border-radius:6px; padding:8px; width:100%; box-sizing:border-box; font-style:italic;"
             readonly disabled>

      <label>Supplier:</label>
      <input type="text" name="supplier" class="val-supplier" placeholder="Supplier Name" maxlength="50" required>

      <label>Delivery Date:</label>
      <input type="date" name="delivery_date" id="addDeliveryDate" onchange="fetchNextDeliveryId(this.value)">

      <label>Batch / Lot Number:</label>
      <input type="text" name="batch_lot_number" class="val-batch" placeholder="Batch or Lot No." maxlength="50">

      <label>Item Name:</label>
      <input type="text" name="item_name" class="val-item-name" placeholder="Name" maxlength="50" required>
      
      <label>Item Type:</label>
      <select name="item_type" id="addType" onchange="toggleExpiration('addType', 'addExpGroup', 'addExpInput')" required>
        <option value="Medicine">Medicine</option>
        <option value="Supply">Supply</option>
      </select>
      
      <label>Quantity:</label>
      <input name="quantity" type="number" class="val-qty" placeholder="Quantity" min="0" onkeydown="if(event.key==='-'||event.key==='e'||event.key==='.')event.preventDefault();" required>

      <label>Unit:</label>
      <select name="unit" required>
        <option value="pcs">pcs</option>
        <option value="box">box</option>
        <option value="bottle">bottle</option>
        <option value="pack">pack</option>
        <option value="tube">tube</option>
        <option value="set">set</option>
      </select>
      
      <label>Price (₱):</label>
      <input name="price" type="number" step="0.01" min="0" placeholder="Price" onkeydown="if(event.key==='-'||event.key==='e')event.preventDefault();" required>

      <label>Expiration Date:</label>
      <input type="date" name="expiration_date" id="addExpInput">

      <label>Received By (Staff):</label>
      <select name="received_by">
        <option value="">-- Select Staff --</option>
        <?php foreach ($staff_list as $staff): ?>
            <option value="<?= $staff['id'] ?>"><?= htmlspecialchars(trim($staff['full_name'])) ?></option>
        <?php endforeach; ?>
      </select>

      <br><br>
      <button name="add">Save</button>
      <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
    </form>
  </div>

  <!-- EDIT MODAL -->
  <div id="editModal" class="modal">
    <form method="post" class="modal-content">
      <h3>Edit Item</h3>
      <input type="hidden" name="id" id="editId">

      <label>Delivery ID:</label>
      <input type="text" name="delivery_id" id="editDeliveryId" class="val-delivery-id" placeholder="Delivery ID" maxlength="50" required>

      <label>Supplier:</label>
      <input type="text" name="supplier" id="editSupplier" class="val-supplier" placeholder="Supplier Name" maxlength="50" required>

      <label>Delivery Date:</label>
      <input type="date" name="delivery_date" id="editDeliveryDate">

      <label>Batch / Lot Number:</label>
      <input type="text" name="batch_lot_number" id="editBatchLotNumber" class="val-batch" placeholder="Batch or Lot No." maxlength="50">
      
      <label>Item Name:</label>
      <input type="text" name="item_name" id="editName" class="val-item-name" maxlength="50" required>
      
      <label>Item Type:</label>
      <select name="item_type" id="editType" onchange="toggleExpiration('editType', 'editExpGroup', 'editExpInput')" required>
        <option value="Medicine">Medicine</option>
        <option value="Supply">Supply</option>
      </select>
      
      <label>Quantity:</label>
      <input name="quantity" type="number" id="editQty" class="val-qty" min="0" onkeydown="if(event.key==='-'||event.key==='e'||event.key==='.')event.preventDefault();" required>

      <label>Unit:</label>
      <select name="unit" id="editUnit" required>
        <option value="pcs">pcs</option>
        <option value="box">box</option>
        <option value="bottle">bottle</option>
        <option value="pack">pack</option>
        <option value="tube">tube</option>
        <option value="set">set</option>
      </select>
      
      <label>Price (₱):</label>
      <input name="price" type="number" step="0.01" id="editPrice" min="0" onkeydown="if(event.key==='-'||event.key==='e')event.preventDefault();" required>

      <label>Expiration Date:</label>
      <input type="date" name="expiration_date" id="editExpInput">

      <label>Received By (Staff):</label>
      <select name="received_by" id="editReceivedBy">
        <option value="">-- Select Staff --</option>
        <?php foreach ($staff_list as $staff): ?>
            <option value="<?= $staff['id'] ?>"><?= htmlspecialchars(trim($staff['full_name'])) ?></option>
        <?php endforeach; ?>
      </select>

      <br><br>
      <button name="edit">Update</button>
      <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
    </form>
  </div>

<script>
  // Auto-submit search after user stops typing (300ms debounce)
  let _invSearchTimer = null;
  function invDebounce() {
      clearTimeout(_invSearchTimer);
      _invSearchTimer = setTimeout(function() {
          document.getElementById('invFilterForm').submit();
      }, 300);
  }

  function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar') || document.querySelector('#sidebar') || document.querySelector('aside');
    if (sidebar) {
        sidebar.classList.toggle('active');
        if (sidebar.classList.contains('active')) {
            sidebar.style.display = 'block';
        }
    }
  }

  // Fetch next auto-generated Delivery ID from server based on selected date
  function fetchNextDeliveryId(dateVal) {
      const display = document.getElementById('addDeliveryIdDisplay');
      if (!dateVal) {
          display.value = 'Will be generated after selecting a delivery date';
          return;
      }
      display.value = 'Generating...';
      fetch('inventory.php?ajax_next_delivery_id=1&date=' + encodeURIComponent(dateVal))
          .then(r => r.json())
          .then(data => {
              display.value = data.id || 'Error generating ID';
          })
          .catch(() => {
              display.value = 'Error generating ID';
          });
  }

  // Real-time Input Validation Scripts
  document.addEventListener('input', function(e) {
    // 1. Item Name: Allow letters, numbers, and spaces, max 50 chars (bawal special characters)
    if (e.target.classList.contains('val-item-name')) {
      e.target.value = e.target.value.replace(/[^a-zA-Z0-9\s]/g, '').replace(/\s+/g, ' ').slice(0, 50).toUpperCase();
    }

    // 2. Supplier: Letters and spaces only, max 50 length
    if (e.target.classList.contains('val-supplier')) {
      e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '').replace(/\s+/g, ' ').slice(0, 50);
    }

    // 3. Batch / Lot Number: Numbers only, max 50 length
    if (e.target.classList.contains('val-batch')) {
      e.target.value = e.target.value.replace(/\D/g, '').slice(0, 50);
    }

    // 4. Quantity: Prevent negative numbers or decimals
    if (e.target.classList.contains('val-qty')) {
      e.target.value = e.target.value.replace(/[^0-9]/g, '');
      if (e.target.value < 0) e.target.value = 0;
    }
  });

  // Toggle Function para sa Expiration Date Field
  function toggleExpiration(selectId, groupId, inputId) {
    const typeSelect = document.getElementById(selectId);
    const expGroup = document.getElementById(groupId);
    const expInput = document.getElementById(inputId);

    if (typeSelect.value.toUpperCase() === 'MEDICINE') {
      if (expGroup) expGroup.style.display = 'block';
      if (expInput) expInput.required = true;
    } else {
      if (expGroup) expGroup.style.display = 'none';
      if (expInput) { expInput.required = false; expInput.value = ''; }
    }
  }

  function openAddModal() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('addDeliveryDate').min = today;
    document.getElementById('addExpInput').min = today;
    // Reset delivery ID display and date
    document.getElementById('addDeliveryDate').value = '';
    document.getElementById('addDeliveryIdDisplay').value = 'Will be generated after selecting a delivery date';
    document.getElementById('addModal').style.display = 'flex';
  }

  function openEdit(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editDeliveryId').value = data.delivery_id || '';
    document.getElementById('editSupplier').value = data.supplier || '';
    document.getElementById('editDeliveryDate').value = data.delivery_date || '';
    document.getElementById('editBatchLotNumber').value = data.batch_lot_number || '';
    document.getElementById('editName').value = data.item_name;
    document.getElementById('editType').value = data.item_type;
    document.getElementById('editQty').value = data.quantity;
    document.getElementById('editUnit').value = data.unit || 'pcs';
    document.getElementById('editPrice').value = data.price || 0;
    document.getElementById('editExpInput').value = data.expiration_date || '';
    document.getElementById('editReceivedBy').value = data.received_by || '';

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('editDeliveryDate').min = today;
    document.getElementById('editExpInput').min = today;

    document.getElementById('editModal').style.display = 'flex';
  }
</script>
</body>
</html>


