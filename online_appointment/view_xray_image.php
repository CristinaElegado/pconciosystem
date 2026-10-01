<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    http_response_code(400);
    exit("Invalid ID");
}

$stmt = $pdo->prepare("SELECT dental_toothxray FROM online_appointment WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['dental_toothxray'])) {
    http_response_code(404);
    exit("X-ray image filename not found in database.");
}

$dbPath = trim($row['dental_toothxray']);
$fileName = basename($dbPath);

$filePath = __DIR__ . '/../uploads/xrays/' . $fileName;

if (!file_exists($filePath) || is_dir($filePath)) {
    http_response_code(404);
    exit("File does not exist on server: " . htmlspecialchars($fileName));
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentType = 'image/jpeg';

if ($ext === 'png') {
    $contentType = 'image/png';
} elseif ($ext === 'webp') {
    $contentType = 'image/webp';
} elseif ($ext === 'pdf') {
    $contentType = 'application/pdf';
}

header("Content-Type: " . $contentType);
header("Content-Length: " . filesize($filePath));
readfile($filePath);
exit;