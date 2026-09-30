<?php
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Prevent browser from caching protected pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to homepage (main page with Book An Appointment)
header("Location: /pconcio_dental/main_page/pconcio_main.php");
exit;
?>
