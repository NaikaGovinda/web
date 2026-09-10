<?php
// create_event.php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/send_fcm.php'; 

if (!isset($_SESSION['user_id'])) {
	http_response_code(403);
	echo json_encode(['success' => false, 'error' => 'Требуется вход']);
	exit;
}

try {
    

    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = (int)($input['group_id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $event_date = $input['event_date'] ?? '';
    
    // [НОВОЕ] Получаем массив будильников-оповещений из фронтенда
    $notifications = $input['notifications'] ?? [];

    if (!$group_id || !$title || !$event_date) {
        throw new Exception('Все поля обязательны');
    }

    // Проверяем, что пользователь — лидер группы ИЛИ админ
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT g.id, g.name 
        FROM `groups` g
        WHERE g.id = ? AND (
            EXISTS(SELECT 1 FROM group_leaders gl WHERE gl.group_id = g.id AND gl.user_id = ?) OR
            EXISTS(SELECT 1 FROM users u WHERE u.id = ? AND u.is_admin = 1)
        )
    ");
    $stmt->execute([$group_id, $user_id, $user_id]);
    $group = $stmt->fetch();

    if (!$group) {
        throw new Exception('У вас нет прав на создание событий в этой группе');
    }

    // Парсим дату самого события
    try {
        $dt = new DateTime($event_date, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        throw new Exception('Неверный формат даты события');
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));
    if ($dt <= $now) {
        throw new Exception('Дата события должна быть в будущем');
    }
    $mysql_date = $dt->format('Y-m-d H:i:s');

    // Сохраняем основное событие в таблицу events
    $stmt = $pdo->prepare("INSERT INTO `events` (group_id, title, description, event_date) VALUES (?, ?, ?, ?)");
    $stmt->execute([$group_id, $title, $description, $mysql_date]);
    $event_id = $pdo->lastInsertId();

    // === [НОВОЕ] ЦИКЛ ПРОВЕРКИ И СОХРАНЕНИЯ БУДИЛЬНИКОВ-ОПОВЕЩЕНИЙ ===
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

            // Напоминание логически должно улетать в будущем времени
            if ($ndt <= $now) {
                throw new Exception('Дата каждого напоминания должна быть в будущем времени');
            }

            // Напоминание не может сработать позже, чем начнётся само событие
            if ($ndt > $dt) {
                throw new Exception('Время напоминания не может быть позже времени самого события');
            }

            // Записываем будильник в таблицу event_dates
            $stmtDate->execute([$event_id, $ndt->format('Y-m-d H:i:s')]);
        }
    }
    // === КОНЕЦ БЛОКА СОХРАНЕНИЯ БУДИЛЬНИКОВ ===

    // === УВЕДОМЛЕНИЕ ВСЕХ УЧАСТНИКОВ ГРУППЫ О СОЗДАНИИ ===
    try {
        $stmtMembers = $pdo->prepare("
            SELECT user_id FROM applications 
            WHERE group_id = ? AND status = 'approved'
        ");
        $stmtMembers->execute([$group_id]);
        $memberIds = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtLeaders = $pdo->prepare("SELECT user_id FROM group_leaders WHERE group_id = ?");
        $stmtLeaders->execute([$group_id]);
        $leaderIds = $stmtLeaders->fetchAll(PDO::FETCH_COLUMN);
        
        $recipientIds = array_unique(array_merge($memberIds, $leaderIds));
        
        if (!empty($recipientIds)) {
            $placeholders = str_repeat('?,', count($recipientIds) - 1) . '?';
            $stmtTokens = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id IN ($placeholders)");
            $stmtTokens->execute($recipientIds);
            $tokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($tokens)) {
                $titleNotif = '📅 Новое событие!';
                $bodyNotif = "В группе «{$group['name']}» запланировано: {$title}";
                $data = [
                    'type' => 'new_event',
                    'event_id' => (string)$event_id,
                    'group_id' => (string)$group_id,
                    'action' => 'view_event'
                ];
                sendFcmMessages($tokens, $titleNotif, $bodyNotif, $data);
            }
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Event notify error: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
