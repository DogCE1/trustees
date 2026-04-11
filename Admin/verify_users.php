<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";

$sql = "SELECT * FROM users WHERE is_verified = 0";
$result = $conn->query($sql);

if($result->num_rows > 0) {
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    echo "No users pending verification.";
    exit();
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
//Approve user
if ($_POST['action'] ==='approve') {
    $user_id = $_POST['user_id'];
    $sql = "UPDATE users SET is_verified = 1 WHERE id = '$user_id'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
}//Reject user
else if ($_POST['action'] === 'reject') {
    $user_id = $_POST['user_id'];
    $sql = "UPDATE users SET is_verified = 0 WHERE id = '$user_id'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
}
header("Location: verify_users.php");
exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Users</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <div class="container">
        <h1>Verify Users</h1>
        <p>Use the buttons below to approve or reject user registrations.</p>
        <a href="users.php">Back to User Management</a>

       
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['name']; ?></td>
                        <td><?php echo $user['email']; ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="action" value="approve">Approve</button>
                                <button type="submit" name="action" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
    </div>
</body>
</html>
