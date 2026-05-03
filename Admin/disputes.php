<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";
include "../Includes/escrow.php";
include "../Includes/notifications.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: disputes.php");
        exit();
    }

    $action      = $_POST['action'] ?? '';
    $dispute_id  = (int)($_POST['dispute_id'] ?? 0);
    $allowed     = ['open', 'resolved', 'closed'];

    if ($action === 'update' && $dispute_id > 0) {
        $reason = trim($_POST['reason'] ?? '');
        $status = $_POST['status'] ?? 'open';
        if (!in_array($status, $allowed, true)) $status = 'open';

        $stmt = $conn->prepare("UPDATE disputes SET reason = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssi", $reason, $status, $dispute_id);
        $stmt->execute();
        $stmt->close();

    } elseif (in_array($action, ['resolve_release', 'resolve_refund'], true) && $dispute_id > 0) {
        $stmt = $conn->prepare("
            SELECT d.id, d.status AS dispute_status,
                   o.id AS order_id, o.status AS order_status, o.buyer_id,
                   l.user_id AS seller_id
            FROM disputes d
            LEFT JOIN orders o ON d.order_id = o.id
            LEFT JOIN listings l ON o.listing_id = l.id
            WHERE d.id = ?
        ");
        $stmt->bind_param("i", $dispute_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !$row['order_id']) {
            set_flash('error', "Dispute is not linked to an order.");
        } elseif ($row['dispute_status'] === 'resolved' || $row['dispute_status'] === 'closed') {
            set_flash('error', "Dispute is already resolved.");
        } elseif (order_is_terminal($row['order_status'])) {
            set_flash('error', "Order is already closed; cannot resolve.");
        } else {
            $conn->begin_transaction();
            try {
                if ($action === 'resolve_release') {
                    release_escrow_to_seller($conn, (int)$row['order_id']);
                } else {
                    refund_escrow_to_buyer($conn, (int)$row['order_id']);
                }

                $stmt = $conn->prepare("UPDATE disputes SET status = 'resolved' WHERE id = ?");
                $stmt->bind_param("i", $dispute_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $oid = (int)$row['order_id'];
                if ($action === 'resolve_release') {
                    notify_buyer_order_status($conn, (int)$row['buyer_id'], $oid, "dispute resolved in seller's favor. Funds released to seller.");
                    notify_seller_order_status($conn, (int)$row['seller_id'], $oid, "dispute resolved in your favor. Funds released to your wallet.");
                } else {
                    notify_buyer_order_status($conn, (int)$row['buyer_id'], $oid, "dispute resolved in your favor. Funds refunded to your wallet.");
                    notify_seller_order_status($conn, (int)$row['seller_id'], $oid, "dispute resolved against you. Funds refunded to the buyer.");
                }
            } catch (Exception $e) {
                $conn->rollback();
                set_flash('error', $e->getMessage());
            }
        }

    } elseif (in_array($action, $allowed, true) && $dispute_id > 0) {
        $stmt = $conn->prepare("UPDATE disputes SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $action, $dispute_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete' && $dispute_id > 0) {
        $stmt = $conn->prepare("DELETE FROM disputes WHERE id = ?");
        $stmt->bind_param("i", $dispute_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: disputes.php");
    exit();
}

$filter = $_GET['status'] ?? 'all';
$valid_filters = ['all', 'open', 'resolved', 'closed'];
if (!in_array($filter, $valid_filters, true)) {
    $filter = 'all';
}

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT d.id, d.reason, d.evidence, d.status, d.created_at,
           d.order_id, u.name AS reporter_name, u.email AS reporter_email,
           o.total_price, o.status AS order_status, l.title AS listing_title
    FROM disputes d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN orders o ON d.order_id = o.id
    LEFT JOIN listings l ON o.listing_id = l.id
";

$where = [];
$params = [];
$types  = '';
if ($filter !== 'all') {
    $where[] = "d.status = ?";
    $params[] = $filter;
    $types .= 's';
}
if ($search !== '') {
    $where[] = "(d.reason LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR l.title LIKE ? OR d.id = ? OR d.order_id = ?)";
    $like = '%' . $search . '%';
    $id_search = ctype_digit($search) ? (int)$search : 0;
    array_push($params, $like, $like, $like, $like, $id_search, $id_search);
    $types .= 'ssssii';
}
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY
            CASE d.status WHEN 'open' THEN 0 WHEN 'resolved' THEN 1 ELSE 2 END,
            d.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$disputes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statuses = ['open', 'resolved', 'closed'];

include "../Includes/header.php";
?>

<div class="container">
    <h1>Dispute Management</h1>
    <p><a href="dashboard.php" class="btn btn-secondary"><strong>&larr; Back to Dashboard</strong></a></p>

    <div class="dispute-filters" style="margin:1em 0;">
        <strong>Filter:</strong>
        <a href="disputes.php?status=all">All</a> |
        <a href="disputes.php?status=open">Open</a> |
        <a href="disputes.php?status=resolved">Resolved</a> |
        <a href="disputes.php?status=closed">Closed</a>
        <span style="margin-left:1em;">Showing: <strong><?php echo htmlspecialchars(ucfirst($filter)); ?></strong></span>
    </div>

    <form method="get" action="disputes.php" style="margin-bottom:1em; display:flex; gap:.5rem; max-width:500px;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
        <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by reason, reporter, listing, or ID…" style="flex:1;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="disputes.php?status=<?php echo urlencode($filter); ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Order</th>
                <th>Listing</th>
                <th>Reported by</th>
                <th>Reason</th>
                <th>Evidence</th>
                <th>Opened</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($disputes)): ?>
                <tr><td colspan="9">No disputes match the current filter.</td></tr>
            <?php else: ?>
                <?php foreach ($disputes as $d): ?>
                    <?php
                        $can_resolve = !empty($d['order_id'])
                            && $d['status'] === 'open'
                            && $d['order_status']
                            && !order_is_terminal($d['order_status']);
                    ?>
                    <tr>
                        <form method="post" action="disputes.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="dispute_id" value="<?php echo (int)$d['id']; ?>">
                            <td>#<?php echo (int)$d['id']; ?></td>
                            <td>
                                <?php if (!empty($d['order_id'])): ?>
                                    #<?php echo (int)$d['order_id']; ?>
                                    <?php if (!empty($d['total_price'])): ?>
                                        <br><small>R<?php echo number_format((float)$d['total_price'], 2); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($d['order_status'])): ?>
                                        <br><small><em><?php echo htmlspecialchars(order_status_label($d['order_status'])); ?></em></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em>n/a</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($d['listing_title'] ?? '—'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($d['reporter_name'] ?? 'Deleted user'); ?>
                                <?php if (!empty($d['reporter_email'])): ?>
                                    <br><small><?php echo htmlspecialchars($d['reporter_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><textarea name="reason" rows="3" cols="30"><?php echo htmlspecialchars($d['reason'] ?? ''); ?></textarea></td>
                            <td>
                                <?php if (!empty($d['evidence'])): ?>
                                    <a href="../<?php echo htmlspecialchars($d['evidence']); ?>" target="_blank">View</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo date("Y-m-d H:i", strtotime($d['created_at'])); ?></td>
                            <td>
                                <select name="status">
                                    <?php foreach ($statuses as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php if ($d['status'] === $s) echo 'selected'; ?>><?php echo ucfirst($s); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="submit" name="action" value="update" class="btn btn-primary">Save</button>
                                <?php if ($can_resolve): ?>
                                    <button type="submit" name="action" value="resolve_release" class="btn btn-success" onclick="return confirm('Resolve in seller\'s favor and release escrow funds to the seller?');">Release to seller</button>
                                    <button type="submit" name="action" value="resolve_refund" class="btn btn-danger" onclick="return confirm('Resolve in buyer\'s favor and refund the escrow to the buyer?');">Refund buyer</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Delete this dispute? This cannot be undone.');">Delete</button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "../Includes/footer.php"; ?>
