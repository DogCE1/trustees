<?php
// Escrow helpers. Call these inside an active mysqli transaction; the caller is
// responsible for begin_transaction / commit / rollback so callers can do
// additional work atomically (e.g. mark a dispute resolved at the same time).

function release_escrow_to_seller(mysqli $conn, int $order_id): void {
    $stmt = $conn->prepare("
        SELECT o.total_price, l.user_id AS seller_id
        FROM orders o
        JOIN listings l ON o.listing_id = l.id
        WHERE o.id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception("Order not found.");
    }
    $seller_id = (int)$row['seller_id'];
    $amount    = (float)$row['total_price'];

    if ($seller_id <= 0) {
        throw new Exception("Seller account no longer exists; cannot release funds.");
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO wallet (user_id, balance) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
    ");
    $stmt->bind_param("id", $seller_id, $amount);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $balance_after = (float)$stmt->get_result()->fetch_assoc()['balance'];
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, order_id, amount, type, balance_after) VALUES (?, ?, ?, 'release', ?)");
    $stmt->bind_param("iidd", $seller_id, $order_id, $amount, $balance_after);
    $stmt->execute();
    $stmt->close();
}

function refund_escrow_to_buyer(mysqli $conn, int $order_id): void {
    $stmt = $conn->prepare("
        SELECT total_price, buyer_id
        FROM orders
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception("Order not found.");
    }
    $buyer_id = (int)$row['buyer_id'];
    $amount   = (float)$row['total_price'];

    if ($buyer_id <= 0) {
        throw new Exception("Buyer account no longer exists; cannot refund.");
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO wallet (user_id, balance) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
    ");
    $stmt->bind_param("id", $buyer_id, $amount);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $balance_after = (float)$stmt->get_result()->fetch_assoc()['balance'];
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, order_id, amount, type, balance_after) VALUES (?, ?, ?, 'refund', ?)");
    $stmt->bind_param("iidd", $buyer_id, $order_id, $amount, $balance_after);
    $stmt->execute();
    $stmt->close();
}

function order_status_label(string $status): string {
    $map = [
        'received'               => 'Awaiting seller',
        'inspecting'             => 'Seller preparing',
        'ready'                  => 'Ready',
        'awaiting_proof'         => 'Awaiting delivery proof',
        'pending_admin_approval' => 'Awaiting admin approval',
        'delivered'              => 'Completed',
        'cancelled'              => 'Cancelled',
        'refunded'               => 'Refunded',
        'disputed'               => 'In dispute',
    ];
    return $map[$status] ?? ucfirst($status);
}

function order_is_terminal(string $status): bool {
    return in_array($status, ['delivered', 'cancelled', 'refunded'], true);
}
