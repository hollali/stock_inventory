<?php
$host = "localhost";
$user = "root";
$pass = "Vendetta7080";
$db = "inventory_system";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
