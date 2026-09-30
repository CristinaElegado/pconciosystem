<?php
include __DIR__ . '/../miscellaneous/database.php';

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

$query = "
    SELECT da.id, da.first_name, da.last_name
    FROM dentist_accounts da
    JOIN dentist_schedule ds ON da.id = ds.dentist_id
    WHERE da.is_active = 1 AND da.is_deleted = 0 AND ds.$dayName = 1
";

$stmt = $pdo->query($query);

$dentists = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dentists[] = [
        'id' => $row['id'],
        'name' => $row['first_name'] . ' ' . $row['last_name']
    ];
}

echo json_encode($dentists);
exit;