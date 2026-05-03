<?php
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
}

$unread_notifications = 0;
if (!empty($user_id) && isset($conn) && $conn instanceof mysqli) {
    if (!function_exists('count_unread_notifications')) {
        @include_once __DIR__ . '/notifications.php';
    }
    if (function_exists('count_unread_notifications')) {
        $unread_notifications = count_unread_notifications($conn, (int)$user_id);
    }
}
?>

<?php
$user_name = $_SESSION['user_name'] ?? '';
$user_initials = '';
if ($user_name !== '') {
    $parts = preg_split('/\s+/', trim($user_name));
    $user_initials = strtoupper(mb_substr($parts[0], 0, 1));
    if (!empty($parts[1])) {
        $user_initials .= strtoupper(mb_substr($parts[1], 0, 1));
    } elseif (mb_strlen($parts[0]) > 1) {
        $user_initials .= strtoupper(mb_substr($parts[0], 1, 1));
    }
}
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
function nav_active($script_names, $current) {
    return in_array($current, (array)$script_names, true) ? ' class="is-active"' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trustees</title>

    <!-- font awesome cdn link  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- custom css file link  -->
    <link rel="stylesheet" href="/ITECA-Website/CSS/style.css">
</head>
<body>
    <!-- header section start -->
    <header class="header">
        <div id="menu-bar" class="fas fa-bars"></div>
        <?php $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; ?>
        <a href="<?= $is_admin ? '/ITECA-Website/Admin/dashboard.php' : '/ITECA-Website/index.php' ?>" class="logo">Trustees</a>
        <?php if ($is_admin): ?>
            <nav class="navbar">
                <ul>
                    <li><a<?= nav_active('dashboard.php', $current) ?> href="/ITECA-Website/Admin/dashboard.php">Dashboard</a></li>
                    <li><a<?= nav_active('disputes.php', $current) ?> href="/ITECA-Website/Admin/disputes.php">Disputes</a></li>
                    <li><a<?= nav_active(['listings.php','verify_listings.php'], $current) ?> href="/ITECA-Website/Admin/listings.php">Listings</a></li>
                    <li><a<?= nav_active('orders.php', $current) ?> href="/ITECA-Website/Admin/orders.php">Orders</a></li>
                    <li><a<?= nav_active('users.php', $current) ?> href="/ITECA-Website/Admin/users.php">Users</a></li>
                    <li><a<?= nav_active('verifications.php', $current) ?> href="/ITECA-Website/Admin/verifications.php">Verifications</a></li>
                </ul>
            </nav>
            <div style="flex:1"></div>
            <div class="header-actions">
                <div class="profile-area">
                    <button type="button" id="user-btn" class="user-btn" aria-haspopup="true" aria-expanded="false" aria-controls="profile-menu">
                        <span class="avatar"><?= htmlspecialchars($user_initials) ?></span>
                        <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                        <i class="fas fa-caret-down"></i>
                    </button>
                    <div id="profile-menu" class="profile-menu" hidden>
                        <a href="/ITECA-Website/Profile/profile.php"><i class="fas fa-id-card"></i> Profile</a>
                        <a href="/ITECA-Website/Admin/dashboard.php"><i class="fas fa-gauge"></i> Dashboard</a>
                        <a href="/ITECA-Website/logout.php" class="profile-menu-danger"><i class="fas fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>

        <?php elseif (isset($_SESSION['user_id'])): ?>
            <nav class="navbar">
                <ul>
                    <li><a<?= nav_active('browse.php', $current) ?> href="/ITECA-Website/Listings/browse.php">Browse</a></li>
                    <li><a<?= nav_active('create.php', $current) ?> href="/ITECA-Website/Listings/create.php">Sell</a></li>
                    <li><a<?= nav_active('conversation.php', $current) ?> href="/ITECA-Website/Messages/conversation.php">Messages</a></li>
                    <li><a<?= nav_active('verify.php', $current) ?> href="/ITECA-Website/Verification/verify.php">Verify</a></li>
                </ul>
            </nav>
            <form class="header-search" action="/ITECA-Website/Listings/browse.php" method="get" role="search">
                <i class="fas fa-search header-search-icon"></i>
                <input type="search" name="q" placeholder="Search listings…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </form>
            <div class="header-actions">
                <a href="/ITECA-Website/Notifications/inbox.php" class="header-bell" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="bell-badge"><?= (int)$unread_notifications ?></span>
                    <?php endif; ?>
                </a>
                <div class="profile-area">
                    <button type="button" id="user-btn" class="user-btn" aria-haspopup="true" aria-expanded="false" aria-controls="profile-menu">
                        <span class="avatar"><?= htmlspecialchars($user_initials) ?></span>
                        <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                        <i class="fas fa-caret-down"></i>
                    </button>
                    <div id="profile-menu" class="profile-menu" hidden>
                        <a href="/ITECA-Website/Profile/profile.php"><i class="fas fa-id-card"></i> Profile</a>
                        <a href="/ITECA-Website/Listings/my_listings.php"><i class="fas fa-tags"></i> My listings</a>
                        <a href="/ITECA-Website/Orders/my_orders.php"><i class="fas fa-bag-shopping"></i> My orders</a>
                        <a href="/ITECA-Website/Orders/my_sales.php"><i class="fas fa-receipt"></i> My sales</a>
                        <a href="/ITECA-Website/Profile/wallet.php"><i class="fas fa-wallet"></i> Wallet</a>
                        <a href="/ITECA-Website/Messages/inbox.php"><i class="fas fa-envelope"></i> Messages</a>
                        <a href="/ITECA-Website/Verification/verify.php"><i class="fas fa-shield-halved"></i> Verify</a>
                        <a href="/ITECA-Website/logout.php" class="profile-menu-danger"><i class="fas fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div style="flex:1"></div>
            <div class="profile">
                <a href="/ITECA-Website/login.php" class="btn btn-secondary">Login</a>
                <a href="/ITECA-Website/register.php" class="btn btn-primary">Register</a>
            </div>
        <?php endif; ?>

    </header>
    <!-- header section end -->
    <?php if ($flash_error = get_flash('error')): ?>
        <div class="flash flash-error"><?php echo htmlspecialchars($flash_error); ?></div>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
