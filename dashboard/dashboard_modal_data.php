<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) { echo json_encode(['error' => 'unauthorized']); exit; }

$type = $_GET['type'] ?? '';

function fmtDate($d) {
    return $d ? date('M d, Y', strtotime($d)) : '—';
}

switch ($type) {

    case 'patients':
        $rows = $pdo->query("
            SELECT DISTINCT
                CONCAT(first_name,' ',last_name) AS name,
                phone_number, date_visit, status
            FROM patients_list
            ORDER BY date_visit DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Total Patients',
            'headers' => ['Name', 'Phone', 'Last Visit', 'Status'],
            'rows'    => array_map(fn($r) => [
                $r['name'], $r['phone_number'] ?? '—', fmtDate($r['date_visit']), $r['status']
            ], $rows)
        ]);
        break;

    case 'ongoing':
        $rows = $pdo->query("
            SELECT CONCAT(p.first_name,' ',p.last_name) AS name,
                   p.time_visit, p.status,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist
            FROM patients_list p
            JOIN dentist_accounts d ON p.dentist_id = d.id
            WHERE p.status = 'ONGOING'
            ORDER BY p.time_visit ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Ongoing Patients',
            'headers' => ['Patient', 'Time', 'Dentist', 'Status'],
            'rows'    => array_map(fn($r) => [
                $r['name'], $r['time_visit'], $r['dentist'], $r['status']
            ], $rows)
        ]);
        break;

    case 'waiting':
        $rows = $pdo->query("
            SELECT CONCAT(p.first_name,' ',p.last_name) AS name,
                   p.time_visit, p.status,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist
            FROM patients_list p
            JOIN dentist_accounts d ON p.dentist_id = d.id
            WHERE p.status = 'WAITING'
            ORDER BY p.time_visit ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Waiting Appointments',
            'headers' => ['Patient', 'Time', 'Dentist', 'Status'],
            'rows'    => array_map(fn($r) => [
                $r['name'], $r['time_visit'], $r['dentist'], $r['status']
            ], $rows)
        ]);
        break;

    case 'dentists':
        $rows = $pdo->query("
            SELECT CONCAT(first_name,' ',last_name) AS name,
                   email, phone
            FROM dentist_accounts
            WHERE is_active = 1
            ORDER BY first_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Active Dentists',
            'headers' => ['Name', 'Email', 'Phone'],
            'rows'    => array_map(fn($r) => [
                $r['name'], $r['email'], $r['phone'] ?? '—'
            ], $rows)
        ]);
        break;

    case 'staff':
        $rows = $pdo->query("
            SELECT CONCAT(first_name,' ',last_name) AS name,
                   email, staff_id, phone
            FROM staff_accounts
            WHERE is_active = 1
            ORDER BY first_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Active Staff',
            'headers' => ['Name', 'Email', 'Staff ID', 'Phone'],
            'rows'    => array_map(fn($r) => [
                $r['name'], $r['email'], $r['staff_id'], $r['phone'] ?? '—'
            ], $rows)
        ]);
        break;

    case 'lowstock':
        $rows = $pdo->query("
            SELECT item_name, item_type, quantity, price
            FROM item_inventory
            WHERE quantity <= 20
            ORDER BY quantity ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Low Stock Items',
            'headers' => ['Item', 'Type', 'Qty', 'Price'],
            'rows'    => array_map(fn($r) => [
                $r['item_name'], $r['item_type'], $r['quantity'],
                '₱' . number_format($r['price'], 2)
            ], $rows)
        ]);
        break;

    case 'appointments_today':
        $rows = $pdo->query("
            SELECT CONCAT(p.first_name,' ',p.last_name) AS name,
                   p.time_visit, p.status,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist
            FROM patients_list p
            JOIN dentist_accounts d ON p.dentist_id = d.id
            WHERE p.date_visit = CURDATE()
            ORDER BY p.time_visit ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Appointments Today',
            'headers' => ['Patient', 'Time', 'Dentist', 'Status'],
            'rows'    => array_map(fn($r) => [
                $r['name'], $r['time_visit'], $r['dentist'], $r['status']
            ], $rows)
        ]);
        break;

    case 'revenue':
        $rows = $pdo->query("
            SELECT patient_name, service_name, price, appointment_type, date_completed
            FROM transaction_history
            WHERE MONTH(date_completed) = MONTH(CURDATE())
              AND YEAR(date_completed)  = YEAR(CURDATE())
            ORDER BY date_completed DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Monthly Revenue Breakdown',
            'headers' => ['Patient', 'Service', 'Price', 'Type', 'Date'],
            'rows'    => array_map(fn($r) => [
                $r['patient_name'], $r['service_name'],
                '₱' . number_format($r['price'], 2),
                $r['appointment_type'],
                fmtDate($r['date_completed'])
            ], $rows)
        ]);
        break;

    case 'sales':
        $rows = $pdo->query("
            SELECT patient_name, service_name, price, appointment_type, date_completed
            FROM transaction_history
            ORDER BY date_completed DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'title'   => 'Total Sales',
            'headers' => ['Patient', 'Service', 'Price', 'Type', 'Date'],
            'rows'    => array_map(fn($r) => [
                $r['patient_name'], $r['service_name'],
                '₱' . number_format($r['price'], 2),
                $r['appointment_type'],
                fmtDate($r['date_completed'])
            ], $rows)
        ]);
        break;

    default:
        echo json_encode(['error' => 'unknown type']);
}
