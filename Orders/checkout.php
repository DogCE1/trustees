<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$unavailable = false;
$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    header("Location: ../Listings/browse.php");
    exit();
}
$listing_id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT l.*, u.name AS seller_name
    FROM listings l
    JOIN users u ON l.user_id = u.id
    WHERE l.id = ?
");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$listing) {
    header("Location: ../Listings/browse.php");
    exit();
}

if ($listing['status'] !== 'verified') {
    $unavailable = "This listing is not available for purchase.";
}
if ((int)$listing['user_id'] === (int)$user_id) {
    $unavailable = "You cannot buy your own listing.";
}

$stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wallet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wallet) {
    $stmt = $conn->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $balance = 0.00;
} else {
    $balance = (float)$wallet['balance'];
}

$error = $unavailable ?? null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$error) {
    $delivery_method  = $_POST['delivery_method'] ?? '';
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $allowed_methods  = ['collect', 'delivery', 'meetup'];

    if (!in_array($delivery_method, $allowed_methods, true)) {
        $error = "Please select a valid delivery method.";
    } elseif ($delivery_method === 'delivery' && $delivery_address === '') {
        $error = "Delivery address is required for delivery orders.";
    } else {
        $price = (float)$listing['price'];

        if ($balance < $price) {
            $error = "Insufficient wallet balance. Please deposit funds first.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$current || (float)$current['balance'] < $price) {
                    throw new Exception("Insufficient wallet balance.");
                }

                $stmt = $conn->prepare("SELECT status FROM listings WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $listing_id);
                $stmt->execute();
                $still = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$still || $still['status'] !== 'verified') {
                    throw new Exception("Listing is no longer available.");
                }

                $stmt = $conn->prepare("
                    INSERT INTO orders (buyer_id, listing_id, delivery_method, delivery_address, status, quantity, total_price)
                    VALUES (?, ?, ?, ?, 'received', 1, ?)
                ");
                $addr_for_db = $delivery_method === 'delivery' ? $delivery_address : null;
                $stmt->bind_param("iissd", $user_id, $listing_id, $delivery_method, $addr_for_db, $price);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
                $stmt->bind_param("di", $price, $user_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type) VALUES (?, ?, 'hold')");
                $stmt->bind_param("id", $user_id, $price);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");
                $stmt->bind_param("i", $listing_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                header("Location: my_orders.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

include "../Includes/header.php";
?>

<div class="container">
    <h1>Checkout</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="checkout-summary">
        <h2><?php echo htmlspecialchars($listing['title']); ?></h2>
        <?php if (!empty($listing['image'])): ?>
            <img src="../<?php echo htmlspecialchars($listing['image']); ?>" alt="" class="listing-image" style="max-width:240px;">
        <?php endif; ?>
        <p><strong>Seller:</strong> <?php echo htmlspecialchars($listing['seller_name']); ?></p>
        <p><strong>Price:</strong> R<?php echo number_format((float)$listing['price'], 2); ?></p>
        <p><strong>Your wallet balance:</strong> R<?php echo number_format($balance, 2); ?></p>
    </div>
    
    <?php if ($unavailable == false): ?>
        <?php if ($balance < (float)$listing['price']): ?>
            <p>You don't have enough funds to buy this item.</p>
            <a href="../Profile/wallet.php" class="btn btn-primary">Deposit Funds</a>
        <?php else: ?>
            <form method="post">
                <h3>Delivery Method</h3>
                <label><input type="radio" name="delivery_method" value="collect" required> Collect from seller</label><br>
                <label><input type="radio" name="delivery_method" value="meetup"> Meet up</label><br>
                <label><input type="radio" name="delivery_method" value="delivery"> Delivery</label><br>

                <label for="delivery_address">Delivery address (only if "Delivery" is selected):</label><br>
                <textarea name="delivery_address" id="delivery_address" rows="3" cols="40"><?php echo htmlspecialchars($_POST['delivery_address'] ?? ''); ?></textarea>

                <p>
                    By confirming, R<?php echo number_format((float)$listing['price'], 2); ?>
                    will be held from your wallet and released to the seller once you confirm delivery.
                </p>

                <button type="submit" class="btn btn-primary">Confirm Purchase</button>
                <a href="../Listings/view.php?id=<?php echo (int)$listing['id']; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <a href="../Listings/browse.php" class="btn btn-secondary">Back to Browse</a>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
