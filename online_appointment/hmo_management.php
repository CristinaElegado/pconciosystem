<?php
// hmo_management.php - Final Complete Code with Blue Tab Theme, HMO Member ID Fix & Notifications
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

// Auto-add missing columns to hmo_requests table if they don't exist yet
try {
    $pdo->exec("ALTER TABLE hmo_requests ADD COLUMN IF NOT EXISTS coverage_type VARCHAR(50) DEFAULT 'Not Covered'");
    $pdo->exec("ALTER TABLE hmo_requests ADD COLUMN IF NOT EXISTS verified_coverage VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE hmo_requests ADD COLUMN IF NOT EXISTS hmo_deduction DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE hmo_requests ADD COLUMN IF NOT EXISTS patient_responsibility DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE hmo_requests ADD COLUMN IF NOT EXISTS verification_notes TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE hmo_requests ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
} catch (PDOException $e) {
    // Ignore if already exists or lacks privilege
}

// Handle Verification & Calculation Backend logic (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'verify_hmo_request') {
            $request_id = (int)($_POST['request_id'] ?? 0);
            $coverage_type = $_POST['coverage_type'] ?? 'Not Covered';
            $verified_coverage_input = trim($_POST['verified_coverage'] ?? '');
            $verification_notes = trim($_POST['verification_notes'] ?? '');
            $new_status = $_POST['status'] ?? 'verified';

            $stmt = $pdo->prepare("
                SELECT r.*, 
                       p.first_name as patient_firstname, 
                       p.last_name as patient_lastname,
                       p.email as patient_email,
                       p.phone_number as patient_phone,
                       hp.hmo_name 
                FROM hmo_requests r
                LEFT JOIN online_appointment p ON r.online_appointment_id = p.id
                LEFT JOIN hmo_providers hp ON r.hmo_id = hp.id
                WHERE r.id = ?
            ");
            $stmt->execute([$request_id]);
            $reqData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reqData) {
                echo json_encode(['success' => false, 'message' => 'HMO Request ID ' . $request_id . ' not found']);
                exit;
            }

            $treatment_fee = (float)($reqData['treatment_fee'] ?? 0);
            $hmo_deduction = 0.00;
            $verified_coverage_saved = $verified_coverage_input;

            switch ($coverage_type) {
                case 'Fully Covered':
                    $hmo_deduction = $treatment_fee;
                    $verified_coverage_saved = '100%';
                    break;
                case 'Percentage':
                    $percentage = (float)filter_var($verified_coverage_input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                    if ($percentage > 100) $percentage = 100.0;
                    if ($percentage < 0) $percentage = 0.0;
                    $hmo_deduction = $treatment_fee * ($percentage / 100.0);
                    $verified_coverage_saved = $percentage . '%';
                    break;
                case 'Fixed Amount':
                case 'Discount':
                    $amount = (float)filter_var($verified_coverage_input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                    if ($amount > $treatment_fee) $amount = $treatment_fee;
                    if ($amount < 0) $amount = 0.0;
                    $hmo_deduction = $amount;
                    $verified_coverage_saved = '₱' . number_format($amount, 2);
                    break;
                case 'Not Covered':
                default:
                    $hmo_deduction = 0.00;
                    $verified_coverage_saved = '₱0.00';
                    break;
            }

            if ($hmo_deduction > $treatment_fee) $hmo_deduction = $treatment_fee;
            $patient_responsibility = max(0.00, $treatment_fee - $hmo_deduction);

            // Start transaction safely bago mag-update
            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare("
                UPDATE hmo_requests 
                SET coverage_type = ?, 
                    verified_coverage = ?, 
                    hmo_deduction = ?, 
                    patient_responsibility = ?, 
                    verification_notes = ?, 
                    status = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $coverage_type, 
                $verified_coverage_saved, 
                $hmo_deduction, 
                $patient_responsibility, 
                $verification_notes, 
                $new_status, 
                $request_id
            ]);

            // --- NOTIFICATION CODES (EMAIL & TEXTBEE SMS) ---
            $patientEmail = $reqData['patient_email'];
            $patientPhone = $reqData['patient_phone'];
            $patientName = $reqData['patient_firstname'] . ' ' . $reqData['patient_lastname'];
            $hmoName = $reqData['hmo_name'] ?? 'HMO';

            // 1. Send Email Notification
            if (!empty($patientEmail)) {
                $emailSubject = "Your HMO Request Status has been updated - Mariategue Dental Clinic";
                $emailMessage = "Hello {$patientName},\n\n" .
                                "Your HMO request for {$hmoName} has been updated.\n" .
                                "Status: " . ucfirst($new_status) . "\n" .
                                "Coverage Type: {$coverage_type} ({$verified_coverage_saved})\n" .
                                "HMO Deduction: ₱" . number_format($hmo_deduction, 2) . "\n" .
                                "Your Responsibility (Patient Pay): ₱" . number_format($patient_responsibility, 2) . "\n\n" .
                                (!empty($verification_notes) ? "Notes: {$verification_notes}\n\n" : "") .
                                "Thank you,\nMariategue Dental Clinic";
                
                $headers = "From: no-reply@mariateguedental.com\r\n" .
                           "Reply-To: no-reply@mariateguedental.com\r\n" .
                           "X-Mailer: PHP/" . phpversion();
                
                @mail($patientEmail, $emailSubject, $emailMessage, $headers);
            }

            // --- Textbee.dev API (SMS Execution Fix) ---
            $smsMessage = "Hi {$patientName}, your HMO request for {$hmoName} is now " . ucfirst($new_status) . ". Deduction: ₱" . number_format($hmo_deduction, 2) . ". Balance: ₱" . number_format($patient_responsibility, 2) . ". - Mariategue Dental Clinic";

            if (!empty($smsMessage) && !empty($patientPhone)) {
                $textbeeApiKey = getenv('TXB_API_KEY') ?: 'txb_dOnYlM9EF7f8RqwdTHpshiL4JXRdm6A2';
                $textbeeDeviceId = getenv('TXB_DEVICE_ID') ?: '6a9de725ccb6c7270925a793';

                $payload = array(
                    'recipients' => array($patientPhone),
                    'message'    => $smsMessage
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.textbee.dev/api/v1/gateway/devices/{$textbeeDeviceId}/send-sms");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'x-api-key: ' . $textbeeApiKey,
                    'Content-Type: application/json'
                ));
                $output = curl_exec($ch);
                curl_close($ch);
            }

            // Commit database transaction
            $pdo->commit();

            // --- END OF NOTIFICATIONS ---

            echo json_encode([
                'success' => true, 
                'message' => 'HMO verification saved and notifications sent successfully!',
                'hmo_deduction' => number_format($hmo_deduction, 2),
                'patient_responsibility' => number_format($patient_responsibility, 2)
            ]);
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch HMO requests with p.hmo_member_id pulled from online_appointment
$requests = [];
try {
    $requests_query = "
        SELECT r.*, 
               COALESCE(p.first_name, 'Unknown') as patient_firstname, 
               COALESCE(p.last_name, 'Patient') as patient_lastname,
               COALESCE(p.phone_number, '') as patient_phone,
               COALESCE(p.hmo_id_image, r.hmo_id_image) as hmo_id_image,
               COALESCE(p.hmo_member_id, 'N/A') as hmo_member_id,
               hp.hmo_name 
        FROM hmo_requests r
        LEFT JOIN online_appointment p ON r.online_appointment_id = p.id
        LEFT JOIN hmo_providers hp ON r.hmo_id = hp.id
        ORDER BY r.id DESC
    ";
    $requests = $pdo->query($requests_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}
 
include __DIR__ . '/../miscellaneous/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMO Requests - Mariategue Ortho-DentalClinic</title>
    <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        #imageModal {
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        /* Pill/Button Style Nav Tabs (Blue Theme matching reference) */
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: none;
            padding-bottom: 0;
        }
        .nav-tab-btn {
            padding: 10px 20px;
            background: #ffffff;           
            border: 1px solid #2563eb;     
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #2563eb;                
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(37, 99, 235, 0.05);
        }
        .nav-tab-btn:hover {
            background: #2563eb;           
            color: #ffffff;                
            border-color: #2563eb;
        }
        .nav-tab-btn.active {
            background: #2563eb;           
            color: #ffffff;                
            border-color: #2563eb;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
    </style>
</head>
<body>

<div class="top-header">
    <h2>HMO Management Dashboard</h2>
    <div class="user-info">
        Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        <span><?php echo htmlspecialchars(ucfirst($_SESSION['user_type'] ?? 'Staff')); ?></span>
    </div>
</div>

<div class="main-content" style="margin-top: -10px;">
    <div class="nav-tabs">
        <button class="nav-tab-btn active" onclick="window.location.href='hmo_management.php'">HMO Requests</button>
        <button class="nav-tab-btn" onclick="window.location.href='hmo_providers.php'">HMO Providers</button>
    </div>
    
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by patient name, provider, member ID, or status...">
    </div>

    <table id="requestsTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Patient Name</th>
                <th>HMO Provider</th>
                <th>HMO Member ID</th>
                <th>HMO ID Image</th>
                <th>Treatment Fee</th>
                <th>Verified Coverage</th>
                <th>HMO Deduction</th>
                <th>Patient Pay</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($requests) > 0): ?>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= htmlspecialchars($req['id']) ?></td>
                    <td><strong><?= htmlspecialchars($req['patient_firstname'] . ' ' . $req['patient_lastname']) ?></strong></td>
                    <td><?= htmlspecialchars($req['hmo_name'] ?? 'N/A') ?></td>
                    <td><code style="background: #eff6ff; padding: 3px 6px; border-radius: 4px; color: #1e40af;"><?= htmlspecialchars($req['hmo_member_id']) ?></code></td>
                    <td>
                        <?php if (!empty($req['hmo_id_image'])): ?>
                            <img src="view_hmo_image.php?id=<?= $req['id'] ?>" alt="HMO ID" class="hmo-img-thumb" style="width: 50px; height: 50px; object-fit: cover; cursor: pointer; border-radius: 4px;" onclick="openImageModal(this.src, '<?= htmlspecialchars($req['patient_firstname'] . ' ' . $req['patient_lastname'], ENT_QUOTES) ?>')">
                            <br><small style="font-size: 9px; color: #64748b;"><?= htmlspecialchars(basename($req['hmo_id_image'])) ?></small>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 12px;">No Image</span>
                        <?php endif; ?>
                    </td>
                    <td><strong>₱<?= number_format($req['treatment_fee'] ?? 0, 2) ?></strong></td>
                    <td>
                        <span style="font-weight: 600; color: #2563eb;"><?= htmlspecialchars($req['coverage_type'] ?? 'Not Verified') ?></span><br>
                        <small style="color: #64748b;"><?= htmlspecialchars($req['verified_coverage'] ?? '-') ?></small>
                    </td>
                    <td style="color: #047857; font-weight: 600;">₱<?= number_format($req['hmo_deduction'] ?? 0, 2) ?></td>
                    <td style="color: #b91c1c; font-weight: 600;">₱<?= number_format($req['patient_responsibility'] ?? 0, 2) ?></td>
                    <td>
                        <?php 
                            $st = strtolower($req['status'] ?? 'pending');
                            $badgeClass = 'badge-pending';
                            if ($st === 'verified') $badgeClass = 'badge-verified';
                            elseif ($st === 'approved') $badgeClass = 'badge-approved';
                            elseif ($st === 'rejected') $badgeClass = 'badge-rejected';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($req['status'] ?? 'pending') ?></span>
                    </td>
                    <td>
                        <button class="btn-verify" onclick="openVerifyModal(
                            '<?= $req['id'] ?>', 
                            '<?= htmlspecialchars($req['patient_firstname'] . ' ' . $req['patient_lastname'], ENT_QUOTES) ?>', 
                            '<?= $req['treatment_fee'] ?? 0 ?>',
                            '<?= htmlspecialchars($req['coverage_type'] ?? 'Not Covered', ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($req['verified_coverage'] ?? '', ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($req['verification_notes'] ?? '', ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($req['status'] ?? 'pending', ENT_QUOTES) ?>'
                        )">Verify</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" class="no-data">No HMO requests found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Image Preview Modal -->
