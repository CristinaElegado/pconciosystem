<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';

// Test with one of the emails from your table
$email = 'alex@gmail.com';  // Change this to match one from your table
$password = 'testpassword';  // Change this to the password you used

echo "<h2>Debug Login Test</h2>";

// Test 1: Check if email exists with is_active = 1
$stmt = $pdo->prepare("SELECT * FROM dentist_accounts WHERE email = :email AND is_active = 1 LIMIT 1");
$stmt->execute(['email' => $email]);
$dentist = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dentist) {
    echo "✅ Dentist found with email: " . $dentist['email'] . "<br>";
    echo "Password hash: " . substr($dentist['password_hash'], 0, 20) . "...<br>";
    echo "Is Active: " . $dentist['is_active'] . "<br>";
    
    // Test 2: Check password_verify
    $verify_result = password_verify($password, $dentist['password_hash']);
    echo "Password verify result: " . ($verify_result ? "✅ TRUE" : "❌ FALSE") . "<br>";
} else {
    echo "❌ Dentist NOT found!<br>";
    
    // Debug: Check if email exists at all
    $stmt2 = $pdo->prepare("SELECT * FROM dentist_accounts WHERE email = :email");
    $stmt2->execute(['email' => $email]);
    $check = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($check) {
        echo "Email exists but is_active = " . $check['is_active'] . "<br>";
    } else {
        echo "Email doesn't exist in database at all<br>";
    }
}
?>