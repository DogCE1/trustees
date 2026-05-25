<?php
require_once "../Includes/auth.php";
if ($_SESSION['role'] !="admin") {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}
?>