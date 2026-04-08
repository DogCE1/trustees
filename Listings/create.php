<?php
session_start();
include "../includes/auth.php";
include "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['Title'];
    $description = $_POST['Description'];
    $price = $_POST['Price'];
    $category = $_POST['Category'];
    $condition = $_POST['Condition'];
    $user_id = $_SESSION['user_id'];

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image_name = basename($_FILES['image']['name']);
        $target_dir = "uploads/";
        $target_file = $target_dir . uniqid() . "_" . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Insert listing into database
            $sql = "INSERT INTO listings (user_id, title, description, price, category, item_condition, image) 
                    VALUES ('$user_id', '$title', '$description', '$price', '$category', '$condition', '$target_file')";
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITECA - Create Listing</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
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
                <option value="New">New</option>
                <option value="Like New">Like New</option>
                <option value="Good">Good</option>
                <option value="Fair">Fair</option>
                <option value="Poor">Poor</option>
            </select>
            <input type="file" name="image" accept="image/*" required>
            <button type="submit">Create Listing</button>
        </form>
    </div>
</body>
</html>