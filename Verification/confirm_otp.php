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
//Read and validate code format
$code = trim($_POST['code'] ?? '');
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    set_flash('error', "CSRF token validation failed. Please try again.");
    header("Location: " . BASE_URL . "/Verification/verify.php");
    exit();
}

if(!ctype_digit($code) || strlen($code) !== 6){
    set_flash('error', "Invalid OTP format. Please enter the 6-digit code sent to your phone.");
    header("Location: " . BASE_URL . "/Verification/verify.php");
    exit();
}

//Verify OTP
$user_id = $_SESSION['user_id'];
list($success, $error) = otp_verify($conn, $user_id, 'seller_verification', $code);

//On success
if($success){
    $_SESSION['otp_verified'] = true; // Mark OTP as verified in session
    set_flash('success', "OTP verified successfully. You can now proceed to the next step.");
    }else{
    set_flash('error', $error);
}

header("Location: " . BASE_URL . "/Verification/verify.php");
?>