<?php
// I-display ang mga error para madaling ma-debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

if (isset($_POST['save_walkin'])) {
    try {
        // Kunin at linisin ang mga ipinasa mula sa form
        $first_name     = trim($_POST['first_name']);
        $middle_name    = trim($_POST['middle_name'] ?? '');
        $last_name      = trim($_POST['last_name']);
        $allergies      = trim($_POST['allergies'] ?? ''); // Kinuha ang allergies mula sa form
        $patient_type   = trim($_POST['patient_type']); // 'New' o 'Returning'
        $age            = intval($_POST['age']);
        $gender         = trim($_POST['gender']);
        $email          = trim($_POST['email']);
        $phone_number   = trim($_POST['phone_number']);
        $service_ids    = trim($_POST['service_ids'] ?? ''); 
        $date_visit     = trim($_POST['date_visit']);
        $dentist_id     = intval($_POST['dentist_id']);
        $time_slot      = trim($_POST['time_slot']);
        $payment_method = trim($_POST['payment_method']);

        // Mga validation
        if (empty($first_name) || empty($last_name) || empty($date_visit) || empty($dentist_id) || empty($time_slot) || empty($service_ids)) {
            throw new Exception("Please fill in all required fields and select at least one service.");
        }

        // Duplicate check: block kung may existing active record na ang pasyente
        // (same first name + last name + phone number, at hindi pa CANCELLED o TREATED)
        $dupCheck = $pdo->prepare("
            SELECT COUNT(*) FROM patients_list
            WHERE LOWER(TRIM(first_name))  = LOWER(?)
              AND LOWER(TRIM(last_name))   = LOWER(?)
              AND phone_number             = ?
              AND is_deleted               = 0
              AND UPPER(TRIM(status)) NOT IN ('CANCELLED', 'TREATED')
        ");
        $dupCheck->execute([$first_name, $last_name, $phone_number]);

        if ((int)$dupCheck->fetchColumn() > 0) {
            throw new Exception("Duplicate record: This patient ({$first_name} {$last_name}, {$phone_number}) already has an active appointment in the queue. Please check the Patient List.");
        }

        // Simulan ang database transaction
        $pdo->beginTransaction();

        // 1. I-save ang pasyente kasama ang allergies, middle_name at iba pa sa patients_list table
        // (Siguraduhing may kolum kang 'allergies' sa iyong patients_list table)
        $stmtPatient = $pdo->prepare("
            INSERT INTO patients_list 
            (first_name, middle_name, last_name, allergies, patient_type, age, gender, email, phone_number, date_visit, time_visit, dentist_id, type_of_appointment, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'WALK-IN', 'WAITING', NOW())
        ");
        
        $stmtPatient->execute([
            $first_name,
            empty($middle_name) ? null : $middle_name,
            $last_name,
            empty($allergies) ? null : $allergies,
            $patient_type,
            $age,
            $gender,
            $email,
            $phone_number,
            $date_visit,
            $time_slot,
            $dentist_id
        ]);

        $patient_id = $pdo->lastInsertId();

        // 2. I-save ang mga napiling serbisyo sa patient_services table
        $servicesArray = explode(',', $service_ids);
        $stmtService = $pdo->prepare("
            INSERT INTO patient_services (patient_id, service_id, status) 
            VALUES (?, ?, 'PENDING')
        ");

        foreach ($servicesArray as $serviceId) {
            $serviceId = trim($serviceId);
            if (!empty($serviceId)) {
                $stmtService->execute([$patient_id, $serviceId]);
            }
        }

        // I-commit ang transaction
        $pdo->commit();

        // I-set ang success message sa session para sa popup banner
        $_SESSION['success_message'] = "Walk-in saved successfully!";

        // Bumalik sa walk_in.php
        header("Location: walk_in.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: walk_in.php");
        exit;
    }
} else {
    header("Location: walk_in.php");
    exit;
}
?>