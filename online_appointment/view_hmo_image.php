<?php
include __DIR__ . '/../miscellaneous/database.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { exit('Invalid ID'); }

$stmt = $pdo->prepare("
    SELECT COALESCE(p.hmo_id_image, r.hmo_id_image) as img 
    FROM hmo_requests r 
    LEFT JOIN online_appointment p ON r.online_appointment_id = p.id 
    WHERE r.id = ?
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['img'])) { exit('Image not found in DB'); }

$filename = basename(trim($row['img']));
$possibleFolders = [
    __DIR__ . '/../uploads/hmo_ids/',
    __DIR__ . '/uploads/hmo_ids/',
    __DIR__ . '/../uploads/',
    __DIR__ . '/uploads/',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/hmo_ids/',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/hmo_ids/'
];

$filePath = '';
foreach ($possibleFolders as $folder) {
    if (file_exists($folder . $filename)) {
        $filePath = $folder . $filename;
        break;
    }
}

if ($filePath && file_exists($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $contentType);
    readfile($filePath);
    exit;
} else {
    exit('File not found on server storage: ' . htmlspecialchars($filename));
}