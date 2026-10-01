<?php
$host = 'mysql.railway.internal';
$port = '3306';
$db   = 'railway';
$user = 'root';
$pass = 'NbWyKGqSfqdkaGCHZaQzTmEFiLpZpoHb';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
