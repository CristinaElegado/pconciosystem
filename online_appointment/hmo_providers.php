<?php
// hmo_providers.php - Adjusted to match HMO Requests design style
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hmo') {
        $hmo_name = trim($_POST['hmo_name']);
        $hmo_code = trim($_POST['hmo_code']);
        $status = $_POST['status'] ?? 'active';

        // Backend Validations
        if (mb_strlen($hmo_name) > 50) {
            echo json_encode(['success' => false, 'message' => 'HMO Name must not exceed 50 characters.']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z\s]+$/', $hmo_name)) {
            echo json_encode(['success' => false, 'message' => 'HMO Name must contain letters and spaces only (no numbers or special characters).']);
            exit;
        }
        if (mb_strlen($hmo_code) > 30) {
            echo json_encode(['success' => false, 'message' => 'HMO Code must not exceed 30 characters.']);
            exit;
        }

        if ($hmo_name && $hmo_code) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM hmo_providers WHERE hmo_name = ? OR hmo_code = ?");
            $checkStmt->execute([$hmo_name, $hmo_code]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'HMO Name or HMO Code already exists! Please use unique values.']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO hmo_providers 
                (hmo_name, hmo_code, status, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$hmo_name, $hmo_code, $status]);
            echo json_encode(['success' => true, 'message' => 'HMO provider added successfully']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid input data']);
            exit;
        }
    }

    elseif ($action === 'edit_hmo') {
        $hmo_id = (int)$_POST['hmo_id'];
        $hmo_name = trim($_POST['hmo_name']);
        $hmo_code = trim($_POST['hmo_code']);
        $status = $_POST['status'] ?? 'active';

        // Backend Validations
        if (mb_strlen($hmo_name) > 50) {
            echo json_encode(['success' => false, 'message' => 'HMO Name must not exceed 50 characters.']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z\s]+$/', $hmo_name)) {
            echo json_encode(['success' => false, 'message' => 'HMO Name must contain letters and spaces only (no numbers or special characters).']);
            exit;
        }
        if (mb_strlen($hmo_code) > 30) {
            echo json_encode(['success' => false, 'message' => 'HMO Code must not exceed 30 characters.']);
            exit;
        }

        if ($hmo_name && $hmo_code) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM hmo_providers WHERE (hmo_name = ? OR hmo_code = ?) AND id != ?");
            $checkStmt->execute([$hmo_name, $hmo_code, $hmo_id]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'HMO Name or HMO Code already exists in another record!']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE hmo_providers 
                SET hmo_name = ?, hmo_code = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hmo_name, $hmo_code, $status, $hmo_id]);
            echo json_encode(['success' => true, 'message' => 'HMO provider updated successfully']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid input data']);
            exit;
        }
    }

    elseif ($action === 'delete_hmo') {
        $hmo_id = (int)$_POST['hmo_id'];
        $stmt = $pdo->prepare("UPDATE hmo_providers SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$hmo_id]);
        echo json_encode(['success' => true, 'message' => 'HMO provider deactivated successfully']);
        exit;
    }
}

