<?php
include "../Includes/db.php";

$q          = trim($_GET['q'] ?? '');
$category   = $_GET['category'] ?? '';
$condition  = $_GET['condition'] ?? '';
$min_price  = $_GET['min_price'] ?? '';
$max_price  = $_GET['max_price'] ?? '';
$sort       = $_GET['sort'] ?? 'newest';

$valid_categories = ['', 'Electronics', 'Furniture', 'Clothing', 'Books', 'Sports', 'Other'];
$valid_conditions = ['', 'new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
$valid_sorts      = ['newest', 'price_asc', 'price_desc'];

if (!in_array($category, $valid_categories, true))   $category = '';
if (!in_array($condition, $valid_conditions, true))  $condition = '';
if (!in_array($sort, $valid_sorts, true))            $sort = 'newest';

$where  = ["l.status = 'verified'"];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $like    = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}
if ($category !== '') {
    $where[] = "l.category = ?";
    $params[] = $category;
    $types   .= "s";
}
if ($condition !== '') {
    $where[] = "l.item_condition = ?";
    $params[] = $condition;
    $types   .= "s";
}
if ($min_price !== '' && is_numeric($min_price)) {
    $where[] = "l.price >= ?";
    $params[] = (float)$min_price;
    $types   .= "d";
}
if ($max_price !== '' && is_numeric($max_price)) {
    $where[] = "l.price <= ?";
    $params[] = (float)$max_price;
    $types   .= "d";
}

$order_by = match ($sort) {
    'price_asc'  => 'l.price ASC',
    'price_desc' => 'l.price DESC',
    default      => 'l.created_at DESC',
};

$sql = "
    SELECT l.id, l.title, l.description, l.price, l.category, l.item_condition, l.image, l.created_at,
           u.name AS seller_name
    FROM listings l
    JOIN users u ON l.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY $order_by
    LIMIT 100
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include "../Includes/header.php";
?>

<div class="container">
    <h1>Search Listings</h1>

    <form method="get" class="search-form">
        <input type="text" name="q" placeholder="Search by title or description"
               value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px;">

        <select name="category">
            <option value="">All categories</option>
            <?php foreach (array_filter($valid_categories) as $c): ?>
                <option value="<?php echo $c; ?>" <?php if ($category === $c) echo 'selected'; ?>>
                    <?php echo $c; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="condition">
            <option value="">Any condition</option>
            <?php foreach (array_filter($valid_conditions) as $c): ?>
                <option value="<?php echo $c; ?>" <?php if ($condition === $c) echo 'selected'; ?>>
                    <?php echo ucfirst(str_replace('_', ' ', $c)); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="number" name="min_price" placeholder="Min R" min="0" step="0.01"
               value="<?php echo htmlspecialchars($min_price); ?>">
        <input type="number" name="max_price" placeholder="Max R" min="0" step="0.01"
               value="<?php echo htmlspecialchars($max_price); ?>">

        <select name="sort">
            <option value="newest"     <?php if ($sort === 'newest')     echo 'selected'; ?>>Newest first</option>
            <option value="price_asc"  <?php if ($sort === 'price_asc')  echo 'selected'; ?>>Price: low to high</option>
            <option value="price_desc" <?php if ($sort === 'price_desc') echo 'selected'; ?>>Price: high to low</option>
        </select>

        <button type="submit" class="btn btn-primary">Search</button>
        <a href="search.php" class="btn btn-secondary">Reset</a>
    </form>

    <p><strong><?php echo count($results); ?></strong> result<?php echo count($results) === 1 ? '' : 's'; ?>.</p>

    <?php if (empty($results)): ?>
        <p>No listings match your filters. Try widening your search.</p>
    <?php else: ?>
        <div class="search-results">
            <?php foreach ($results as $r): ?>
                <div class="listing-card">
                    <?php if (!empty($r['image'])): ?>
                        <img src="../<?php echo htmlspecialchars($r['image']); ?>" alt="" style="max-width:200px;">
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($r['title']); ?></h2>
                    <p><?php echo htmlspecialchars(mb_strimwidth($r['description'] ?? '', 0, 140, '…')); ?></p>
                    <p>
                        <strong>R<?php echo number_format((float)$r['price'], 2); ?></strong> &middot;
                        <?php echo htmlspecialchars($r['category']); ?> &middot;
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $r['item_condition']))); ?>
                    </p>
                    <p><small>Seller: <?php echo htmlspecialchars($r['seller_name']); ?></small></p>
                    <a href="view.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-primary">View</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include "../Includes/footer.php"; ?>
