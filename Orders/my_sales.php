<?php
include "../Includes/auth.php";
include "../Includes/db.php";
include "../Includes/escrow.php";
include "../Includes/notifications.php";

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['order_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: my_sales.php");
        exit();
    }
    $order_id = (int)$_POST['order_id'];
    $action   = $_POST['action'];

    $stmt = $conn->prepare("
        SELECT o.id, o.status, o.delivery_method, o.buyer_id, o.buyer_confirmed_meetup, o.seller_confirmed_meetup
        FROM orders o
        JOIN listings l ON o.listing_id = l.id
        WHERE o.id = ? AND l.user_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $current = $order['status'];
        $method  = $order['delivery_method'];

        $buyer_id = (int)$order['buyer_id'];

        if ($action === 'mark_inspecting' && $current === 'received') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'inspecting' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();

            notify_buyer_order_status($conn, $buyer_id, $order_id, "seller is preparing your item.");

        } elseif ($action === 'mark_ready' && $current === 'inspecting') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'ready' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();

            $msg = $method === 'collect' ? "ready for collection."
                 : ($method === 'meetup'  ? "ready for meetup. Confirm when the meetup happens."
                 : "seller has packed your item.");
            notify_buyer_order_status($conn, $buyer_id, $order_id, $msg);

        } elseif ($action === 'mark_shipped'
            && $method === 'delivery'
            && $current === 'ready'
        ) {
            $stmt = $conn->prepare("UPDATE orders SET status = 'awaiting_proof' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();

            notify_buyer_order_status($conn, $buyer_id, $order_id, "shipped. Awaiting delivery proof.");

        } elseif ($action === 'confirm_meetup'
            && $method === 'meetup'
            && $current === 'ready'
            && (int)$order['seller_confirmed_meetup'] === 0
        ) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE orders SET seller_confirmed_meetup = 1 WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                $released = false;
                if ((int)$order['buyer_confirmed_meetup'] === 1) {
                    release_escrow_to_seller($conn, $order_id);
                    $released = true;
                }
                $conn->commit();

                $msg = $released
                    ? "meetup confirmed by both parties. Funds released to seller."
                    : "seller confirmed meetup. Confirm on your end to release funds.";
                notify_buyer_order_status($conn, $buyer_id, $order_id, $msg);
            } catch (Exception $e) {
                $conn->rollback();
                set_flash('error', $e->getMessage());
            }
        }
    }
    header("Location: my_sales.php");
    exit();
}

$filter_order_id = isset($_GET['order_id']) && ctype_digit((string)$_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$sql = "
    SELECT o.id, o.status, o.delivery_method, o.delivery_address, o.total_price, o.created_at,
           o.buyer_confirmed_meetup, o.seller_confirmed_meetup, o.delivery_proof_image,
           l.title, u.name AS buyer_name,
           s.name AS store_name, s.address AS store_address
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    LEFT JOIN users u ON o.buyer_id = u.id
    LEFT JOIN stores s ON o.meetup_store_id = s.id
    WHERE l.user_id = ?
";
if ($filter_order_id > 0) {
    $sql .= " AND o.id = ?";
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
if ($filter_order_id > 0) {
    $stmt->bind_param("ii", $user_id, $filter_order_id);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>My Sales</h1>
    <p>Items sold from your listings. Funds are released to your wallet once delivery is confirmed.</p>

    <?php if ($filter_order_id > 0): ?>
        <div class="alert alert-info">
            Showing sale #<?php echo $filter_order_id; ?> only.
            <a href="my_sales.php">View all sales</a>
        </div>
    <?php endif; ?>

    <?php if (empty($sales)): ?>
        <?php if ($filter_order_id > 0): ?>
            <p>No matching sale found. <a href="my_sales.php">View all sales</a>.</p>
        <?php else: ?>
            <p>You have no sales yet.</p>
            <a href="../Listings/create.php" class="btn btn-primary">Create a Listing</a>
        <?php endif; ?>
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
                    <?php
                        $method = $sale['delivery_method'];
                        $status = $sale['status'];
                    ?>
                    <tr>
                        <td>#<?php echo (int)$sale['id']; ?></td>
                        <td><?php echo htmlspecialchars($sale['title']); ?></td>
                        <td><?php echo htmlspecialchars($sale['buyer_name'] ?? 'Deleted user'); ?></td>
                        <td>
                            <?php echo htmlspecialchars(ucfirst($method)); ?>
                            <?php if ($method === 'delivery' && !empty($sale['delivery_address'])): ?>
                                <br><small><?php echo nl2br(htmlspecialchars($sale['delivery_address'])); ?></small>
                            <?php elseif ($method === 'meetup' && !empty($sale['store_name'])): ?>
                                <br><small><strong><?php echo htmlspecialchars($sale['store_name']); ?></strong><br><?php echo htmlspecialchars($sale['store_address']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>R<?php echo number_format((float)$sale['total_price'], 2); ?></td>
                        <td>
                            <?php echo htmlspecialchars(order_status_label($status)); ?>
                            <?php if ($method === 'meetup' && $status === 'ready'): ?>
                                <br><small>
                                    Buyer: <?php echo (int)$sale['buyer_confirmed_meetup'] ? 'confirmed' : 'pending'; ?> &middot;
                                    You: <?php echo (int)$sale['seller_confirmed_meetup'] ? 'confirmed' : 'pending'; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date("Y-m-d", strtotime($sale['created_at'])); ?></td>
                        <td>
                            <?php if ($status === 'received'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$sale['id']; ?>">
                                    <button type="submit" name="action" value="mark_inspecting" class="btn btn-secondary">Mark inspecting</button>
                                </form>

                            <?php elseif ($status === 'inspecting'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$sale['id']; ?>">
                                    <?php
                                        $label = $method === 'collect' ? 'Mark ready for collection'
                                              : ($method === 'meetup'  ? 'Mark ready for meetup'
                                              : 'Mark packed and ready');
                                    ?>
                                    <button type="submit" name="action" value="mark_ready" class="btn btn-primary"><?php echo $label; ?></button>
                                </form>

                            <?php elseif ($method === 'collect' && $status === 'ready'): ?>
                                <em>Waiting for buyer to confirm collection</em>

                            <?php elseif ($method === 'meetup' && $status === 'ready' && (int)$sale['seller_confirmed_meetup'] === 0): ?>
                                <form method="post" onsubmit="return confirm('Confirm the meetup happened and you handed over the item?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$sale['id']; ?>">
                                    <button type="submit" name="action" value="confirm_meetup" class="btn btn-success">Confirm meetup</button>
                                </form>

                            <?php elseif ($method === 'meetup' && $status === 'ready' && (int)$sale['seller_confirmed_meetup'] === 1): ?>
                                <em>Waiting for buyer to confirm</em>

                            <?php elseif ($method === 'delivery' && $status === 'ready'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$sale['id']; ?>">
                                    <button type="submit" name="action" value="mark_shipped" class="btn btn-primary">Mark shipped</button>
                                </form>

                            <?php elseif ($method === 'delivery' && $status === 'awaiting_proof'): ?>
                                <a href="upload_proof.php?order_id=<?php echo (int)$sale['id']; ?>" class="btn btn-primary">Upload delivery proof</a>

                            <?php elseif ($method === 'delivery' && $status === 'pending_admin_approval'): ?>
                                <em>Awaiting admin approval</em>
                                <?php if (!empty($sale['delivery_proof_image'])): ?>
                                    <br><a href="../<?php echo htmlspecialchars($sale['delivery_proof_image']); ?>" target="_blank">View proof</a>
                                <?php endif; ?>

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
