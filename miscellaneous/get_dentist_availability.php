<?php
session_start();
include __DIR__ . '/database.php';
header('Content-Type: application/json');

if (!isset($_GET['dentist_id'])) {
    echo json_encode(['error' => 'Missing dentist_id']);
    exit;
}

$dentist_id = intval($_GET['dentist_id']);

try {
    // Get dentist's time slots with day association
    $stmt = $pdo->prepare("SELECT day, time_slot_id FROM dentist_time_schedules WHERE dentist_id = ?");
    $stmt->execute([$dentist_id]);
    $raw_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $schedule = [];
    foreach ($raw_slots as $row) {
        $day = strtolower($row['day']);
        if (!isset($schedule[$day])) {
            $schedule[$day] = [];
        }
        $schedule[$day][] = $row['time_slot_id'];
    }

    echo json_encode(['schedule' => $schedule]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
