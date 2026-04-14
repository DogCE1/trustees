<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';

$sql = "Select * from listings where status = 'pending'";
$result = $conn->query($sql);

$listings = [];
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $listings[] = $row;
    }
} else {
    $no_listings = true;
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'],$_POST['listing_id'])){  //Check if action and listing_id are set in the POST request
    
    $listing_id = $_POST['listing_id'];
    $action = $_POST['action'];
    $new_status = null;

//Approve listing
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
    header("Location: listings.php");
    exit();
}
?>


   
