<?php
$host = "localhost";
$user = "root";
$pass = "Vendetta7080";
$db = "inventory_system";

$conn = mysqli_connect($host, $user, $pass, $db);

// Include Settings class
require_once __DIR__ . '/Settings.php';

// Initialize settings
$settings = new Settings($conn);

// Set timezone from settings
date_default_timezone_set($settings->get('timezone', 'Ghana/Accra'));

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
