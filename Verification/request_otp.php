<?php
require_once '../Includes/auth.php';
require_once '../Includes/db.php';
require_once '../Includes/otp.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/Verification/verify.php");
    exit();
}

//CSRF check - ensure the token is valid and matches the session
if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
    set_flash('error',"CSRF token validation failed. Please try again.");
    header("Location: " . BASE_URL . "/Verification/verify.php");
    exit();
}

//Generate OTP and send via email
//Check cooldown for this user + purpose (e.g. 'seller_verification')
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT email, is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash('error', "User not found.");
    header("Location: " . BASE_URL . "/Verification/verify.php");
    exit();
}

// If user is already verified, no need to send OTP
if ((int)$user['is_verified'] === 1) {
    set_flash('error', "You're already verified.");
    header("Location: " . BASE_URL . "/Verification/verify.php");
    exit();
}

$email = $user['email'];

// If no email is associated, cannot send OTP
if(empty($email)){
    set_flash('error', "No email address associated with your account. Please update your profile.");
    header("Location: " . BASE_URL . "/Verification/verify.php");
    exit();
}

// Generate OTP and handle result
list($success, $error) = otp_generate($conn, $user_id, 'seller_verification', $email);
if($success){
    set_flash('success', "Verification code sent to your registered email address.");
}else{
    set_flash('error', $error);
}
header("Location: " . BASE_URL . "/Verification/verify.php");
?>