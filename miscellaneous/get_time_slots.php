<?php
include __DIR__ . '/database.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, time_slot FROM time_slots WHERE is_active = 1 ORDER BY STR_TO_DATE(time_slot, '%h:%i %p') ASC");
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['slots' => $slots]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>