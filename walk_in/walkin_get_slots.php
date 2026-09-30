<?php
include __DIR__ . '/../miscellaneous/database.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (!isset($_GET['date']) || !isset($_GET['dentist_id'])) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

$dentist_id = (int)$_GET['dentist_id'];
$dayName = strtolower(date('l', strtotime($date)));

if (date('w', strtotime($date)) == 0) {
    echo json_encode([]);
    exit;
}

try {
    // 1. Kunin ang start at end time ng dentista para sa araw na ito
    $stmt = $pdo->prepare("
        SELECT {$dayName}_start as start_time, {$dayName}_end as end_time 
        FROM dentist_schedule 
        WHERE dentist_id = :dentist_id AND {$dayName} = 1
    ");
    $stmt->execute(['dentist_id' => $dentist_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule || empty($schedule['start_time']) || empty($schedule['end_time'])) {
        echo json_encode([]);
        exit;
    }

    $start = strtotime($schedule['start_time']);
    $end = strtotime($schedule['end_time']);
    $all_slots = [];
    
    // Gumawa ng 15-minute intervals
    while ($start < $end) {
        $all_slots[] = ['time_slot' => date('H:i', $start)];
        $start = strtotime('+15 minutes', $start);
    }

    // 2. Kunin ang mga naka-book na slot para sa petsang ito
    $dentist_query = "
        SELECT DISTINCT TIME_FORMAT(time_visit, '%H:%i') 
        FROM (
            SELECT time_visit FROM online_appointment WHERE date_visit = :date AND dentist_id = :dentist_id AND status = 'PENDING'
            UNION ALL
            SELECT time_visit FROM patients_list WHERE date_visit = :date AND dentist_id = :did AND status IN ('WAITING', 'ONGOING')
        ) combined
    ";
    $stmt = $pdo->prepare($dentist_query);
    $stmt->execute(['date' => $date, 'dentist_id' => $dentist_id, 'did' => $dentist_id]);
    $dentist_booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results = [];
    foreach ($all_slots as $slot) {
        $time = $slot['time_slot'];
        $is_dentist_busy = in_array($time, $dentist_booked_slots);

        $results[] = [
            'time' => $time,
            'full' => $is_dentist_busy
        ];
    }
    
    echo json_encode($results);
} catch (Exception $e) {
    echo json_encode([]);
}
exit;