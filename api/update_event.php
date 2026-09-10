<?php
// update_event.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';

// Теперь проверка авторизации отработает корректно
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // [ИСПРАВЛЕНО] Удалена дублирующая строка чтения php://input
    $input = json_decode(file_get_contents('php://input'), true);

    $user_id = (int)$_SESSION['user_id'];
    
    // Перебираем все возможные варианты написания ID события
    $event_id = (int)($input['id'] ?? $input['event_id'] ?? 0);

    // Перебираем все возможные варианты написания ID группы
    $group_id = (int)($input['group_id'] ?? $input['groupId'] ?? 0);

    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');

    // Перебираем все возможные варианты написания даты события
    $event_date = $input['event_date'] ?? $input['eventDate'] ?? '';

    $notifications = $input['notifications'] ?? [];

    // Проверяем обязательные поля
    if (!$event_id || !$group_id || empty($title) || empty($event_date)) {
        $missing = [];
        if (!$event_id) $missing[] = 'id события';
        if (!$group_id) $missing[] = 'id группы';
        if (empty($title)) $missing[] = 'название';
        if (empty($event_date)) $missing[] = 'дата';

        throw new Exception('Все поля обязательны. Не получено: ' . implode(', ', $missing));
    }

    // 1. Проверяем, что событие существует и принадлежит указанной группе
    $stmt = $pdo->prepare("SELECT group_id FROM `events` WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    if (!$event || (int)$event['group_id'] !== $group_id) {
        throw new Exception('Событие не найдено или не принадлежит этой группе');
    }

    // [АРХИТЕКТУРА] Проверяем статус глобального суперадмина строго по числовому флагу is_admin === 1
    $adminStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $adminStmt->execute([$user_id]);
    $userRow = $adminStmt->fetch();
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);

    if (!$isAdmin) {
        // 2. БЕЗОПАСНАЯ ПРОВЕРКА: Если не админ, то пользователь должен быть лидером группы (через group_leaders)
        $stmt = $pdo->prepare("
            SELECT g.id 
            FROM `groups` g
            JOIN group_leaders gl ON g.id = gl.group_id
            WHERE g.id = ? AND gl.user_id = ?
        ");
        $stmt->execute([$group_id, $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception('У вас нет прав на редактирование событий в этой группе');
        }
    }

    // Парсим дату самого события для валидации будильников
    try {
        $dt = new DateTime($event_date, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        throw new Exception('Неверный формат даты события');
    }
    $now = new DateTime('now', new DateTimeZone('UTC'));

    // 3. Обновляем основное событие
    $stmt = $pdo->prepare("
        UPDATE `events` 
        SET title = ?, description = ?, event_date = ?
        WHERE id = ?
    ");
    $stmt->execute([$title, $description, $event_date, $event_id]);

    // === ОБНОВЛЕНИЕ БУДИЛЬНИКОВ МЕТОДОМ ПЕРЕЗАПИСИ ===

    // Сначала полностью вычищаем все старые будильники этого события
    $deleteDatesStmt = $pdo->prepare("DELETE FROM `event_dates` WHERE event_id = ?");
    $deleteDatesStmt->execute([$event_id]);

    // Теперь накатываем новые будильники из формы
    if (!empty($notifications) && is_array($notifications)) {
        $stmtDate = $pdo->prepare("INSERT INTO `event_dates` (event_id, notify_at) VALUES (?, ?)");

        foreach ($notifications as $notify_date) {
            $notify_date = trim($notify_date);
            if (empty($notify_date)) continue;

            try {
                $ndt = new DateTime($notify_date, new DateTimeZone('UTC'));
            } catch (Exception $e) {
                throw new Exception('Неверный формат даты напоминания: ' . $notify_date);
            }

            // Проверка: будильник должен быть в будущем
            if ($ndt <= $now) {
                throw new Exception('Дата каждого напоминания должна быть в будущем времени');
            }

            // Проверка: будильник не должен быть позже самой встречи
            if ($ndt > $dt) {
                throw new Exception('Время напоминания не может быть позже времени самого события');
            }

            // Записываем обновленный будильник в базу
            $stmtDate->execute([$event_id, $ndt->format('Y-m-d H:i:s')]);
        }
    }
    // === КОНЕЦ БЛОКА ОБНОВЛЕНИЯ БУДИЛЬНИКОВ ===

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
