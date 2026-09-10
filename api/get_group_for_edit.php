<?php
// /api/get_group_for_edit.php
// [КОДИРОВКА] Явно задаем UTF-8 для заголовка ответа, чтобы полностью убрать кракозябры в браузере и Android WebView
header('Content-Type: application/json; charset=utf-8');

// db.php подключен на самом верху, сессии централизованы в ядре
$pdo = require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

try {
    // [ИСПРАВЛЕНО] Убрано жесткое условие AND status = 'active', так как у некоторых групп статус может быть пустым
    $stmt = $pdo->prepare("
        SELECT id, name, description, type, is_online, city, address, timezone, lat, lng 
        FROM `groups` 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $group = $stmt->fetch();

    if (!$group) {
        throw new Exception('Группа не найдена в базе данных');
    }

    // Собираем массив ID лидеров этой группы из таблицы связей
    $stmtLeaders = $pdo->prepare("SELECT user_id FROM group_leaders WHERE group_id = ?");
    $stmtLeaders->execute([$id]);
    $group['leaders'] = $stmtLeaders->fetchAll(PDO::FETCH_COLUMN);

    // Строгая типизация переменных для стабильности фронтенда
    $group['id'] = (int)$group['id'];
    $group['is_online'] = (int)$group['is_online'];
    $group['lat'] = $group['lat'] !== null ? (float)$group['lat'] : null;
    $group['lng'] = $group['lng'] !== null ? (float)$group['lng'] : null;

    // [КОДИРОВКА] Флаг JSON_UNESCAPED_UNICODE гарантирует вывод чистой кириллицы
    echo json_encode(['success' => true, 'group' => $group], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
