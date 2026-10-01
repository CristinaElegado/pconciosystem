<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
include __DIR__ . '/../PHPMailer/src/Exception.php';
include __DIR__ . '/../PHPMailer/src/PHPMailer.php';
include __DIR__ . '/../PHPMailer/src/SMTP.php';

// Restrict access for Staff, Dentist, and Admin
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'staff') {
        header("Location: ../online_appointment/online_appointment.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'dentist') {
        header("Location: ../patient_list/patient_list.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'admin') {
        header("Location: ../dashboard/dashboard.php");
        exit;
    }
}

// Enforce login
if (!isset($_SESSION['user_name']) || !isset($_SESSION['user_email'])) {
    header("Location: pconcio_main.php?showLogin=1");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {

    // --- BASIC FIELDS ---
    $first_name   = htmlspecialchars(trim($_POST['firstname'] ?? ''));
    $last_name    = htmlspecialchars(trim($_POST['lastname'] ?? ''));
    $patient_type = htmlspecialchars(trim($_POST['patient_type'] ?? 'New')); 
    $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone        = preg_replace('/[^0-9]/', '', $_POST['phone_number'] ?? '');
    $age          = (int)($_POST['age'] ?? 0);
    $gender       = htmlspecialchars(trim($_POST['gender'] ?? ''));
    $allergies    = htmlspecialchars(trim($_POST['allergies'] ?? 'None')); // Dagdag para sa Allergies
    $date_visit   = htmlspecialchars(trim($_POST['date'] ?? ''));
    $time_visit   = htmlspecialchars(trim($_POST['time_slot'] ?? ''));
    $dentist_id   = (int)($_POST['dentist'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    
    // HMO Fields
    $hmo_id = ($payment_method === 'HMO') ? (int)($_POST['hmo_id'] ?? 0) : null;
    $hmo_member_id = ($payment_method === 'HMO') ? htmlspecialchars(trim($_POST['hmo_member_id'] ?? '')) : null;

    // Validate patient_type value
    if (!in_array($patient_type, ['New', 'Returning'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid patient type selected.']);
        exit;
    }

    // Validate HMO Member ID length if HMO is selected
    if ($payment_method === 'HMO' && strlen($hmo_member_id) > 30) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'HMO Member ID / Policy Number cannot exceed 30 characters.']);
        exit;
    }

    $proof_of_payment = null;
    $dental_toothxray_path = null;
    $hmo_id_image_path = null;

    // Parse service ids early so we can decide if X-Ray is required
    $service_ids_raw = $_POST['service_ids'] ?? '';
    $service_ids = array_filter(array_map('intval', explode(",", $service_ids_raw)));

    // Validate that at least one service selected
    if (empty($service_ids)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please select at least one service.']);
        exit;
    }

    // Determine X-Ray requirements for selected services
    $requires_xray = false;

    try {
        $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
        $stmtReq = $pdo->prepare("SELECT xray_requirement, COUNT(*) as cnt FROM services WHERE id IN ($placeholders) GROUP BY xray_requirement");
        $stmtReq->execute($service_ids);
        while ($r = $stmtReq->fetch(PDO::FETCH_ASSOC)) {
            $xr = $r['xray_requirement'] ?? 'none';
            if ($xr === 'required') $requires_xray = true;
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error reading service requirements: ' . $e->getMessage()]);
        exit;
    }

    if ($requires_xray) {
        if (!isset($_FILES['dental_toothxray']) || $_FILES['dental_toothxray']['error'] !== UPLOAD_ERR_OK) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'A Dental Tooth/X-Ray image is required for the selected service.']);
            exit;
        }
    }

    // Handle Dental X-Ray Upload
    if (isset($_FILES['dental_toothxray']) && $_FILES['dental_toothxray']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['dental_toothxray']['tmp_name'];
        $fileName    = $_FILES['dental_toothxray']['name'];
        $fileSize    = $_FILES['dental_toothxray']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid file format for X-Ray. Mangyaring mag-upload ng JPG, PNG, o WEBP na imahe.']);
            exit;
        }

        if ($fileSize > 5 * 1024 * 1024) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'X-Ray file size must not exceed 5MB.']);
            exit;
        }

        $uploadFileDir = __DIR__ . '/../uploads/xrays/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        $newFileName = uniqid('xray_', true) . '.' . $fileExtension;
        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $dental_toothxray_path = $newFileName;
        }
    }

    // Handle HMO ID Image Upload kung HMO ang napili
    if ($payment_method === 'HMO') {
        if (empty($hmo_id) || empty($hmo_member_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please fill in all HMO details (Provider and Member ID).']);
            exit;
        }

        if (!isset($_FILES['hmo_id_image']) || $_FILES['hmo_id_image']['error'] !== UPLOAD_ERR_OK) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please attach a photo of your HMO ID/Card.']);
            exit;
        }

        $hmoFileTmp = $_FILES['hmo_id_image']['tmp_name'];
        $hmoFileName = $_FILES['hmo_id_image']['name'];
        $hmoFileSize = $_FILES['hmo_id_image']['size'];
        $hmoFileExt  = strtolower(pathinfo($hmoFileName, PATHINFO_EXTENSION));

        $allowedHmoExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!in_array($hmoFileExt, $allowedHmoExts)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid HMO ID format. Gumamit ng JPG, PNG, WEBP, o PDF.']);
            exit;
        }

        if ($hmoFileSize > 5 * 1024 * 1024) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'HMO ID file size must not exceed 5MB.']);
            exit;
        }

        $hmoUploadDir = __DIR__ . '/../uploads/hmo_ids/';
        if (!is_dir($hmoUploadDir)) {
            mkdir($hmoUploadDir, 0755, true);
        }

        $newHmoFileName = uniqid('hmo_id_', true) . '.' . $hmoFileExt;
        $hmoDestPath = $hmoUploadDir . $newHmoFileName;

        if (move_uploaded_file($hmoFileTmp, $hmoDestPath)) {
            $hmo_id_image_path = $newHmoFileName;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Nagkaroon ng problema sa pag-save ng HMO ID file.']);
            exit;
        }
    }

    // Standard Validations
    if (strlen($first_name) > 100 || strlen($last_name) > 100) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Name fields cannot exceed 100 characters.']);
        exit;
    }

    if ($age <= 0 || $age > 150) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please enter a valid age.']);
        exit;
    }

    if (strlen($phone) !== 11 || !preg_match('/^09\d{9}$/', $phone)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits and start with 09.']);
        exit;
    }

    if (!in_array($payment_method, ['Cash', 'Online', 'Down Payment', 'HMO'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid payment method selected.']);
        exit;
    }

    // Calculate total amount
    $clean_service_ids = $service_ids;
    if (!empty($clean_service_ids)) {
        $placeholders = implode(',', array_fill(0, count($clean_service_ids), '?'));
        $stmtPrice = $pdo->prepare("SELECT SUM(price) FROM services WHERE id IN ($placeholders)");
        $stmtPrice->execute(array_values($clean_service_ids));
        $total_price = $stmtPrice->fetchColumn();
        if ($total_price === false) $total_price = 0;
    } else {
        $total_price = 0;
    }

    $checkout_url = null;

    // Handle Online Payment via PayMongo
    if ($payment_method === 'Online' || $payment_method === 'Down Payment') {
        $amount_to_pay = ($payment_method === 'Down Payment') ? ($total_price / 2) : $total_price;

        $ch = curl_init('https://api.paymongo.com/v1/links');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PconcioDental/1.0');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode((getenv('STRIPE_SECRET_KEY') ?: 'sk_test_REPLACE_WITH_YOUR_KEY') . ':')
        ]);
        $payload = json_encode(['data' => ['attributes' => [
            'amount' => intval($amount_to_pay * 100),
            'description' => "Dental Appointment for $first_name $last_name" . ($payment_method === 'Down Payment' ? ' (Down Payment)' : ' (Full Payment)')
        ]]]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);

        if (isset($result['data']['attributes']['checkout_url'])) {
            $checkout_url = $result['data']['attributes']['checkout_url'];
            $proof_of_payment = "PayMongo Link ID: " . $result['data']['id'];
        } else {
             $msg = isset($result['errors'][0]['detail']) ? $result['errors'][0]['detail'] : 'Failed to initialize payment gateway.';
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => $msg]);
             exit;
        }
    }

    try {
        $dayName = strtolower(date('l', strtotime($date_visit)));

        // Check availability
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM online_appointment WHERE date_visit = :dv AND time_visit = :tv AND status = 'PENDING') +
                (SELECT COUNT(*) FROM patients_list WHERE date_visit = :dv AND time_visit = :tv AND status IN ('WAITING', 'ONGOING')) AS total_booked,
                (SELECT COUNT(*) FROM (
                    SELECT id FROM online_appointment WHERE date_visit = :dv AND time_visit = :tv AND dentist_id = :did AND status = 'PENDING'
                    UNION ALL
                    SELECT id FROM patients_list WHERE date_visit = :dv AND time_visit = :tv AND dentist_id = :did AND status IN ('WAITING', 'ONGOING')
                ) t) AS dentist_booked,
                (SELECT COUNT(*) FROM dentist_schedule 
                 WHERE dentist_id = :did AND {$dayName} = 1 
                 AND STR_TO_DATE(:tv, '%H:%i') >= {$dayName}_start 
                 AND STR_TO_DATE(:tv, '%H:%i') < {$dayName}_end) AS is_scheduled
        ");
        $stmt->execute(['dv' => $date_visit, 'tv' => $time_visit, 'did' => $dentist_id]);
        $availability = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($availability['total_booked'] >= 2) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'This time slot is already full.']);
            exit;
        }
        if ($availability['dentist_booked'] > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'The selected dentist is already booked for this time slot.']);
            exit;
        }
        if ($availability['is_scheduled'] == 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'The selected dentist is not available at this time.']);
            exit;
        }

        // Insert Appointment including patient_type and allergies
        $stmt = $pdo->prepare("
            INSERT INTO online_appointment 
            (first_name, last_name, patient_type, age, gender, allergies, gmail, phone_number,
             date_visit, time_visit, dentist_id, payment_method, proof_of_payment, dental_toothxray, hmo_id, hmo_member_id, hmo_id_image)
            VALUES 
            (:first_name, :last_name, :patient_type, :age, :gender, :allergies, :gmail, :phone_number,
             :date_visit, :time_visit, :dentist_id, :payment_method, :proof_of_payment, :dental_toothxray, :hmo_id, :hmo_member_id, :hmo_id_image)
        ");

        $stmt->execute([
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'patient_type'      => $patient_type,
            'age'               => $age,
            'gender'            => $gender,
            'allergies'         => $allergies,
            'gmail'             => $email,
            'phone_number'      => $phone,
            'date_visit'        => $date_visit,
            'time_visit'        => $time_visit,
            'dentist_id'        => $dentist_id,
            'payment_method'    => $payment_method,
            'proof_of_payment'  => $proof_of_payment,
            'dental_toothxray'  => $dental_toothxray_path,
            'hmo_id'            => $hmo_id,
            'hmo_member_id'     => $hmo_member_id,
            'hmo_id_image'      => $hmo_id_image_path
        ]);
        
        $appointment_id = $pdo->lastInsertId();

        // Insert Services
        $service_stmt = $pdo->prepare("
            INSERT INTO online_appointment_services (appointment_id, service_id)
            VALUES (:appointment_id, :service_id)
        ");

        foreach ($service_ids as $sid) {
            if (!empty($sid) && $sid > 0) {
                $service_stmt->execute([
                    'appointment_id' => $appointment_id,
                    'service_id'     => $sid
                ]);
            }
        }

        // Kung HMO ang payment, gumawa ng entry sa `hmo_requests` table
        if ($payment_method === 'HMO' && $hmo_id) {
            $stmtCov = $pdo->prepare("SELECT coverage_percentage FROM hmo_providers WHERE id = ?");
            $stmtCov->execute([$hmo_id]);
            $hmoProvider = $stmtCov->fetch(PDO::FETCH_ASSOC);
            $coverage_percentage = $hmoProvider ? (int)$hmoProvider['coverage_percentage'] : 0;

            $hmo_deduction = ($total_price * $coverage_percentage) / 100;
            $patient_responsibility = $total_price - $hmo_deduction;

            $stmtHmoReq = $pdo->prepare("
                INSERT INTO hmo_requests 
                (patient_id, online_appointment_id, hmo_id, treatment_fee, coverage_percentage, hmo_deduction, patient_responsibility, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmtHmoReq->execute([$appointment_id, $appointment_id, $hmo_id, $total_price, $coverage_percentage, $hmo_deduction, $patient_responsibility]);
        }

        // Send Email Confirmation
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'beanchcanonego1212@gmail.com';
        $mail->Password   = 'xksi pzdv avxp clby';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('beanchcanonego1212@gmail.com', 'Mariategue Ortho-DentalClinic');
        $mail->addAddress($email);
        $mail->addBCC('farishitomi@gmail.com');

        $mail->isHTML(true);
        $mail->Subject = 'Dental Appointment Confirmation';
        $mail->Body    = "
            Hello <b>$first_name $last_name</b>,<br><br>
            Your appointment request has been received" . ($payment_method === 'HMO' ? ' using HMO coverage.' : '.') . "<br><br>
            <b>Patient Type:</b> $patient_type <br>
            <b>Allergies:</b> $allergies <br>
            <b>Date:</b> $date_visit <br>
            <b>Time:</b> $time_visit <br><br>
            We will notify you once the staff approves your appointment.<br><br>
            Thank you!
        ";

        $mail->send();

        header('Content-Type: application/json');
        echo json_encode([
            'success'      => true, 
            'message'      => 'Appointment booked successfully!',
            'checkout_url' => $checkout_url
        ]);
        exit;

    } catch (\Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - MariategueOrtho-DentalClinic</title>
    <link rel="stylesheet" href="pconcio_main_design.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style1.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Inline CSS para sa mobile menu toggle na hindi nawawala o nagtatago -->
    <style>
        .burger-menu {
            display: none;
            font-size: 24px;
            cursor: pointer;
            background: none;
            border: none;
            color: #0284c7;
        }
        .nav-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }
        @media (max-width: 768px) {
            .burger-menu {
                display: block;
            }
            header nav {
                position: fixed;
                top: 0;
                left: -250px;
                width: 250px;
                height: 100%;
                background: #ffffff;
                box-shadow: 2px 0 5px rgba(0,0,0,0.1);
                transition: left 0.3s ease;
                z-index: 999;
                padding-top: 60px;
            }
            header nav.active {
                left: 0;
            }
            header nav ul {
                flex-direction: column;
                padding: 20px;
            }
            header nav ul li {
                margin-bottom: 15px;
            }
            .nav-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="loader"></div>
</div>

<div class="nav-overlay" onclick="toggleMobileMenu()"></div>

<header>
    <div class="burger-menu" onclick="toggleMobileMenu()">&#9776;</div>
    <a href="pconcio_main.php" class="logo">
        <span class="logo-blue">MariategueOrtho</span><span class="logo-pink">-DentalClinic</span>
    </a>
    <nav id="mobileNav">
        <ul>
            <li><a href="pconcio_main.php">Home</a></li>
            <li><a href="pconcio_main.php#services">Services</a></li>
            <li><a href="pconcio_main.php#contact">Contact Us</a></li>
            <li><a href="appointment_records.php">Appointment Records</a></li>
        </ul>
    </nav>
</header>

<section class="form-container">
    <h1>Dental Appointment</h1>

    <form method="POST" id="appointmentForm" enctype="multipart/form-data">
        <div id="appointmentMessage" class="form-message"></div>

        <div class="single-form-grid">
            <!-- Personal Information -->
            <div class="input-wrapper">
                <label>First Name:</label>
                <input type="text" name="firstname" required value="<?= $_SESSION['first_name'] ?? '' ?>" maxlength="100">
            </div>
            <div class="input-wrapper">
                <label>Last Name:</label>
                <input type="text" name="lastname" required value="<?= $_SESSION['last_name'] ?? '' ?>" maxlength="100">
            </div>
            <!-- Patient Type -->
            <div class="input-wrapper">
                <label>Patient Type:</label>
                <select name="patient_type" required>
                    <option value="New" selected>New</option>
                    <option value="Returning">Returning</option>
                </select>
            </div>
            <div class="input-wrapper">
                <label>Age:</label>
                <input type="number" name="age" required value="<?= $_SESSION['age'] ?? '' ?>">
            </div>
            <div class="input-wrapper">
                <label>Gender:</label>
                <select name="gender" required>
                    <option value="">-- Select Gender --</option>
                    <option value="MALE" <?= (($_SESSION['gender'] ?? '') == 'MALE') ? 'selected' : '' ?>>MALE</option>
                    <option value="FEMALE" <?= (($_SESSION['gender'] ?? '') == 'FEMALE') ? 'selected' : '' ?>>FEMALE</option>
                </select>
            </div>
            <!-- Dagdag para sa Allergies Input Field -->
            <div class="input-wrapper">
                <label>Allergies (kung meron, ilagay kung wala ay "None"):</label>
                <input type="text" name="allergies" placeholder="e.g., Penicillin, Latex, None" value="None" maxlength="255">
            </div>
            <div class="input-wrapper">
                <label>Email:</label>
                <input type="email" name="email" required maxlength="320" value="<?= $_SESSION['user_email'] ?? '' ?>">
            </div>
            <div class="input-wrapper">
                <label>Phone Number:</label>
                <input type="tel" name="phone_number" maxlength="11" minlength="11" required value="<?= $_SESSION['phone_number'] ?? '' ?>">
            </div>

            <!-- Services & X-Ray -->
            <div class="input-wrapper full-width">
                <label>Service:</label>
                <div id="selectedServicesContainer"></div>
                <input type="hidden" id="selectedServiceIds" name="service_ids">
                <select id="serviceSelect" name="service" onchange="addService()">
                    <option value="">-- Select Services --</option>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT id, service_name, price, xray_requirement FROM services WHERE status = 'enable'");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $serviceName = htmlspecialchars($row['service_name']);
                            $xrayReq = htmlspecialchars($row['xray_requirement'] ?? 'none');
                            echo "<option value='" . $row['id'] . "' data-name='{$serviceName}' data-xray='{$xrayReq}' data-price='" . number_format($row['price'], 2) . "'>"
                                . $serviceName . " - ₱" . number_format($row['price'], 2) . "</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option disabled>Error loading services</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="input-wrapper full-width" id="xrayWrapper" style="display: none;">
                <label>Dental Tooth / X-Ray Image:</label>
                <div class="xray-upload-box">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Mag-upload ng X-Ray o Larawan ng Ngipin</span>
                    <input type="file" name="dental_toothxray" id="dental_toothxray" accept="image/*">
                </div>
            </div>

            <!-- Schedule & Payment -->
            <div class="input-wrapper">
                <label>Date Visit:</label>
                <input type="date" name="date" required id="dateInput" min="<?= date('Y-m-d') ?>">
            </div>

            <div class="input-wrapper">
                <label>Dentist Availability:</label>
                <select id="dentistSelect" name="dentist" required>
                    <option value="">-- Select a Dentist --</option>
                </select>
            </div>

            <div class="input-wrapper full-width">
                <label>Time Visit:</label>
                <select name="time_slot" id="timeSlotSelect" required>
                    <option value="">Please select a dentist first</option>
                </select>
            </div>

            <div class="input-wrapper full-width">
                <label>Payment Method:</label>
                <div class="payment-methods-group">
                    <label class="payment-radio-label">
                        <input type="radio" name="payment_method" value="Cash" checked onchange="togglePayment(this.value)">
                        <span>Cash Payment</span>
                    </label>
                    <label class="payment-radio-label">
                        <input type="radio" name="payment_method" value="Online" onchange="togglePayment(this.value)">
                        <span>Online (Full Payment)</span>
                    </label>
                    <label class="payment-radio-label">
                        <input type="radio" name="payment_method" value="Down Payment" onchange="togglePayment(this.value)">
                        <span>Down Payment (50% Online)</span>
                    </label>
                    <label class="payment-radio-label">
                        <input type="radio" name="payment_method" value="HMO" onchange="togglePayment(this.value)">
                        <span>HMO (Health Maintenance Organization)</span>
                    </label>
                </div>
            </div>

            <!-- HMO Extra Fields Container with ID Upload -->
            <div id="hmoDetailsContainer" class="full-width" style="display:none; border:1px solid #fbcfe8; padding:15px; border-radius:8px; background:#fff1f2; margin-bottom:15px;">
                <p style="margin-top:0; font-weight:bold; color:#831843;">HMO Account Details & Verification</p>
                
                <div class="input-wrapper" style="margin-bottom: 10px;">
                    <label>Select HMO Provider:</label>
                    <select name="hmo_id" id="hmoSelect">
                        <option value="">-- Select HMO Provider --</option>
                        <?php
                        try {
                            $stmtHmo = $pdo->query("SELECT id, hmo_name FROM hmo_providers WHERE status = 'active'");
                            while ($hmoRow = $stmtHmo->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$hmoRow['id']}'>".htmlspecialchars($hmoRow['hmo_name'])."</option>";
                            }
                        } catch (Exception $e) {
                            echo "<option disabled>Error loading HMO providers</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-wrapper" style="margin-bottom: 10px;">
                    <label>HMO Member ID / Policy Number:</label>
                    <input type="text" name="hmo_member_id" id="hmoMemberId" maxlength="30" placeholder="Ilagay ang iyong Card/Member ID">
                </div>

                <div class="input-wrapper" style="margin-bottom: 0;">
                    <label>Attach HMO ID / Card Picture:</label>
                    <div class="xray-upload-box" style="padding: 15px; border: 2px dashed #fda4af; background: #fff; text-align: center; border-radius: 6px;">
                        <i class="fa-solid fa-id-card" style="font-size: 1.5rem; color: #db2777; margin-bottom: 5px;"></i>
                        <span style="display: block; font-size: 0.9rem; color: #4b5563;">Mag-upload ng larawan ng HMO ID o Card (Front/Back)</span>
                        <input type="file" name="hmo_id_image" id="hmoIdImage" accept="image/*,application/pdf" style="margin-top: 5px;">
                    </div>
                </div>
            </div>

            <div id="onlineDetails" class="full-width" style="display:none; border:1px solid #fbcfe8; padding:15px; border-radius:8px; background:#fff1f2; margin-bottom:15px;">
                <p style="margin-top:0; font-weight:bold; color:#831843;">Online Payment</p>
                <p style="font-size: 0.9rem; margin-bottom: 10px; color:#9f1239;">You will be redirected to a secure PayMongo checkout page.</p>
            </div>

            <input type="hidden" name="book_appointment" value="1">

            <div class="form-submit-container">
                <button type="submit" id="bookOnlineBtn">Book Appointment</button>
            </div>
        </div>
    </form>
</section>

<script>
// Mobile Navigation Toggle Function
function toggleMobileMenu() {
    const nav = document.getElementById('mobileNav');
    const overlay = document.querySelector('.nav-overlay');
    nav.classList.toggle('active');
    overlay.classList.toggle('active');
}

let selectedServices = [];
let selectedIds = [];

function addService() {
    const select = document.getElementById("serviceSelect");
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.value !== "") {
        const id = selectedOption.value;
        const name = selectedOption.getAttribute("data-name");
        const xray = selectedOption.getAttribute("data-xray") || 'none';
        const price = selectedOption.getAttribute("data-price");

        if (!selectedIds.includes(id)) {
            selectedIds.push(id);
            selectedServices.push({ name: name, xray: xray, price: price });
            updateServicesUI();
            checkXrayRequirement();
        }
        select.value = "";
    }
}

function removeService(index) {
    selectedIds.splice(index, 1);
    selectedServices.splice(index, 1);
    updateServicesUI();
    checkXrayRequirement();
}

function updateServicesUI() {
    const container = document.getElementById("selectedServicesContainer");
    const hiddenInput = document.getElementById("selectedServiceIds");
    container.innerHTML = "";
    selectedServices.forEach((service, index) => {
        container.innerHTML += `
            <span style="display:inline-block; background:#ffe4e6; color:#831843; padding:4px 8px; margin:2px; border-radius:4px; font-size:0.9rem; border:1px solid #fbcfe8;">
                ${service.name} <i class="fa-solid fa-xmark" style="cursor:pointer; margin-left:5px;" onclick="removeService(${index})"></i>
            </span>
        `;
    });
    hiddenInput.value = selectedIds.join(",");
}

function checkXrayRequirement() {
    const xrayWrapper = document.getElementById('xrayWrapper');
    const xrayInput = document.getElementById('dental_toothxray');
    
    const needsXray = selectedServices.some(service => service.xray && service.xray !== 'none');
    
    if (needsXray) {
        xrayWrapper.style.display = 'block';
        xrayInput.required = true;
    } else {
        xrayWrapper.style.display = 'none';
        xrayInput.required = false;
        xrayInput.value = '';
    }
}

function togglePayment(val) {
    const onlineDetails = document.getElementById('onlineDetails');
    const hmoDetailsContainer = document.getElementById('hmoDetailsContainer');
    
    if (val === 'Online' || val === 'Down Payment') {
        onlineDetails.style.display = 'block';
        hmoDetailsContainer.style.display = 'none';
    } else if (val === 'HMO') {
        hmoDetailsContainer.style.display = 'block';
        onlineDetails.style.display = 'none';
    } else {
        onlineDetails.style.display = 'none';
        hmoDetailsContainer.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const dateInput = document.getElementById('dateInput');
    const slotSelect = document.getElementById('timeSlotSelect');
    const appointmentForm = document.getElementById('appointmentForm');
    const dentistSelect = document.getElementById('dentistSelect');

    function updateTimeSlots() {
        const selectedDate = dateInput.value;
        const selectedDentist = dentistSelect.value;
        if (!selectedDate || !selectedDentist) return;

        fetch(`get_slots.php?date=${selectedDate}&dentist_id=${selectedDentist}`)
            .then(res => res.json())
            .then(data => {
                slotSelect.innerHTML = '<option value="">-- Select Time Slot --</option>';
                data.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.time;
                    // Convert 24h to 12h AM/PM for display
                    const [h, m] = slot.time.split(':').map(Number);
                    const period = h < 12 ? 'AM' : 'PM';
                    const hour12 = h % 12 === 0 ? 12 : h % 12;
                    const displayTime = hour12 + ':' + String(m).padStart(2, '0') + ' ' + period;
                    option.textContent = displayTime + (slot.full ? ' (Full)' : '');
                    option.disabled = slot.full;
                    slotSelect.appendChild(option);
                });
            });
    }

    dateInput.addEventListener('change', function () {
        const selectedDate = this.value;
        if (!selectedDate) return;
        dentistSelect.disabled = false;
        fetch('get_dentists.php?date=' + selectedDate)
            .then(res => res.json())
            .then(data => {
                dentistSelect.innerHTML = '<option value="">-- Select a Dentist --</option>';
                data.forEach(d => {
                    const option = document.createElement('option');
                    option.value = d.id;
                    option.textContent = d.name;
                    dentistSelect.appendChild(option);
                });
            });
    });

    dentistSelect.addEventListener('change', updateTimeSlots);

    appointmentForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const messageDiv = document.getElementById('appointmentMessage');
        const overlay = document.getElementById("loadingOverlay");
        overlay.style.display = "flex";

        const formData = new FormData(appointmentForm);
        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const result = await response.json();
            overlay.style.display = "none";

            if (result.success) {
                messageDiv.textContent = result.message;
                messageDiv.className = 'form-message success';
                messageDiv.style.display = 'block';
                if (result.checkout_url) {
                    window.location.href = result.checkout_url;
                } else {
                    setTimeout(() => { window.location.href = 'pconcio_main.php'; }, 2000);
                }
            } else {
                messageDiv.textContent = result.message;
                messageDiv.className = 'form-message error';
                messageDiv.style.display = 'block';
            }
        } catch (err) {
            overlay.style.display = "none";
            messageDiv.textContent = 'May naganap na error sa pag-proseso.';
            messageDiv.className = 'form-message error';
            messageDiv.style.display = 'block';
        }
    });
});
</script>
</body>
</html>