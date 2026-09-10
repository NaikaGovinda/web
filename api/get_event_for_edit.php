<?php
// get_event_for_edit.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php перенесен на самый верх, ручной session_start() отсутствует
$pdo = require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$event_id = (int)($_GET['id'] ?? 0);

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверные данные']);
    exit;
}

try {
    // [АРХИТЕКТУРА] Сначала проверяем, является ли пользователь глобальным админом (is_admin === 1)
    $adminStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $adminStmt->execute([$user_id]);
    $userRow = $adminStmt->fetch();
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);

    if ($isAdmin) {
        // Администратор видит любое событие без проверки лидерства в группе
        $stmt = $pdo->prepare("SELECT * FROM `events` WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
    } else {
        // Лидер группы проверяется локально через таблицу group_leaders
        $stmt = $pdo->prepare("
            SELECT e.*
            FROM `events` e
            JOIN `groups` g ON e.group_id = g.id
            JOIN group_leaders gl ON g.id = gl.group_id
            WHERE e.id = ? AND gl.user_id = ?
        ");
        $stmt->execute([$event_id, $user_id]);
        $event = $stmt->fetch();
    }

    if (!$event) {
        throw new Exception('Событие не найдено или доступ запрещён');
    }

    // === Достаем все будильники-оповещения для этого события из event_dates ===
    $notifStmt = $pdo->prepare("
        SELECT notify_at 
        FROM `event_dates` 
        WHERE event_id = ? 
        ORDER BY notify_at ASC
    ");
    $notifStmt->execute([$event_id]);

    // Складываем даты в плоский массив строк
    $notifications = $notifStmt->fetchAll(PDO::FETCH_COLUMN);

    // Типизация ID для стабильности Android WebView
    $event['id'] = (int)$event['id'];
    $event['group_id'] = (int)$event['group_id'];

    // Добавляем массив будильников прямо внутрь JSON ответа
    echo json_encode([
        'success' => true, 
        'event' => $event,
        'notifications' => $notifications
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
