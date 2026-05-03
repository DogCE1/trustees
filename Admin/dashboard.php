<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";

function scalar_count(mysqli $conn, string $sql): int {
    $r = $conn->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

$counts = [
    'users'                 => scalar_count($conn, "SELECT COUNT(*) AS c FROM users"),
    'listings_total'        => scalar_count($conn, "SELECT COUNT(*) AS c FROM listings l JOIN users u ON l.user_id = u.id"),
    'listings_pending'      => scalar_count($conn, "SELECT COUNT(*) AS c FROM listings l JOIN users u ON l.user_id = u.id WHERE l.status = 'pending'"),
    'listings_verified'     => scalar_count($conn, "SELECT COUNT(*) AS c FROM listings l JOIN users u ON l.user_id = u.id WHERE l.status = 'verified'"),
    'orders_active'         => scalar_count($conn, "SELECT COUNT(*) AS c FROM orders WHERE status NOT IN ('delivered','cancelled','refunded')"),
    'orders_proof'          => scalar_count($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'pending_admin_approval'"),
    'orders_completed'      => scalar_count($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'delivered'"),
    'disputes_open'         => scalar_count($conn, "SELECT COUNT(*) AS c FROM disputes WHERE status = 'open'"),
    'disputes_total'        => scalar_count($conn, "SELECT COUNT(*) AS c FROM disputes"),
    'verifications_pending' => scalar_count($conn, "SELECT COUNT(*) AS c FROM verifications v JOIN users u ON v.user_id = u.id WHERE v.status = 'pending'"),
    'verifications_total'   => scalar_count($conn, "SELECT COUNT(*) AS c FROM verifications v JOIN users u ON v.user_id = u.id"),
];

$attention_total = $counts['listings_pending'] + $counts['orders_proof'] + $counts['disputes_open'] + $counts['verifications_pending'];

include "../Includes/header.php";
?>

<div class="container">
    <h1>Admin Dashboard</h1>

    <?php if ($attention_total > 0): ?>
        <h2>Needs your attention <span class="thread-badge"><?php echo $attention_total; ?></span></h2>
        <div class="dashboard">
            <?php if ($counts['listings_pending'] > 0): ?>
                <a class="dashboard-item dashboard-item-attention" href="verify_listings.php">
                    <h2>Pending listings</h2>
                    <p><?php echo $counts['listings_pending']; ?></p>
                    <span>Review &rarr;</span>
                </a>
            <?php endif; ?>
            <?php if ($counts['verifications_pending'] > 0): ?>
                <a class="dashboard-item dashboard-item-attention" href="verifications.php">
                    <h2>Account verifications</h2>
                    <p><?php echo $counts['verifications_pending']; ?></p>
                    <span>Review &rarr;</span>
                </a>
            <?php endif; ?>
            <?php if ($counts['orders_proof'] > 0): ?>
                <a class="dashboard-item dashboard-item-attention" href="orders.php">
                    <h2>Delivery proofs awaiting review</h2>
                    <p><?php echo $counts['orders_proof']; ?></p>
                    <span>Review &rarr;</span>
                </a>
            <?php endif; ?>
            <?php if ($counts['disputes_open'] > 0): ?>
                <a class="dashboard-item dashboard-item-attention" href="disputes.php?status=open">
                    <h2>Open disputes</h2>
                    <p><?php echo $counts['disputes_open']; ?></p>
                    <span>Resolve &rarr;</span>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-success">All clear &mdash; no pending admin actions.</div>
    <?php endif; ?>

    <h2>Marketplace</h2>
    <div class="dashboard">
        <a class="dashboard-item" href="users.php">
            <h2>Users</h2>
            <p><?php echo $counts['users']; ?></p>
            <span>View &rarr;</span>
        </a>
        <a class="dashboard-item" href="listings.php">
            <h2>Listings (active)</h2>
            <p><?php echo $counts['listings_verified']; ?></p>
            <span>View &rarr;</span>
        </a>
        <a class="dashboard-item" href="listings.php">
            <h2>Listings (total)</h2>
            <p><?php echo $counts['listings_total']; ?></p>
            <span>View &rarr;</span>
        </a>
        <a class="dashboard-item" href="orders.php">
            <h2>Orders in progress</h2>
            <p><?php echo $counts['orders_active']; ?></p>
            <span>View &rarr;</span>
        </a>
        <a class="dashboard-item" href="orders.php">
            <h2>Orders completed</h2>
            <p><?php echo $counts['orders_completed']; ?></p>
            <span>View &rarr;</span>
        </a>
        <a class="dashboard-item" href="disputes.php">
            <h2>Disputes (total)</h2>
            <p><?php echo $counts['disputes_total']; ?></p>
            <span>View &rarr;</span>
        </a>
        <a class="dashboard-item" href="verifications.php">
            <h2>Verifications (total)</h2>
            <p><?php echo $counts['verifications_total']; ?></p>
            <span>View &rarr;</span>
        </a>
    </div>

    <p><a href="../logout.php" class="btn btn-secondary">Logout</a></p>
</div>

<?php include "../Includes/footer.php"; ?>
