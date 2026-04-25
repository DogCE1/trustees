<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['dispute_id'])) {
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: disputes.php");
        exit();
    }
    $dispute_id = (int)$_POST['dispute_id'];
    $action     = $_POST['action'];
    $allowed    = ['open', 'resolved', 'closed'];

    if (in_array($action, $allowed, true)) {
        $stmt = $conn->prepare("UPDATE disputes SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $action, $dispute_id);
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

$sql = "
    SELECT d.id, d.reason, d.evidence, d.status, d.created_at,
           d.order_id, u.name AS reporter_name, u.email AS reporter_email,
           o.total_price, l.title AS listing_title
    FROM disputes d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN orders o ON d.order_id = o.id
    LEFT JOIN listings l ON o.listing_id = l.id
";
if ($filter !== 'all') {
    $sql .= " WHERE d.status = ? ";
}
$sql .= " ORDER BY
            CASE d.status WHEN 'open' THEN 0 WHEN 'resolved' THEN 1 ELSE 2 END,
            d.created_at DESC";

$stmt = $conn->prepare($sql);
if ($filter !== 'all') {
    $stmt->bind_param("s", $filter);
}
$stmt->execute();
$disputes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>Dispute Management</h1>
    <a href="dashboard.php">Back to Dashboard</a>

    <div class="dispute-filters" style="margin:1em 0;">
        <strong>Filter:</strong>
        <a href="disputes.php?status=all">All</a> |
        <a href="disputes.php?status=open">Open</a> |
        <a href="disputes.php?status=resolved">Resolved</a> |
        <a href="disputes.php?status=closed">Closed</a>
        <span style="margin-left:1em;">Showing: <strong><?php echo htmlspecialchars(ucfirst($filter)); ?></strong></span>
    </div>

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
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($disputes)): ?>
                <tr><td colspan="9">No disputes match the current filter.</td></tr>
            <?php else: ?>
                <?php foreach ($disputes as $d): ?>
                    <tr>
                        <td>#<?php echo (int)$d['id']; ?></td>
                        <td>
                            <?php if (!empty($d['order_id'])): ?>
                                #<?php echo (int)$d['order_id']; ?>
                                <?php if (!empty($d['total_price'])): ?>
                                    <br><small>R<?php echo number_format((float)$d['total_price'], 2); ?></small>
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
                        <td><?php echo nl2br(htmlspecialchars($d['reason'] ?? '')); ?></td>
                        <td>
                            <?php if (!empty($d['evidence'])): ?>
                                <a href="../<?php echo htmlspecialchars($d['evidence']); ?>" target="_blank">View</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo date("Y-m-d H:i", strtotime($d['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($d['status'])); ?></td>
                        <td>
                            <?php if ($d['status'] !== 'resolved'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="dispute_id" value="<?php echo (int)$d['id']; ?>">
                                    <button type="submit" name="action" value="resolved" class="btn btn-success">Resolve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($d['status'] !== 'closed'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="dispute_id" value="<?php echo (int)$d['id']; ?>">
                                    <button type="submit" name="action" value="closed" class="btn btn-secondary">Close</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($d['status'] !== 'open'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="dispute_id" value="<?php echo (int)$d['id']; ?>">
                                    <button type="submit" name="action" value="open" class="btn btn-warning">Reopen</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "../Includes/footer.php"; ?>
