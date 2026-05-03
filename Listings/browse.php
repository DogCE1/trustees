<?php
include '../Includes/auth.php';
require_once __DIR__ . '/../Includes/db.php';

$q          = trim($_GET['q'] ?? '');
$category   = trim($_GET['category'] ?? '');
$condition  = $_GET['condition'] ?? '';
$min_price  = $_GET['min_price'] ?? '';
$max_price  = $_GET['max_price'] ?? '';
$sort       = $_GET['sort'] ?? 'newest';

// Derive the category list from actually-verified listings so legacy / admin-set
// categories (like the seed's 'Home') aren't silently dropped from the filter.
$db_categories = [];
$cat_res = $conn->query("SELECT DISTINCT category FROM listings WHERE status = 'verified' AND user_id IS NOT NULL AND category IS NOT NULL AND category <> '' ORDER BY category ASC");
if ($cat_res) {
    while ($row = $cat_res->fetch_assoc()) {
        $db_categories[] = $row['category'];
    }
}
$valid_categories = array_values(array_unique(array_merge(
    ['Electronics', 'Furniture', 'Clothing', 'Books', 'Sports', 'Other'],
    $db_categories
)));
sort($valid_categories);

$valid_conditions = ['', 'new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
$valid_sorts      = ['newest', 'price_asc', 'price_desc'];

if ($category !== '' && !in_array($category, $valid_categories, true))   $category = '';
if (!in_array($condition, $valid_conditions, true))  $condition = '';
if (!in_array($sort, $valid_sorts, true))            $sort = 'newest';

$where  = ["l.status = 'verified'", "l.user_id IS NOT NULL"];
$params = [];
$types  = '';

if ($q !== '') {
    $where[]  = "(l.title LIKE ? OR l.description LIKE ?)";
    $like     = "%" . $q . "%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}
if ($category !== '') {
    $where[]  = "l.category = ?";
    $params[] = $category;
    $types   .= "s";
}
if ($condition !== '') {
    $where[]  = "l.item_condition = ?";
    $params[] = $condition;
    $types   .= "s";
}
if ($min_price !== '' && is_numeric($min_price)) {
    $where[]  = "l.price >= ?";
    $params[] = (float)$min_price;
    $types   .= "d";
}
if ($max_price !== '' && is_numeric($max_price)) {
    $where[]  = "l.price <= ?";
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
    LEFT JOIN users u ON l.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY $order_by
    LIMIT 100
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../Includes/header.php';

function listing_glyph($title) {
    $clean = preg_replace('/[^A-Za-z0-9 ]/', '', (string)$title);
    $words = preg_split('/\s+/', trim($clean));
    if (!empty($words[0])) {
        return strtoupper(substr($words[0], 0, 3)) ?: '·';
    }
    return '·';
}
function format_rand($n) {
    return number_format((float)$n, 0, ',', "\u{2009}");
}
?>

<div class="page">
    <h1>Browse listings</h1>
    <p class="tr-muted" style="margin-top:-4px;"><?= count($listings) ?> verified item<?= count($listings) === 1 ? '' : 's' ?> · tap a card for details</p>

    <form method="get" class="search-form" data-live-search="search_api.php">
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
            <input type="text" name="q" placeholder="Search by title or description"
                   value="<?php echo htmlspecialchars($q); ?>" style="flex:1; min-width:200px;"
                   autocomplete="off">

            <select name="category" data-filter-category style="width:auto;">
                <option value="">All categories</option>
                <?php foreach ($valid_categories as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php if ($category === $c) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="condition" style="width:auto;">
                <option value="">Any condition</option>
                <?php foreach (array_filter($valid_conditions) as $c): ?>
                    <option value="<?php echo $c; ?>" <?php if ($condition === $c) echo 'selected'; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $c)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="number" name="min_price" placeholder="Min R" min="0" step="0.01" style="width:110px;"
                   value="<?php echo htmlspecialchars($min_price); ?>">
            <input type="number" name="max_price" placeholder="Max R" min="0" step="0.01" style="width:110px;"
                   value="<?php echo htmlspecialchars($max_price); ?>">

            <select name="sort" style="width:auto;">
                <option value="newest"     <?php if ($sort === 'newest')     echo 'selected'; ?>>Newest first</option>
                <option value="price_asc"  <?php if ($sort === 'price_asc')  echo 'selected'; ?>>Price: low to high</option>
                <option value="price_desc" <?php if ($sort === 'price_desc') echo 'selected'; ?>>Price: high to low</option>
            </select>

            <button type="submit" class="btn btn-primary">Search</button>
            <a href="browse.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="Filter-buttons">
        <button type="button" data-quick-category=""<?= $category === '' ? ' class="is-active"' : '' ?>>All</button>
        <?php foreach ($valid_categories as $c): ?>
            <button type="button" data-quick-category="<?= htmlspecialchars($c) ?>"<?= $category === $c ? ' class="is-active"' : '' ?>><?= htmlspecialchars($c) ?></button>
        <?php endforeach; ?>
    </div>

    <p class="search-status" data-live-status>
        <strong><?php echo count($listings); ?></strong> result<?php echo count($listings) === 1 ? '' : 's'; ?>.
    </p>

    <div class="search-results" data-live-results>
        <?php if (empty($listings)): ?>
            <p>No listings match your filters. Try widening your search.</p>
        <?php else: ?>
            <?php foreach ($listings as $r): ?>
                <a class="listing-card" href="view.php?id=<?= (int)$r['id'] ?>">
                    <div class="listing-card-media">
                        <?php if (!empty($r['image'])): ?>
                            <img src="../<?= htmlspecialchars($r['image']) ?>" alt="">
                        <?php else: ?>
                            <span class="listing-card-glyph"><?= htmlspecialchars(listing_glyph($r['title'])) ?></span>
                        <?php endif; ?>
                        <span class="pill pill-verified listing-card-pill">✓ Verified</span>
                    </div>
                    <div class="listing-card-body">
                        <h3 class="listing-card-title"><?= htmlspecialchars($r['title']) ?></h3>
                        <div class="listing-card-row">
                            <span class="listing-card-meta"><?= htmlspecialchars($r['seller_name'] ?? 'Deleted user') ?></span>
                            <span class="price">R<?= format_rand($r['price']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../Includes/footer.php'; ?>
