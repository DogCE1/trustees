<?php
include_once __DIR__ . "/session.php";

$env = parse_ini_file(__DIR__ . "/../.env");
if ($env === false) {
    die("Configuration error: .env file missing or unreadable.");
}

$conn = mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
mysqli_set_charset($conn, "utf8");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
