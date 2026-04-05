<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "trustees_db";
$conn = mysqli_connect($host, $username, $password, $dbname);
mysqli_set_charset($conn, "utf8");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
