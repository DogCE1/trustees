<?php
include "../Includes/auth.php";
include "../Includes/db.php";

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: /ITECA-Website/Admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || (int)$user['is_verified'] !== 1) {
    header("Location: ../index.php");
    exit();
}

$create_error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: create.php");
        exit();
    }
    $title       = $_POST['Title'];
    $description = $_POST['Description'];
    $price       = $_POST['Price'];
    $category    = $_POST['Category'];
    $condition   = $_POST['Condition'];

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    $max_images   = 5;

    if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
        $create_error = "Please upload at least one image.";
    } else {
        $files = $_FILES['images'];
        $count = 0;
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $count++;
        }
        if ($count < 1) {
            $create_error = "Please upload at least one image.";
        } elseif ($count > $max_images) {
            $create_error = "You can upload up to $max_images images.";
        }
    }

    $saved_paths = [];

    if (!$create_error) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/Uploads/listings/";

        for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                $create_error = "Upload failed for one of the images.";
                break;
            }

            $tmp_path      = $_FILES['images']['tmp_name'][$i];
            $original_name = $_FILES['images']['name'][$i];
            $mime          = mime_content_type($tmp_path);
            $ext_in_name   = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            if (!isset($allowed_mimes[$mime]) || !in_array($ext_in_name, $allowed_exts, true)) {
                $create_error = "Invalid image format on \"$original_name\". Only JPG, PNG, and WEBP are allowed.";
                break;
            }

            $image = false;
            switch ($mime) {
                case 'image/jpeg': $image = @imagecreatefromjpeg($tmp_path); break;
                case 'image/png':  $image = @imagecreatefrompng($tmp_path); break;
                case 'image/webp': $image = @imagecreatefromwebp($tmp_path); break;
            }
            if (!$image) {
                $create_error = "Failed to process \"$original_name\".";
                break;
            }

            $new_ext      = $allowed_mimes[$mime];
            $unique_name  = uniqid('', true) . "." . $new_ext;
            $target_file  = $target_dir . $unique_name;
            $image_for_db = "Uploads/listings/" . $unique_name;

            $saved = false;
            switch ($mime) {
                case 'image/jpeg': $saved = imagejpeg($image, $target_file, 85); break;
                case 'image/png':  $saved = imagepng($image, $target_file); break;
                case 'image/webp': $saved = imagewebp($image, $target_file, 85); break;
            }
            imagedestroy($image);

            if (!$saved) {
                $create_error = "Error saving \"$original_name\".";
                break;
            }
            $saved_paths[] = $image_for_db;
        }

        if ($create_error && !empty($saved_paths)) {
            foreach ($saved_paths as $p) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . '/ITECA-Website/' . $p);
            }
            $saved_paths = [];
        }
    }

    if (!$create_error && !empty($saved_paths)) {
        $cover  = $saved_paths[0];
        $extras = array_slice($saved_paths, 1);

        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO listings (user_id, title, description, price, category, item_condition, image) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdsss", $user_id, $title, $description, $price, $category, $condition, $cover);
            $stmt->execute();
            $listing_id = $conn->insert_id;
            $stmt->close();

            if (!empty($extras)) {
                $stmt = $conn->prepare("INSERT INTO listing_images (listing_id, image, sort_order) VALUES (?, ?, ?)");
                foreach ($extras as $idx => $path) {
                    $sort = $idx + 1;
                    $stmt->bind_param("isi", $listing_id, $path, $sort);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $conn->commit();
            set_flash('success', "Listing \"" . $title . "\" created and submitted for admin verification.");
            header("Location: ../Listings/my_listings.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            foreach ($saved_paths as $p) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . '/ITECA-Website/' . $p);
            }
            $create_error = "Error creating listing.";
        }
    }
}

include "../Includes/header.php";
?>

    <div class="container">
        <h1>Create a New Listing</h1>

        <?php if (!empty($create_error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($create_error); ?></div>
        <?php endif; ?>

        <form action="create.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="text" name="Title" placeholder="Title" required>
            <textarea name="Description" placeholder="Description" required></textarea>
            <input type="number" name="Price" placeholder="Price" required>
            <select name="Category" required>
                <option value="" disabled selected>Select Category</option>
                <option value="Electronics">Electronics</option>
                <option value="Furniture">Furniture</option>
                <option value="Clothing">Clothing</option>
                <option value="Books">Books</option>
                <option value="Sports">Sports</option>
                <option value="Other">Other</option>
            </select>
            <select name="Condition" required>
                <option value="" disabled selected>Select Condition</option>
                <option value="new">New</option>
                <option value="like_new">Like New</option>
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
                <option value="refurbished">Refurbished</option>
            </select>

            <label for="images">Photos (1&ndash;5; first is the cover):</label>
            <input type="file" name="images[]" id="images" accept="image/*" multiple required>

            <button type="submit">Create Listing</button>
        </form>
    </div>

<?php
include "../Includes/footer.php";
?>