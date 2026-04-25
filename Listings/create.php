<?php
include "../Includes/auth.php";
include "../Includes/db.php";

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

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) {
        echo "No image uploaded or there was an error.";
    } else {
        $tmp_path      = $_FILES['image']['tmp_name'];
        $original_name = $_FILES['image']['name'];

        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        $mime        = mime_content_type($tmp_path);
        $ext_in_name = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if (!isset($allowed_mimes[$mime]) || !in_array($ext_in_name, $allowed_exts, true)) {
            echo "Invalid image format. Only JPG, PNG, and WEBP are allowed.";
        } else {
            $image = false;
            switch ($mime) {
                case 'image/jpeg':
                    $image = @imagecreatefromjpeg($tmp_path);
                    break;
                case 'image/png':
                    $image = @imagecreatefrompng($tmp_path);
                    break;
                case 'image/webp':
                    $image = @imagecreatefromwebp($tmp_path);
                    break;
            }

            if (!$image) {
                echo "Failed to process image.";
            } else {
                $target_dir   = $_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/Uploads/listings/";
                $new_ext      = $allowed_mimes[$mime];
                $unique_name  = uniqid() . "." . $new_ext;
                $target_file  = $target_dir . $unique_name;
                $image_for_db = "Uploads/listings/" . $unique_name;

                $saved = false;
                switch ($mime) {
                    case 'image/jpeg':
                        $saved = imagejpeg($image, $target_file, 85);
                        break;
                    case 'image/png':
                        $saved = imagepng($image, $target_file);
                        break;
                    case 'image/webp':
                        $saved = imagewebp($image, $target_file, 85);
                        break;
                }
                imagedestroy($image);

                if (!$saved) {
                    echo "Error saving image.";
                } else {
                    $sql = "INSERT INTO listings (user_id, title, description, price, category, item_condition, image) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("issdsss", $user_id, $title, $description, $price, $category, $condition, $image_for_db);
                    if ($stmt->execute()) {
                        header("Location: ../index.php");
                        exit();
                    } else {
                        echo "Error creating listing.";
                    }
                }
            }
        }
    }
}

include "../Includes/header.php";
?>

    <div class="container">
        <h1> Create a New Listing</h1>
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
                <!-- Add more categories as needed -->
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
            <input type="file" name="image" accept="image/*" required>
            <button type="submit">Create Listing</button>
        </form>
    </div>

<?php
include "../Includes/footer.php";
?>