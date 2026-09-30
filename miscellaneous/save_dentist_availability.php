<?php
include __DIR__ . '/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['dentist_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing dentist_id']);
    exit;
}

$dentist_id = (int)$data['dentist_id'];
$days = $data['days'] ?? [];
$slots = $data['slots'] ?? [];
$schedule = $data['schedule'] ?? []; // Support for per-day schedule

try {
    $pdo->beginTransaction();

    // 1. Update Days Schedule
    $dayMap = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $sqlParts = [];
    $params = [];
    
    foreach ($dayMap as $day) {
        $sqlParts[] = "$day = ?";
        if (!empty($schedule)) {
            // If using per-day schedule, active days are keys of schedule
            $params[] = (isset($schedule[$day]) && !empty($schedule[$day])) ? 1 : 0;
        } else {
            $params[] = in_array($day, $days) ? 1 : 0;
        }
    }
    
    $params[] = $dentist_id;
    $stmt = $pdo->prepare("UPDATE dentist_schedule SET " . implode(', ', $sqlParts) . " WHERE dentist_id = ?");
    $stmt->execute($params);

    // 2. Update Time Slots
    // Clear existing
    $stmt = $pdo->prepare("DELETE FROM dentist_time_schedules WHERE dentist_id = ?");
    $stmt->execute([$dentist_id]);

    // Insert new selections
    $stmt = $pdo->prepare("INSERT INTO dentist_time_schedules (dentist_id, time_slot_id, day) VALUES (?, ?, ?)");

    if (!empty($schedule)) {
        // Per-day schedule (New Format)
        foreach ($schedule as $day => $daySlots) {
            foreach ($daySlots as $slot_id) {
                $stmt->execute([$dentist_id, (int)$slot_id, strtolower($day)]);
            }
        }
    } elseif (!empty($slots) && !empty($days)) {
        // Legacy/Simple mode: Apply selected slots to ALL selected days
        foreach ($days as $day) {
            foreach ($slots as $slot_id) {
                $stmt->execute([$dentist_id, (int)$slot_id, strtolower($day)]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>