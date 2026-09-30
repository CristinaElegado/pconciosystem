<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_service_coverage') {
        $hmo_id = (int)$_POST['hmo_id'];
        $service_id = (int)$_POST['service_id'];
        $coverage_rate = (int)$_POST['coverage_rate'];
        $remarks = trim($_POST['remarks'] ?? '');

        if ($hmo_id && $service_id && $coverage_rate >= 0 && $coverage_rate <= 100) {
            $stmt = $pdo->prepare("
                INSERT INTO hmo_service_coverage 
                (hmo_id, service_id, coverage_rate, remarks, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$hmo_id, $service_id, $coverage_rate, $remarks]);
            echo json_encode(['success' => true, 'message' => 'Service coverage added successfully']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid input data']);
            exit;
        }
    }

    elseif ($action === 'edit_service_coverage') {
        $coverage_id = (int)$_POST['coverage_id'];
        $hmo_id = (int)$_POST['hmo_id'];
        $service_id = (int)$_POST['service_id'];
        $coverage_rate = (int)$_POST['coverage_rate'];
        $remarks = trim($_POST['remarks'] ?? '');

        if ($hmo_id && $service_id && $coverage_rate >= 0 && $coverage_rate <= 100) {
            $stmt = $pdo->prepare("
                UPDATE hmo_service_coverage 
                SET hmo_id = ?, service_id = ?, coverage_rate = ?, remarks = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hmo_id, $service_id, $coverage_rate, $remarks, $coverage_id]);
            echo json_encode(['success' => true, 'message' => 'Service coverage updated successfully']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid input data']);
            exit;
        }
    }

    elseif ($action === 'delete_service_coverage') {
        $coverage_id = (int)$_POST['coverage_id'];
        $stmt = $pdo->prepare("DELETE FROM hmo_service_coverage WHERE id = ?");
        $stmt->execute([$coverage_id]);
        echo json_encode(['success' => true, 'message' => 'Service coverage deleted successfully']);
        exit;
    }
}

