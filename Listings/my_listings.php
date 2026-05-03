<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT l.id, l.title, l.description, l.price, l.category, l.item_condition, l.status,
           l.rejection_reason, l.image, l.created_at,
           (SELECT o.id FROM orders o WHERE o.listing_id = l.id ORDER BY o.created_at DESC LIMIT 1) AS order_id
    FROM listings l
    WHERE l.user_id = ?
    ORDER BY
        CASE l.status
            WHEN 'pending'  THEN 0
            WHEN 'rejected' THEN 1
            WHEN 'verified' THEN 2
            WHEN 'sold'     THEN 3
        END,
        l.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$gallery_by_listing = [];
if (!empty($listings)) {
    foreach ($listings as $l) {
        $gallery_by_listing[(int)$l['id']] = [];
        if (!empty($l['image'])) {
            $gallery_by_listing[(int)$l['id']][] = $l['image'];
        }
    }
    $ids = array_keys($gallery_by_listing);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT listing_id, image FROM listing_images WHERE listing_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $extras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($extras as $e) {
            $gallery_by_listing[(int)$e['listing_id']][] = $e['image'];
        }
    }
}

$flash_success = get_flash('success');

include "../Includes/header.php";
?>

<div class="container">
    <h1>My Listings</h1>
    <p>Every item you have posted, including those still awaiting review or rejected by an admin.</p>

    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div>
    <?php endif; ?>

    <p><a href="create.php" class="btn btn-primary">Create New Listing</a></p>

    <?php if (empty($listings)): ?>
        <p>You haven't posted any listings yet.</p>
    <?php else: ?>
        <?php foreach ($listings as $listing): ?>
            <?php
                $lid = (int)$listing['id'];
                $gallery = $gallery_by_listing[$lid] ?? [];
                $can_edit = in_array($listing['status'], ['pending', 'rejected', 'verified'], true);
            ?>
            <div class="listing-card" style="margin-bottom:1.5rem;">
                <h2><?php echo htmlspecialchars($listing['title']); ?></h2>
                <p>
                    <strong>Price:</strong> R<?php echo number_format((float)$listing['price'], 2); ?>
                    &middot; <strong>Category:</strong> <?php echo htmlspecialchars($listing['category'] ?? '—'); ?>
                    &middot; <strong>Condition:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $listing['item_condition'] ?? '—'))); ?>
                    &middot; <strong>Posted:</strong> <?php echo date("Y-m-d", strtotime($listing['created_at'])); ?>
                </p>
                <p>
                    <strong>Status:</strong>
                    <?php echo htmlspecialchars(ucfirst($listing['status'])); ?>
                    <?php if ($listing['status'] === 'rejected' && !empty($listing['rejection_reason'])): ?>
                        <br><small><strong>Rejection reason:</strong> <?php echo nl2br(htmlspecialchars($listing['rejection_reason'])); ?></small>
                    <?php endif; ?>
                </p>

                <?php if (!empty($gallery)): ?>
                    <div style="display:flex; flex-wrap:wrap; gap:.5rem; margin:.5rem 0 1rem;">
                        <?php foreach ($gallery as $img): ?>
                            <a href="../<?php echo htmlspecialchars($img); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($img); ?>" alt="" style="max-width:160px; max-height:120px; object-fit:cover; border:1px solid #ccc; border-radius:4px;">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><em>No photos uploaded.</em></p>
                <?php endif; ?>

                <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                    <a href="view.php?id=<?php echo $lid; ?>" class="btn btn-secondary">View</a>
                    <?php if ($can_edit): ?>
                        <a href="edit.php?id=<?php echo $lid; ?>" class="btn btn-primary">Edit</a>
                    <?php endif; ?>
                    <?php if ($listing['status'] === 'sold' && !empty($listing['order_id'])): ?>
                        <a href="../Orders/my_sales.php?order_id=<?php echo (int)$listing['order_id']; ?>" class="btn btn-primary">View Sale #<?php echo (int)$listing['order_id']; ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
