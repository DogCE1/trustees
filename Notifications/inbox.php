<?php
include "../Includes/auth.php";
require_once __DIR__ . '/../Includes/db.php';
require_once __DIR__ . '/../Includes/notifications.php';

$user_id = (int)$_SESSION['user_id'];

// Normalize a notification link so it works regardless of whether the value
// stored in the DB is absolute ("/ITECA-Website/...") or relative ("Orders/...").
function normalize_notif_link(?string $link): ?string {
    if ($link === null || $link === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }
    if (str_starts_with($link, '/')) {
        return $link;
    }
    return '/ITECA-Website/' . ltrim($link, '/');
}

// "Mark all as read"
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'mark_all_read') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: inbox.php");
        exit();
    }
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: inbox.php");
    exit();
}

// Click-through: mark this notification read and redirect to its target view.
if (isset($_GET['go']) && ctype_digit((string)$_GET['go'])) {
    $notif_id = (int)$_GET['go'];

    $stmt = $conn->prepare("SELECT link FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $target = normalize_notif_link($row['link']);
        header("Location: " . ($target ?? 'inbox.php'));
        exit();
    }
    header("Location: inbox.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT id, message, link, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY is_read ASC, created_at DESC
    LIMIT 200
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unread_count = count_unread_notifications($conn, $user_id);

include "../Includes/header.php";
?>

<div class="container">
    <h1>Notifications</h1>
    <p><?php echo (int)$unread_count; ?> unread.</p>

    <?php if ($unread_count > 0): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <button type="submit" name="action" value="mark_all_read" class="btn btn-secondary">Mark all as read</button>
        </form>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <p>No notifications yet.</p>
    <?php else: ?>
        <ul class="thread-list">
            <?php foreach ($notifications as $n): ?>
                <?php $has_link = !empty($n['link']); ?>
                <li class="thread-item <?php if (!$n['is_read']) echo 'thread-unread'; ?>">
                    <?php if ($has_link): ?>
                        <a href="inbox.php?go=<?php echo (int)$n['id']; ?>">
                    <?php else: ?>
                        <a href="#" onclick="return false;">
                    <?php endif; ?>
                        <div class="thread-preview"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="thread-time"><?php echo date("Y-m-d H:i", strtotime($n['created_at'])); ?></div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
