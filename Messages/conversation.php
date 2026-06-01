<?php
include "../Includes/auth.php";
require_once __DIR__ . '/../Includes/db.php';
require_once __DIR__ . '/../Includes/notifications.php';
require_once __DIR__ . '/../Includes/rate_limit.php';

$me      = (int)$_SESSION['user_id'];
$with    = isset($_GET['with']) ? (int)$_GET['with'] : 0;
$listing = isset($_GET['listing']) && $_GET['listing'] !== '' ? (int)$_GET['listing'] : null;

if ($with <= 0 || $with === $me) {
    header("Location: inbox.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
$stmt->bind_param("i", $with);
$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$other) {
    header("Location: inbox.php");
    exit();
}

$listing_row = null;
if ($listing !== null) {
    $stmt = $conn->prepare("SELECT id, title, user_id FROM listings WHERE id = ?");
    $stmt->bind_param("i", $listing);
    $stmt->execute();
    $listing_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$listing_row) {
        $listing = null;
    }
}

// Handle send
$send_error = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: " . BASE_URL . "/Messages/conversation.php?with=$with" . ($listing ? "&listing=$listing" : ""));
        exit();
    }
    $body = trim($_POST['body'] ?? '');
        if ($body === '') {
        $send_error = "Message can't be empty.";
    } elseif (mb_strlen($body) > 2000) {
        $send_error = "Message is too long (max 2000 characters).";
    } elseif (rate_limit_exceeded($conn, 'message_send', (string)$me, 20, 60)) {
        $send_error = "You're sending messages too fast. Please slow down.";
    } elseif (rate_limit_exceeded($conn, 'message_to_user', "$me:$with", 10, 300)) {
        $send_error = "You've sent many messages to this user recently. Please wait.";
    } else {
        rate_limit_log($conn, 'message_send', (string)$me);
        rate_limit_log($conn, 'message_to_user', "$me:$with");

        try {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, listing_id, body) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $me, $with, $listing, $body);
            $stmt->execute();
            $stmt->close();

            $sender_name = $_SESSION['user_name'] ?? 'Someone';
            $notif_link  = BASE_URL . "/Messages/conversation.php?with=$me" . ($listing ? "&listing=$listing" : "");
            notify($conn, $with, "New message from " . $sender_name, $notif_link);

            header("Location: " . BASE_URL . "/Messages/conversation.php?with=$with" . ($listing ? "&listing=$listing" : ""));
            exit();
        } catch (Throwable $e) {
            $send_error = "Could not send message: " . $e->getMessage();
        }
    }
}

// Mark inbound messages as read
$mark = $conn->prepare("
    UPDATE messages SET is_read = 1
    WHERE recipient_id = ? AND sender_id = ?
      AND (listing_id <=> ?) AND is_read = 0
");
$mark->bind_param("iii", $me, $with, $listing);
$mark->execute();
$mark->close();

// Pagination — fetch newest page first, show oldest at top via array_reverse.
$per_page = 100;
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset   = ($page - 1) * $per_page;

$cnt = $conn->prepare("
    SELECT COUNT(*) AS c FROM messages
    WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
      AND (listing_id <=> ?)
");
$cnt->bind_param("iiiii", $me, $with, $with, $me, $listing);
$cnt->execute();
$total_messages = (int)($cnt->get_result()->fetch_assoc()['c'] ?? 0);
$cnt->close();

$total_pages = max(1, (int)ceil($total_messages / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$stmt = $conn->prepare("
    SELECT id, sender_id, body, created_at
    FROM messages
    WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
      AND (listing_id <=> ?)
    ORDER BY created_at DESC, id DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iiiiiii", $me, $with, $with, $me, $listing, $per_page, $offset);
$stmt->execute();
$messages = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
$stmt->close();

$page_link = function ($p) use ($with, $listing) {
    $q = "with=$with";
    if ($listing) $q .= "&listing=$listing";
    $q .= "&page=$p";
    return BASE_URL . "/Messages/conversation.php?" . $q;
};

include "../Includes/header.php";
?>

<div class="container">
    <p><a href="inbox.php">&larr; Back to inbox</a></p>
    <h1>Conversation with <?php echo htmlspecialchars($other['name']); ?></h1>
    <?php if ($listing_row): ?>
        <p>About listing:
            <a href="../Listings/view.php?id=<?php echo (int)$listing_row['id']; ?>">
                <?php echo htmlspecialchars($listing_row['title']); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($send_error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($send_error); ?></div>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
        <div class="message-pagination" style="display:flex;justify-content:space-between;align-items:center;margin:.5rem 0;">
            <?php if ($page < $total_pages): ?>
                <a href="<?php echo htmlspecialchars($page_link($page + 1)); ?>">&larr; Earlier messages</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <small class="text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?> &middot; <?php echo $total_messages; ?> messages</small>
            <?php if ($page > 1): ?>
                <a href="<?php echo htmlspecialchars($page_link($page - 1)); ?>">Newer messages &rarr;</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="message-thread">
        <?php if (empty($messages)): ?>
            <p class="text-muted">No messages yet — say hi.</p>
        <?php endif; ?>
        <?php foreach ($messages as $m): ?>
            <div class="message-row <?php echo $m['sender_id'] == $me ? 'message-mine' : 'message-theirs'; ?>">
                <!-- Leave in one row to avoid extra whitespace between messages from same sender -->
                <div class="message-bubble"><?php echo nl2br(htmlspecialchars(trim($m['body']))); ?></div>

                <div class="message-meta">
                    <?php echo date("Y-m-d H:i", strtotime($m['created_at'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" class="message-form">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <textarea name="body" rows="3" maxlength="2000" placeholder="Write a message…" required></textarea>
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
</div>

<?php include "../Includes/footer.php"; ?>
