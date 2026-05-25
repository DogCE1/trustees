<?php
require_once "Includes/session.php";

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: " . BASE_URL . "/Admin/dashboard.php");
    exit();
}

header("Location: " . BASE_URL . "/Listings/browse.php");
exit();
