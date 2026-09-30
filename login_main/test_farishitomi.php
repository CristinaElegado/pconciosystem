<?php
include __DIR__ . '/../miscellaneous/database.php';

$email = 'farishitomi@gmail.com';
$password = '09569985418';

echo "<h2>Testing farishitomi@gmail.com Login</h2>";

$stmt = $pdo->prepare("SELECT * FROM dentist_accounts WHERE email = :email AND is_active = 1 LIMIT 1");
$stmt->execute(['email' => $email]);
$dentist = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dentist) {
    echo "✅ Account found<br>";
    echo "Email: " . $dentist['email'] . "<br>";
    echo "Password hash: " . substr($dentist['password_hash'], 0, 30) . "...<br><br>";
    
    $verify = password_verify($password, $dentist['password_hash']);
    echo "Password verify result: " . ($verify ? "✅ TRUE - LOGIN WORKS!" : "❌ FALSE - PASSWORD MISMATCH") . "<br>";
    
    if (!$verify) {
        echo "<br><strong>The password '09569985418' does NOT match the hash in the database.</strong><br>";
        echo "This means either:<br>";
        echo "1. You used a different password when creating the account<br>";
        echo "2. The password wasn't hashed correctly when saved<br>";
    }
} else {
    echo "❌ Account not found or not active<br>";
}
?>