<?php
include "../Includes/auth.php";
include "../Includes/db.php";

header("Content-Type: application/json; charset=utf-8");

$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);

$result = $conn->query("SELECT id, name, address, latitude, longitude FROM stores ORDER BY name ASC");
$stores = [];
while ($row = $result->fetch_assoc()) {
    $stores[] = $row;
}

function haversine_km($lat1, $lng1, $lat2, $lng2) {
    $r = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $r * $c;
}

if ($lat !== false && $lng !== false && $lat !== null && $lng !== null) {
    foreach ($stores as &$s) {
        $s['distance_km'] = round(haversine_km($lat, $lng, (float)$s['latitude'], (float)$s['longitude']), 1);
    }
    unset($s);
    usort($stores, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);
}

echo json_encode(['stores' => $stores]);
