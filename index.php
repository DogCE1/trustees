<?php
include "Includes/session.php";

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: Admin/dashboard.php");
    exit();
}

header("Location: Listings/browse.php");
exit();
