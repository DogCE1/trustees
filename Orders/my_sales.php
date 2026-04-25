<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['order_id'])) {
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: my_sales.php");
        exit();
    }
    $order_id  = (int)$_POST['order_id'];
    $action    = $_POST['action'];

    $stmt = $conn->prepare("
        SELECT o.id, o.status
        FROM orders o
        JOIN listings l ON o.listing_id = l.id
        WHERE o.id = ? AND l.user_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $allowed_transitions = [
            'received'   => ['inspecting'],
            'inspecting' => ['ready'],
            'ready'      => [],
        ];
        $current = $order['status'];
        $next    = null;

        if ($action === 'mark_inspecting') $next = 'inspecting';
        elseif ($action === 'mark_ready')  $next = 'ready';

        if ($next && isset($allowed_transitions[$current]) && in_array($next, $allowed_transitions[$current], true)) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $next, $order_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: my_sales.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT o.id, o.status, o.delivery_method, o.delivery_address, o.total_price, o.created_at,
           l.title, u.name AS buyer_name
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    LEFT JOIN users u ON o.buyer_id = u.id
    WHERE l.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>My Sales</h1>
    <p>Items sold from your listings. Funds are released to your wallet once the buyer confirms delivery.</p>

    <?php if (empty($sales)): ?>
        <p>You have no sales yet.</p>
        <a href="../Listings/create.php" class="btn btn-primary">Create a Listing</a>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Item</th>
                    <th>Buyer</th>
                    <th>Delivery</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Ordered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td>#<?php echo (int)$sale['id']; ?></td>
                        <td><?php echo htmlspecialchars($sale['title']); ?></td>
                        <td><?php echo htmlspecialchars($sale['buyer_name'] ?? 'Deleted user'); ?></td>
                        <td>
                            <?php echo htmlspecialchars(ucfirst($sale['delivery_method'])); ?>
                            <?php if ($sale['delivery_method'] === 'delivery' && !empty($sale['delivery_address'])): ?>
                                <br><small><?php echo nl2br(htmlspecialchars($sale['delivery_address'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>R<?php echo number_format((float)$sale['total_price'], 2); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($sale['status'])); ?></td>
                        <td><?php echo date("Y-m-d", strtotime($sale['created_at'])); ?></td>
                        <td>
                            <?php if ($sale['status'] === 'received'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$sale['id']; ?>">
                                    <button type="submit" name="action" value="mark_inspecting" class="btn btn-secondary">Mark Inspecting</button>
                                </form>
                            <?php elseif ($sale['status'] === 'inspecting'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$sale['id']; ?>">
                                    <button type="submit" name="action" value="mark_ready" class="btn btn-primary">Mark Ready for Buyer</button>
                                </form>
                            <?php else: ?>
                                <em>—</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
