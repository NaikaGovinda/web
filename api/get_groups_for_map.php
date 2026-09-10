<?php
// get_groups_for_map.php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

// [ИЗМЕНЕНО] Разрешаем просмотр без авторизации
$type = $_GET['type'] ?? 'namahatta';

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            city,
            address,
            lat,
            lng
        FROM `groups`
        WHERE type = ?
          AND status = 'active'
          AND lat IS NOT NULL
          AND lng IS NOT NULL
    ");

    $stmt->execute([$type]);
    $groups = $stmt->fetchAll();

    // Типизация
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