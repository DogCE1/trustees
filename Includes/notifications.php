<?php
// Notification helpers. Safe to include alongside db.php.

function notify(mysqli $conn, int $user_id, string $message, ?string $link = null): void {
    if ($user_id <= 0) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $message, $link);
    $stmt->execute();
    $stmt->close();
}

function count_unread_notifications(mysqli $conn, int $user_id): int {
    if ($user_id <= 0) {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0);
}

// Convenience helpers used by Tier 1 flow hooks.
function notify_seller_of_order(mysqli $conn, int $seller_id, int $order_id, string $listing_title): void {
    notify($conn, $seller_id, "New order #$order_id: \"$listing_title\"", "/ITECA-Website/Orders/my_sales.php");
}

function notify_buyer_order_status(mysqli $conn, int $buyer_id, int $order_id, string $message): void {
    notify($conn, $buyer_id, "Order #$order_id: $message", "/ITECA-Website/Orders/my_orders.php");
}

function notify_seller_order_status(mysqli $conn, int $seller_id, int $order_id, string $message): void {
    notify($conn, $seller_id, "Order #$order_id: $message", "/ITECA-Website/Orders/my_sales.php");
}
