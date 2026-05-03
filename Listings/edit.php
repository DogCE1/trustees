<?php
include "../Includes/auth.php";
include "../Includes/db.php";

$user_id = (int)$_SESSION['user_id'];

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    header("Location: my_listings.php");
    exit();
}
$listing_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $listing_id, $user_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$listing) {
    header("Location: my_listings.php");
    exit();
}

if (!in_array($listing['status'], ['pending', 'rejected', 'verified'], true)) {
    set_flash('error', "This listing cannot be edited (status: " . $listing['status'] . ").");
    header("Location: my_listings.php");
    exit();
}

$gallery = [];
if (!empty($listing['image'])) {
    $gallery[] = $listing['image'];
}
$stmt = $conn->prepare("SELECT image FROM listing_images WHERE listing_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$extras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($extras as $e) {
    $gallery[] = $e['image'];
}

$error = null;
$conditions = ['new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
$categories = ['Electronics', 'Furniture', 'Clothing', 'Books', 'Sports', 'Other'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: edit.php?id=" . $listing_id);
        exit();
    }

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category    = trim($_POST['category'] ?? '');
    $condition   = $_POST['item_condition'] ?? 'good';

    if ($title === '') {
        $error = "Title is required.";
    } elseif ($price < 0) {
        $error = "Price cannot be negative.";
    } elseif (!in_array($condition, $conditions, true)) {
        $error = "Invalid condition.";
    } else {
        // Re-edit sends the listing back to pending so an admin reviews the changes.
        $stmt = $conn->prepare("
            UPDATE listings
            SET title = ?, description = ?, price = ?, category = ?, item_condition = ?,
                status = 'pending', rejection_reason = NULL
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ssdssii", $title, $description, $price, $category, $condition, $listing_id, $user_id);
        $stmt->execute();
        $stmt->close();

        set_flash('success', "Listing updated and re-submitted for admin verification.");
        header("Location: my_listings.php");
        exit();
    }
}

include "../Includes/header.php";
?>

<div class="container">
    <h1>Edit Listing</h1>
    <p><a href="my_listings.php" class="btn btn-secondary">&larr; Back to My Listings</a></p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        Editing a listing sends it back to <strong>pending</strong> so an admin can re-verify your changes.
    </div>

    <?php if (!empty($gallery)): ?>
        <h3>Current photos</h3>
        <div style="display:flex; flex-wrap:wrap; gap:.5rem; margin:.5rem 0 1rem;">
            <?php foreach ($gallery as $img): ?>
                <a href="../<?php echo htmlspecialchars($img); ?>" target="_blank">
                    <img src="../<?php echo htmlspecialchars($img); ?>" alt="" style="max-width:180px; max-height:140px; object-fit:cover; border:1px solid #ccc; border-radius:4px;">
                </a>
            <?php endforeach; ?>
        </div>
        <p><small>Photo management isn't editable here yet — to change photos, contact an admin or recreate the listing.</small></p>
    <?php endif; ?>

    <form method="post" action="edit.php?id=<?php echo $listing_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <label for="title">Title</label><br>
        <input type="text" name="title" id="title" maxlength="150" required value="<?php echo htmlspecialchars($_POST['title'] ?? $listing['title'] ?? ''); ?>"><br>

        <label for="description">Description</label><br>
        <textarea name="description" id="description" rows="4" cols="60" required><?php echo htmlspecialchars($_POST['description'] ?? $listing['description'] ?? ''); ?></textarea><br>

        <label for="price">Price (R)</label><br>
        <input type="number" name="price" id="price" min="0" step="0.01" required value="<?php echo htmlspecialchars($_POST['price'] ?? $listing['price'] ?? '0'); ?>"><br>

        <label for="category">Category</label><br>
        <select name="category" id="category" required>
            <?php
            $current_category = $_POST['category'] ?? $listing['category'] ?? '';
            $known = in_array($current_category, $categories, true);
            ?>
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo $c; ?>" <?php if ($current_category === $c) echo 'selected'; ?>><?php echo $c; ?></option>
            <?php endforeach; ?>
            <?php if (!$known && $current_category !== ''): ?>
                <option value="<?php echo htmlspecialchars($current_category); ?>" selected><?php echo htmlspecialchars($current_category); ?></option>
            <?php endif; ?>
        </select><br>

        <label for="item_condition">Condition</label><br>
        <select name="item_condition" id="item_condition" required>
            <?php $current_condition = $_POST['item_condition'] ?? $listing['item_condition'] ?? 'good'; ?>
            <?php foreach ($conditions as $c): ?>
                <option value="<?php echo $c; ?>" <?php if ($current_condition === $c) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $c)); ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit" class="btn btn-primary">Save changes</button>
        <a href="my_listings.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include "../Includes/footer.php"; ?>