$hmo_list = $pdo->query("
    SELECT * FROM hmo_providers 
    ORDER BY hmo_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../miscellaneous/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMO Providers - Mariategue Ortho-DentalClinic</title>
    <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
    <style>
        body {
            background-image: none !important;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
        }
        .main-content {
            padding: 30px;
            margin-left: 250px;
        }
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 15px 30px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .top-header h2 {
            margin: 0;
            color: #1e293b;
            font-size: 22px;
            font-weight: 700;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #475569;
            font-weight: 500;
        }
        .user-info span {
            background: #f1f5f9;
            color: #334155;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        /* Adjusted Nav Tabs to match HMO Requests Style (Pill/Button format) */
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: none; /* Inalis ang bottom line para maging katulad ng HMO Requests */
            padding-bottom: 0;
        }
        .nav-tab-btn {
            padding: 10px 20px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            border-radius: 8px; /* Rounded pill/button shape */
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .nav-tab-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        .nav-tab-btn.active {
            background-color: #2563eb; /* Solid blue background */
            color: white;
            border-color: #2563eb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 25px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .modal-header h2 {
            color: #1e293b;
            margin: 0;
            font-size: 20px;
        }
        .close-btn {
            cursor: pointer;
            font-size: 28px;
            font-weight: bold;
            color: #64748b;
            background: none;
            border: none;
            padding: 0;
        }
        .close-btn:hover {
            color: #1e293b;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            outline: none;
            background-color: white;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn-submit {
            background-color: #059669;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-submit:hover {
            background-color: #047857;
        }
        .btn-cancel {
            background-color: #64748b;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-cancel:hover {
            background-color: #475569;
        }
        .btn-add {
            background-color: #059669;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 20px;
            transition: background-color 0.2s;
        }
        .btn-add:hover {
            background-color: #047857;
        }
        .btn-edit, .btn-delete {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            margin-right: 4px;
            color: white;
            font-weight: 600;
            transition: opacity 0.2s;
        }
        .btn-edit { background-color: #3b82f6; }
        .btn-edit:hover { background-color: #2563eb; }
        .btn-delete { background-color: #dc2626; }
        .btn-delete:hover { background-color: #b91c1c; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        thead {
            background-color: #334155;
            color: white;
        }
        th, td {
            padding: 14px 12px;
            text-align: left;
            font-size: 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            font-weight: 600;
        }
        tr:hover { background-color: #f8fafc; }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active { background-color: #d1fae5; color: #065f46; }
        .badge-inactive { background-color: #fee2e2; color: #991b1b; }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        /* Adjusted Search Box style to match HMO Requests border theme */
        .search-box input {
            padding: 10px 15px;
            border: 1px solid #fbcfe8; /* Ginawang pink/red border para maging kapareho ng HMO requests search box */
            border-radius: 8px;
            width: 320px;
            font-size: 14px;
            outline: none;
            background-color: white;
        }
        .search-box input:focus {
            border-color: #db2777;
            box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1);
        }
    </style>
</head>
<body>

<div class="top-header">
    <h2>HMO Providers Management</h2>
    <div class="user-info">
        Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        <span><?php echo htmlspecialchars(ucfirst($_SESSION['user_type'] ?? 'Staff')); ?></span>
    </div>
</div>

<div class="main-content" style="margin-top: -10px;">
    <div class="nav-tabs">
        <button class="nav-tab-btn" onclick="navigateTo('hmo_management.php')">HMO Requests</button>
        <button class="nav-tab-btn active" onclick="navigateTo('hmo_providers.php')">HMO Providers</button>
    </div>
    
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by HMO name or code...">
    </div>

    <button class="btn-add" onclick="openAddHmoModal()">+ Add New HMO Provider</button>

    <table id="hmoTable">
        <thead>
            <tr>
                <th>HMO Name</th>
                <th>HMO Code</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($hmo_list) > 0): ?>
                <?php foreach ($hmo_list as $hmo): ?>
                <tr>
                    <td><?= htmlspecialchars($hmo['hmo_name']) ?></td>
                    <td><?= htmlspecialchars($hmo['hmo_code']) ?></td>
                    <td>
                        <span class="badge <?= $hmo['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                            <?= ucfirst($hmo['status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn-edit" onclick="openEditHmoModal(<?= $hmo['id'] ?>, '<?= htmlspecialchars($hmo['hmo_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($hmo['hmo_code'], ENT_QUOTES) ?>', '<?= $hmo['status'] ?>')">Edit</button>
                        <button class="btn-delete" onclick="deleteHmo(<?= $hmo['id'] ?>)">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="no-data">No HMO providers found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit HMO Provider Modal -->
<div id="hmoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="hmoModalTitle">Add New HMO Provider</h2>
            <button class="close-btn" onclick="closeModal('hmoModal')">&times;</button>
        </div>
        <form id="hmoForm">
            <input type="hidden" id="hmoId" name="hmo_id">
            <input type="hidden" name="action" id="hmoAction" value="add_hmo">

            <div class="form-group">
                <label>HMO Name *</label>
                <input type="text" id="hmoName" name="hmo_name" maxlength="50" pattern="[a-zA-Z\s]+" title="Letters and spaces only (max 50 chars)" required>
                <small style="color: #64748b;">Letters and spaces only, max 50 characters, unique.</small>
            </div>

            <div class="form-group">
                <label>HMO Code *</label>
                <input type="text" id="hmoCode" name="hmo_code" maxlength="30" required>
                <small style="color: #64748b;">Max 30 characters, must be unique.</small>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select id="hmoStatus" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeModal('hmoModal')">Cancel</button>
                <button type="submit" class="btn-submit">Save Provider</button>
            </div>
        </form>
    </div>
</div>

<script>
    function navigateTo(page) {
        window.location.href = page;
    }

    document.getElementById('hmoName').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
    });

    function openAddHmoModal() {
        document.getElementById('hmoModalTitle').textContent = 'Add New HMO Provider';
        document.getElementById('hmoAction').value = 'add_hmo';
        document.getElementById('hmoForm').reset();
        document.getElementById('hmoId').value = '';
        document.getElementById('hmoModal').style.display = 'block';
    }

    function openEditHmoModal(id, name, code, status) {
        document.getElementById('hmoModalTitle').textContent = 'Edit HMO Provider';
        document.getElementById('hmoAction').value = 'edit_hmo';
        document.getElementById('hmoId').value = id;
        document.getElementById('hmoName').value = name;
        document.getElementById('hmoCode').value = code;
        document.getElementById('hmoStatus').value = status;
        document.getElementById('hmoModal').style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function deleteHmo(hmoId) {
        showConfirmDialog('Are you sure you want to deactivate this HMO provider?', function() {
            const formData = new FormData();
            formData.append('action', 'delete_hmo');
            formData.append('hmo_id', hmoId);

            fetch('', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message);
                        location.reload();
                    } else {
                        showAlert('Error: ' + data.message);
                    }
                });
        }, { title: 'Deactivate HMO', icon: 'warning', danger: true, okText: 'Deactivate' });
    }

    document.getElementById('hmoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    location.reload();
                } else {
                    showAlert('Error: ' + data.message);
                }
            });
    });

    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#hmoTable tbody tr');
        
        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    window.onclick = function(event) {
        let modal = document.getElementById('hmoModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>

