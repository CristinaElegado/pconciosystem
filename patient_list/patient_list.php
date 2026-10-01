<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';
include __DIR__ . '/../miscellaneous/log_audit.php';

// --- SIMPLE PRINT PRESCRIPTION HANDLER (INTEGRATED & FIXED STYLING) ---
if (isset($_GET['print_prescription']) && isset($_GET['patient_id'])) {
    $patient_id = intval($_GET['patient_id']);
    if ($patient_id <= 0) {
        http_response_code(400);
        echo "Invalid patient id.";
        exit;
    }

    // Fetch patient + dentist info
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(d.first_name, ' ', IF(d.middle_name IS NULL OR UPPER(d.middle_name) = 'NONE', '', CONCAT(d.middle_name, ' ')), d.last_name) AS dentist_fullname
        FROM patients_list p
        LEFT JOIN dentist_accounts d ON p.dentist_id = d.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        http_response_code(404);
        echo "Patient not found.";
        exit;
    }

    // format values with middle name inclusion
    $p_middle = trim($patient['middle_name'] ?? '');
    if (strtoupper($p_middle) === 'NONE' || $p_middle === '') {
        $p_middle = '';
    } else {
        $p_middle = $p_middle . ' ';
    }
    $patient_name = htmlspecialchars($patient['first_name'] . ' ' . $p_middle . $patient['last_name']);

    $dentist = htmlspecialchars(trim($patient['dentist_fullname'] ?? 'TBA'));
    $issued_date = date('Y-m-d');

    // Render printable prescription and exit
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Prescription - <?= $patient_name ?></title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <style>
            body {
                background: #e2e8f0 !important;
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .page {
                background: #ffffff;
                width: 100%;
                max-width: 650px;
                padding: 40px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                border-radius: 8px;
                box-sizing: border-box;
            }
            .clinic-head {
                text-align: center;
                margin-bottom: 15px;
            }
            .clinic-title {
                font-size: 20px;
                font-weight: bold;
                color: #0284c7;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .clinic-sub, .clinic-contact {
                font-size: 13px;
                color: #475569;
                margin-top: 3px;
            }
            .hr {
                border-bottom: 2px solid #0284c7;
                margin: 20px 0;
            }
            .patient-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 25px;
                font-size: 14px;
            }
            .patient-left, .patient-right {
                display: flex;
                align-items: flex-end;
                flex: 1;
            }
            .patient-right {
                justify-content: flex-end;
            }
            .field-label {
                font-weight: bold;
                color: #334155;
                margin-right: 8px;
            }
            .underline {
                border-bottom: 1px solid #94a3b8;
                flex: 1;
                padding-bottom: 2px;
                color: #0f172a;
                font-weight: 600;
            }
            .patient-right .underline {
                flex: 0 0 140px;
                text-align: center;
            }
            .rx-left {
                font-size: 28px;
                font-weight: bold;
                color: #0284c7;
                font-family: serif;
                margin-right: 15px;
                align-self: flex-start;
            }
            .rx-box {
                flex: 1;
                margin-bottom: 40px;
            }
            .rx-box-border {
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                min-height: 220px;
                padding: 15px;
                background: #ffffff;
            }
            .rx-editable {
                outline: none;
                font-size: 14px;
                line-height: 1.6;
                color: #0f172a;
                min-height: 200px;
                white-space: pre-wrap;
            }
            .footer-sign {
                text-align: right;
                margin-top: 40px;
                float: right;
                width: 250px;
            }
            .sig-line {
                border-bottom: 1px solid #0f172a;
                margin-bottom: 5px;
            }
            .dentist-name {
                font-weight: bold;
                font-size: 14px;
                color: #0f172a;
            }
            .license {
                font-size: 12px;
                color: #64748b;
            }
            .print-controls {
                clear: both;
                text-align: center;
                margin-top: 60px;
                padding-top: 20px;
                border-top: 1px dashed #cbd5e1;
            }
            .btn {
                background: #0284c7;
                color: white;
                padding: 10px 20px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: bold;
                display: inline-block;
                margin: 0 5px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .btn:hover {
                background: #0369a1;
            }
            @media print {
                body {
                    background: transparent !important;
                    padding: 0;
                }
                .page {
                    box-shadow: none;
                    border: none;
                    padding: 0;
                    max-width: 100%;
                }
                .print-controls {
                    display: none;
                }
                .rx-box-border {
                    border: none;
                    padding: 0;
                }
            }
        </style>
    </head>
    <body>
    <div class="page">
        <!-- Clinic Header -->
        <div class="clinic-head">
            <div class="clinic-title">MARIATEGUE Ortho-Dental Clinic</div>
            <div class="clinic-sub">141 M.L. Quezon St., Lower Bicutan, Taguig City</div>
            <div class="clinic-contact">09204522181 / (02)8837 – 6832</div>
        </div>

        <div class="hr"></div>

        <!-- Patient name and date -->
        <div class="patient-row">
            <div class="patient-left">
                <span class="field-label">Name:</span>
                <span class="underline"><?= $patient_name ?></span>
            </div>
            <div class="patient-right">
                <span class="field-label">Date:</span>
                <span class="underline"><?= $issued_date ?></span>
            </div>
        </div>

        <!-- Rx and editable prescription area -->
        <div style="clear:both; display:flex; align-items:flex-start;">
            <div class="rx-left">Rx</div>

            <div class="rx-box" style="flex:1;">
                <div class="rx-box-border">
                    <div id="rxBox" class="rx-editable" contenteditable="true" spellcheck="false"></div>
                </div>
            </div>
        </div>

        <!-- Signature -->
        <div class="footer-sign">
            <div class="sig-line"></div>
            <div class="dentist-name"><?= $dentist ?></div>
            <div class="license">License No. <?= htmlspecialchars($patient['id'] ? str_pad($patient['id'], 6, '0', STR_PAD_LEFT) : '000000') ?></div>
        </div>

        <!-- Print and Close controls (screen only) -->
        <div class="print-controls">
            <a href="#" class="btn" onclick="window.print(); return false;"><i class="fa-solid fa-print"></i> Print Prescription</a>
            <a href="#" class="btn" style="background:#64748b;" onclick="window.close(); return false;">Close</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rx = document.getElementById('rxBox');
            if (!rx) return;
            rx.focus();
            if (document.createRange && window.getSelection) {
                var range = document.createRange();
                range.selectNodeContents(rx);
                range.collapse(false);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}
// --- END SIMPLE PRINT HANDLER ---

try {
    $pdo->exec("ALTER TABLE patients_list ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {}

$rowsPerPage = 5;

function buildQueryPreserve($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    return http_build_query($params);
}

function recomputePatientStatus(PDO $pdo, int $patient_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(UPPER(TRIM(status))='PENDING') AS pending,
            SUM(UPPER(TRIM(status))='ONGOING') AS ongoing,
            SUM(UPPER(TRIM(status))='DONE') AS done,
            SUM(UPPER(TRIM(status))='CANCELLED') AS cancelled
        FROM patient_services
        WHERE patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    $total = (int)$row['total'];
    $pending = (int)$row['pending'];
    $ongoing = (int)$row['ongoing'];
    $done = (int)$row['done'];
    $cancelled = (int)$row['cancelled'];

    if ($ongoing > 0) $new = 'ONGOING';
    elseif ($cancelled === $total && $total > 0) $new = 'CANCELLED';
    elseif ($done > 0 && ($done + $cancelled) === $total) $new = 'TREATED';
    else $new = 'WAITING';

    $cur = $pdo->prepare("SELECT status FROM patients_list WHERE id = ?");
    $cur->execute([$patient_id]);
    $curStatus = $cur->fetchColumn();

    if ($curStatus !== $new) {
        $pdo->prepare("UPDATE patients_list SET status = ? WHERE id = ?")->execute([$new, $patient_id]);
    }
}

if (isset($_POST['update_service_status'])) {
    $service_id = intval($_POST['id'] ?? 0);
    $allowed = ['ONGOING','DONE','CANCELLED'];
    $new_status = $_POST['new_status'];

    if (!in_array($new_status, $allowed)) {
        header("Location: patient_list.php?" . buildQueryPreserve());
        exit;
    }

    $pid = $pdo->prepare("SELECT patient_id FROM patient_services WHERE id = ?");
    $pid->execute([$service_id]);
    $pid = $pid->fetchColumn();

    try {
        if ($new_status === 'ONGOING') {
            $pdo->prepare("UPDATE patient_services SET status='ONGOING', date_start=COALESCE(date_start, NOW()) WHERE id=?")->execute([$service_id]);
        } elseif ($new_status === 'DONE') {
            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE patient_services 
                SET status='DONE', date_end=NOW() 
                WHERE id=?
            ")->execute([$service_id]);

            $sv = $pdo->prepare("SELECT service_id, patient_id FROM patient_services WHERE id=?");
            $sv->execute([$service_id]);
            $serviceRow = $sv->fetch(PDO::FETCH_ASSOC);
            
            if (!$serviceRow) {
                throw new Exception("Service record not found.");
            }

            $real_service_id = $serviceRow['service_id'];
            $patient_id = $serviceRow['patient_id'];

            $items = $pdo->prepare("SELECT item_id, quantity_needed FROM service_items WHERE service_id = ?");
            $items->execute([$real_service_id]);
            $items = $items->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $it) {
                $pdo->prepare("UPDATE item_inventory SET quantity = quantity - ? WHERE id = ?")
                    ->execute([$it['quantity_needed'], $it['item_id']]);
            }

            $info = $pdo->prepare("
                SELECT 
                    p.id AS patient_id,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    d.id AS dentist_id,
                    CONCAT(
                        d.first_name,
                        IF(d.middle_name = 'None' OR d.middle_name IS NULL, '', CONCAT(' ', d.middle_name)),
                        ' ',
                        d.last_name
                    ) AS dentist_name,
                    s.id AS service_id,
                    s.service_name,
                    s.price,
                    p.type_of_appointment
                FROM patient_services ps
                JOIN patients_list p ON ps.patient_id = p.id
                JOIN services s ON ps.service_id = s.id
                JOIN dentist_accounts d ON p.dentist_id = d.id
                WHERE ps.id = ?
            ");
            $info->execute([$service_id]);
            $row = $info->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $appointment_type = !empty($row['type_of_appointment']) ? $row['type_of_appointment'] : 'WALK-IN';
                $pdo->prepare("
                    INSERT INTO transaction_history
                    (patient_id, patient_name, dentist_id, dentist_name, service_id, service_name, price, appointment_type, date_completed)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $row['patient_id'],
                    $row['patient_name'],
                    $row['dentist_id'],
                    $row['dentist_name'],
                    $row['service_id'],
                    $row['service_name'],
                    $row['price'],
                    $appointment_type
                ]);
            }

            $pdo->commit();
        } else {
            $pdo->prepare("UPDATE patient_services SET status='CANCELLED', date_end=NOW() WHERE id=?")->execute([$service_id]);
        }
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('Error updating status: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }

    if ($pid) recomputePatientStatus($pdo, (int)$pid);
    // Log service status change
    $actor = $_SESSION['username'] ?? 'Unknown';
    $actor_type = $_SESSION['user_type'] ?? 'staff';
    log_audit($pdo, $actor_type, $actor, 'Updated Service Status', "Service ID: {$service_id} | New Status: {$new_status}");
    header("Location: patient_list.php?" . buildQueryPreserve());
    exit;
}

if (isset($_POST['delete_patient'])) {
    $id = $_POST['id'];
    // Fetch patient name before soft-delete
    $pName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patients_list WHERE id=?");
    $pName->execute([$id]);
    $patientName = $pName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("UPDATE patients_list SET is_deleted=1 WHERE id=?")->execute([$id]);
    $actor = $_SESSION['username'] ?? 'Unknown';
    $actor_type = $_SESSION['user_type'] ?? 'staff';
    log_audit($pdo, $actor_type, $actor, 'Deleted Patient from Queue', "Patient: {$patientName}");
    header("Location: patient_list.php?" . buildQueryPreserve());
    exit;
}

if (isset($_POST['restore_patient'])) {
    $id = $_POST['id'];
    $pName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patients_list WHERE id=?");
    $pName->execute([$id]);
    $patientName = $pName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("UPDATE patients_list SET is_deleted=0 WHERE id=?")->execute([$id]);
    $actor = $_SESSION['username'] ?? 'Unknown';
    $actor_type = $_SESSION['user_type'] ?? 'staff';
    log_audit($pdo, $actor_type, $actor, 'Restored Patient to Queue', "Patient: {$patientName}");
    header("Location: patient_list.php?" . buildQueryPreserve());
    exit;
}

if (isset($_POST['refund_patient'])) {
    $id = $_POST['id'];
    $pName = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patients_list WHERE id=?");
    $pName->execute([$id]);
    $patientName = $pName->fetchColumn() ?: 'ID:'.$id;
    $pdo->prepare("DELETE FROM patient_services WHERE patient_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM patients_list WHERE id=?")->execute([$id]);
    $actor = $_SESSION['username'] ?? 'Unknown';
    $actor_type = $_SESSION['user_type'] ?? 'staff';
    log_audit($pdo, $actor_type, $actor, 'Refunded & Removed Patient', "Patient: {$patientName}");
    header("Location: patient_list.php?" . buildQueryPreserve());
    exit;
}

include __DIR__ . '/../miscellaneous/sidebar.php';

$dentists = $pdo->query("SELECT id, first_name, middle_name, last_name FROM dentist_accounts ORDER BY first_name, middle_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

$search = trim($_GET['q'] ?? '');
$dentist_filter = $_GET['dentist'] ?? 'all';
$status_filter = $_GET['status'] ?? 'ALL';
$page = max(1, intval($_GET['page'] ?? 1));
$view = $_GET['view'] ?? 'active';

$where = [];
$params = [];

if ($_SESSION['user_type'] === 'dentist') {
    $where[] = "p.dentist_id = ?";
    $params[] = intval($_SESSION['id']);
}

if ($view === 'deleted') {
    $where[] = "p.is_deleted = 1";
} else {
    $where[] = "p.is_deleted = 0";
}

if ($search !== '') {
    $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($dentist_filter !== 'all' && $dentist_filter !== '') {
    if ($dentist_filter === 'none') {
        $where[] = "p.dentist_id IS NULL";
    } else {
        $where[] = "p.dentist_id = ?";
        $params[] = intval($dentist_filter);
    }
}

if (in_array($status_filter, ['WAITING','ONGOING','TREATED','CANCELLED'])) {
    $where[] = "p.status = ?";
    $params[] = $status_filter;
}

$appt_filter = $_GET['appt'] ?? 'ALL';
if (in_array($appt_filter, ['ONLINE', 'WALK-IN'])) {
    $where[] = "p.type_of_appointment = ?";
    $params[] = $appt_filter;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM patients_list p $where_sql");
$stmt->execute($params);
$totalRows = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $rowsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $rowsPerPage;

$sql = "
    SELECT p.*, d.first_name AS dentist_first, d.middle_name AS dentist_middle, d.last_name AS dentist_last,
           oa.allergies AS online_allergies, oa.age AS online_age
    FROM patients_list p
    LEFT JOIN dentist_accounts d ON p.dentist_id = d.id
    LEFT JOIN online_appointment oa ON (LOWER(TRIM(p.first_name)) = LOWER(TRIM(oa.first_name)) AND LOWER(TRIM(p.last_name)) = LOWER(TRIM(oa.last_name)) AND p.phone_number = oa.phone_number)
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
$idx = 1;
foreach ($params as $pr) $stmt->bindValue($idx++, $pr);
$stmt->bindValue($idx++, (int)$rowsPerPage, PDO::PARAM_INT);
$stmt->bindValue($idx, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getServices(PDO $pdo, int $patient_id) {
    $st = $pdo->prepare("
        SELECT ps.*, s.service_name
        FROM patient_services ps
        JOIN services s ON ps.service_id = s.id
        WHERE ps.patient_id = ?
        ORDER BY ps.id ASC
    ");
    $st->execute([$patient_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient List</title>
<link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
<link rel="stylesheet" href="patient_list_design.css">
<style>
   body {
      background: #f8fafc;
   }
    .main-content {
        padding-bottom: 50px;
        overflow-x: auto;
    }
    .table-wrapper {
        margin-bottom: 20px;
    }
    /* Bagong kulay at design para sa Type at Age badge */
    .badge-patient-new {
        background-color: #dcfce7;
        color: #166534;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        display: inline-block;
        border: 1px solid #bbf7d0;
    }
    .badge-patient-returning {
        background-color: #e0f2fe;
        color: #0369a1;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        display: inline-block;
        border: 1px solid #bae6fd;
    }
    /* Make sure panel doesn't cover small screens awkwardly */
    @media (max-width: 900px) {
        .prescription-panel { right: 10px; width: 220px; top: 70px; }
        .prescription-toggle { right: 10px; top: 40px; }
    }
    /* --- CLEAN & WORKING MOBILE SIDEBAR FIX --- */
@media (max-width: 768px) {
  /* 1. Sidebar Hiding & Slide-out Logic */
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

  /* Kapag pinindot ang menu at pumasok ang .active class */
  .sidebar.active {
    transform: translateX(0) !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
  }

  /* 2. Top Header Adjustments */
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

  /* 3. Main Content Spacing */
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
<div class="main-content" data-no-tbody-refresh="true">
<h2>Patient Queue</h2>

<div style="display: flex; gap: 10px; margin-bottom: 15px;">
    <a href="patient_list.php?<?= buildQueryPreserve(['view' => 'active', 'page' => 1]) ?>" class="btn-add" style="text-decoration: none; <?= $view === 'active' ? '' : 'background-color: #64748b !important;' ?>">Active Patients</a>
    <a href="patient_list.php?<?= buildQueryPreserve(['view' => 'deleted', 'page' => 1]) ?>" class="btn-add" style="text-decoration: none; <?= $view === 'deleted' ? '' : 'background-color: #64748b !important;' ?>">Deleted</a>
</div>

<div class="table-wrapper">
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Patient</th>
      <th>Age</th>
      <th>Type</th>
      <th>Number</th>
      <th>Email</th>
      <th>Schedule</th>
      <th>Dentist</th>
      <th>Allergies</th>
      <th>Appointment Type</th>
      <th>Services</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($patients)): ?>
      <tr><td colspan="13" style="text-align:center;">No patients found.</td></tr>
    <?php endif; ?>

    <?php foreach ($patients as $idx => $p): ?>
    <tr>
      <td data-label="#"><?= $offset + $idx + 1 ?></td>
      <td data-label="Patient">
        <?php
            $m_name = trim($p['middle_name'] ?? '');
            if (strtoupper($m_name) === 'NONE' || $m_name === '') {
                $formatted_patient_name = $p['first_name'] . ' ' . $p['last_name'];
            } else {
                $formatted_patient_name = $p['first_name'] . ' ' . $m_name . ' ' . $p['last_name'];
            }
        ?>
        <strong><?= htmlspecialchars($formatted_patient_name) ?></strong><br>
        <small style="color: #64748b;"><?= htmlspecialchars($p['gender']) ?></small>
      </td>
      <td data-label="Age">
        <?php 
            $age = !empty($p['age']) ? $p['age'] : ($p['online_age'] ?? 'N/A');
            echo htmlspecialchars($age);
        ?>
      </td>
      <td data-label="Type">
        <?php
            $p_type = ucfirst(strtolower($p['patient_type'] ?? 'New'));
            if ($p_type === 'New') {
                echo '<span class="badge-patient-new">New</span>';
            } else {
                echo '<span class="badge-patient-returning">Returning</span>';
            }
        ?>
      </td>
      <td data-label="Phone Number"><?= htmlspecialchars($p['phone_number']) ?></td>
      <td data-label="Email"><?= $p['email'] !== null ? htmlspecialchars($p['email']) : 'N/A' ?></td>
      <td data-label="Schedule"><?= htmlspecialchars($p['date_visit']) ?> @ <?= date('g:i A', strtotime($p['time_visit'])) ?></td>
      <td data-label="Dentist">
        <?php
            $df = $p['dentist_first'] ?? '';
            $dm = strtoupper(trim($p['dentist_middle'] ?? ''));
            $dl = $p['dentist_last'] ?? '';

            if ($dm === 'NONE') $dm = '';

            $dentist_full = trim($df . ' ' . ($dm ? $dm . ' ' : '') . $dl);
            echo htmlspecialchars($dentist_full);
        ?>
      </td>
      <td data-label="Allergies">
        <?php 
            $raw_allergies = !empty($p['allergies']) ? $p['allergies'] : ($p['online_allergies'] ?? '');
            $allergies = trim($raw_allergies);
            
            if ($allergies === '' || strcasecmp($allergies, 'none') === 0) {
                echo '<span style="color: #64748b; font-style: italic;">None</span>';
            } else {
                echo '<span class="allergy-text" style="color: #dc2626; font-weight: 600;">' . htmlspecialchars($allergies) . '</span>';
            }
        ?>
      </td>
      <td data-label="Appointment Type"><?= htmlspecialchars($p['type_of_appointment']) ?></td>
      <td data-label="Services">
        <?php if ($view === 'active'): ?>
          <button class="btn-view" onclick="toggleRow(<?= $p['id'] ?>)">View Services</button>
        <?php else: ?>
          <span style="color: #666; font-style: italic;">Unavailable</span>
        <?php endif; ?>
      </td>
      <td data-label="Status">
        <?php 
          $statusClass = match($p['status']) {
            'WAITING' => 'badge-waiting',
            'ONGOING' => 'badge-ongoing',
            'CANCELLED' => 'badge-cancelled',
            default => 'badge-treated'
          };
        ?>
        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($p['status']) ?></span>
      </td>
      <td data-label="Actions">
        <?php if ($view === 'active'): ?>
          <form method="post" style="display:inline;" id="patListDeleteForm_<?= $p['id'] ?>">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="delete_patient" value="1">
            <button type="button" class="btn-view btn-cancel" onclick="showConfirmDialog('Are you sure you want to delete this patient?', function(){ document.getElementById('patListDeleteForm_<?= $p['id'] ?>').submit(); }, { title: 'Delete Patient', icon: 'delete', danger: true, okText: 'Delete' })">Delete</button>
          </form>

          <?php if (strtoupper(trim($p['status'] ?? '')) === 'TREATED'): ?>
            <br><br>
            <a class="btn-view" href="patient_list.php?print_prescription=1&patient_id=<?= urlencode($p['id']) ?>" target="_blank" style="width:74px !important; height:auto !important; white-space:normal !important; line-height:1.3 !important; padding:6px 8px !important; font-size:12px !important; text-align:center !important;">Print Prescription</a>
          <?php endif; ?>

        <?php else: ?>
          <form method="post" style="display:inline;" id="patListRestoreForm_<?= $p['id'] ?>">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="restore_patient" value="1">
            <button type="button" class="btn-view" onclick="showConfirmDialog('Restore this patient?', function(){ document.getElementById('patListRestoreForm_<?= $p['id'] ?>').submit(); }, { title: 'Restore Patient', icon: 'restore', okText: 'Restore' })">Restore</button>
          </form>
          <form method="post" style="display:inline;" id="patListRefundForm_<?= $p['id'] ?>">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="refund_patient" value="1">
            <button type="button" class="btn-view btn-cancel" title="Refund this patient" onclick="showConfirmDialog('Refund this patient?', function(){ document.getElementById('patListRefundForm_<?= $p['id'] ?>').submit(); }, { title: 'Refund Patient', icon: 'refund', okText: 'Refund' })">Refund</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>

    <?php if ($view === 'active'): ?>
    <tr id="services-<?= $p['id'] ?>" class="expandable" style="display:none;">
      <td colspan="13">
        <strong>Services for <?= htmlspecialchars($formatted_patient_name) ?>:</strong>
        <table class="expandable-table">
          <thead>
            <tr style="background: linear-gradient(180deg, #0ea5e9, #0284c7); color:white;">
              <th>Service</th>
              <th>Status</th>
              <th>Start At</th>
              <th>End At</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $services = getServices($pdo, (int)$p['id']);
            if (empty($services)): ?>
              <tr><td colspan="5" style="text-align:center;">No services assigned.</td></tr>
            <?php else: ?>
              <?php foreach ($services as $s):
                $statusClass = match($s['status']) {
                  'PENDING' => 'service-pending',
                  'ONGOING' => 'service-ongoing',
                  'DONE' => 'service-done',
                  'CANCELLED' => 'service-cancelled',
                  default => ''
                };
              ?>
              <tr>
                <td data-label="Service"><?= htmlspecialchars($s['service_name']) ?></td>
                <td data-label="Status"><span class="service-badge <?= $statusClass ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                <td data-label="Start At"><?= htmlspecialchars($s['date_start'] ?? 'N/A') ?></td>
                <td data-label="End At"><?= htmlspecialchars($s['date_end'] ?? 'N/A') ?></td>
                <td data-label="Action">
                  <form method="post" style="display:flex; gap:5px; align-items:center;">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <select name="new_status" style="padding:4px; border-radius:4px; border:1px solid #cbd5e1;">
                      <option value="ONGOING" <?= $s['status'] === 'ONGOING' ? 'selected' : '' ?>>Ongoing</option>
                      <option value="DONE" <?= $s['status'] === 'DONE' ? 'selected' : '' ?>>Done</option>
                      <option value="CANCELLED" <?= $s['status'] === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button type="submit" name="update_service_status" class="btn-view" style="background:#0ea5e9; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">Update</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Pagination Controls -->
<div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="patient_list.php?<?= buildQueryPreserve(['page' => $i]) ?>" class="btn-add" style="text-decoration: none; padding: 6px 12px; <?= $i === $page ? 'background-color: #0284c7;' : 'background-color: #64748b;' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>

</div>

<script>
function toggleRow(id) {
    const allRows = document.querySelectorAll('tr.expandable');
    allRows.forEach(r => {
        if (r.id !== 'services-' + id) {
            r.classList.remove('row-open');
            r.style.display = 'none';
        }
    });
    const row = document.getElementById('services-' + id);
    if (row) {
        if (row.classList.contains('row-open')) {
            row.classList.remove('row-open');
            row.style.display = 'none';
        } else {
            row.classList.add('row-open');
            row.style.display = 'table-row';
        }
    }
}
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
</script>
</body>
</html>

