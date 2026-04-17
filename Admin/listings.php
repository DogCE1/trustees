<?php
//Still need to change  listing views and create listing to only show verified listings
include '../Admin/verify_listings.php';
include '../Includes/header.php';
?>

 <div class ="container">
        <h1> Verify Listings</h1>
        <p>Use the buttons below to approve or reject listing submissions.</p>
        <a href="/ITECA-Website/Admin/dashboard.php">Back to Dashboard</a>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($listings as $listing): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($listing['title']); ?></td>
                        <td><?php echo htmlspecialchars($listing['description']); ?></td>
                        <td><?php echo htmlspecialchars($listing['price']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="action" value="approve">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="action" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php
include '../Includes/footer.php';
?>