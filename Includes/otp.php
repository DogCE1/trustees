<?php
// OTP functions for email verification and 2FA
require_once __DIR__ . '/db.php'; // Include database connection

function otp_generate(mysqli $conn, int $user_id, string $purpose, string $email): array{
    //COOLDOWN: query otp_codes for this user + purpose with created_at > NOW() - Interval 1 minute
    $stmt = $conn->prepare("SELECT id
                            FROM otp_codes
                            WHERE user_id = ? AND purpose = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)");
    $stmt->bind_param("is", $user_id, $purpose);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $stmt->close();
        return [false, "Please wait before requesting another OTP."];
    }

    $stmt->close();

    //Generate 6-digit OTP
    $code = random_int(100000, 999999);

    //hash OTP
    $hash = password_hash((string)$code, PASSWORD_DEFAULT);

    //Insert OTP into database
    $stmt = $conn->prepare("INSERT INTO otp_codes (user_id, purpose, code_hash, email, expires_at)
                            VALUES (?, ?, ?, ?, (NOW() + INTERVAL 5 MINUTE))");
    $stmt->bind_param("isss", $user_id, $purpose, $hash, $email);
    $stmt->execute();
    $stmt->close();

    //Deliver OTP via email
    otp_delivery($email, (string)$code);

    return [true, null];
}

function otp_verify(mysqli $conn, int $user_id, string $purpose, string $input_code): array{
    //Contract: return [true, null] on success, [false, error_message] on failure.

    //Fetch latest OTP for user + purpose that is not expired
    $stmt = $conn->prepare("SELECT id, code_hash, attempts
                            FROM otp_codes
                            WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
                            ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("is", $user_id, $purpose);
    $stmt->execute();
    $results = $stmt->get_result();

    $stmt->close();

    if($results->num_rows === 0){
        return [false, "No valid OTP found. Please request a new one."];
    }

    //Attempts check
    $otp = $results->fetch_assoc();
    if($otp['attempts'] >= 5){
        return [false, "Too many failed attempts. Please request a new OTP."];
    }

    //Verify OTP
    if(password_verify($input_code, $otp['code_hash'])){
        // Mark OTP as used
        $stmt = $conn->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $otp['id']);
        $stmt->execute();
        $stmt->close();
        return [true, null];
    } else {
        // Increment attempts
        $stmt = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
        $stmt->bind_param("i", $otp['id']);
        $stmt->execute();
        $stmt->close();
        return [false, "Invalid OTP. Please try again."];
    }
}

function otp_delivery(string $email, string $code): void {
    // Deliver via PHP mail(). On InfinityFree this routes through their SMTP;
    // on XAMPP local dev it silently fails — the log fallback below still records the code so testing works.

    $subject = "Your Trustees verification code";
    $body    = "Your Trustees verification code is: $code\r\n\r\n"
             . "This code expires in 5 minutes. If you did not request it, please ignore this email.";

    $from    = 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'trustees.local');
    $headers = "From: Trustees <$from>\r\n"
             . "Reply-To: $from\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "X-Mailer: PHP/" . phpversion();

    @mail($email, $subject, $body, $headers);

    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/otp.log';
    $line = sprintf("[%s] OTP sent to %s: %s\n", date('Y-m-d H:i:s'), $email, $code);
    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}


function otp_pending(mysqli $conn, int $user_id, string $purpose): bool{
    $stmt = $conn->prepare("SELECT 1 FROM otp_codes
                            WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
                            LIMIT 1");
    $stmt->bind_param("is", $user_id, $purpose);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}
?>
