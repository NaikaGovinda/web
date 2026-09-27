<?php
// api/sadhana/leaderboard.php — общий прогресс участников
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_helper.php';

$user_id = requireAuth($pdo);

$limit = min(max((int)($_GET['limit'] ?? 10), 1), 50);

try {
    // Получаем прогресс всех активных пользователей за сегодня
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            COALESCE(
                NULLIF(TRIM(CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, ''))), ''),
                NULLIF(u.email, ''),
                CONCAT('Участник #', u.id)
            ) as name,
            COUNT(DISTINCT sc.id) as total_cards,
            COUNT(DISTINCT CASE
                WHEN sdl.actual_value >= sc.target_value AND sc.target_value > 0 AND sdl.actual_value > 0 THEN sc.id
            END) as completed_cards,
            ROUND(
                CASE 
                    WHEN COUNT(DISTINCT sc.id) = 0 THEN 0
                    ELSE AVG(
                        LEAST(
                            IFNULL(sdl.actual_value, 0) / GREATEST(sc.target_value, 1),
                            1.0
                        )
                    ) * 100
                END
            ) as completion_percent,
            (u.id = :user_id) as is_current_user
        FROM users u
        JOIN sadhana_cards sc ON sc.user_id = u.id AND sc.is_archived = 0
        LEFT JOIN sadhana_daily_logs sdl ON sdl.user_id = u.id AND sdl.card_id = sc.id AND sdl.date = :today_date
        GROUP BY u.id
        HAVING total_cards > 0
        ORDER BY is_current_user DESC, completion_percent DESC, completed_cards DESC
        LIMIT {$limit}
    ");
    $stmt->execute([
        'user_id' => $user_id,
        'today_date' => $today
    ]);
    $users = $stmt->fetchAll();
    
    $result = [];
    foreach ($users as $user) {
        $result[] = [
            'userId' => (int)$user['user_id'],
            'name' => trim($user['name']),
            'totalCards' => (int)$user['total_cards'],
            'completedCards' => (int)$user['completed_cards'],
            'completionPercent' => (int)$user['completion_percent'],
            'isCurrentUser' => (int)$user['is_current_user']
        ];
    }
    
    echo json_encode(['success' => true, 'leaderboard' => $result], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'sadhana') !== false) {
        echo json_encode(['success' => true, 'leaderboard' => []], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
