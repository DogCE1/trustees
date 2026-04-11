<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';

$sql = "Select * from listings where status = 'pending'";
$result = $conn->query($sql);

if($result-> num_rows > 0){
    $listings = [];
    while($row = $result->fetch_assoc()){
        $listings[] = $row;
    }
} else {
    echo "No listings pending verification.";
    exit();
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    //Approve listing
    if($_POST['action'] === 'approve'){
        $listing_id = $_POST['listing_id'];
        $sql = "UPDATE listings SET status = 'verified' WHERE id = '$listing_id'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }
    //Reject listing
    else if($_POST['action'] === 'reject'){
        $listing_id = $_POST['listing_id'];
        $sql = "UPDATE listings SET status = 'rejected' WHERE id = '$listing_id'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }
    header("Location: verify_listings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Listings</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <div class ="container">
        <h1> Verify Listings</h1>
        <p>Use the buttons below to approve or reject listing submissions.</p>
        <a href="listings.php">Back to Listing Management</a>
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
</body>
</html>