<?php
include "../Includes/auth_admin.php";
include "../Includes/db.php";

//Fetch total users
$sql = "Select COUNT(*) as Total_Users From users";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalUsers = $row['Total_Users'];

//Fetch total listings
$sql = "Select COUNT(*) as Total_Listings From listings";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalListings = $row['Total_Listings'];

//Fetch total pending listings
$sql = "Select COUNT(*) as Total_Pending From listings where status = 'pending'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalPending = $row['Total_Pending'];

//Fetch total disputes
$sql = "Select COUNT(*) as Total_Disputes From disputes";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalDisputes = $row['Total_Disputes'];

include "../Includes/header.php";
?>


<div class="container">
    <h1>Admin Dashboard</h1>
    <div class="dashboard">
        <div class="dashboard-item">
            <h2>Total Users</h2>
            <p><?php echo $totalUsers; ?></p>
            <a href="users.php">View Users</a>
        </div>
        <div class="dashboard-item">
            <h2>Total Listings</h2>
            <p><?php echo $totalListings; ?></p>
            <a href="listings.php">View Listings</a>
        </div>
        <div class="dashboard-item">
            <h2>Pending Listings</h2>
            <p><?php echo $totalPending; ?></p>
            <a href="verify_listings.php">View Pending Listings</a>
        </div>
        <div class="dashboard-item">
            <h2>Total Disputes</h2>
            <p><?php echo $totalDisputes; ?></p>
            <a href="disputes.php">View Disputes</a>
        </div>
    </div>
    <a href="../index.php">Back to Home</a>

    <a href="../logout.php">Logout</a>
</div>

<?php
include "../Includes/footer.php";
?>