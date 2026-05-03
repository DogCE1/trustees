<?php
include '../Includes/auth_admin.php';
include '../Includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        set_flash('error', "CSRF token validation failed.");
        header("Location: listings.php");
        exit();
    }

    $action     = $_POST['action'] ?? '';
    $listing_id = (int)($_POST['listing_id'] ?? 0);

    if ($action === 'update' && $listing_id > 0) {
        $title            = trim($_POST['title'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $price            = (float)($_POST['price'] ?? 0);
        $category         = trim($_POST['category'] ?? '');
        $item_condition   = $_POST['item_condition'] ?? 'good';
        $status           = $_POST['status'] ?? 'pending';
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        $rejection_reason = ($rejection_reason === '') ? null : $rejection_reason;

        $allowed_conditions = ['new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
        $allowed_statuses   = ['pending', 'verified', 'sold', 'rejected'];
        if (!in_array($item_condition, $allowed_conditions, true)) $item_condition = 'good';
        if (!in_array($status, $allowed_statuses, true))           $status         = 'pending';

        $stmt = $conn->prepare("UPDATE listings SET title = ?, description = ?, price = ?, category = ?, item_condition = ?, status = ?, rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("ssdssssi", $title, $description, $price, $category, $item_condition, $status, $rejection_reason, $listing_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete' && $listing_id > 0) {
        $stmt = $conn->prepare("DELETE FROM listings WHERE id = ?");
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: listings.php");
    exit();
}

$search = trim($_GET['q'] ?? '');

$sql = "SELECT l.*, u.name AS seller_name, u.surname AS seller_surname
        FROM listings l
        JOIN users u ON l.user_id = u.id";
$params = [];
$types  = '';
if ($search !== '') {
    $sql .= " WHERE l.title LIKE ? OR l.description LIKE ? OR l.category LIKE ? OR u.name LIKE ? OR u.surname LIKE ?";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types = 'sssss';
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conditions = ['new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
$statuses   = ['pending', 'verified', 'sold', 'rejected'];

include '../Includes/header.php';
?>

<div class="container">
    <h1>Listings Management</h1>
    <p>Edit any listing's details. Use the status dropdown to approve, reject, or mark as sold.</p>
    <p><a href="dashboard.php" class="btn btn-secondary"><strong>&larr; Back to Dashboard</strong></a></p>

    <form method="get" action="listings.php" style="margin-bottom:1em; display:flex; gap:.5rem; max-width:500px;">
        <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title, category, description, or seller…" style="flex:1;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="listings.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Seller</th>
                <th>Title</th>
                <th>Description</th>
                <th>Price</th>
                <th>Category</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Rejection Reason</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($listings)): ?>
                <tr><td colspan="10">No listings yet.</td></tr>
            <?php else: ?>
                <?php foreach ($listings as $listing): ?>
                    <tr>
                        <form method="POST" action="listings.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="listing_id" value="<?php echo (int)$listing['id']; ?>">
                            <td><?php echo (int)$listing['id']; ?></td>
                            <td><?php echo htmlspecialchars($listing['seller_name'] ?? '—'); ?></td>
                            <td><input type="text" name="title" value="<?php echo htmlspecialchars($listing['title'] ?? ''); ?>" required></td>
                            <td><textarea name="description" rows="2"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></textarea></td>
                            <td><input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($listing['price'] ?? '0'); ?>" required></td>
                            <td><input type="text" name="category" value="<?php echo htmlspecialchars($listing['category'] ?? ''); ?>"></td>
                            <td>
                                <select name="item_condition">
                                    <?php foreach ($conditions as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php if ($listing['item_condition'] === $c) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $c)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="status">
                                    <?php foreach ($statuses as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php if ($listing['status'] === $s) echo 'selected'; ?>><?php echo ucfirst($s); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><textarea name="rejection_reason" rows="2" placeholder="(optional)"><?php echo htmlspecialchars($listing['rejection_reason'] ?? ''); ?></textarea></td>
                            <td>
                                <button type="submit" name="action" value="update" class="btn btn-primary">Save</button>
                                <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Delete this listing? This cannot be undone.');">Delete</button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../Includes/footer.php'; ?>
