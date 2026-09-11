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

    // Группировка уведомлений по group_id для типа chat_message
    $grouped = [];
    $ungrouped = [];

    foreach ($notifications as $notif) {
        // Группируем только chat_message с group_id
        if ($notif['type'] === 'chat_message' && !empty($notif['group_id'])) {
            $groupKey = 'group_' . $notif['group_id'];
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'is_grouped' => true,
                    'group_key' => $groupKey,
                    'group_id' => $notif['group_id'],
                    'count' => 0,
                    'items' => []
                ];
            }
            $grouped[$groupKey]['count']++;
            $grouped[$groupKey]['items'][] = $notif;
        } else {
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
        
        $result[] = [
            'is_grouped' => true,
            'group_key' => $group['group_key'],
            'group_id' => $group['group_id'],
            'count' => $group['count'],
            'items' => $group['items'],
            'is_read' => $group['items'][0]['is_read'] // Нечитанное если хотя бы одно не прочитано
        ];
    }

    // Добавляем несгруппированные
    $result = array_merge($result, $ungrouped);

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
