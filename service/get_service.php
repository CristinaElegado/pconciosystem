<?php
include __DIR__ . '/../miscellaneous/database.php';
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM services WHERE id=?");
$stmt->execute([$id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT si.item_id, si.quantity_needed, i.item_name, i.item_type
                        FROM service_items si
                        JOIN item_inventory i ON si.item_id = i.id
                        WHERE si.service_id=?");
$stmt2->execute([$id]);
$service['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($service);
?>
