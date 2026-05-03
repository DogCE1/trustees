<?php
include "../Includes/auth.php";
include "../Includes/db.php";
include "../Includes/escrow.php";
include "../Includes/notifications.php";

$user_id = $_SESSION['user_id'];

if (!isset($_GET['order_id']) || !ctype_digit((string)$_GET['order_id'])) {
    header("Location: my_orders.php");
    exit();
}
$order_id = (int)$_GET['order_id'];

$stmt = $conn->prepare("
    SELECT o.id, o.status, o.delivery_method, o.total_price, l.title, l.user_id AS seller_id
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    WHERE o.id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    set_flash('error', "Order not found.");
    header("Location: my_orders.php");
    exit();
}

if (order_is_terminal($order['status'])) {
    set_flash('error', "This order is already closed and cannot be disputed.");
    header("Location: my_orders.php");
    exit();
}

$stmt = $conn->prepare("SELECT id FROM disputes WHERE order_id = ? AND status = 'open' LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    set_flash('error', "You already have an open dispute on this order.");
    header("Location: my_orders.php");
    exit();
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "CSRF token validation failed.";
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $error = "Please describe the issue.";
        } elseif (strlen($reason) > 2000) {
            $error = "Reason is too long (max 2000 characters).";
        } else {
            $evidence_for_db = null;

            if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                $tmp_path      = $_FILES['evidence']['tmp_name'];
                $original_name = $_FILES['evidence']['name'];

                $allowed_mimes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

                $mime        = mime_content_type($tmp_path);
                $ext_in_name = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                if (!isset($allowed_mimes[$mime]) || !in_array($ext_in_name, $allowed_exts, true)) {
                    $error = "Evidence must be JPG, PNG, or WEBP.";
                } else {
                    $image = false;
                    switch ($mime) {
                        case 'image/jpeg': $image = @imagecreatefromjpeg($tmp_path); break;
                        case 'image/png':  $image = @imagecreatefrompng($tmp_path); break;
                        case 'image/webp': $image = @imagecreatefromwebp($tmp_path); break;
                    }
                    if (!$image) {
                        $error = "Failed to process evidence image.";
                    } else {
                        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/Uploads/disputes/";
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0755, true);
                        }
                        $new_ext     = $allowed_mimes[$mime];
                        $unique_name = uniqid() . "." . $new_ext;
                        $target_file = $target_dir . $unique_name;
                        $evidence_for_db = "Uploads/disputes/" . $unique_name;

                        $saved = false;
                        switch ($mime) {
                            case 'image/jpeg': $saved = imagejpeg($image, $target_file, 85); break;
                            case 'image/png':  $saved = imagepng($image, $target_file); break;
                            case 'image/webp': $saved = imagewebp($image, $target_file, 85); break;
                        }
                        imagedestroy($image);

                        if (!$saved) {
                            $error = "Failed to save evidence image.";
                            $evidence_for_db = null;
                        }
                    }
                }
            } elseif (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
                $error = "Evidence upload failed. Please try again.";
            }

            if (!$error) {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO disputes (order_id, user_id, reason, evidence, status) VALUES (?, ?, ?, ?, 'open')");
                    $stmt->bind_param("iiss", $order_id, $user_id, $reason, $evidence_for_db);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE orders SET status = 'disputed' WHERE id = ?");
                    $stmt->bind_param("i", $order_id);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();

                    notify_seller_order_status($conn, (int)$order['seller_id'], $order_id, "buyer opened a dispute. Funds remain in escrow until an admin reviews.");

                    set_flash('error', "Dispute submitted. An admin will review it shortly.");
                    header("Location: my_orders.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to submit dispute. Please try again.";
                }
            }
        }
    }
}

include "../Includes/header.php";
?>

<div class="container">
    <h1>Open a Dispute</h1>
    <p>For order #<?php echo (int)$order['id']; ?> &mdash; <strong><?php echo htmlspecialchars($order['title']); ?></strong> (R<?php echo number_format((float)$order['total_price'], 2); ?>, <?php echo htmlspecialchars(ucfirst($order['delivery_method'])); ?>)</p>
    <p>Funds will remain held in escrow while an admin reviews your dispute.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <label for="reason">What went wrong?</label><br>
        <textarea name="reason" id="reason" rows="6" cols="60" maxlength="2000" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>

        <p>
            <label for="evidence">Evidence (optional, image only):</label><br>
            <input type="file" name="evidence" id="evidence" accept="image/*">
        </p>

        <button type="submit" class="btn btn-danger">Submit dispute</button>
        <a href="my_orders.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include "../Includes/footer.php"; ?>
