<?php
include "../Includes/auth.php";
include "../Includes/db.php";
include "../Includes/notifications.php";

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

$stores = [];
$result = $conn->query("SELECT id, name, address, latitude, longitude FROM stores ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stores[] = $row;
    }
}

$error = $unavailable ?? null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$error) {
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: checkout.php?id=" . urlencode($listing_id));
        exit();
    }
    $delivery_method  = $_POST['delivery_method'] ?? '';
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $meetup_store_id  = isset($_POST['meetup_store_id']) && ctype_digit((string)$_POST['meetup_store_id'])
        ? (int)$_POST['meetup_store_id']
        : 0;
    $allowed_methods  = ['collect', 'delivery', 'meetup'];

    if (!in_array($delivery_method, $allowed_methods, true)) {
        $error = "Please select a valid delivery method.";
    } elseif ($delivery_method === 'delivery' && $delivery_address === '') {
        $error = "Delivery address is required for delivery orders.";
    } elseif ($delivery_method === 'meetup' && $meetup_store_id <= 0) {
        $error = "Please choose a meetup location.";
    } elseif ($delivery_method === 'meetup') {
        $stmt = $conn->prepare("SELECT id FROM stores WHERE id = ?");
        $stmt->bind_param("i", $meetup_store_id);
        $stmt->execute();
        $valid_store = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$valid_store) {
            $error = "The selected meetup location is no longer available.";
        }
    }

    if (!$error) {
        $unit_price = (float)$listing['price'];
        $total_price = $unit_price;

        if ($balance < $total_price) {
            $error = "Insufficient wallet balance. Please deposit funds first.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$current || (float)$current['balance'] < $total_price) {
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

                $addr_for_db  = $delivery_method === 'delivery' ? $delivery_address : null;
                $store_for_db = $delivery_method === 'meetup'   ? $meetup_store_id  : null;

                $stmt = $conn->prepare("
                    INSERT INTO orders (buyer_id, listing_id, delivery_method, delivery_address, meetup_store_id, status, quantity, total_price, unit_price_at_purchase)
                    VALUES (?, ?, ?, ?, ?, 'received', 1, ?, ?)
                ");
                $stmt->bind_param("iissidd", $user_id, $listing_id, $delivery_method, $addr_for_db, $store_for_db, $total_price, $unit_price);
                $stmt->execute();
                $order_id = (int)$conn->insert_id;
                $stmt->close();

                if ($order_id <= 0) {
                    throw new Exception("Could not create order record.");
                }

                $stmt = $conn->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
                $stmt->bind_param("di", $total_price, $user_id);
                $stmt->execute();
                $stmt->close();
                $balance_after = (float)$current['balance'] - $total_price;

                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, order_id, amount, type, balance_after) VALUES (?, ?, ?, 'hold', ?)");
                $stmt->bind_param("iidd", $user_id, $order_id, $total_price, $balance_after);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");
                $stmt->bind_param("i", $listing_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                notify_seller_of_order($conn, (int)$listing['user_id'], $order_id, $listing['title']);

                header("Location: my_orders.php");
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Could not complete purchase: " . $e->getMessage();
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
            <form method="post" id="checkout-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <h3>Delivery Method</h3>
                <label><input type="radio" name="delivery_method" value="collect" required> Collect from seller</label>
                <p class="method-hint" data-for="collect">You arrange to collect the item directly from the seller. Confirm collection in <em>My Orders</em> to release funds.</p>

                <label><input type="radio" name="delivery_method" value="meetup"> Meet up</label>
                <p class="method-hint" data-for="meetup">You and the seller agree on a public place to meet. Both parties confirm the meetup happened to release funds.</p>

                <label><input type="radio" name="delivery_method" value="delivery"> Delivery</label>
                <p class="method-hint" data-for="delivery">The seller arranges delivery to your address. A delivery proof photo is uploaded and reviewed by an admin before funds release.</p>

                <div id="delivery-address-block" hidden>
                    <label for="delivery_address">Delivery address:</label><br>
                    <textarea name="delivery_address" id="delivery_address" rows="3" cols="40"><?php echo htmlspecialchars($_POST['delivery_address'] ?? ''); ?></textarea>
                </div>

                <div id="meetup-store-block" hidden>
                    <label for="meetup_store_id">Meetup location:</label>
                    <button type="button" id="find-nearest-btn" class="btn btn-secondary" style="margin-left:0.5rem;">Find nearest to me</button>
                    <span id="meetup-geo-status" class="search-status" style="display:block;"></span>
                    <select name="meetup_store_id" id="meetup_store_id">
                        <option value="">— Choose a store —</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"
                                data-lat="<?php echo htmlspecialchars($s['latitude']); ?>"
                                data-lng="<?php echo htmlspecialchars($s['longitude']); ?>"
                                <?php if ((int)($_POST['meetup_store_id'] ?? 0) === (int)$s['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['name']); ?> &mdash; <?php echo htmlspecialchars($s['address']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <p>
                    By confirming, R<?php echo number_format((float)$listing['price'], 2); ?>
                    will be held in escrow from your wallet and released to the seller once delivery is confirmed.
                </p>

                <button type="submit" class="btn btn-primary">Confirm Purchase</button>
                <a href="../Listings/view.php?id=<?php echo (int)$listing['id']; ?>" class="btn btn-secondary">Cancel</a>
            </form>
            <script>
            (function () {
                var form       = document.getElementById('checkout-form');
                var addrBlock  = document.getElementById('delivery-address-block');
                var addrField  = document.getElementById('delivery_address');
                var storeBlock = document.getElementById('meetup-store-block');
                var storeField = document.getElementById('meetup_store_id');
                var geoBtn     = document.getElementById('find-nearest-btn');
                var geoStatus  = document.getElementById('meetup-geo-status');
                var hints      = form.querySelectorAll('.method-hint');

                function update() {
                    var selected = form.querySelector('input[name="delivery_method"]:checked');
                    var value = selected ? selected.value : null;
                    var isDelivery = value === 'delivery';
                    var isMeetup   = value === 'meetup';
                    addrBlock.hidden  = !isDelivery;
                    addrField.required = isDelivery;
                    storeBlock.hidden  = !isMeetup;
                    storeField.required = isMeetup;
                    hints.forEach(function (h) {
                        h.style.display = (h.dataset.for === value) ? 'block' : 'none';
                    });
                }
                form.addEventListener('change', update);
                update();

                geoBtn.addEventListener('click', function () {
                    if (!navigator.geolocation) {
                        geoStatus.textContent = 'Geolocation is not supported in this browser.';
                        return;
                    }
                    geoStatus.textContent = 'Locating you…';
                    navigator.geolocation.getCurrentPosition(function (pos) {
                        var lat = pos.coords.latitude;
                        var lng = pos.coords.longitude;
                        fetch('nearest_stores.php?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng))
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data.stores || !data.stores.length) {
                                    geoStatus.textContent = 'No stores configured.';
                                    return;
                                }
                                var prev = storeField.value;
                                storeField.innerHTML = '<option value="">— Choose a store —</option>' +
                                    data.stores.map(function (s) {
                                        var dist = (s.distance_km != null) ? ' (' + s.distance_km + ' km)' : '';
                                        var sel  = String(s.id) === prev ? ' selected' : '';
                                        return '<option value="' + s.id + '"' + sel + '>' +
                                            escapeHtml(s.name) + dist + ' — ' + escapeHtml(s.address) +
                                            '</option>';
                                    }).join('');
                                geoStatus.textContent = 'Sorted by distance from your location.';
                            })
                            .catch(function () {
                                geoStatus.textContent = 'Could not load stores.';
                            });
                    }, function (err) {
                        geoStatus.textContent = 'Could not get your location: ' + err.message;
                    }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 });
                });

                function escapeHtml(s) {
                    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                }
            })();
            </script>
        <?php endif; ?>
    <?php else: ?>
        <a href="../Listings/browse.php" class="btn btn-secondary">Back to Browse</a>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
