<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'], $_POST['listing_id'])){
     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: verify_listings.php");
        exit();
    }
    $listing_id = $_POST['listing_id'];
    $action = $_POST['action'];
    $new_status = null;

    if($action === 'approve'){
        $new_status = 'verified';
    } elseif($action === 'reject'){
        $new_status = 'rejected';
    }

    if($new_status){
        $update_sql = "UPDATE listings SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $listing_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: verify_listings.php");
    exit();
}

$sql = "SELECT * FROM listings WHERE status = 'pending'";
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
    <h1>Verify Listings</h1>
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
            <?php if(empty($listings)): ?>
                <tr><td colspan="4">No listings awaiting approval.</td></tr>
            <?php else: ?>
                <?php foreach($listings as $listing): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($listing['title']); ?></td>
                        <td><?php echo htmlspecialchars($listing['description']); ?></td>
                        <td><?php echo htmlspecialchars($listing['price']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit" name="action" value="approve">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit" name="action" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
include '../Includes/footer.php';
?>
