<?php
include "../Includes/auth.php";
require_once __DIR__ . '/../Includes/db.php';

$me = (int)$_SESSION['user_id'];

// Group by (other party, listing). Pick the most recent message per conversation.
$sql = "
    SELECT
        t.other_id,
        t.listing_id,
        u.name  AS other_name,
        l.title AS listing_title,
        m.body  AS last_body,
        m.sender_id AS last_sender_id,
        m.created_at AS last_at,
        (
            SELECT COUNT(*) FROM messages
            WHERE recipient_id = ?
              AND sender_id    = t.other_id
              AND ((listing_id <=> t.listing_id))
              AND is_read = 0
        ) AS unread
    FROM (
        SELECT
            CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS other_id,
            listing_id,
            MAX(id) AS last_id
        FROM messages
        WHERE sender_id = ? OR recipient_id = ?
        GROUP BY other_id, listing_id
    ) t
    JOIN messages m ON m.id = t.last_id
    JOIN users    u ON u.id = t.other_id
    LEFT JOIN listings l ON l.id = t.listing_id
    ORDER BY m.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $me, $me, $me, $me);
$stmt->execute();
$threads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>Messages</h1>

    <?php if (empty($threads)): ?>
        <p>You have no conversations yet. Open a listing and click "Contact Seller" to start one.</p>
    <?php else: ?>
        <ul class="thread-list">
            <?php foreach ($threads as $t): ?>
                <?php
                    $url = "conversation.php?with=" . (int)$t['other_id'];
                    if (!empty($t['listing_id'])) {
                        $url .= "&listing=" . (int)$t['listing_id'];
                    }
                ?>
                <li class="thread-item <?php echo $t['unread'] > 0 ? 'thread-unread' : ''; ?>">
                    <a href="<?php echo htmlspecialchars($url); ?>">
                        <div class="thread-head">
                            <strong><?php echo htmlspecialchars($t['other_name'] ?? 'Unknown user'); ?></strong>
                            <?php if (!empty($t['listing_title'])): ?>
                                <span class="thread-listing">re: <?php echo htmlspecialchars($t['listing_title']); ?></span>
                            <?php endif; ?>
                            <?php if ($t['unread'] > 0): ?>
                                <span class="thread-badge"><?php echo (int)$t['unread']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="thread-preview">
                            <?php echo $t['last_sender_id'] == $me ? 'You: ' : ''; ?>
                            <?php echo htmlspecialchars(mb_strimwidth($t['last_body'], 0, 120, '…')); ?>
                        </div>
                        <div class="thread-time">
                            <?php echo date("Y-m-d H:i", strtotime($t['last_at'])); ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
