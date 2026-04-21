<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $action   = $_POST['action'];

    $stmt = $conn->prepare("
        SELECT o.id, o.status, o.total_price, l.user_id AS seller_id
        FROM orders o
        JOIN listings l ON o.listing_id = l.id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        if ($action === 'confirm_delivery' && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                $seller_id = (int)$order['seller_id'];
                $amount    = (float)$order['total_price'];

                $stmt = $conn->prepare("
                    INSERT INTO wallet (user_id, balance) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
                ");
                $stmt->bind_param("id", $seller_id, $amount);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type) VALUES (?, ?, 'release')");
                $stmt->bind_param("id", $seller_id, $amount);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
    header("Location: my_orders.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT o.id, o.status, o.delivery_method, o.delivery_address, o.total_price, o.created_at,
           l.title, l.image, u.name AS seller_name
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    JOIN users u ON l.user_id = u.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>My Orders</h1>
    <p>Items you have purchased. Funds are held in escrow until you confirm delivery.</p>

    <?php if (empty($orders)): ?>
        <p>You haven't bought anything yet.</p>
        <a href="../Listings/browse.php" class="btn btn-primary">Browse Listings</a>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Item</th>
                    <th>Seller</th>
                    <th>Delivery</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Ordered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo (int)$order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['title']); ?></td>
                        <td><?php echo htmlspecialchars($order['seller_name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars(ucfirst($order['delivery_method'])); ?>
                            <?php if ($order['delivery_method'] === 'delivery' && !empty($order['delivery_address'])): ?>
                                <br><small><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>R<?php echo number_format((float)$order['total_price'], 2); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($order['status'])); ?></td>
                        <td><?php echo date("Y-m-d", strtotime($order['created_at'])); ?></td>
                        <td>
                            <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                <form method="post" onsubmit="return confirm('Confirm you have received this item? Funds will be released to the seller.');">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <button type="submit" name="action" value="confirm_delivery" class="btn btn-success">Confirm Delivery</button>
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
