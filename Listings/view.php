<?php
include "../includes/db.php";

if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}
$id = $_GET['id'];
$sql = "Select * from listings where id = $id";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITECA - View Listing</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="container">
    <?php if ($result->num_rows > 0): ?>
        <?php $listing = $result->fetch_assoc(); ?>
        <h1><?php echo htmlspecialchars($listing['title']); ?></h1>
        <img src="../<?php echo htmlspecialchars($listing['image']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="listing-image">
        <p><?php echo nl2br(htmlspecialchars($listing['description'])); ?></p>
        <p><strong>Price:</strong> R<?php echo htmlspecialchars($listing['price']); ?></p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars($listing['category']); ?></p>
        <p><strong>Condition:</strong> <?php echo htmlspecialchars($listing['item_condition']); ?></p>
    <?php else: ?>
        <p>Listing not found.</p>
    <?php endif; ?>
    <a href="../index.php">Back to Home</a>
</div>
</body>
</html>