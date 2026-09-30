<?php
include __DIR__ . '/../miscellaneous/database.php';

// Replace with your dentist email
$email = 'your_dentist_email@example.com';
$password_to_test = 'your_password_here'; // The plain password you're trying to login with

$stmt = $pdo->prepare("SELECT * FROM dentist_accounts WHERE email = ?");
$stmt->execute([$email]);
$dentist = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dentist) {
    echo "Found dentist: " . $dentist['email'] . "<br>";
    echo "Password hash in DB: " . $dentist['password_hash'] . "<br>";
    echo "Password verify result: " . (password_verify($password_to_test, $dentist['password_hash']) ? "✅ TRUE (MATCH)" : "❌ FALSE (NO MATCH)") . "<br>";
} else {
    echo "❌ Dentist not found in database";
}
?>