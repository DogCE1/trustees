<?php
include "Includes/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        header("Location: login.php");
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
    $log->bind_param("ssi", $email, $ip, $success);
    $log->execute();
    $log->close();

    if ($success) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['role'] = $row['role'];
        if ($row['role'] == 'admin') {
            header("Location: Admin/dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit();
    } else {
        set_flash('error', "Invalid email or password.");
        header("Location: login.php");
        exit();
    }
}
include "Includes/header.php";
?>


<div class="container">
    <h2>Login</h2>
    <?php if ($error = get_flash('error')): ?>
        <div class="flash flash-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form action="login.php" method="post">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a></p>
</div>

<?php
include "Includes/footer.php";
?>
