<?php
require_once __DIR__ . '/../Includes/db.php';

if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}
$id = $_GET['id'];
$sql = "SELECT * FROM listings WHERE id = ? AND user_id IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();
$stmt->close();

$gallery = [];
if ($listing) {
    if (!empty($listing['image'])) {
        $gallery[] = $listing['image'];
    }
    $stmt = $conn->prepare("SELECT image FROM listing_images WHERE listing_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $extras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($extras as $e) {
        $gallery[] = $e['image'];
    }
}

$viewer_id = $_SESSION['user_id'] ?? null;
$is_owner  = $listing && $viewer_id !== null && (int)$listing['user_id'] === (int)$viewer_id;

if (!$listing) {
    http_response_code(404);
}

$more_from_seller = [];
$seller_name = null;
if ($listing && !empty($listing['user_id'])) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $listing['user_id']);
    $stmt->execute();
    $seller_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $seller_name = $seller_row['name'] ?? null;

    $stmt = $conn->prepare("
        SELECT id, title, price, image
        FROM listings
        WHERE user_id = ? AND id <> ? AND status = 'verified'
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->bind_param("ii", $listing['user_id'], $id);
    $stmt->execute();
    $more_from_seller = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

require_once "../Includes/header.php";
?>


<div class="container">
    <?php if ($listing): ?>
        <h1><?php echo htmlspecialchars($listing['title']); ?></h1>

        <?php if ($is_owner && $listing['status'] === 'rejected' && !empty($listing['rejection_reason'])): ?>
            <div class="alert alert-danger">
                <strong>This listing was rejected by an admin.</strong><br>
                <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($listing['rejection_reason'])); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($gallery)): ?>
            <div class="gallery">
                <p><a href="<?php echo BASE_URL; ?>/Listings/browse.php">&larr; Back to browse</a></p>
                <img src="../<?php echo htmlspecialchars($gallery[0]); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="listing-image gallery-main" id="gallery-main">
                <?php if (count($gallery) > 1): ?>
                    <div class="gallery-thumbs">
                        <?php foreach ($gallery as $idx => $img): ?>
                            <img src="../<?php echo htmlspecialchars($img); ?>" alt="" class="gallery-thumb<?php if ($idx === 0) echo ' is-active'; ?>" data-full="../<?php echo htmlspecialchars($img); ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (count($gallery) > 1): ?>
                <script>
                (function () {
                    var main = document.getElementById('gallery-main');
                    var thumbs = document.querySelectorAll('.gallery-thumb');
                    thumbs.forEach(function (t) {
                        t.addEventListener('click', function () {
                            main.src = t.dataset.full;
                            thumbs.forEach(function (x) { x.classList.remove('is-active'); });
                            t.classList.add('is-active');
                        });
                    });
                })();
                </script>
            <?php endif; ?>
        <?php endif; ?>
        <p><?php echo nl2br(htmlspecialchars($listing['description'])); ?></p>
        <p><strong>Price:</strong> R<?php echo htmlspecialchars($listing['price']); ?></p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars($listing['category']); ?></p>
        <p><strong>Condition:</strong> <?php echo htmlspecialchars($listing['item_condition']); ?></p>
        <p><strong>Posted on:</strong> <?php echo date("F j, Y, g:i a", strtotime($listing['created_at'])); ?></p>
        <p></p>
        <div class="d-flex gap-2">
            <?php if (!$is_owner): ?>
                <?php if ($viewer_id !== null): ?>
                    <p><button class="btn btn-primary" onclick="location.href='<?php echo BASE_URL; ?>/Orders/checkout.php?id=<?php echo (int)$listing['id']; ?>'">Buy Now</button></p>
                    <p><a class="btn btn-primary"
                        href="<?php echo BASE_URL; ?>/Messages/conversation.php?with=<?php echo (int)$listing['user_id']; ?>&listing=<?php echo (int)$listing['id']; ?>">
                        Contact Seller
                    </a></p>
                <?php else: ?>
                    <p><a class="btn btn-primary" href="<?php echo BASE_URL; ?>/login.php">Log in to buy</a></p>
                    <p><a class="btn btn-primary" href="<?php echo BASE_URL; ?>/login.php">Log in to contact seller</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($more_from_seller)): ?>
            <section class="more-from-seller" style="margin-top:2rem;">
                <h2>More from <?php echo htmlspecialchars($seller_name ?? 'this seller'); ?></h2>
                <div class="more-from-seller-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
                    <?php foreach ($more_from_seller as $other): ?>
                        <a href="view.php?id=<?php echo (int)$other['id']; ?>" class="more-from-seller-card" style="text-decoration:none;color:inherit;">
                            <?php if (!empty($other['image'])): ?>
                                <img src="../<?php echo htmlspecialchars($other['image']); ?>" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:6px;">
                            <?php endif; ?>
                            <div><strong><?php echo htmlspecialchars($other['title']); ?></strong></div>
                            <div>R<?php echo htmlspecialchars($other['price']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <p>Listing not found.</p>
    <?php endif; ?>
</div>


<?php
require_once "../Includes/footer.php";
?>