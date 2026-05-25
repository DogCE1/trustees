<?php
require_once "Includes/db.php";
require_once "Includes/account.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
    $email = $_POST['email'];
    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'];

    // Rate limit: count failed attempts for this email in the last minute
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c FROM login_attempts
        WHERE email = ? AND success = 0
          AND attempted_at > (NOW() - INTERVAL 1 MINUTE)
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $fails = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    if ($fails >= 5) {
        set_flash('error', "Too many failed attempts. Please wait a minute and try again.");
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $success = ($row && password_verify($password, $row['password'])) ? 1 : 0;

    $log = $conn->prepare("INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)");
    if ($log) {
        $log->bind_param("ssi", $email, $ip, $success);
        try {
            // Login should still work even if logging table is temporarily inconsistent.
            $log->execute();
        } catch (mysqli_sql_exception $e) {
            error_log("login_attempts insert failed: " . $e->getMessage());
        }
        $log->close();
    }

    if ($success) {
        // If the user previously requested account deletion and the grace period has expired,
        // attempt the hard delete now (subject to the same active-order/wallet-balance checks).
        if (account_grace_expired($row['delete_requested_at'] ?? null)) {
            $reason = null;
            if (account_can_be_deleted($conn, (int)$row['id'], $reason)) {
                account_hard_delete($conn, (int)$row['id']);
                set_flash('error', "Your account has been deleted as requested. Goodbye.");
                header("Location: " . BASE_URL . "/login.php");
                exit();
            }
            // Blocked: log them in so they can resolve the blocker, then they'll see the banner.
            set_flash('error', "Your scheduled deletion is blocked: $reason Cancel deletion or resolve the issue, then it will run on next login.");
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['role'] = $row['role'];
        if ($row['role'] == 'admin') {
            header("Location: " . BASE_URL . "/Admin/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "/index.php");
        }
        exit();
    } else {
        set_flash('error', "Invalid email or password.");
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}
require_once "Includes/header.php";
?>


<div class="container">
    <h2>Login</h2>
    <form action="login.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a></p>
</div>

<?php
require_once "Includes/footer.php";
?>
