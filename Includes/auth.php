<?php
include_once __DIR__ . '/session.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /ITECA-Website/login.php");
    exit();
}
?>