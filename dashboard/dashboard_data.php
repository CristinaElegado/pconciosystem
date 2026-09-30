<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

$result = ['columns' => [], 'rows' => []];

switch ($type) {

  case 'total_patients':
    $stmt = $pdo->query("SELECT DISTINCT CONCAT(first_name, ' ', last_name) AS 'Patient Name', gender AS 'Gender', date_visit AS 'Date Visit', status AS 'Status' FROM patients_list ORDER BY date_visit DESC");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Patient Name', 'Gender', 'Date Visit', 'Status'];
    break;

  case 'ongoing':
    $stmt = $pdo->query("SELECT CONCAT(first_name, ' ', last_name) AS 'Patient Name', gender AS 'Gender', date_visit AS 'Date Visit', time_visit AS 'Time' FROM patients_list WHERE status = 'ONGOING' ORDER BY date_visit DESC");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Patient Name', 'Gender', 'Date Visit', 'Time'];
    break;

  case 'waiting':
    $stmt = $pdo->query("SELECT CONCAT(first_name, ' ', last_name) AS 'Patient Name', gender AS 'Gender', date_visit AS 'Date Visit', time_visit AS 'Time' FROM patients_list WHERE status = 'WAITING' ORDER BY date_visit DESC");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Patient Name', 'Gender', 'Date Visit', 'Time'];
    break;

  case 'dentists':
    $stmt = $pdo->query("SELECT CONCAT(first_name, ' ', last_name) AS 'Name', email AS 'Email', phone AS 'Phone', IF(is_active, 'Active', 'Inactive') AS 'Status' FROM dentist_accounts WHERE is_deleted = 0 ORDER BY first_name");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Name', 'Email', 'Phone', 'Status'];
    break;

  case 'staff':
    $stmt = $pdo->query("SELECT CONCAT(first_name, ' ', last_name) AS 'Name', email AS 'Email', phone AS 'Phone', IF(is_active, 'Active', 'Inactive') AS 'Status' FROM staff_accounts WHERE is_deleted = 0 ORDER BY first_name");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Name', 'Email', 'Phone', 'Status'];
    break;

  case 'low_stock':
    $stmt = $pdo->query("SELECT item_name AS 'Item', quantity AS 'Qty', unit AS 'Unit', expiration_date AS 'Expiration' FROM item_inventory WHERE quantity <= 20 ORDER BY quantity ASC");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Item', 'Qty', 'Unit', 'Expiration'];
    break;

  case 'appointments_today':
    $stmt = $pdo->query("SELECT CONCAT(p.first_name, ' ', p.last_name) AS 'Patient', p.time_visit AS 'Time', CONCAT(d.first_name, ' ', d.last_name) AS 'Dentist', p.status AS 'Status' FROM patients_list p JOIN dentist_accounts d ON p.dentist_id = d.id WHERE p.date_visit = CURDATE() ORDER BY p.time_visit");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Patient', 'Time', 'Dentist', 'Status'];
    break;

  case 'monthly_revenue':
    $stmt = $pdo->query("SELECT patient_name AS 'Patient', service_name AS 'Service', CONCAT('₱', FORMAT(price, 2)) AS 'Amount', appointment_type AS 'Type', date_completed AS 'Date' FROM transaction_history WHERE MONTH(date_completed) = MONTH(CURDATE()) AND YEAR(date_completed) = YEAR(CURDATE()) ORDER BY date_completed DESC");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Patient', 'Service', 'Amount', 'Type', 'Date'];
    break;

  case 'total_sales':
    $stmt = $pdo->query("SELECT patient_name AS 'Patient', service_name AS 'Service', CONCAT('₱', FORMAT(price, 2)) AS 'Amount', appointment_type AS 'Type', date_completed AS 'Date' FROM transaction_history ORDER BY date_completed DESC");
    $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['columns'] = ['Patient', 'Service', 'Amount', 'Type', 'Date'];
    break;

  default:
    $result = ['columns' => [], 'rows' => []];
}

echo json_encode($result);
