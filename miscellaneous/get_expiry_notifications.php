<?php
session_start();
include __DIR__ . '/database.php';
header('Content-Type: application/json');

// Only accessible to admin and staff
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

try {
    $today = date('Y-m-d');
    $in7days = date('Y-m-d', strtotime('+7 days'));

    // Fetch: expired items OR expiring within 7 days, with expiration_date set
    $stmt = $pdo->prepare("
        SELECT 
            item_name,
            item_type,
            expiration_date,
            quantity,
            CASE
                WHEN expiration_date < :today THEN 'EXPIRED'
                ELSE 'EXPIRING SOON'
            END AS status
        FROM item_inventory
        WHERE expiration_date IS NOT NULL
          AND expiration_date <= :in7days
        ORDER BY expiration_date ASC
    ");
    $stmt->execute([
        ':today'   => $today,
        ':in7days' => $in7days
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'item_name'       => $r['item_name'],
            'item_type'       => $r['item_type'],
            'quantity'        => $r['quantity'],
            'expiration_date' => date('M d, Y', strtotime($r['expiration_date'])),
            'status'          => $r['status'],
        ];
    }

    echo json_encode([
        'count' => count($items),
        'items' => $items
    ]);

} catch (Exception $e) {
    echo json_encode(['count' => 0, 'items' => [], 'error' => $e->getMessage()]);
}
exit;
