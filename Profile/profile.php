<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$user_id = $_SESSION['user_id'];

$error = null;
$success = null;

$stmt = $conn->prepare("SELECT id, name, surname, email, phonenr, role, is_verified, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../logout.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $phone   = trim($_POST['phonenr'] ?? '');

        if ($name === '' || $surname === '') {
            $error = "Name and surname are required.";
        } elseif ($phone !== '' && !preg_match('/^[0-9 +\-]{7,20}$/', $phone)) {
            $error = "Phone number format is invalid.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, surname = ?, phonenr = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $surname, $phone, $user_id);
            if ($stmt->execute()) {
                $success = "Profile updated.";
                $_SESSION['user_name'] = $name;
                $user['name']    = $name;
                $user['surname'] = $surname;
                $user['phonenr'] = $phone;
            } else {
                $error = "Could not update profile.";
            }
            $stmt->close();
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($new !== $confirm) {
            $error = "New password and confirmation do not match.";
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row || !password_verify($current, $row['password'])) {
                $error = "Current password is incorrect.";
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $user_id);
                $stmt->execute();
                $stmt->close();
                $success = "Password updated.";
            }
        }
    }
}

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM listings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listing_count = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE buyer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$order_count = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM orders o JOIN listings l ON o.listing_id = l.id
    WHERE l.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sales_count = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT status FROM verifications WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ver = $stmt->get_result()->fetch_assoc();
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>My Profile</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="profile-summary">
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?> <em>(cannot be changed)</em></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
        <p><strong>Member since:</strong> <?php echo date("Y-m-d", strtotime($user['created_at'])); ?></p>
        <p><strong>Verification:</strong>
            <?php if ((int)$user['is_verified'] === 1): ?>
                Verified seller
            <?php elseif ($ver && $ver['status'] === 'pending'): ?>
                Pending review &mdash; <a href="../Verification/verify.php">view submission</a>
            <?php elseif ($ver && $ver['status'] === 'rejected'): ?>
                Rejected &mdash; <a href="../Verification/verify.php">resubmit</a>
            <?php else: ?>
                Not verified &mdash; <a href="../Verification/verify.php">start verification</a>
            <?php endif; ?>
        </p>
        <p>
            <a href="../Listings/create.php">Create Listing</a> &middot;
            <a href="../Orders/my_orders.php">My Orders (<?php echo $order_count; ?>)</a> &middot;
            <a href="../Orders/my_sales.php">My Sales (<?php echo $sales_count; ?>)</a> &middot;
            <a href="wallet.php">Wallet</a>
        </p>
        <p><small>You have <?php echo $listing_count; ?> total listings.</small></p>
    </div>

    <h2>Edit Details</h2>
    <form method="post">
        <input type="hidden" name="action" value="update_profile">
        <label for="name">First name:</label><br>
        <input type="text" name="name" id="name" required maxlength="100"
               value="<?php echo htmlspecialchars($user['name']); ?>"><br>

        <label for="surname">Surname:</label><br>
        <input type="text" name="surname" id="surname" required maxlength="100"
               value="<?php echo htmlspecialchars($user['surname']); ?>"><br>

        <label for="phonenr">Phone number:</label><br>
        <input type="text" name="phonenr" id="phonenr" maxlength="20"
               value="<?php echo htmlspecialchars($user['phonenr'] ?? ''); ?>"><br>

        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>

    <h2>Change Password</h2>
    <form method="post">
        <input type="hidden" name="action" value="change_password">
        <label for="current_password">Current password:</label><br>
        <input type="password" name="current_password" id="current_password" required><br>

        <label for="new_password">New password (min 8 chars):</label><br>
        <input type="password" name="new_password" id="new_password" required minlength="8"><br>

        <label for="confirm_password">Confirm new password:</label><br>
        <input type="password" name="confirm_password" id="confirm_password" required minlength="8"><br>

        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
</div>

<?php include "../Includes/footer.php"; ?>
