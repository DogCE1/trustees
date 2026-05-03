<?php
include "../Includes/auth.php";
include "../Includes/db.php";
include "../Includes/escrow.php";
include "../Includes/notifications.php";

$user_id = $_SESSION['user_id'];

if (!isset($_GET['order_id']) || !ctype_digit((string)$_GET['order_id'])) {
    header("Location: my_sales.php");
    exit();
}
$order_id = (int)$_GET['order_id'];

$stmt = $conn->prepare("
    SELECT o.id, o.status, o.delivery_method, o.delivery_address, o.total_price, o.buyer_id,
           l.title, l.user_id AS seller_id
    FROM orders o
    JOIN listings l ON o.listing_id = l.id
    WHERE o.id = ? AND l.user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    set_flash('error', "Order not found.");
    header("Location: my_sales.php");
    exit();
}

if ($order['delivery_method'] !== 'delivery') {
    set_flash('error', "This order does not require a delivery proof photo.");
    header("Location: my_sales.php");
    exit();
}

if ($order['status'] !== 'awaiting_proof') {
    set_flash('error', "This order is not awaiting a delivery proof photo.");
    header("Location: my_sales.php");
    exit();
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "CSRF token validation failed.";
    } elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a delivery proof photo.";
    } else {
        $tmp_path      = $_FILES['proof']['tmp_name'];
        $original_name = $_FILES['proof']['name'];

        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        $mime        = mime_content_type($tmp_path);
        $ext_in_name = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if (!isset($allowed_mimes[$mime]) || !in_array($ext_in_name, $allowed_exts, true)) {
            $error = "Proof must be JPG, PNG, or WEBP.";
        } else {
            $image = false;
            switch ($mime) {
                case 'image/jpeg': $image = @imagecreatefromjpeg($tmp_path); break;
                case 'image/png':  $image = @imagecreatefrompng($tmp_path); break;
                case 'image/webp': $image = @imagecreatefromwebp($tmp_path); break;
            }

            if (!$image) {
                $error = "Failed to process image.";
            } else {
                $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/Uploads/delivery_proofs/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                $new_ext     = $allowed_mimes[$mime];
                $unique_name = uniqid() . "." . $new_ext;
                $target_file = $target_dir . $unique_name;
                $proof_for_db = "Uploads/delivery_proofs/" . $unique_name;

                $saved = false;
                switch ($mime) {
                    case 'image/jpeg': $saved = imagejpeg($image, $target_file, 85); break;
                    case 'image/png':  $saved = imagepng($image, $target_file); break;
                    case 'image/webp': $saved = imagewebp($image, $target_file, 85); break;
                }
                imagedestroy($image);

                if (!$saved) {
                    $error = "Failed to save proof image.";
                } else {
                    $stmt = $conn->prepare("UPDATE orders SET delivery_proof_image = ?, status = 'pending_admin_approval' WHERE id = ? AND status = 'awaiting_proof'");
                    $stmt->bind_param("si", $proof_for_db, $order_id);
                    $stmt->execute();
                    $stmt->close();

                    notify_buyer_order_status($conn, (int)$order['buyer_id'], $order_id, "delivery proof uploaded. Awaiting admin review.");

                    header("Location: my_sales.php");
                    exit();
                }
            }
        }
    }
}

include "../Includes/header.php";
?>

<div class="container">
    <h1>Upload Delivery Proof</h1>
    <p>Order #<?php echo (int)$order['id']; ?> &mdash; <strong><?php echo htmlspecialchars($order['title']); ?></strong></p>
    <p>
        Take a photo of the delivered product (ideally with the recipient holding it).
        An admin will review the photo before funds are released from escrow.
    </p>

    <?php if (!empty($order['delivery_address'])): ?>
        <p><strong>Delivery address:</strong><br><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <label for="proof">Proof photo (JPG, PNG, or WEBP):</label><br>
        <input type="file" name="proof" id="proof" accept="image/*" required>
        <p>
            <button type="submit" class="btn btn-primary">Upload proof</button>
            <a href="my_sales.php" class="btn btn-secondary">Cancel</a>
        </p>
    </form>
</div>

<?php include "../Includes/footer.php"; ?>
