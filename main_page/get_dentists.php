<?php
include __DIR__ . '/../miscellaneous/database.php';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

$dayName = strtolower(date('l', strtotime($date))); 

try {
    $query = "
        SELECT da.id, da.first_name, da.last_name, ds.{$dayName}_start as start_time, ds.{$dayName}_end as end_time
        FROM dentist_accounts da
        JOIN dentist_schedule ds ON da.id = ds.dentist_id
        WHERE da.is_active = 1 AND da.is_deleted = 0 AND ds.$dayName = 1
    ";

    $stmt = $pdo->query($query);

    $dentists = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $start = !empty($row['start_time']) ? date('H:i', strtotime($row['start_time'])) : '';
        $end = !empty($row['end_time']) ? date('H:i', strtotime($row['end_time'])) : '';
        $timeStr = ($start && $end) ? " ($start - $end)" : "";
        
        $dentists[] = [
            'id' => $row['id'],
            'name' => 'Dr. ' . $row['first_name'] . ' ' . $row['last_name'] . $timeStr
        ];
    }

    echo json_encode($dentists);
} catch (\Throwable $e) {
    echo json_encode([]); // Fails gracefully by telling the UI no dentists are available
}
exit;