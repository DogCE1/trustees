<?php
require_once "../Includes/auth_admin.php";
require_once "../Includes/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: users.php");
        exit();
    }

    $action  = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'update' && $user_id > 0) {
        $name        = trim($_POST['name'] ?? '');
        $surname     = trim($_POST['surname'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phonenr     = trim($_POST['phonenr'] ?? '');
        $role        = $_POST['role'] ?? 'buyer';
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;

        $allowed_roles = ['buyer', 'seller', 'admin'];
        if (!in_array($role, $allowed_roles, true)) {
            $role = 'buyer';
        }

        $stmt = $conn->prepare("UPDATE users SET name = ?, surname = ?, email = ?, phonenr = ?, role = ?, is_verified = ? WHERE id = ?");
        $stmt->bind_param("sssssii", $name, $surname, $email, $phonenr, $role, $is_verified, $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete' && $user_id > 0 && $user_id !== (int)$_SESSION['user_id']) {
        $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $balance = $wallet ? (float)$wallet['balance'] : 0.0;

        $terminal = ['delivered', 'cancelled', 'refunded'];
        $placeholders = implode(',', array_fill(0, count($terminal), '?'));
        $types = 'i' . str_repeat('s', count($terminal)) . 'i' . str_repeat('s', count($terminal));
        $sql = "
            SELECT COUNT(*) AS active_orders FROM (
                SELECT o.id FROM orders o
                WHERE o.buyer_id = ? AND o.status NOT IN ($placeholders)
                UNION
                SELECT o.id FROM orders o
                JOIN listings l ON o.listing_id = l.id
                WHERE l.user_id = ? AND o.status NOT IN ($placeholders)
            ) AS active
        ";
        $stmt = $conn->prepare($sql);
        $params = array_merge([$user_id], $terminal, [$user_id], $terminal);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $active = (int)$stmt->get_result()->fetch_assoc()['active_orders'];
        $stmt->close();

        if (abs($balance) > 0.005) {
            set_flash('error', "Cannot delete user: wallet still holds R" . number_format($balance, 2) . ". Refund or withdraw the balance first.");
        } elseif ($active > 0) {
            set_flash('error', "Cannot delete user: $active active order(s) still in progress. Resolve, refund, or cancel them first.");
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: users.php");
    exit();
}

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM users WHERE name LIKE ? OR surname LIKE ? OR email LIKE ? OR phonenr LIKE ? ORDER BY id ASC");
    $like = '%' . $search . '%';
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM users ORDER BY id ASC");
    $users = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

require_once "../Includes/header.php";
?>

<div class="container">
    <h1>User Management</h1>
    <p>Edit user details inline. Changes are saved on submit.</p>
    <p><a href="dashboard.php" class="btn btn-secondary"><strong>&larr; Back to Dashboard</strong></a></p>

    <form method="get" action="users.php" style="margin-bottom:1em; display:flex; gap:.5rem; max-width:500px;">
        <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, surname, email or phone…" style="flex:1;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="users.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($users)): ?>
        <p>No users match the current search.</p>
    <?php else: ?>
    <?php foreach ($users as $user): ?>
        <?php $fid = 'form-post-user-' . (int)$user['id']; ?>
        <form id="<?php echo $fid; ?>" method="POST" action="users.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
        </form>
    <?php endforeach; ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Surname</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Verified</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <?php $fid = 'form-post-user-' . (int)$user['id']; ?>
                <tr>
                    <td><?php echo (int)$user['id']; ?></td>
                    <td><input form="<?php echo $fid; ?>" type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required></td>
                    <td><input form="<?php echo $fid; ?>" type="text" name="surname" value="<?php echo htmlspecialchars($user['surname'] ?? ''); ?>"></td>
                    <td><input form="<?php echo $fid; ?>" type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required></td>
                    <td><input form="<?php echo $fid; ?>" type="text" name="phonenr" value="<?php echo htmlspecialchars($user['phonenr'] ?? ''); ?>"></td>
                    <td>
                        <select form="<?php echo $fid; ?>" name="role">
                            <option value="buyer" <?php if ($user['role'] === 'buyer') echo 'selected'; ?>>Buyer</option>
                            <option value="seller" <?php if ($user['role'] === 'seller') echo 'selected'; ?>>Seller</option>
                            <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                        </select>
                    </td>
                    <td>
                        <input form="<?php echo $fid; ?>" type="checkbox" name="is_verified" value="1" <?php if ($user['is_verified']) echo 'checked'; ?>>
                    </td>
                    <td>
                        <button form="<?php echo $fid; ?>" type="submit" name="action" value="update" class="btn btn-primary">Save</button>
                        <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                            <button form="<?php echo $fid; ?>" type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Delete this user? This cannot be undone.');">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
