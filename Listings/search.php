<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header("Location: browse.php" . ($qs !== '' ? "?$qs" : ""));
exit();
