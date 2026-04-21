<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";

if (!isset($_GET['vid'], $_GET['field']) || !ctype_digit((string)$_GET['vid'])) {
    http_response_code(400);
    exit("Bad request.");
}

$vid   = (int)$_GET['vid'];
$field = $_GET['field'];

$allowed_fields = ['id_document', 'selfie_photo', 'verification_video'];
if (!in_array($field, $allowed_fields, true)) {
    http_response_code(400);
    exit("Bad field.");
}

$stmt = $conn->prepare("SELECT $field AS path FROM verifications WHERE id = ?");
$stmt->bind_param("i", $vid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['path'])) {
    http_response_code(404);
    exit("Not found.");
}

$base = realpath($_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/Uploads/verification");
$full = realpath($_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/" . $row['path']);

if (!$full || !$base || strpos($full, $base) !== 0 || !is_file($full)) {
    http_response_code(404);
    exit("File missing.");
}

$mime = mime_content_type($full) ?: 'application/octet-stream';
header("Content-Type: " . $mime);
header("Content-Length: " . filesize($full));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");
readfile($full);
exit();
