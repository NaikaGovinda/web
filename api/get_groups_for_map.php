<?php
// get_groups_for_map.php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

$type = $_GET['type'] ?? null;
$city = $_GET['city'] ?? null;

try {
    $whereClauses = ["status = 'active'", "lat IS NOT NULL", "lng IS NOT NULL"];
    $params = [];

    if (!empty($type) && $type !== 'all') {
        $whereClauses[] = "type = ?";
        $params[] = $type;
    }

    if (!empty($city) && $city !== 'all') {
        $whereClauses[] = "city = ?";
        $params[] = $city;
    }

    $whereSql = implode(" AND ", $whereClauses);

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            city,
            address,
            lat,
            lng,
            type,
            description
        FROM `groups`
        WHERE $whereSql
    ");

    $stmt->execute($params);
    $groups = $stmt->fetchAll();

    foreach ($groups as &$g) {
        $g['id'] = (int)$g['id'];
        $g['lat'] = (float)$g['lat'];
        $g['lng'] = (float)$g['lng'];
    }
    unset($g);

    echo json_encode($groups, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>