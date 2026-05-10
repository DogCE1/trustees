<?php
// Helpers for account lifecycle (admin delete, user-initiated grace-period delete).

const ACCOUNT_DELETE_GRACE_DAYS = 20;

/**
 * Returns true if the user can be hard-deleted right now. Sets $reason to a
 * user-facing message when blocked.
 */
function account_can_be_deleted(mysqli $conn, int $user_id, ?string &$reason = null): bool {
    $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $balance = $row ? (float)$row['balance'] : 0.0;

    if (abs($balance) > 0.005) {
        $reason = "Wallet still holds R" . number_format($balance, 2) . ". Withdraw or refund the balance first.";
        return false;
    }

    $terminal     = ['delivered', 'cancelled', 'refunded'];
    $placeholders = implode(',', array_fill(0, count($terminal), '?'));
    $types        = 'i' . str_repeat('s', count($terminal)) . 'i' . str_repeat('s', count($terminal));
    $sql = "
        SELECT COUNT(*) AS active FROM (
            SELECT o.id FROM orders o
            WHERE o.buyer_id = ? AND o.status NOT IN ($placeholders)
            UNION
            SELECT o.id FROM orders o
            JOIN listings l ON o.listing_id = l.id
            WHERE l.user_id = ? AND o.status NOT IN ($placeholders)
        ) AS t
    ";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$user_id], $terminal, [$user_id], $terminal);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $active = (int)$stmt->get_result()->fetch_assoc()['active'];
    $stmt->close();

    if ($active > 0) {
        $reason = "$active active order(s) still in progress. Resolve, refund, or cancel them first.";
        return false;
    }
    return true;
}

/**
 * Hard-delete the user. Caller is responsible for checking account_can_be_deleted() first.
 * Returns true on success.
 */
function account_hard_delete(mysqli $conn, int $user_id): bool {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

/**
 * Returns the unix timestamp at which a delete-request becomes eligible to run.
 * Returns null if no pending request.
 */
function account_grace_expires_at(?string $delete_requested_at): ?int {
    if (!$delete_requested_at) return null;
    return strtotime($delete_requested_at) + (ACCOUNT_DELETE_GRACE_DAYS * 86400);
}

function account_grace_expired(?string $delete_requested_at): bool {
    $expires = account_grace_expires_at($delete_requested_at);
    return $expires !== null && time() >= $expires;
}
