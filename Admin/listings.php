<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';

$sql = "SELECT * FROM listings WHERE status = 'verified'";
$result = $conn->query($sql);

$listings = [];
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $listings[] = $row;
    }
}

include '../Includes/header.php';
?>

<div class="container">
    <h1>Verified Listings</h1>
    <p>All listings that have been approved and are currently live.</p>
    <a href="/ITECA-Website/Admin/dashboard.php">Back to Dashboard</a>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($listings)): ?>
                <tr><td colspan="3">No verified listings yet.</td></tr>
            <?php else: ?>
                <?php foreach($listings as $listing): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($listing['title']); ?></td>
                        <td><?php echo htmlspecialchars($listing['description']); ?></td>
                        <td><?php echo htmlspecialchars($listing['price']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
include '../Includes/footer.php';
?>
