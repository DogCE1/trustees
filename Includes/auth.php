<?php
include_once __DIR__ . '/session.php';
if(empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
if (!isset($_SESSION['user_id'])) {
    header("Location: /ITECA-Website/login.php");
    exit();
}
?>