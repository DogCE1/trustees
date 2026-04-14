<?php
include "includes/db.php";

$sql = "SELECT * FROM listings 
        WHERE status = 'verified' 
        ORDER BY created_at DESC 
        LIMIT 8";

$result = $conn->query($sql);
$listings = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }
}

include "Includes/header.php";
?>

<!-- main section start -->

<div class="container">
    <h1>Welcome to ITECA</h1>
    <h2>Latest Listings</h2>
    <div class="listings">
        <?php foreach ($listings as $listing): ?>
            <div class="listing">
                <h3><?php echo htmlspecialchars($listing['title']); ?></h3>
                <p><?php echo htmlspecialchars($listing['description']); ?></p>
                <a href="listings/view.php?id=<?php echo $listing['id']; ?>">
                    View listing
                </a>
                <p><strong>Price:</strong> R<?php echo htmlspecialchars($listing['price']); ?></p>
                
            </div>
        <?php endforeach; ?>
        <?php if (empty($listings)): ?>
            <p>No listings available yet.</p>
            <a href="listings/create.php" class="btn">Create New Listing</a>
        <?php endif; ?>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <a href="Admin/dashboard.php" class="btn">Admin Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<?php
include "Includes/footer.php";
?>