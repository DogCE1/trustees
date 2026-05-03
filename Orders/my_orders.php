<?php
include "../Includes/auth.php";
include "../Includes/db.php";
include "../Includes/escrow.php";
include "../Includes/notifications.php";

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['order_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: my_orders.php");
        exit();
    }
    $order_id = (int)$_POST['order_id'];
    $action   = $_POST['action'];

    $stmt = $conn->prepare("
        SELECT o.id, o.status, o.delivery_method, o.buyer_confirmed_meetup, o.seller_confirmed_meetup,
               o.listing_id, o.total_price,
               l.user_id AS seller_id, l.title AS listing_title
        FROM orders o
        JOIN listings l ON o.listing_id = l.id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        try {
            if ($action === 'cancel'
                && in_array($order['status'], ['received', 'inspecting'], true)
            ) {
                $conn->begin_transaction();
                refund_escrow_to_buyer($conn, $order_id);

                $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE listings SET status = 'verified' WHERE id = ? AND status = 'sold'");
                $stmt->bind_param("i", (int)$order['listing_id']);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                notify_seller_order_status(
                    $conn,
                    (int)$order['seller_id'],
                    $order_id,
                    "buyer cancelled the order. Funds refunded to buyer."
                );
                set_flash('success', "Order #$order_id cancelled and refunded to your wallet.");

            } elseif ($action === 'confirm_collected'
                && $order['delivery_method'] === 'collect'
                && $order['status'] === 'ready'
            ) {
                $conn->begin_transaction();
                release_escrow_to_seller($conn, $order_id);
                $conn->commit();

                notify_seller_order_status($conn, (int)$order['seller_id'], $order_id, "buyer confirmed collection. Funds released to your wallet.");

            } elseif ($action === 'confirm_meetup'
                && $order['delivery_method'] === 'meetup'
                && $order['status'] === 'ready'
                && (int)$order['buyer_confirmed_meetup'] === 0
            ) {
                $conn->begin_transaction();

                $stmt = $conn->prepare("UPDATE orders SET buyer_confirmed_meetup = 1 WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                $released = false;
                if ((int)$order['seller_confirmed_meetup'] === 1) {
                    release_escrow_to_seller($conn, $order_id);
                    $released = true;
                }
                $conn->commit();

                $msg = $released
                    ? "buyer confirmed meetup. Funds released to your wallet."
                    : "buyer confirmed meetup. Confirm on your end to release funds.";
                notify_seller_order_status($conn, (int)$order['seller_id'], $order_id, $msg);
            }
        } catch (Exception $e) {
            $conn->rollback();
            set_flash('error', $e->getMessage());
        }
    }
    header("Location: my_orders.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT o.id, o.status, o.delivery_method, o.delivery_address, o.total_price, o.created_at,
           o.buyer_confirmed_meetup, o.seller_confirmed_meetup,
           l.title, l.image, u.name AS seller_name,
           s.name AS store_name, s.address AS store_address,
           (SELECT d.id FROM disputes d WHERE d.order_id = o.id AND d.status = 'open' LIMIT 1) AS open_dispute_id
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN stores s ON o.meetup_store_id = s.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<?php $flash_success = get_flash('success'); ?>
<div class="container">
    <h1>My Orders</h1>
    <p>Items you have purchased. Funds are held in escrow until delivery is confirmed.</p>

    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div>
    <?php endif; ?>

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
                    <?php
                        $method      = $order['delivery_method'];
                        $status      = $order['status'];
                        $is_terminal = order_is_terminal($status);
                        $in_dispute  = !empty($order['open_dispute_id']) || $status === 'disputed';
                    ?>
                    <tr>
                        <td>#<?php echo (int)$order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['title']); ?></td>
                        <td><?php echo htmlspecialchars($order['seller_name'] ?? 'Deleted user'); ?></td>
                        <td>
                            <?php echo htmlspecialchars(ucfirst($method)); ?>
                            <?php if ($method === 'delivery' && !empty($order['delivery_address'])): ?>
                                <br><small><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></small>
                            <?php elseif ($method === 'meetup' && !empty($order['store_name'])): ?>
                                <br><small><strong><?php echo htmlspecialchars($order['store_name']); ?></strong><br><?php echo htmlspecialchars($order['store_address']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>R<?php echo number_format((float)$order['total_price'], 2); ?></td>
                        <td>
                            <?php echo htmlspecialchars(order_status_label($status)); ?>
                            <?php if ($method === 'meetup' && $status === 'ready'): ?>
                                <br><small>
                                    You: <?php echo (int)$order['buyer_confirmed_meetup'] ? 'confirmed' : 'pending'; ?> &middot;
                                    Seller: <?php echo (int)$order['seller_confirmed_meetup'] ? 'confirmed' : 'pending'; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date("Y-m-d", strtotime($order['created_at'])); ?></td>
                        <td>
                            <?php if ($method === 'collect' && $status === 'ready'): ?>
                                <form method="post" onsubmit="return confirm('Confirm you have collected this item? Funds will be released to the seller.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <button type="submit" name="action" value="confirm_collected" class="btn btn-success">I collected the item</button>
                                </form>
                            <?php elseif ($method === 'meetup' && $status === 'ready' && (int)$order['buyer_confirmed_meetup'] === 0): ?>
                                <form method="post" onsubmit="return confirm('Confirm the meetup happened and you received the item?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <button type="submit" name="action" value="confirm_meetup" class="btn btn-success">Confirm meetup</button>
                                </form>
                            <?php elseif ($method === 'meetup' && $status === 'ready' && (int)$order['buyer_confirmed_meetup'] === 1): ?>
                                <em>Waiting for seller to confirm</em>
                            <?php elseif ($method === 'delivery' && $status === 'pending_admin_approval'): ?>
                                <em>Awaiting admin review</em>
                            <?php elseif (!$is_terminal && in_array($status, ['received','inspecting'], true)): ?>
                                <em>Waiting for seller</em>
                            <?php elseif ($method === 'delivery' && $status === 'awaiting_proof'): ?>
                                <em>Awaiting delivery proof</em>
                            <?php elseif ($status === 'disputed'): ?>
                                <em>In dispute</em>
                            <?php else: ?>
                                <em>—</em>
                            <?php endif; ?>

                            <?php if (in_array($status, ['received', 'inspecting'], true)): ?>
                                <br>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Cancel this order? Your wallet will be refunded.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <button type="submit" name="action" value="cancel" class="btn btn-secondary">Cancel order</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$is_terminal && !$in_dispute): ?>
                                <br><a href="dispute.php?order_id=<?php echo (int)$order['id']; ?>" class="btn btn-danger">Open dispute</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
