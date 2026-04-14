<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>

    <!-- font awesome cdn link  -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- custom css file link  -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- header section start -->
    <header class="header">
        <div id="menu-bar" class="fas fa-bars"></div>
        <a href="#" class="logo">Trustees</a>
        <?php if(!isset($_SESSION['user_id'])): ?>
            <a href="/Login.php" >Login</a>
            <a href="/Register.php" >Register</a>
        <?php endif; ?>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <nav class="navbar">
                <ul>
                    <li><a href="/Admin/dashboard.php">Dashboard</a></li>
                    <li><a href= "/Admin/disputes.php">Disputes</a></li>
                    <li><a href="/Admin/listings.php">Listings</a></li>
                    <li><a href="/Admin/orders.php"> Orders</a></li>
                    <li><a href="/Admin/users.php"> Users</a></li>
                    <li><a href="/Logout.php">Logout</a></li>
                </ul>
            </nav>

        <?php elseif(isset($_SESSION['user_id'])): ?>
            <nav class="navbar">
                <ul>
                    <li><a href="/Listings/browse.php">Browse</a></li>
                    <li><a href="/Listings/create.php">Sell</a></li>
                    <li><a href="/Profile/wallet.php">Wallet</a></li>
                    <li><a href="/Profile/profile.php">Profile</a></li>
                    <li><a href="/Logout.php">Logout</a></li>
                </ul>
            </nav>
        <?php endif; ?>
        <div class="icons">
            <a href="/Listings/search.php"></a>
            <?php
            if ($user_id != '') {
                echo '<div id="user-btn" class="fas fa-user"></div>';
            } else {
                echo '<a href="/Login.php" class="btn btn-primary">Login</a>';
            }
            ?>
        </div>

        <div class="profile">
            <?php
            if ($user_id != '') {
                echo '<p>' . $_SESSION['user_name'] . '</p>';
                echo '<a href="/Logout.php" class="btn btn-danger">Logout</a>';
            }
            ?>
        </div>

    </header>
    <!-- header section end -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>