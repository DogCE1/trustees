<?php
require_once "../Includes/auth.php";
require_once "../Includes/db.php";

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
    $gallery[] = ['path' => $listing['image'], 'is_cover' => true, 'image_id' => null];
}
$stmt = $conn->prepare("SELECT id, image FROM listing_images WHERE listing_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$extras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($extras as $e) {
    $gallery[] = ['path' => $e['image'], 'is_cover' => false, 'image_id' => (int)$e['id']];
}
$max_images = 5;

$error = null;
$conditions = ['new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
$categories = ['Electronics', 'Furniture', 'Clothing', 'Books', 'Sports', 'Other'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: edit.php?id=" . $listing_id);
        exit();
    }

    $action = $_POST['action'] ?? 'save_details';

    // Helper: flip listing back to pending whenever its content (photos or details) changes.
    $repend = function() use ($conn, $listing_id, $user_id) {
        $s = $conn->prepare("UPDATE listings SET status = 'pending', rejection_reason = NULL WHERE id = ? AND user_id = ?");
        $s->bind_param("ii", $listing_id, $user_id);
        $s->execute();
        $s->close();
    };

    // Helper: delete an image file from disk (best-effort).
    $unlink_image = function($web_path) {
        if (!$web_path) return;
        @unlink($_SERVER['DOCUMENT_ROOT'] . BASE_URL . '/' . $web_path);
    };

    if ($action === 'delete') {
        // Eligibility: status must be pending/rejected/verified (sold is blocked by the
        // gate above). Belt-and-braces: refuse if any order ever attached, since
        // orders.listing_id is ON DELETE SET NULL and we'd orphan order history.
        $s = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE listing_id = ?");
        $s->bind_param("i", $listing_id);
        $s->execute();
        $order_count = (int)$s->get_result()->fetch_assoc()['c'];
        $s->close();

        if ($order_count > 0) {
            set_flash('error', "This listing has order history and cannot be deleted.");
            header("Location: my_listings.php");
            exit();
        }

        // Collect image paths before dropping the row. listing_images CASCADEs at the
        // DB layer but the files on disk don't. Order: collect → delete row → unlink.
        $paths_to_unlink = [];
        foreach ($gallery as $g) {
            if (!empty($g['path'])) $paths_to_unlink[] = $g['path'];
        }

        $conn->begin_transaction();
        try {
            $s = $conn->prepare("DELETE FROM listings WHERE id = ? AND user_id = ?");
            $s->bind_param("ii", $listing_id, $user_id);
            $s->execute();
            $affected = $s->affected_rows;
            $s->close();
            if ($affected !== 1) {
                throw new RuntimeException("Listing row was not deleted.");
            }
            $conn->commit();
            foreach ($paths_to_unlink as $p) $unlink_image($p);
            set_flash('success', "Listing deleted.");
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash('error', "Could not delete listing. Please try again.");
        }
        header("Location: my_listings.php");
        exit();
    }

    if ($action === 'remove_photo') {
        $photo = $_POST['photo'] ?? '';
        $matched = null;
        foreach ($gallery as $g) {
            if ($g['path'] === $photo) { $matched = $g; break; }
        }
        if (!$matched) {
            set_flash('error', "That photo isn't part of this listing.");
            header("Location: edit.php?id=" . $listing_id);
            exit();
        }
        $conn->begin_transaction();
        try {
            if ($matched['is_cover']) {
                // Promote the first extra to cover (if any), then delete its row.
                $promo = null;
                foreach ($gallery as $g) {
                    if (!$g['is_cover']) { $promo = $g; break; }
                }
                if ($promo) {
                    $s = $conn->prepare("UPDATE listings SET image = ? WHERE id = ? AND user_id = ?");
                    $s->bind_param("sii", $promo['path'], $listing_id, $user_id);
                    $s->execute(); $s->close();
                    $s = $conn->prepare("DELETE FROM listing_images WHERE id = ? AND listing_id = ?");
                    $s->bind_param("ii", $promo['image_id'], $listing_id);
                    $s->execute(); $s->close();
                } else {
                    $s = $conn->prepare("UPDATE listings SET image = NULL WHERE id = ? AND user_id = ?");
                    $s->bind_param("ii", $listing_id, $user_id);
                    $s->execute(); $s->close();
                }
            } else {
                $s = $conn->prepare("DELETE FROM listing_images WHERE id = ? AND listing_id = ?");
                $s->bind_param("ii", $matched['image_id'], $listing_id);
                $s->execute(); $s->close();
            }
            $repend();
            $conn->commit();
            $unlink_image($matched['path']);
            set_flash('success', "Photo removed and listing re-submitted for verification.");
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash('error', "Could not remove photo. Please try again.");
        }
        header("Location: edit.php?id=" . $listing_id);
        exit();
    }

    if ($action === 'set_cover') {
        $image_id = (int)($_POST['image_id'] ?? 0);
        $matched = null;
        foreach ($gallery as $g) {
            if (!$g['is_cover'] && $g['image_id'] === $image_id) { $matched = $g; break; }
        }
        if (!$matched) {
            set_flash('error', "That photo isn't an extra on this listing.");
            header("Location: edit.php?id=" . $listing_id);
            exit();
        }
        $conn->begin_transaction();
        try {
            // If a current cover exists, demote it into listing_images at the end.
            if (!empty($listing['image'])) {
                $s = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next FROM listing_images WHERE listing_id = ?");
                $s->bind_param("i", $listing_id);
                $s->execute();
                $next = (int)$s->get_result()->fetch_assoc()['next'];
                $s->close();
                $s = $conn->prepare("INSERT INTO listing_images (listing_id, image, sort_order) VALUES (?, ?, ?)");
                $s->bind_param("isi", $listing_id, $listing['image'], $next);
                $s->execute(); $s->close();
            }
            $s = $conn->prepare("UPDATE listings SET image = ? WHERE id = ? AND user_id = ?");
            $s->bind_param("sii", $matched['path'], $listing_id, $user_id);
            $s->execute(); $s->close();
            $s = $conn->prepare("DELETE FROM listing_images WHERE id = ? AND listing_id = ?");
            $s->bind_param("ii", $matched['image_id'], $listing_id);
            $s->execute(); $s->close();
            $repend();
            $conn->commit();
            set_flash('success', "Cover photo updated and listing re-submitted for verification.");
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash('error', "Could not set cover photo. Please try again.");
        }
        header("Location: edit.php?id=" . $listing_id);
        exit();
    }

    if ($action === 'upload_more') {
        $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $allowed_exts  = ['jpg', 'jpeg', 'png', 'webp'];
        $current_count = count($gallery);
        $remaining     = $max_images - $current_count;

        if ($remaining <= 0) {
            set_flash('error', "This listing already has the maximum of $max_images photos.");
            header("Location: edit.php?id=" . $listing_id);
            exit();
        }
        if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
            set_flash('error', "No photos selected.");
            header("Location: edit.php?id=" . $listing_id);
            exit();
        }

        // Count actual files being uploaded (skip empty slots).
        $upload_count = 0;
        for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_NO_FILE) $upload_count++;
        }
        if ($upload_count === 0) {
            set_flash('error', "No photos selected.");
            header("Location: edit.php?id=" . $listing_id);
            exit();
        }
        if ($upload_count > $remaining) {
            set_flash('error', "You can only add $remaining more photo(s). You tried to add $upload_count.");
            header("Location: edit.php?id=" . $listing_id);
            exit();
        }

        $target_dir = __DIR__ . "/../Uploads/listings/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

        $saved_paths = [];
        $upload_error = null;
        for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                $upload_error = "Upload failed for one of the images."; break;
            }
            $tmp_path    = $_FILES['images']['tmp_name'][$i];
            $orig_name   = $_FILES['images']['name'][$i];
            $mime        = mime_content_type($tmp_path);
            $ext_in_name = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!isset($allowed_mimes[$mime]) || !in_array($ext_in_name, $allowed_exts, true)) {
                $upload_error = "Invalid format on \"$orig_name\". Only JPG, PNG, WEBP."; break;
            }
            $img = false;
            switch ($mime) {
                case 'image/jpeg': $img = @imagecreatefromjpeg($tmp_path); break;
                case 'image/png':  $img = @imagecreatefrompng($tmp_path); break;
                case 'image/webp': $img = @imagecreatefromwebp($tmp_path); break;
            }
            if (!$img) { $upload_error = "Failed to process \"$orig_name\"."; break; }

            $unique     = uniqid('', true) . "." . $allowed_mimes[$mime];
            $target     = $target_dir . $unique;
            $web_path   = "Uploads/listings/" . $unique;
            $saved      = false;
            switch ($mime) {
                case 'image/jpeg': $saved = imagejpeg($img, $target, 85); break;
                case 'image/png':  $saved = imagepng($img, $target);     break;
                case 'image/webp': $saved = imagewebp($img, $target, 85); break;
            }
            imagedestroy($img);
            if (!$saved) { $upload_error = "Could not save \"$orig_name\"."; break; }
            $saved_paths[] = $web_path;
        }

        if ($upload_error) {
            // Clean up any partially-saved files; nothing has hit the DB yet.
            foreach ($saved_paths as $p) $unlink_image($p);
            set_flash('error', $upload_error);
            header("Location: edit.php?id=" . $listing_id);
            exit();
        }

        // Insert into listing_images (or promote the first one to cover if there's no cover yet).
        $conn->begin_transaction();
        try {
            $s = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) AS m FROM listing_images WHERE listing_id = ?");
            $s->bind_param("i", $listing_id);
            $s->execute();
            $next_sort = (int)$s->get_result()->fetch_assoc()['m'];
            $s->close();

            $i = 0;
            if (empty($listing['image']) && !empty($saved_paths)) {
                $first = array_shift($saved_paths);
                $s = $conn->prepare("UPDATE listings SET image = ? WHERE id = ? AND user_id = ?");
                $s->bind_param("sii", $first, $listing_id, $user_id);
                $s->execute(); $s->close();
            }
            if (!empty($saved_paths)) {
                $s = $conn->prepare("INSERT INTO listing_images (listing_id, image, sort_order) VALUES (?, ?, ?)");
                foreach ($saved_paths as $idx => $path) {
                    $sort = $next_sort + $idx + 1;
                    $s->bind_param("isi", $listing_id, $path, $sort);
                    $s->execute();
                }
                $s->close();
            }
            $repend();
            $conn->commit();
            set_flash('success', "Photo(s) added and listing re-submitted for verification.");
        } catch (Throwable $e) {
            $conn->rollback();
            foreach ($saved_paths as $p) $unlink_image($p);
            set_flash('error', "Could not save photos. Please try again.");
        }
        header("Location: edit.php?id=" . $listing_id);
        exit();
    }

    // Default: save_details (existing behavior).
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
    }elseif(mb_strlen($title) > 150) {
        $error = "Title cannot exceed 150 characters.";
    } elseif (mb_strlen($description) > 5000) {
        $error = "Description cannot exceed 5000 characters.";
    }
    else {
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

require_once "../Includes/header.php";
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
        <h3>Current photos (<?php echo count($gallery); ?>/<?php echo $max_images; ?>)</h3>
        <div style="display:flex; flex-wrap:wrap; gap:1rem; margin:.5rem 0 1rem;">
            <?php foreach ($gallery as $img): ?>
                <div style="display:flex; flex-direction:column; gap:.25rem; align-items:center;">
                    <a href="../<?php echo htmlspecialchars($img['path']); ?>" target="_blank">
                        <img src="../<?php echo htmlspecialchars($img['path']); ?>" alt="" style="max-width:180px; max-height:140px; object-fit:cover; border:<?php echo $img['is_cover'] ? '2px solid #2a7' : '1px solid #ccc'; ?>; border-radius:4px;">
                    </a>
                    <small><?php echo $img['is_cover'] ? '<strong>Cover</strong>' : 'Extra'; ?></small>
                    <div style="display:flex; gap:.25rem;">
                        <?php if (!$img['is_cover']): ?>
                            <form method="post" action="edit.php?id=<?php echo $listing_id; ?>" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="set_cover">
                                <input type="hidden" name="image_id" value="<?php echo (int)$img['image_id']; ?>">
                                <button type="submit" class="btn btn-secondary" style="font-size:.8rem; padding:.2rem .4rem;">Set as cover</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="edit.php?id=<?php echo $listing_id; ?>" style="margin:0;" onsubmit="return confirm('Remove this photo? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="remove_photo">
                            <input type="hidden" name="photo" value="<?php echo htmlspecialchars($img['path']); ?>">
                            <button type="submit" class="btn btn-danger" style="font-size:.8rem; padding:.2rem .4rem;">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (count($gallery) < $max_images): ?>
        <h3>Add more photos</h3>
        <form method="post" action="edit.php?id=<?php echo $listing_id; ?>" enctype="multipart/form-data" style="margin-bottom:1rem;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="upload_more">
            <p><small>Up to <?php echo $max_images - count($gallery); ?> more photo(s) (JPG, PNG, WEBP).</small></p>
            <input type="file" name="images[]" accept="image/*" multiple required>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
    <?php else: ?>
        <p><small>This listing already has the maximum of <?php echo $max_images; ?> photos. Remove one to upload another.</small></p>
    <?php endif; ?>

    <form method="post" action="edit.php?id=<?php echo $listing_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="save_details">

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
