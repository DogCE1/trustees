<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if(empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>