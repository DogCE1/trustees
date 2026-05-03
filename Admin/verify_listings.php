<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';
include '../Includes/notifications.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: verify_listings.php");
        exit();
    }

    $action     = $_POST['action'] ?? '';
    $listing_id = (int)($_POST['listing_id'] ?? 0);

    if ($listing_id <= 0) {
        header("Location: verify_listings.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT user_id, title FROM listings WHERE id = ? AND user_id IS NOT NULL");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$info) {
        set_flash('error', "Listing not found.");
        header("Location: verify_listings.php");
        exit();
    }

    $seller_id = (int)$info['user_id'];
    $title     = $info['title'] ?? '';

    if ($action === 'save_edit') {
        $new_title       = trim($_POST['title'] ?? '');
        $new_description = trim($_POST['description'] ?? '');
        $new_price       = (float)($_POST['price'] ?? 0);
        $new_category    = trim($_POST['category'] ?? '');
        $new_condition   = $_POST['item_condition'] ?? 'good';

        $allowed_conditions = ['new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
        if (!in_array($new_condition, $allowed_conditions, true)) $new_condition = 'good';

        if ($new_title === '' || $new_price < 0) {
            set_flash('error', "Title is required and price must be non-negative.");
        } else {
            $stmt = $conn->prepare("UPDATE listings SET title = ?, description = ?, price = ?, category = ?, item_condition = ? WHERE id = ?");
            $stmt->bind_param("ssdssi", $new_title, $new_description, $new_price, $new_category, $new_condition, $listing_id);
            $stmt->execute();
            $stmt->close();
            set_flash('success', "Listing #$listing_id updated.");
        }
    } elseif ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE listings SET status = 'verified', rejection_reason = NULL WHERE id = ?");
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        $approved = $stmt->affected_rows > 0;
        $stmt->close();

        if ($approved) {
            notify($conn, $seller_id, "Your listing \"$title\" was approved.", "/ITECA-Website/Listings/my_listings.php");
            set_flash('success', "Listing \"$title\" approved.");
        } else {
            set_flash('error', "Could not approve listing.");
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');
        $reason_or_null = ($reason === '') ? null : $reason;

        $stmt = $conn->prepare("UPDATE listings SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $reason_or_null, $listing_id);
        $stmt->execute();
        $rejected = $stmt->affected_rows > 0;
        $stmt->close();

        if ($rejected) {
            $msg = "Your listing \"$title\" was rejected.";
            if ($reason !== '') {
                $msg .= " Reason: $reason";
            }
            notify($conn, $seller_id, $msg, "/ITECA-Website/Listings/my_listings.php");
            set_flash('success', "Listing \"$title\" rejected.");
        } else {
            set_flash('error', "Could not reject listing.");
        }
    }

    header("Location: verify_listings.php" . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit();
}

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT l.id, l.user_id, l.title, l.description, l.price, l.category, l.item_condition,
           l.image, l.status, l.created_at,
           u.name AS seller_name, u.surname AS seller_surname, u.email AS seller_email
    FROM listings l
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'pending'
";
$params = [];
$types  = '';
if ($search !== '') {
    $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.category LIKE ? OR u.name LIKE ? OR u.surname LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types = 'sssss';
}
$sql .= " ORDER BY l.created_at ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
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
        $types_g = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT listing_id, image FROM listing_images WHERE listing_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
        $stmt->bind_param($types_g, ...$ids);
        $stmt->execute();
        $extras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($extras as $e) {
            $gallery_by_listing[(int)$e['listing_id']][] = $e['image'];
        }
    }
}

$flash_success = get_flash('success');
$conditions = ['new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];

include '../Includes/header.php';
?>

<div class="container">
    <h1>Verify Listings</h1>
    <p><a href="/ITECA-Website/Admin/dashboard.php" class="btn btn-secondary"><strong>&larr; Back to Dashboard</strong></a></p>
    <p>Review pending listings. You can edit details before approving, or reject with a reason.</p>

    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div>
    <?php endif; ?>

    <form method="get" action="verify_listings.php" style="margin-bottom:1em; display:flex; gap:.5rem; max-width:500px;">
        <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search pending by title, category, or seller…" style="flex:1;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="verify_listings.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($listings)): ?>
        <p>No listings awaiting approval<?php echo ($search !== '') ? ' for that search' : ''; ?>.</p>
    <?php else: ?>
        <?php foreach ($listings as $listing): ?>
            <?php $lid = (int)$listing['id']; $gallery = $gallery_by_listing[$lid] ?? []; ?>
            <div class="listing-card" style="margin-bottom:1.5rem;">
                <h2><?php echo htmlspecialchars($listing['title']); ?></h2>
                <p>
                    <strong>Seller:</strong>
                    <?php echo htmlspecialchars(trim(($listing['seller_name'] ?? '') . ' ' . ($listing['seller_surname'] ?? ''))); ?>
                    &middot; <?php echo htmlspecialchars($listing['seller_email'] ?? ''); ?>
                    &middot; <strong>Submitted:</strong> <?php echo date("Y-m-d H:i", strtotime($listing['created_at'])); ?>
                </p>

                <?php if (!empty($gallery)): ?>
                    <div style="display:flex; flex-wrap:wrap; gap:.5rem; margin:.5rem 0 1rem;">
                        <?php foreach ($gallery as $img): ?>
                            <a href="../<?php echo htmlspecialchars($img); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($img); ?>" alt="" style="max-width:200px; max-height:140px; object-fit:cover; border:1px solid #ccc; border-radius:4px;">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><em>No photos uploaded.</em></p>
                <?php endif; ?>

                <form method="POST" action="verify_listings.php<?php echo ($search !== '') ? '?q=' . urlencode($search) : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="listing_id" value="<?php echo $lid; ?>">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem; max-width:800px;">
                        <label>Title
                            <input type="text" name="title" value="<?php echo htmlspecialchars($listing['title'] ?? ''); ?>" required>
                        </label>
                        <label>Price (R)
                            <input type="number" step="0.01" min="0" name="price" value="<?php echo htmlspecialchars($listing['price'] ?? '0'); ?>" required>
                        </label>
                        <label>Category
                            <input type="text" name="category" value="<?php echo htmlspecialchars($listing['category'] ?? ''); ?>">
                        </label>
                        <label>Condition
                            <select name="item_condition">
                                <?php foreach ($conditions as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php if ($listing['item_condition'] === $c) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $c)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label style="display:block; margin-top:.5rem;">Description
                        <textarea name="description" rows="3" style="width:100%; max-width:800px;"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></textarea>
                    </label>

                    <div style="margin-top:.5rem;">
                        <textarea name="rejection_reason" rows="2" cols="50" placeholder="Reason (required for rejection)"></textarea>
                    </div>

                    <div style="margin-top:.5rem; display:flex; gap:.5rem; flex-wrap:wrap;">
                        <button type="submit" name="action" value="save_edit" class="btn btn-secondary">Save edits</button>
                        <button type="submit" name="action" value="approve" class="btn btn-success" onclick="return confirm('Approve this listing? The seller will be notified.');">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this listing? The seller will be notified.');">Reject</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../Includes/footer.php'; ?>
