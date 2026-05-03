<?php
require_once __DIR__ . '/../includes/db.php';

header("Content-Type: application/json; charset=utf-8");

$q          = trim($_GET['q'] ?? '');
$category   = trim($_GET['category'] ?? '');
$condition  = $_GET['condition'] ?? '';
$min_price  = $_GET['min_price'] ?? '';
$max_price  = $_GET['max_price'] ?? '';
$sort       = $_GET['sort'] ?? 'newest';

// Accept any category that actually exists on a verified listing — keeps the API
// in sync with the dropdown that browse.php builds dynamically.
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

$valid_conditions = ['', 'new', 'like_new', 'good', 'fair', 'poor', 'refurbished'];
$valid_sorts      = ['newest', 'price_asc', 'price_desc'];

if ($category !== '' && !in_array($category, $valid_categories, true))   $category = '';
if (!in_array($condition, $valid_conditions, true))  $condition = '';
if (!in_array($sort, $valid_sorts, true))            $sort = 'newest';

$where  = ["l.status = 'verified'", "l.user_id IS NOT NULL"];
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
        COALESCE(u.name, 'Deleted user') AS seller_name
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
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['results' => $results]);
