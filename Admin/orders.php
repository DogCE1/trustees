<?php
require_once '../Includes/auth_admin.php';
require_once '../Includes/db.php';
require_once '../Includes/escrow.php';
require_once '../Includes/notifications.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: orders.php");
        exit();
    }

    $action   = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    $allowed_statuses = ['received', 'inspecting', 'ready', 'awaiting_proof', 'pending_admin_approval', 'delivered', 'cancelled', 'refunded', 'disputed'];
    $allowed_methods  = ['collect', 'delivery', 'meetup'];

    if ($action === 'update' && $order_id > 0) {
        $status           = $_POST['status'] ?? 'received';
        $delivery_method  = $_POST['delivery_method'] ?? 'collect';
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $quantity         = max(1, (int)($_POST['quantity'] ?? 1));
        $total_price      = (float)($_POST['total_price'] ?? 0);

        if (!in_array($status, $allowed_statuses, true))         $status          = 'received';
        if (!in_array($delivery_method, $allowed_methods, true)) $delivery_method = 'collect';

        $stmt = $conn->prepare("UPDATE orders SET status = ?, delivery_method = ?, delivery_address = ?, quantity = ?, total_price = ? WHERE id = ?");
        $stmt->bind_param("sssidi", $status, $delivery_method, $delivery_address, $quantity, $total_price, $order_id);
        $stmt->execute();
        $stmt->close();

    } elseif ($action === 'approve_proof' && $order_id > 0) {
        $stmt = $conn->prepare("
            SELECT o.status, o.buyer_id, l.user_id AS seller_id
            FROM orders o
            JOIN listings l ON o.listing_id = l.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['status'] === 'pending_admin_approval') {
            $conn->begin_transaction();
            try {
                release_escrow_to_seller($conn, $order_id);
                $conn->commit();

                notify_buyer_order_status($conn, (int)$row['buyer_id'], $order_id, "delivery proof approved. Order completed.");
                notify_seller_order_status($conn, (int)$row['seller_id'], $order_id, "delivery proof approved. Funds released to your wallet.");
            } catch (Exception $e) {
                $conn->rollback();
                set_flash('error', $e->getMessage());
            }
        }

    } elseif ($action === 'reject_proof' && $order_id > 0) {
        $stmt = $conn->prepare("
            SELECT l.user_id AS seller_id
            FROM orders o
            JOIN listings l ON o.listing_id = l.id
            WHERE o.id = ? AND o.status = 'pending_admin_approval'
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $rejrow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE orders SET status = 'awaiting_proof', delivery_proof_image = NULL WHERE id = ? AND status = 'pending_admin_approval'");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        if ($rejrow) {
            notify_seller_order_status($conn, (int)$rejrow['seller_id'], $order_id, "admin rejected your delivery proof. Please re-upload.");
        }

    } elseif ($action === 'delete' && $order_id > 0) {
        $stmt = $conn->prepare("SELECT status, listing_id FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            set_flash('error', "Order not found.");
        } elseif (!order_is_terminal($row['status'])) {
            set_flash('error', "Cannot delete an order that still holds escrow. Resolve, refund, or cancel it first.");
        } else {
            $linked_listing_id = (int)($row['listing_id'] ?? 0);
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                // If the listing has no remaining orders and is still flagged 'sold',
                // revert it to 'verified' so the seller isn't stuck with an orphaned sold listing.
                if ($linked_listing_id > 0) {
                    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE listing_id = ?");
                    $stmt->bind_param("i", $linked_listing_id);
                    $stmt->execute();
                    $remaining = (int)$stmt->get_result()->fetch_assoc()['c'];
                    $stmt->close();

                    if ($remaining === 0) {
                        $stmt = $conn->prepare("UPDATE listings SET status = 'verified' WHERE id = ? AND status = 'sold'");
                        $stmt->bind_param("i", $linked_listing_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                set_flash('error', "Could not delete order. Please try again.");
            }
        }
    }

    header("Location: orders.php");
    exit();
}

$filter = $_GET['filter'] ?? 'all';
$valid_filters = ['all', 'proof', 'active', 'completed'];
if (!in_array($filter, $valid_filters, true)) {
    $filter = 'all';
}

$search = trim($_GET['q'] ?? '');

$sql = "SELECT
            o.*,
            u.name AS user_name,
            u.surname AS user_surname,
            l.title AS listing_title
        FROM orders o
        LEFT JOIN users u ON o.buyer_id = u.id
        LEFT JOIN listings l ON o.listing_id = l.id";
$where  = [];
$params = [];
$types  = '';

if ($filter === 'proof') {
    $where[] = "o.status = 'pending_admin_approval'";
} elseif ($filter === 'active') {
    $where[] = "o.status NOT IN ('delivered', 'cancelled', 'refunded')";
} elseif ($filter === 'completed') {
    $where[] = "o.status = 'delivered'";
}

if ($search !== '') {
    $where[] = "(o.id = ? OR l.title LIKE ? OR u.name LIKE ? OR u.surname LIKE ? OR o.delivery_address LIKE ?)";
    $like = '%' . $search . '%';
    $id_search = ctype_digit($search) ? (int)$search : 0;
    array_push($params, $id_search, $like, $like, $like, $like);
    $types = 'issss';
}
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY
            CASE o.status
                WHEN 'pending_admin_approval' THEN 0
                WHEN 'disputed' THEN 1
                ELSE 2
            END,
            o.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statuses = ['received', 'inspecting', 'ready', 'awaiting_proof', 'pending_admin_approval', 'delivered', 'cancelled', 'refunded', 'disputed'];
$methods  = ['collect', 'delivery', 'meetup'];

require_once '../Includes/header.php';
?>

<div class="container">
    <h1>Order Management</h1>
    <p>Edit any order's details. Orders awaiting admin approval (delivery proof review) are listed first.</p>
    <p><a href="dashboard.php" class="btn btn-secondary"><strong>&larr; Back to Dashboard</strong></a></p>

    <?php
    $filter_labels = [
        'all'       => 'All',
        'proof'     => 'Awaiting proof review',
        'active'    => 'In progress',
        'completed' => 'Completed',
    ];
    ?>
    <div class="dispute-filters" style="margin:1em 0;">
        <strong>Filter:</strong>
        <?php $links = []; foreach ($filter_labels as $key => $label): ?>
            <?php $links[] = '<a href="orders.php?filter=' . $key . '">' . $label . '</a>'; ?>
        <?php endforeach; echo implode(' | ', $links); ?>
        <span style="margin-left:1em;">Showing: <strong><?php echo htmlspecialchars($filter_labels[$filter]); ?></strong></span>
    </div>

    <form method="get" action="orders.php" style="margin-bottom:1em; display:flex; gap:.5rem; max-width:500px;">
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by order ID, listing, buyer, or address…" style="flex:1;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="orders.php?filter=<?php echo urlencode($filter); ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (!empty($orders)): ?>
        <?php foreach ($orders as $order): ?>
            <?php $fid = 'form-post-order-' . (int)$order['id']; ?>
            <form id="<?php echo $fid; ?>" method="POST" action="orders.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Buyer</th>
                <th>Listing</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Delivery Method</th>
                <th>Delivery Address</th>
                <th>Proof</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="10">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php $fid = 'form-post-order-' . (int)$order['id']; ?>
                    <tr>
                        <td>#<?php echo (int)$order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['user_name'] ?? 'Deleted user'); ?></td>
                        <td><?php echo htmlspecialchars($order['listing_title'] ?? '—'); ?></td>
                        <td><input form="<?php echo $fid; ?>" type="number" name="quantity" min="1" value="<?php echo (int)$order['quantity']; ?>" required></td>
                        <td><input form="<?php echo $fid; ?>" type="number" step="0.01" min="0" name="total_price" value="<?php echo htmlspecialchars($order['total_price'] ?? '0'); ?>" required></td>
                        <td>
                            <select form="<?php echo $fid; ?>" name="delivery_method">
                                <?php foreach ($methods as $m): ?>
                                    <option value="<?php echo $m; ?>" <?php if ($order['delivery_method'] === $m) echo 'selected'; ?>><?php echo ucfirst($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input form="<?php echo $fid; ?>" type="text" name="delivery_address" value="<?php echo htmlspecialchars($order['delivery_address'] ?? ''); ?>"></td>
                        <td>
                            <?php if (!empty($order['delivery_proof_image'])): ?>
                                <a href="../<?php echo htmlspecialchars($order['delivery_proof_image']); ?>" target="_blank">View</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <select form="<?php echo $fid; ?>" name="status">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php if ($order['status'] === $s) echo 'selected'; ?>><?php echo htmlspecialchars(order_status_label($s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button form="<?php echo $fid; ?>" type="submit" name="action" value="update" class="btn btn-primary">Save</button>
                            <?php if ($order['status'] === 'pending_admin_approval'): ?>
                                <button form="<?php echo $fid; ?>" type="submit" name="action" value="approve_proof" class="btn btn-success" onclick="return confirm('Approve delivery proof and release escrow funds to the seller?');">Approve proof</button>
                                <button form="<?php echo $fid; ?>" type="submit" name="action" value="reject_proof" class="btn btn-danger" onclick="return confirm('Reject this delivery proof? The seller will be asked to re-upload.');">Reject proof</button>
                            <?php endif; ?>
                            <?php if (order_is_terminal($order['status'])): ?>
                                <button form="<?php echo $fid; ?>" type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Delete this order? This cannot be undone.');">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../Includes/footer.php'; ?>
