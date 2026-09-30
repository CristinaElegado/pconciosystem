<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';
include __DIR__ . '/../miscellaneous/auth_check.php';

// Bypass verification and redirect to settings
$_SESSION['verified'] = true;
header("Location: settings.php");
exit;
?>