<div id="imageModal" class="modal">
    <div class="image-modal-content">
        <div class="modal-header">
            <h2 id="imageModalTitle">HMO ID Image</h2>
            <button class="close-btn" onclick="closeModal('imageModal')">&times;</button>
        </div>
        <div style="padding: 10px; text-align: center;">
            <img id="previewImageSrc" src="" alt="HMO Card Full Preview" style="max-width: 100%; max-height: 70vh; border-radius: 8px; border: 1px solid #bfdbfe;">
        </div>
    </div>
</div>

<!-- Verify Modal -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Verify HMO Request</h2>
            <button class="close-btn" onclick="closeModal('verifyModal')">&times;</button>
        </div>
        <form id="verifyForm">
            <input type="hidden" name="action" value="verify_hmo_request">
            <input type="hidden" id="requestId" name="request_id">
            
            <div style="background: #eff6ff; padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #bfdbfe;">
                <span id="modalPatientText" style="font-weight: bold; color: #1e40af;">Patient: Patient</span><br>
                <span id="modalFeeText" style="color: #1d4ed8; font-weight: 600;">Treatment Fee: ₱0.00</span>
            </div>

            <div class="form-group">
                <label>Coverage Type *</label>
                <select id="coverage_type" name="coverage_type" required onchange="toggleCoverageInput()">
                    <option value="Fully Covered">Fully Covered</option>
                    <option value="Percentage">Percentage</option>
                    <option value="Fixed Amount">Fixed Amount</option>
                    <option value="Discount">Discount</option>
                    <option value="Not Covered">Not Covered</option>
                </select>
            </div>

            <div class="form-group" id="verifiedAmountGroup">
                <label id="verifiedLabel">Verified Coverage Value *</label>
                <input type="number" id="verified_coverage" name="verified_coverage" step="0.01" min="0" max="100" placeholder="Enter value...">
                <small style="color: #64748b;" id="coverageHelpText">Enter percentage or peso amount.</small>
            </div>

            <div class="form-group">
                <label>Verification Status *</label>
                <select name="status" id="verificationStatus" required>
                    <option value="verified">Verified</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="form-group">
                <label>Verification Notes</label>
                <textarea name="verification_notes" id="verificationNotes" rows="3" placeholder="Enter remarks or approval code..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeModal('verifyModal')">Cancel</button>
                <button type="submit" class="btn-submit">Save Verification</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openImageModal(imgSrc, patientName) {
        document.getElementById('imageModalTitle').innerText = 'HMO ID - ' + patientName;
        document.getElementById('previewImageSrc').src = imgSrc;
        const modal = document.getElementById('imageModal');
        modal.style.display = 'flex';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
    }

    function openVerifyModal(id, patientName, fee, coverageType, verifiedCov, notes, status) {
        document.getElementById('requestId').value = id;
        document.getElementById('modalPatientText').innerText = 'Patient: ' + patientName;
        document.getElementById('modalFeeText').innerText = 'Treatment Fee: ₱' + parseFloat(fee).toLocaleString('en-US', {minimumFractionDigits: 2});
        
        document.getElementById('coverage_type').value = coverageType || 'Not Covered';
        document.getElementById('verified_coverage').value = (verifiedCov || '').replace(/[^0-9.]/g, '');
        document.getElementById('verificationStatus').value = status || 'pending';
        document.getElementById('verificationNotes').value = notes || '';

        toggleCoverageInput();
        const vModal = document.getElementById('verifyModal');
        vModal.style.display = 'flex';
        vModal.style.justifyContent = 'center';
        vModal.style.alignItems = 'center';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function toggleCoverageInput() {
        const type = document.getElementById('coverage_type').value;
        const group = document.getElementById('verifiedAmountGroup');
        const label = document.getElementById('verifiedLabel');
        const input = document.getElementById('verified_coverage');
        const helpText = document.getElementById('coverageHelpText');

        if (type === 'Fully Covered' || type === 'Not Covered') {
            group.style.display = 'none';
            input.required = false;
        } else {
            group.style.display = 'block';
            input.required = true;
            if (type === 'Percentage') {
                label.innerText = 'Verified Percentage (%) *';
                input.setAttribute('type', 'number');
                input.setAttribute('max', '100');
                input.setAttribute('min', '0');
                input.setAttribute('step', '0.01');
                helpText.innerText = 'Enter percentage value (maximum 100%).';
            } else {
                label.innerText = 'Verified Amount / Discount (₱) *';
                input.setAttribute('type', 'number');
                input.removeAttribute('max');
                input.setAttribute('min', '0');
                input.setAttribute('step', '0.01');
                helpText.innerText = 'Enter peso amount.';
            }
        }
    }

    document.getElementById('verifyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('', { method: 'POST', body: formData })
        .then(async response => {
            const text = await response.text();
            try { return JSON.parse(text); } 
            catch (err) { throw new Error('Invalid response: ' + text.substring(0, 100)); }
        })
        .then(data => {
            if (data.success) {
                showAlert(data.message);
                location.reload();
            } else {
                showAlert('Error: ' + data.message);
            }
        })
        .catch(err => { showAlert('An error occurred: ' + err.message); });
    });

    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('#requestsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
        });
    });

    window.onclick = function(event) {
        if (event.target === document.getElementById('verifyModal')) document.getElementById('verifyModal').style.display = 'none';
        if (event.target === document.getElementById('imageModal')) document.getElementById('imageModal').style.display = 'none';
    }
</script>

</body>
</html>

