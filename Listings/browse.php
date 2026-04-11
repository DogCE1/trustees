<?php
include '../Includes/db.php';
include '../Includes/auth.php';
$sql = "SELECT 
            l.*, 
            u.name as seller_name 
        FROM 
            listings l 
        JOIN 
            users u ON l.user_id = u.id 
        WHERE 
            l.status = 'verified'
        Group BY l.id
        Order BY l.created_at DESC";

$result = $conn->query($sql);

if($result->num_rows > 0){
    $listings = [];
    while($row = $result->fetch_assoc()){
        $listings[] = $row;
    }
} else {
    echo "No verified listings found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Listings</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
   <div class="container">
        <h1>Browse Listings</h1>
            <p>Explore the latest verified listings below. Click "View Details" to see more information about each listing.</p>
        <div class="Filter-buttons">
            <button onclick="filterCategories('All')"> All</button>
            <button onclick="filterCategories('Electronics')">Electronics</button>
            <button onclick="filterCategories('Furniture')" >Furniture</button>
            <button onclick="filterCategories('Clothing')" >Clothing</button>
            <button onclick="filterCategories('Books')" >Books</button>
            <button onclick="filterCategories('Sports')" >Sports</button>
            <button onclick="filterCategories('Other')" >Other</button>
        </div>
         <?php foreach ($listings as $listing): ?>
        <div class= "listing-card" data-category="<?php echo htmlspecialchars($listing['category']); ?>">
            <h2><?php echo htmlspecialchars($listing['title']); ?></h2>
            <p><?php echo htmlspecialchars($listing['description']); ?></p>
            <p><strong>Seller:</strong> <?php echo htmlspecialchars($listing['seller_name']); ?></p>
            <p><strong>Price:</strong> R<?php echo htmlspecialchars($listing['price']); ?></p>
            <a href="view.php?id=<?php echo $listing['id']; ?>" class="btn">View Details</a>
        </div>
        <?php endforeach; ?>
        <?php if (empty($listings)): ?>
            <p>No listings available yet.</p>
            <a href="create.php" class="btn">Create New Listing</a>
        <?php endif; ?>
   </div>
   <script src="../JavaScript/main.js"> </script>
</body>
</html>