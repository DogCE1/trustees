<?php
include_once __DIR__ . "/session.php";

$env = parse_ini_file(__DIR__ . "/../.env");
if ($env === false) {
    die("Configuration error: .env file missing or unreadable.");
}

// Throw exceptions on any mysqli error so failed statements inside a transaction
// are caught and rolled back instead of being silently skipped (which previously
// allowed wallet balance changes to commit even when the orders INSERT failed).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
mysqli_set_charset($conn, "utf8");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
