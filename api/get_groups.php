<?php
// get_groups.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, структура сессий соблюдена
$pdo = require __DIR__ . '/db.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

try {
    $type = $_GET['type'] ?? 'namahatta';
    $online = isset($_GET['online']) ? (int)$_GET['online'] : null;
    $all = isset($_GET['all']) ? (int)$_GET['all'] : null;

    // Принимаем координаты пользователя с фронтенда
    $user_lat = isset($_GET['user_lat']) && $_GET['user_lat'] !== '' ? (float)$_GET['user_lat'] : null;
    $user_lng = isset($_GET['user_lng']) && $_GET['user_lng'] !== '' ? (float)$_GET['user_lng'] : null;

    // Базовые условия фильтрации
    $whereClauses = ["g.type = ?"];
    $params = [$type];

    if ($online === 1) {
        $whereClauses[] = "g.is_online = 1";
    }

    $whereSql = implode(" AND ", $whereClauses);

    // Если координаты пользователя переданы, внедряем формулу гаверсинусов (радиус Земли ~6371 км)
    $distanceSelect = "NULL as distance";
    $orderBy = "g.id DESC"; // Сортировка по умолчанию для новых групп

    if ($user_lat !== null && $user_lng !== null) {
        $distanceSelect = "(6371 * acos(
            cos(radians(?)) * cos(radians(g.lat)) * 
            cos(radians(g.lng) - radians(?)) + 
            sin(radians(?)) * sin(radians(g.lat))
        )) AS distance";

        // Порядок добавления переменных строго соответствует знакам '?' в SQL
        array_unshift($params, $user_lat, $user_lng, $user_lat);

        // Сначала показываем онлайн-группы, затем офлайн-группы по возрастанию расстояния
        $orderBy = "g.is_online DESC, distance ASC";
    }

    // Запрос собирает данные групп, количество участников и считает расстояние
    $sql = "
        SELECT g.*, $distanceSelect,
               (SELECT COUNT(*) FROM applications a WHERE a.group_id = g.id AND a.status = 'approved') as member_count
        FROM `groups` g
        WHERE $whereSql
        ORDER BY $orderBy
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $groups = $stmt->fetchAll();

    // [ОПТИМИЗАЦИЯ] Строгое приведение типов данных для корректной работы JS/WebView
    foreach ($groups as &$g) {
        $g['id'] = (int)$g['id'];
        $g['is_online'] = (int)$g['is_online'];
        $g['member_count'] = (int)$g['member_count'];
        
        if ($g['lat'] !== null) $g['lat'] = (float)$g['lat'];
        if ($g['lng'] !== null) $g['lng'] = (float)$g['lng'];
        if ($g['distance'] !== null) $g['distance'] = (float)$g['distance'];
    }

    // [ИСПРАВЛЕНО] Добавлен флаг вывода чистой кириллицы
    echo json_encode([
        'success' => true,
        'groups' => $groups
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
