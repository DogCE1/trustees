<?php
include "../Includes/db.php";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = mysqli_real_escape_string($conn, $_POST['Title']);
    $description = mysqli_real_escape_string($conn, $_POST['Description']);
    $price = mysqli_real_escape_string($conn, $_POST['Price']);
    $category = mysqli_real_escape_string($conn, $_POST['Category']);
    $condition = mysqli_real_escape_string($conn, $_POST['Condition']);
    $user_id = $_SESSION['user_id'];

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image_name = basename($_FILES['image']['name']);
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/ITECA-Website/Uploads/listings/";
        $unique_id = uniqid() . "_" . $image_name;
        $target_file = $target_dir . $unique_id;
        $image_for_db = "Uploads/listings/" . $unique_id; // Path to store in database

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Insert listing into database
            $sql = "INSERT INTO listings (user_id, title, description, price, category, item_condition, image) 
                    VALUES ('$user_id', '$title', '$description', '$price', '$category', '$condition', '$image_for_db')";
            if (mysqli_query($conn, $sql)) {
                header("Location: ../index.php");
                exit();
            } else {
                echo "Error: " . $sql . "<br>" . mysqli_error($conn);
            }
        } else {
            echo "Error uploading image.";
        }
    } else {
        echo "No image uploaded or there was an error.";
    }
}
include "../Includes/header.php";
?>

    <div class="container">
        <h1> Create a New Listing</h1>
        <form action="create.php" method="post" enctype="multipart/form-data">
            <input type="text" name="Title" placeholder="Title" required>
            <textarea name="Description" placeholder="Description" required></textarea>
            <input type="number" name="Price" placeholder="Price" required>
            <select name="Category" required>
                <option value="" disabled selected>Select Category</option>
                <option value="Electronics">Electronics</option>
                <option value="Furniture">Furniture</option>
                <option value="Clothing">Clothing</option>
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