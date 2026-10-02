<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';
include __DIR__ . '/../miscellaneous/log_audit.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include __DIR__ . '/../PHPMailer/src/Exception.php';
include __DIR__ . '/../PHPMailer/src/PHPMailer.php';
include __DIR__ . '/../PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'])) {

    $id = $_POST['id'];
    $action = $_POST['action'];

    $stmtEmail = $pdo->prepare("
        SELECT oa.gmail, oa.first_name, oa.last_name, oa.date_visit, oa.time_visit, oa.phone_number,
               CONCAT(d.first_name, ' ', d.last_name) as dentist_name
        FROM online_appointment oa
        LEFT JOIN dentist_accounts d ON oa.dentist_id = d.id
        WHERE oa.id = ?
    ");
    $stmtEmail->execute([$id]);
    $info = $stmtEmail->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        echo "ERROR: Appointment not found";
        exit;
    }

    $email = $info['gmail'];
    $phoneNumber = $info['phone_number'];
    $fullName = $info['first_name'] . " " . $info['last_name'];
    $dateVisit = $info['date_visit'];
    $timeVisit = date("h:i A", strtotime($info['time_visit']));
    $dentistName = $info['dentist_name'] ?? 'TBA';

    $smsMessage = "";
    $emailSubject = "";
    $emailBody = "";

    try {
        $pdo->beginTransaction();

        if ($action === "APPROVE") {
            // 1. Update online_appointment status
            $stmt = $pdo->prepare("UPDATE online_appointment SET status = 'APPROVE' WHERE id = ?");

            // 2. Check for an existing patient record to prevent duplicates on double-approve
            $checkDuplicate = $pdo->prepare("
                SELECT COUNT(*) FROM patients_list pl
                JOIN online_appointment oa ON (
                    LOWER(TRIM(pl.first_name))  = LOWER(TRIM(oa.first_name))  AND
                    LOWER(TRIM(pl.last_name))   = LOWER(TRIM(oa.last_name))   AND
                    pl.phone_number             = oa.phone_number              AND
                    pl.date_visit               = oa.date_visit               AND
                    pl.time_visit               = oa.time_visit               AND
                    pl.type_of_appointment      = 'ONLINE'
                )
                WHERE oa.id = ?
            ");
            $checkDuplicate->execute([$id]);
            $alreadyExists = (int)$checkDuplicate->fetchColumn() > 0;

            if ($alreadyExists) {
                // Patient already inserted (e.g. double-click / duplicate approve) — skip insert
                $patientId = null;
                $serviceNames = [];
                $servicesText = '';
            } else {
                // 3. Insert into patients_list
                $insertPatient = $pdo->prepare("
                    INSERT INTO patients_list 
                    (first_name, last_name, age, gender, email, phone_number, date_visit, time_visit, dentist_id, type_of_appointment, status)
                    SELECT first_name, last_name, age, gender, gmail, phone_number, date_visit, time_visit, dentist_id, 'ONLINE', 'WAITING'
                    FROM online_appointment
                    WHERE id = ?
                ");
                $insertPatient->execute([$id]);

                // Get newly inserted patient ID
                $patientId = $pdo->lastInsertId();

                // 4. Copy appointment services -> patient_services
                $services = $pdo->prepare("
                    SELECT oas.service_id, s.service_name 
                    FROM online_appointment_services oas
                    JOIN services s ON oas.service_id = s.id
                    WHERE oas.appointment_id = ?
                ");
                $services->execute([$id]);
                $servicesList = $services->fetchAll(PDO::FETCH_ASSOC);

                $insertService = $pdo->prepare("
                    INSERT INTO patient_services (patient_id, service_id, status)
                    VALUES (?, ?, 'PENDING')
                ");

                $serviceNames = [];
                foreach ($servicesList as $s) {
                    $insertService->execute([$patientId, $s['service_id']]);
                    $serviceNames[] = $s['service_name'];
                }
                $servicesText = implode(", ", $serviceNames);
            } // end if (!$alreadyExists)

            // Notification Payload (sent regardless of whether patient was newly inserted)
            $smsMessage = "Good day $fullName! Your appointment at Mariategue Ortho-Dental Clinic has been APPROVED. Schedule: $dateVisit at $timeVisit with Dr. $dentistName. Please arrive 10-15 mins early. Thank you!";

            $emailSubject = "Appointment Confirmed - Mariategue Ortho-Dental Clinic";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background-color: #ffffff;'>
                <div style='background-color: #007bff; color: #ffffff; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 22px;'>Mariategue Ortho-Dental Clinic</h2>
                </div>
                <div style='padding: 25px; color: #333333; line-height: 1.6;'>
                    <p style='font-size: 16px;'>Magandang araw, <b>$fullName</b>!</p>
                    <p>Ikinagagalak naming ipaalam na <b>APPROVED</b> na ang iyong appointment request.</p>
                    
                    <div style='background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <h3 style='margin-top: 0; color: #007bff; font-size: 16px;'>Detalye ng Appointment:</h3>
                        <p style='margin: 5px 0;'><b>Petsa:</b> $dateVisit</p>
                        <p style='margin: 5px 0;'><b>Oras:</b> $timeVisit</p>
                        <p style='margin: 5px 0;'><b>Dentista:</b> Dr. $dentistName</p>
                        <p style='margin: 5px 0;'><b>Serbisyo:</b> $servicesText</p>
                    </div>

                    <p style='font-size: 14px; color: #666666;'><i>Paalala: Mangyaring dumating 10–15 minuto bago ang iyong nakatakdang oras.</i></p>
                    <p style='margin-top: 25px;'>Maraming salamat at makikita ka namin sa klinik!</p>
                </div>
                <div style='background-color: #f1f1f1; color: #777777; padding: 15px; text-align: center; font-size: 12px;'>
                    &copy; " . date('Y') . " Mariategue Ortho-Dental Clinic. All rights reserved.
                </div>
            </div>";

        } elseif ($action === "CANCEL") {
            $reason = $_POST['reason'] ?? '';
            $stmt = $pdo->prepare("UPDATE online_appointment SET status='CANCELLED' WHERE id=?");

            $deletePatientServices = $pdo->prepare("
                DELETE FROM patient_services 
                WHERE patient_id IN (
                    SELECT id FROM patients_list 
                    WHERE email = ? AND date_visit = ? AND time_visit = ? AND type_of_appointment = 'ONLINE'
                )
            ");
            $deletePatientServices->execute([$email, $dateVisit, $info['time_visit']]);

            $deletePatient = $pdo->prepare("
                DELETE FROM patients_list 
                WHERE email = ? AND date_visit = ? AND time_visit = ? AND type_of_appointment = 'ONLINE'
            ");
            $deletePatient->execute([$email, $dateVisit, $info['time_visit']]);

            $reasonClean = htmlspecialchars(trim($reason));
            $reasonTextEmail = !empty($reasonClean) ? "<div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; color: #856404;'><b>Dahilan ng Cancellation:</b> $reasonClean</div>" : "";
            $reasonTextSMS = !empty($reasonClean) ? " Reason: $reasonClean." : "";

            $smsMessage = "Hello $fullName, your appointment at Mariategue Ortho-Dental Clinic scheduled for $dateVisit has been CANCELLED.$reasonTextSMS You may reschedule anytime on our website. Thank you.";

            $emailSubject = "Appointment Cancellation - Mariategue Ortho-Dental Clinic";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background-color: #ffffff;'>
                <div style='background-color: #dc3545; color: #ffffff; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 22px;'>Mariategue Ortho-Dental Clinic</h2>
                </div>
                <div style='padding: 25px; color: #333333; line-height: 1.6;'>
                    <p style='font-size: 16px;'>Kumusta, <b>$fullName</b>,</p>
                    <p>Ipinapaalam namin na ang iyong appointment ay na-<b>CANCEL</b>.</p>
                    $reasonTextEmail
                    <p>Maaari kang mag-schedule ulit ng panibagong appointment sa aming website anumang oras na available ka.</p>
                    <p style='margin-top: 25px;'>Salamat sa iyong pag-unawa.</p>
                </div>
                <div style='background-color: #f1f1f1; color: #777777; padding: 15px; text-align: center; font-size: 12px;'>
                    &copy; " . date('Y') . " Mariategue Ortho-Dental Clinic. All rights reserved.
                </div>
            </div>";

        } elseif ($action === "RESTORE") {
            $stmt = $pdo->prepare("UPDATE online_appointment SET status='PENDING' WHERE id=?");
            $emailSubject = "Appointment Restored - Mariategue Ortho-Dental Clinic";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background-color: #ffffff;'>
                <div style='background-color: #17a2b8; color: #ffffff; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 22px;'>Mariategue Ortho-Dental Clinic</h2>
                </div>
                <div style='padding: 25px; color: #333333; line-height: 1.6;'>
                    <p style='font-size: 16px;'>Kumusta, <b>$fullName</b>,</p>
                    <p>Ang iyong dating na-cancel na appointment ay <b>NAIBALIK (Restored)</b> na at kasalukuyang nakatala uli bilang <b>Pending Approval</b>.</p>
                    <p>Magpapadala kami ng panibagong email kapag na-review at na-approve na ito ng clinic administration.</p>
                    <p style='margin-top: 25px;'>Maraming salamat!</p>
                </div>
            </div>";

        } elseif ($action === "REFUND") {
            $stmt = $pdo->prepare("UPDATE online_appointment SET status='REFUNDED' WHERE id=?");
            $emailSubject = "Refund Process Status - Mariategue Ortho-Dental Clinic";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background-color: #ffffff;'>
                <div style='background-color: #6c757d; color: #ffffff; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 22px;'>Mariategue Ortho-Dental Clinic</h2>
                </div>
                <div style='padding: 25px; color: #333333; line-height: 1.6;'>
                    <p style='font-size: 16px;'>Kumusta, <b>$fullName</b>,</p>
                    <p>Your appointment is currently marked for <b>REFUND</b>.</p>
                    <p>Kasalukuyan na naming pinoproseso ang pagbabalik ng iyong naibayad na pera.</p>
                    <p style='margin-top: 25px;'>Salamat sa iyong paghihintay at pag-unawa.</p>
                </div>
            </div>";

        } else {
            echo "Invalid action";
            exit;
        }

        $stmt->execute([$id]);

        $pdo->commit();

        // --- PHPMailer Execution ---
        // Wrapped in its own try-catch: email failure must NEVER cancel a
        // successfully-committed DB action (Cancel / Approve / Restore / Refund).
        if (!empty($emailSubject) && !empty($emailBody)) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USER') ?: 'beanchcanonego1212@gmail.com';
                $mail->Password   = 'xksi pzdv avxp clby';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                // Fail fast: 10-second connect + command timeout
                $mail->Timeout    = 10;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ];

                $mail->setFrom($mail->Username, 'Mariategue Ortho-Dental Clinic');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = $emailSubject;
                $mail->Body    = $emailBody;
                $mail->send();
            } catch (\Exception $mailEx) {
                // Log the failure but do NOT surface it to the user.
                error_log('Appointment email failed (action=' . $action . ', id=' . $id . '): ' . $mailEx->getMessage());
            }
        }

        // --- Textbee.dev API (SMS Execution) ---
        // Also wrapped: SMS failure must NOT revert the already-committed action.
        if (!empty($smsMessage) && !empty($phoneNumber)) {
            try {
                $textbeeApiKey   = 'txb_dOnYlM9EF7f8RqwdTHpshiL4JXRdm6A2';
                $textbeeDeviceId = '6a9de725ccb6c7270925a793';

                $payload = json_encode([
                    'recipients' => [$phoneNumber],
                    'message'    => $smsMessage
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.textbee.dev/api/v1/gateway/devices/{$textbeeDeviceId}/send-sms");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'x-api-key: ' . $textbeeApiKey,
                    'Content-Type: application/json'
                ]);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $smsEx) {
                error_log('SMS failed (action=' . $action . ', id=' . $id . '): ' . $smsEx->getMessage());
            }
        }

        // ── Audit log ──
        $actor      = $_SESSION['username'] ?? 'Unknown';
        $actor_type = $_SESSION['user_type'] ?? 'staff';
        $actionLabel = match($action) {
            'APPROVE'  => 'Approved Online Appointment',
            'CANCEL'   => 'Cancelled Online Appointment',
            'RESTORE'  => 'Restored Online Appointment',
            'REFUND'   => 'Refunded Online Appointment',
            default    => $action . ' Online Appointment',
        };
        log_audit($pdo, $actor_type, $actor, $actionLabel, "Patient: {$fullName} | Date: {$dateVisit} at {$timeVisit}");

    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "ERROR: " . $e->getMessage();
        exit;
    }

    echo "SUCCESS";
    exit;
}

include __DIR__ . '/../miscellaneous/sidebar.php';

// Search and Sort Logic
$search    = $_GET['search'] ?? '';
$sort      = $_GET['sort'] ?? 'created_at';
$order     = $_GET['order'] ?? 'DESC';
$activeTab = $_GET['tab'] ?? 'pending';

$allowed_sorts = ['created_at', 'date_visit', 'full_name', 'gmail', 'age', 'gender', 'dentist_name', 'payment_method', 'service_name'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'created_at';
}

$dentists_list = $pdo->query("SELECT id, first_name, last_name FROM dentist_accounts ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$services_list = $pdo->query("SELECT id, service_name FROM services WHERE status = 'enable' ORDER BY service_name ASC")->fetchAll(PDO::FETCH_ASSOC);

function fetchAppointments($pdo, $status, $search, $sort, $order) {
    $sql = "
        SELECT oa.*, 
               CONCAT(oa.first_name, ' ', oa.last_name) AS full_name,
               CONCAT(d.first_name, ' ', d.last_name) AS dentist_name
        FROM online_appointment oa
        LEFT JOIN dentist_accounts d ON oa.dentist_id = d.id
        WHERE ";

    $params = [];
    if (is_array($status)) {
        $placeholders = [];
        foreach ($status as $i => $s) {
            $key = 'status' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $s;
        }
        $sql .= "oa.status IN (" . implode(',', $placeholders) . ")";
    } else {
        $sql .= "oa.status = :status";
        $params['status'] = $status;
    }

    if ($search) {
        $sql .= " AND (oa.first_name LIKE :search OR oa.last_name LIKE :search OR oa.gmail LIKE :search OR oa.phone_number LIKE :search OR oa.gender LIKE :search OR CONCAT(d.first_name, ' ', d.last_name) LIKE :search)";
        $params['search'] = "%$search%";
    }

    if ($sort === 'gender') {
        if ($order === 'MALE' || $order === 'FEMALE') {
            $sql .= " AND oa.gender = :genderVal";
            $params['genderVal'] = $order;
        }
        $sql .= " ORDER BY oa.created_at DESC";
        
    } elseif ($sort === 'dentist_name') {
        if (is_numeric($order)) {
            $sql .= " AND oa.dentist_id = :dentistVal";
            $params['dentistVal'] = $order;
        }
        $sql .= " ORDER BY oa.created_at DESC";

    } elseif ($sort === 'payment_method') {
        if (in_array($order, ['Cash', 'Online', 'Down Payment'])) {
            $sql .= " AND oa.payment_method = :paymentVal";
            $params['paymentVal'] = $order;
        }
        $sql .= " ORDER BY oa.created_at DESC";

    } elseif ($sort === 'service_name') {
        if (is_numeric($order)) {
            $sql .= " AND EXISTS (SELECT 1 FROM online_appointment_services oas WHERE oas.appointment_id = oa.id AND oas.service_id = :serviceVal)";
            $params['serviceVal'] = $order;
        }
        $sql .= " ORDER BY oa.created_at DESC";

    } else {
        $sortMap = [
            'created_at' => 'oa.created_at',
            'date_visit' => 'oa.date_visit',
            'full_name'  => 'full_name',
            'gmail'      => 'oa.gmail',
            'age'        => 'oa.age',
        ];
        $orderBy = $sortMap[$sort] ?? 'oa.created_at';
        $dir = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderBy $dir";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pending   = fetchAppointments($pdo, 'PENDING', $search, $sort, $order);
$approved  = fetchAppointments($pdo, 'APPROVE', $search, $sort, $order);
$cancelled = fetchAppointments($pdo, ['CANCELLED', 'REFUNDED'], $search, $sort, $order);

$service_stmt = $pdo->prepare("
    SELECT s.service_name, s.price 
    FROM online_appointment_services oas
    JOIN services s ON oas.service_id = s.id
    WHERE oas.appointment_id = ?
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Appointments</title>
    <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
    <link rel="stylesheet" href="online_appointment_design.css">

    <style>
        body {
            background-image: none !important;
        }
        .btn-add {
            height: 38px;
            padding: 0 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            box-sizing: border-box;
        }
        .btn-approve, .btn-cancel {
            width: 85px;
            text-align: center;
            padding: 6px 0;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            color: white !important;
        }
        .btn-cancel {
            background-color: #dc3545 !important;
        }
        .btn-cancel:hover {
            background-color: #c82333 !important;
        }
        .btn-approve {
            background-color: #22c55e;
        }
        .btn-approve:hover {
            background-color: #16a34a;
        }
        /* Override sidebar_design.css .modal backdrop-filter which causes
           GPU layer thrashing and UI flicker/crash on button hover */
        .modal {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            background: rgba(0, 0, 0, 0.5) !important;
        }
        /* Pause the spinner animation when overlay is hidden to reduce GPU load */
        #loadingOverlay[style*="display: none"] .loader,
        #loadingOverlay:not([style*="flex"]) .loader {
            animation-play-state: paused;
        }
 /* --- FIX PARA SA MOBILE TOPBAR AT HEADER OVERLAP --- */
@media (max-width: 768px) {
  .topbar {
    padding: 10px 15px !important;
    height: auto !important; /* Hinahayaan itong lumaki nang pahaba kung kinakailangan */
    min-height: 60px;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
  }

  .topbar-left {
    gap: 8px !important;
    flex: 1;
    min-width: 0;
  }

  /* Pinaliliit ang font ng logo para hindi sumabog sa mobile */
  .logo, .topbar .logo {
    font-size: 15px !important;
    letter-spacing: -0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Inaayos ang "Welcome, CristinaAdmin!" para hindi bumangga */
  .topbar div:last-child, 
  .topbar span, 
  .topbar h3 {
    font-size: 0.75rem !important;
    white-space: nowrap;
  }

  /* Dinadagdagan ang margin-top ng main content para hindi matakpan ng lumaking topbar */
  .main-content {
    margin-top: 80px !important;
    padding: 10px !important;
  }
}
/* --- TAMA AT LIGTAS NA MOBILE SIDEBAR HIDING --- */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: -280px !important; /* Siguraduhing sapat ang laki para maitago ito nang tuluyan sa kaliwa */
    width: 260px;
    height: 100vh;
    z-index: 1000;
    transition: left 0.3s ease-in-out !important;
  }

  /* Kapag may class na active (binuksan na), saka lang siya lilitaw */
  .sidebar.active {
    left: 0 !important;
  }
}
    </style>
</head>
<body>
    
<div id="loadingOverlay">
    <div class="loader"></div>
</div>

<div class="main-content">
    <h2 id="pageTitle">Online Appointments</h2>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 5px;">
            <button class="btn-add" onclick="showTab('pending')">Pending</button>
            <button class="btn-add" onclick="showTab('approved')">Approved</button>
            <button class="btn-add" onclick="showTab('cancelled')">Cancelled</button>
        </div>
        
        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="tab" id="activeTabInput" value="<?= htmlspecialchars($activeTab) ?>">
            <div style="display: flex;">
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px 0 0 4px; border-right: none; height: 38px; box-sizing: border-box; width: 300px;">
                <button type="submit" style="padding: 0 12px; background-color: #0ea5e9; color: white; border: 1px solid #0ea5e9; border-radius: 0 4px 4px 0; cursor: pointer; height: 38px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
            
            <select name="sort" id="sortSelect" onchange="updateSecondDropdown()" style="padding: 0 8px; border: 1px solid #ccc; border-radius: 4px; height: 38px; box-sizing: border-box;">
                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                <option value="date_visit" <?= $sort === 'date_visit' ? 'selected' : '' ?>>Date Visit</option>
                <option value="full_name" <?= $sort === 'full_name' ? 'selected' : '' ?>>Patient Name</option>
                <option value="gmail" <?= $sort === 'gmail' ? 'selected' : '' ?>>Email</option>
                <option value="age" <?= $sort === 'age' ? 'selected' : '' ?>>Age</option>
                <option value="gender" <?= $sort === 'gender' ? 'selected' : '' ?>>Gender</option>
                <option value="dentist_name" <?= $sort === 'dentist_name' ? 'selected' : '' ?>>Dentist</option>
                <option value="payment_method" <?= $sort === 'payment_method' ? 'selected' : '' ?>>Payment Method</option>
                <option value="service_name" <?= $sort === 'service_name' ? 'selected' : '' ?>>Service</option>
            </select>
            <select name="order" id="orderSelect" style="padding: 0 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 150px; height: 38px; box-sizing: border-box;">
            </select>
            
            <button type="submit" style="padding: 0 20px; background-color: #0ea5e9; color: white; border: 1px solid #0ea5e9; border-radius: 4px; cursor: pointer; height: 38px; box-sizing: border-box; font-weight: 500; display: inline-flex; align-items: center; justify-content: center;">Filter</button>
            <?php if($search || $sort !== 'created_at' || $order !== 'DESC'): ?>
                <a href="online_appointment.php" style="padding: 0 20px; background-color: #64748b; color: white; text-decoration: none; border: 1px solid #64748b; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; height: 38px; box-sizing: border-box; font-weight: 500;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div id="pending" class="tab-content">
        <?php include 'table_template.php'; ?>
    </div>

    <div id="approved" class="tab-content">
        <?php include 'table_template_approved.php'; ?>
    </div>

    <div id="cancelled" class="tab-content">
        <?php include 'table_template_cancelled.php'; ?>
    </div>
</div>

<script>
    const dentists = <?= json_encode($dentists_list) ?>;
    const services = <?= json_encode($services_list) ?>;
    const currentOrder = '<?= htmlspecialchars($order) ?>';

    window.onload = function() {
        showTab('<?= $activeTab ?>');
        updateSecondDropdown(currentOrder);
    };

    function showTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(div => {
            div.style.display = 'none';
        });
        document.getElementById(tabId).style.display = 'block';
        
        const tabInput = document.getElementById('activeTabInput');
        if(tabInput) tabInput.value = tabId;

        const title = document.getElementById("pageTitle");
        if (tabId === "pending") {
            title.textContent = "Online Appointments";
        } else if (tabId === "approved") {
            title.textContent = "Approved Appointments";
        } else if (tabId === "cancelled") {
            title.textContent = "Cancelled Appointments";
        }
    }

    function updateSecondDropdown(selectedValue = null) {
        const sortSelect = document.getElementById('sortSelect');
        const orderSelect = document.getElementById('orderSelect');
        const selected = sortSelect.value;

        orderSelect.innerHTML = '';

        function addOption(val, text) {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = text;
            if (selectedValue && String(val) === String(selectedValue)) {
                opt.selected = true;
            }
            orderSelect.appendChild(opt);
        }

        if (selected === 'gender') {
            addOption('MALE', 'Male');
            addOption('FEMALE', 'Female');
        } else if (selected === 'payment_method') {
            addOption('Cash', 'Cash');
            addOption('Online', 'Online');
            addOption('Down Payment', 'Down Payment');
        } else if (selected === 'service_name') {
            if (services.length > 0) {
                services.forEach(s => {
                    addOption(s.id, s.service_name);
                });
            } else {
                addOption('', 'No Services Found');
            }
        } else if (selected === 'dentist_name') {
            if (dentists.length > 0) {
                dentists.forEach(d => {
                    addOption(d.id, d.first_name + ' ' + d.last_name);
                });
            } else {
                addOption('', 'No Dentists Found');
            }
        } else {
            if (selected === 'age') {
                addOption('DESC', 'Oldest First');
                addOption('ASC', 'Youngest First');
            } else if (['full_name', 'gmail'].includes(selected)) {
                addOption('ASC', 'A-Z (Ascending)');
                addOption('DESC', 'Z-A (Descending)');
            } else {
                addOption('DESC', 'Newest First');
                addOption('ASC', 'Oldest First');
            }
        }
    }

    function handleAction(id, action) {
        let msg = "";
        let reason = "";
        if (action === "APPROVE") {
            msg = "Are you sure you want to APPROVE this appointment?";
        } else if (action === "CANCEL") {
            reason = prompt("Are you sure you want to CANCEL this appointment?\n\nPlease enter a reason (optional):");
            if (reason === null) return;
        } else if (action === "RESTORE") {
            msg = "Are you sure you want to RESTORE this appointment?";
        } else if (action === "REFUND") {
            msg = "Are you sure you want to process a REFUND for this appointment?";
        }

        function doAction() {
            // Show loading overlay ONLY after user confirms — prevents hover flicker
            const overlay = document.getElementById("loadingOverlay");
            overlay.style.display = "flex";
            document.body.classList.add("disable-clicks");

            let formData = new FormData();
            formData.append("id", id);
            formData.append("action", action);
            if (action === "CANCEL") {
                formData.append("reason", reason);
            }

            fetch("", { method: "POST", body: formData })
                .then(response => response.text())
                .then(result => {
                    overlay.style.display = "none";
                    document.body.classList.remove("disable-clicks");

                    if (result.trim() === "SUCCESS") {
                        showAlert(action + " successful!");
                        location.reload();
                    } else {
                        showAlert("Error: " + result);
                    }
                })
                .catch(err => {
                    overlay.style.display = "none";
                    document.body.classList.remove("disable-clicks");
                    showAlert("Request error: " + err);
                });
        }

        if (action === "CANCEL") {
            doAction();
        } else {
            const isDanger = (action === "REFUND");
            showConfirmDialog(msg, doAction, {
                title: action.charAt(0) + action.slice(1).toLowerCase() + ' Appointment',
                icon: action === "APPROVE" ? 'approve' : action === "RESTORE" ? 'restore' : 'refund',
                danger: isDanger,
                okText: action.charAt(0) + action.slice(1).toLowerCase()
            });
        }
    }
</script>
</body>
</html>

