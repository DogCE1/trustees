<?php
include "Includes/db.php";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phonenr = $_POST['phonenr'];
    $sql = "INSERT INTO users (name, surname, email, password, phonenr) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $surname, $email, $password, $phonenr);
    @$stmt->execute();

    if ($stmt->affected_rows > 0) {
        header("Location: login.php");
        exit();
    } elseif (mysqli_errno($conn) === 1062) {
        $register_error = "An account with this email already exists.";
    } else {
        $register_error = "Registration failed. Please try again.";
    }
    include "Includes/header.php";
}

?>


<div class="container">
    <h2>Register</h2>
    <?php if (!empty($register_error)) { echo '<p class="error">' . htmlspecialchars($register_error) . '</p>'; } ?>
    <form action="register.php" method="post">
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
include "Includes/footer.php";
?>