// Fetch all service coverage with HMO and Service details
$coverage_list = $pdo->query("
    SELECT 
        hsc.id,
        hp.hmo_name,
        s.service_name,
        s.price,
        hsc.coverage_rate,
        hsc.remarks,
        hsc.created_at,
        hsc.updated_at,
        hp.id as hmo_id,
        s.id as service_id
    FROM hmo_service_coverage hsc
    JOIN hmo_providers hp ON hsc.hmo_id = hp.id
    JOIN services s ON hsc.service_id = s.id
    WHERE hp.status = 'active'
    ORDER BY hp.hmo_name, s.service_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all HMO providers
$hmo_list = $pdo->query("
    SELECT id, hmo_name FROM hmo_providers 
    WHERE status = 'active'
    ORDER BY hmo_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all services
$services_list = $pdo->query("
    SELECT id, service_name, price FROM services 
    WHERE status = 'enable' 
    ORDER BY service_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../miscellaneous/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Coverage</title>
    <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
    <style>
        body {
            background-image: none !important;
        }
        .main-content {
            padding: 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0;
        }
        .nav-tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .nav-tab-btn:hover {
            color: #0ea5e9;
        }
        .nav-tab-btn.active {
            color: #0ea5e9;
            border-bottom-color: #0ea5e9;
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
            background-color: #fefefe;
            margin: 5% auto;
            padding: 25px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #0ea5e9;
            padding-bottom: 10px;
        }
        .modal-header h2 {
            margin: 0;
            color: #333;
        }
        .close-btn {
            cursor: pointer;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            background: none;
            border: none;
            padding: 0;
        }
        .close-btn:hover {
            color: #000;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 5px rgba(14, 165, 233, 0.3);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn-submit {
            background-color: #0ea5e9;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-submit:hover {
            background-color: #0284c7;
        }
        .btn-cancel {
            background-color: #64748b;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            margin-left: 10px;
        }
        .btn-cancel:hover {
            background-color: #475569;
        }
        .btn-add {
            background-color: #22c55e;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .btn-add:hover {
            background-color: #16a34a;
        }
        .btn-edit {
            background-color: #f59e0b;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }
        .btn-edit:hover {
            background-color: #d97706;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        thead {
            background-color: #f3f4f6;
            border-bottom: 2px solid #e5e7eb;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        tr:hover {
            background-color: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background-color: #dbeafe;
            color: #1e40af;
        }
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 300px;
            font-size: 14px;
        }
        .info-box {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 13px;
            color: #166534;
        }
    </style>
</head>
<body>

<div class="main-content">
    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <button class="nav-tab-btn active" onclick="navigateTo('service_coverage.php')">Service Coverage</button>
        <button class="nav-tab-btn" onclick="navigateTo('hmo_providers.php')">HMO Providers</button>
        <button class="nav-tab-btn" onclick="navigateTo('hmo_management.php')">HMO Requests</button>
    </div>

    <h2>Service Coverage</h2>
    
    <div class="info-box">
        ℹ️ Manage which services are covered by each HMO and at what coverage rate
    </div>

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by HMO name or service...">
    </div>

    <button class="btn-add" onclick="openAddCoverageModal()">+ Add Service Coverage</button>

    <table id="coverageTable">
        <thead>
            <tr>
                <th>HMO Provider</th>
                <th>Service Name</th>
                <th>Service Price</th>
                <th>Coverage Rate %</th>
                <th>Remarks</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($coverage_list) > 0): ?>
                <?php foreach ($coverage_list as $coverage): ?>
                <tr>
                    <td><?= htmlspecialchars($coverage['hmo_name']) ?></td>
                    <td><?= htmlspecialchars($coverage['service_name']) ?></td>
                    <td>₱<?= number_format($coverage['price'], 2) ?></td>
                    <td><span class="badge"><?= $coverage['coverage_rate'] ?>%</span></td>
                    <td><?= htmlspecialchars($coverage['remarks']) ?></td>
                    <td>
                        <button class="btn-edit" onclick="openEditCoverageModal(<?= $coverage['id'] ?>, <?= $coverage['hmo_id'] ?>, <?= $coverage['service_id'] ?>, <?= $coverage['coverage_rate'] ?>, '<?= htmlspecialchars($coverage['remarks']) ?>')">Edit</button>
                        <button class="btn-delete" onclick="deleteCoverage(<?= $coverage['id'] ?>)">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="no-data">No service coverage found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Service Coverage Modal -->
<div id="coverageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="coverageModalTitle">Add Service Coverage</h2>
            <button class="close-btn" onclick="closeModal('coverageModal')">&times;</button>
        </div>
        <form id="coverageForm">
            <input type="hidden" id="coverageId" name="coverage_id">
            <input type="hidden" name="action" id="coverageAction" value="add_service_coverage">

            <div class="form-group">
                <label>HMO Provider *</label>
                <select id="hmoSelect" name="hmo_id" required>
                    <option value="">Select HMO</option>
                    <?php foreach ($hmo_list as $hmo): ?>
                    <option value="<?= $hmo['id'] ?>"><?= htmlspecialchars($hmo['hmo_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Service *</label>
                <select id="serviceSelect" name="service_id" required>
                    <option value="">Select Service</option>
                    <?php foreach ($services_list as $service): ?>
                    <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['service_name']) ?> - ₱<?= number_format($service['price'], 2) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Coverage Rate (%) *</label>
                <input type="number" id="coverageRate" name="coverage_rate" min="0" max="100" required>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" placeholder="Optional notes (e.g., conditions, restrictions)"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-cancel" onclick="closeModal('coverageModal')">Cancel</button>
                <button type="submit" class="btn-submit">Save Coverage</button>
            </div>
        </form>
    </div>
</div>

<script>
    function navigateTo(page) {
        window.location.href = page;
    }

    function openAddCoverageModal() {
        document.getElementById('coverageModalTitle').textContent = 'Add Service Coverage';
        document.getElementById('coverageAction').value = 'add_service_coverage';
        document.getElementById('coverageForm').reset();
        document.getElementById('coverageId').value = '';
        document.getElementById('coverageModal').style.display = 'block';
    }

    function openEditCoverageModal(id, hmoId, serviceId, coverageRate, remarks) {
        document.getElementById('coverageModalTitle').textContent = 'Edit Service Coverage';
        document.getElementById('coverageAction').value = 'edit_service_coverage';
        document.getElementById('coverageId').value = id;
        document.getElementById('hmoSelect').value = hmoId;
        document.getElementById('serviceSelect').value = serviceId;
        document.getElementById('coverageRate').value = coverageRate;
        document.getElementById('remarks').value = remarks;
        document.getElementById('coverageModal').style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function deleteCoverage(coverageId) {
        showConfirmDialog('Are you sure you want to delete this service coverage?', function() {
            const formData = new FormData();
            formData.append('action', 'delete_service_coverage');
            formData.append('coverage_id', coverageId);

            fetch('', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }, { title: 'Delete Coverage', icon: 'delete', danger: true, okText: 'Delete' });
    }

    document.getElementById('coverageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    });

    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#coverageTable tbody tr');
        
        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    window.onclick = function(event) {
        let modal = document.getElementById('coverageModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>
