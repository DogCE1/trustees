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
    $users = [];
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
//Approve user
if ($_POST['action'] ==='approve') {
    $sql = "UPDATE users SET is_verified = 1 WHERE id = ?";
    
    $stmt->bind_param("i", $_POST['user_id']);
    $stmt->execute();
}//Reject user
else if ($_POST['action'] === 'reject') {
    $sql = "UPDATE users SET is_verified = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_POST['user_id']);
    $stmt->execute();
}
header("Location: verify_users.php");
exit();
}
include "../Includes/header.php";
?>


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
                    <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="3">No users pending verification.</td>
                    </tr>
                    <?php endif; ?>
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


<?php
include "../Includes/footer.php";
?>