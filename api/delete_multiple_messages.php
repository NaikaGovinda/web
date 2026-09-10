<?php
// /api/delete_multiple_messages.php
header('Content-Type: application/json; charset=utf8mb4');

// Порядок идеальный: db.php загружается первым и автоматически стартует сессию на 30 дней!
$pdo = require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$messageIds = $input['message_ids'] ?? [];

if (empty($messageIds) || !is_array($messageIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не выбраны сообщения для удаления']);
    exit;
}

try {
    // 1. Проверяем глобального администратора или лидера
    $stmtUser = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
    $stmtUser->execute([$currentUserId]);
    $user = $stmtUser->fetch();

    $isAdmin = $user && ((int)$user['is_admin'] === 1);
    $isGlobalLeader = $user && ($user['role'] === 'leader');

    $isModerator = false;

    // Подготавливаем плейсхолдеры (?, ?, ?) для безопасного IN-запроса
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));

    if ($isAdmin || $isGlobalLeader) {
        $isModerator = true;
    } else {
        // Выясняем, к какой группе относится первое сообщение (если это чат группы)
        $firstMsgId = (int)$messageIds[0];
        $groupStmt = $pdo->prepare("SELECT group_id FROM group_messages WHERE id = ?");
        $groupStmt->execute([$firstMsgId]);
        $groupId = (int)$groupStmt->fetchColumn();

        // Проверяем локальные права лидера группы (только если это групповой чат, id > 0)
        if ($groupId > 0) {
            $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
            $leaderCheck->execute([$groupId, $currentUserId]);
            if ((int)$leaderCheck->fetchColumn() > 0) {
                $isModerator = true;
            }
        }
    }

    // 2. Выполнение удаления на основе вычисленных прав
    if ($isModerator) {
        // Модераторы и админы могут массово удалять ЛЮБЫЕ сообщения в этом запросе
        $stmt = $pdo->prepare("DELETE FROM group_messages WHERE id IN ($placeholders)");
        $stmt->execute($messageIds);
    } else {
        // 🔥 ИСПРАВЛЕНО ДЛЯ ЛС И ОБЫЧНЫХ ПОЛЬЗОВАТЕЛЕЙ: 
        // Обычный пользователь стирает ТОЛЬКО те сообщения из выделенных, где он лично является автором (sender_id)
        $sql = "DELETE FROM group_messages WHERE id IN ($placeholders) AND sender_id = ?";
        $stmt = $pdo->prepare($sql);
        
        $params = array_merge($messageIds, [$currentUserId]);
        $stmt->execute($params);
    }

    // Возвращаем успешный ответ фронтенду
    echo json_encode(['success' => true, 'count' => $stmt->rowCount()]);

} catch (PDOException $e) {
    error_log("Ошибка массового удаления: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных при удалении']);
}
