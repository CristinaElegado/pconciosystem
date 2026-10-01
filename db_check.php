<?php
echo "<h2>PHP Version: " . phpversion() . "</h2>";
echo "<h3>PDO Drivers:</h3>";
echo "<pre>";
print_r(PDO::getAvailableDrivers());
echo "</pre>";
echo "<h3>Loaded Extensions:</h3>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";
?>
