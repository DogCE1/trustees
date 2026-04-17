<?php
include "../Includes/db.php";

if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}
$id = $_GET['id'];
$sql = "SELECT * FROM listings WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

include "../Includes/header.php";
?>


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


<?php
include "../Includes/footer.php";
?>