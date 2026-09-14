<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Получаем последние 50 уведомлений пользователя с новыми полями
    $stmt = $pdo->prepare("
        SELECT id, type, title, message, group_id, target_page, target_params, `read` AS is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Группировка уведомлений
    $grouped = [];
    $ungrouped = [];

    foreach ($notifications as $notif) {
        // Определяем тип уведомления и ключ группировки
        $isPrivate = false;
        $groupKey = null;
        
        if ($notif['target_params']) {
            try {
                $params = json_decode($notif['target_params'], true);
                if (isset($params['open_private_chat']) && $params['open_private_chat']) {
                    $isPrivate = true;
                    // Для ЛС группируем по отправителю (open_private_chat)
                    $groupKey = 'private_' . (int)$params['open_private_chat'];
                } elseif (isset($params['group_id']) && $params['group_id']) {
                    // Для групповых чатов группируем по group_id
                    $groupKey = 'group_' . (int)$params['group_id'];
                }
            } catch (Exception $e) {
                // Ignoring parse errors
            }
        }
        
        // Если есть ключ группировки и это chat_message — добавляем в группу
        if ($notif['type'] === 'chat_message' && $groupKey) {
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'is_grouped' => true,
                    'group_key' => $groupKey,
                    'group_id' => $isPrivate ? null : (int)preg_replace('/^group_/', '', $groupKey),
                    'count' => 0,
                    'items' => []
                ];
            }
            $grouped[$groupKey]['count']++;
            $grouped[$groupKey]['items'][] = $notif;
        } else {
            // Не группируем
            $ungrouped[] = [
                'is_grouped' => false,
                'id' => $notif['id'],
                'type' => $notif['type'],
                'title' => $notif['title'],
                'message' => $notif['message'],
                'group_id' => $notif['group_id'],
                'target_page' => $notif['target_page'],
                'target_params' => $notif['target_params'],
                'is_read' => $notif['is_read'],
                'created_at' => $notif['created_at']
            ];
        }
    }

    // Формируем итоговый список: сгруппированные сначала, затем отдельные
    $result = [];
    foreach ($grouped as $gKey => $group) {
        // Сортируем по дате (новые первые)
        usort($group['items'], function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Если в группе только 1 сообщение — не группируем, показываем отдельно
        if ($group['count'] === 1) {
            $result[] = [
                'is_grouped' => false,
                'id' => $group['items'][0]['id'],
                'type' => $group['items'][0]['type'],
                'title' => $group['items'][0]['title'],
                'message' => $group['items'][0]['message'],
                'group_id' => $group['items'][0]['group_id'],
                'target_page' => $group['items'][0]['target_page'],
                'target_params' => $group['items'][0]['target_params'],
                'is_read' => $group['items'][0]['is_read'],
                'created_at' => $group['items'][0]['created_at']
            ];
        } else {
            // Несколько сообщений — группируем
            $result[] = [
                'is_grouped' => true,
                'group_key' => $group['group_key'],
                'group_id' => $group['group_id'],
                'count' => $group['count'],
                'items' => $group['items'],
                'is_read' => $group['items'][0]['is_read'] // Нечитанное если хотя бы одно не прочитано
            ];
        }
    }

    // Добавляем несгруппированные
    $result = array_merge($result, $ungrouped);
    
    // Отладка: логируем результаты группировки
    error_log('[get_notifications] User ' . $user_id . ': Grouped groups: ' . count($grouped) . ', Ungrouped count: ' . count($ungrouped));
    foreach ($grouped as $gKey => $group) {
        error_log('[get_notifications] Group ' . $gKey . ' has ' . $group['count'] . ' items');
    }
    
    // Отладка: логируем финальный ответ
    $debugResult = $result;
    error_log('[get_notifications] User ' . $user_id . ': Final notifications count: ' . count($result));

    echo json_encode([
        'success' => true,
        'notifications' => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
