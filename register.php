<?php
require_once "Includes/db.php";
require_once "Includes/rate_limit.php";

$register_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: register.php");
        exit();
    }

    $ip = $_SERVER['REMOTE_ADDR'];
    if (rate_limit_exceeded($conn, 'register', $ip, 5, 600)){
        set_flash('error', "Too many registration attempts. Please try again later.");
        header("Location: register.php");
        exit();
    }
    rate_limit_log($conn, 'register', $ip);

    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $raw_password = $_POST['password'] ?? '';
    $phonenr = trim($_POST['phonenr'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Please enter a valid email address.";
    } elseif ($raw_password === '') {
        $register_error = "Password is required.";
    } else {
        $password = password_hash($raw_password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, surname, email, password, phonenr) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $name, $surname, $email, $password, $phonenr);
        try{
            $stmt->execute();
            $new_id = $conn->insert_id;

            $role_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $role_stmt->bind_param("i", $new_id);
            $role_stmt->execute();
            $role_row = $role_stmt->get_result()->fetch_assoc();
            $role_stmt->close();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = $role_row['role'];
            header("Location: index.php");
            exit();
        }
        catch(mysqli_sql_exception $e){
            if ((int)$e->getCode() === 1062) {
                $register_error = "An account with that email already exists. Try logging in instead.";
            } else {
                $register_error = "Registration failed. Please try again.";
            }
            error_log("register insert failed:" . $e->getMessage());
        }
    }
}

require_once "Includes/header.php";
?>


<div class="container">
    <h2>Register</h2>
    <?php if (!empty($register_error)) { echo '<p class="error">' . htmlspecialchars($register_error) . '</p>'; } ?>
    <form action="register.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="text" name="name" placeholder="Name" required>
        <input type="text" name="surname" placeholder="Surname" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="phonenr" placeholder="Phone Number" required>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>

<?php
require_once "Includes/footer.php";
?>
