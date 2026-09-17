<?php
// /api/update_message.php
// Редактирование сообщений

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    require __DIR__ . '/load_env.php';
    $pdo = require __DIR__ . '/db.php';
    require_once __DIR__ . '/auth_helper.php';

    // Проверяем авторизацию (сессия ИЛИ токен)
    $userId = requireAuth($pdo);

    // Получаем JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса']);
        exit;
    }

    $messageId = intval($data['message_id'] ?? 0);
    $newText = trim($data['message_text'] ?? '');

    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID сообщения']);
        exit;
    }

    if (empty($newText)) {
        echo json_encode(['success' => false, 'error' => 'Сообщение не может быть пустым']);
        exit;
    }

    if (strlen($newText) > 2000) {
        echo json_encode(['success' => false, 'error' => 'Сообщение слишком длинное']);
        exit;
    }

    // Проверяем сообщение
    $msgStmt = $pdo->prepare("SELECT group_id, sender_id, message_text, created_at, is_forwarded FROM group_messages WHERE id = ?");
    $msgStmt->execute([$messageId]);
    $messageData = $msgStmt->fetch();

    if (!$messageData) {
        echo json_encode(['success' => false, 'error' => 'Сообщение не найдено']);
        exit;
    }

    $groupId = intval($messageData['group_id']);
    $senderId = intval($messageData['sender_id']);
    $originalText = $messageData['message_text'];
    $createdAt = $messageData['created_at'];

    // Запрет редактирования пересланных
    if (isset($messageData['is_forwarded']) && intval($messageData['is_forwarded']) === 1) {
        echo json_encode(['success' => false, 'error' => 'Пересланные сообщения нельзя редактировать']);
        exit;
    }

    // Только автор может редактировать
    if ($userId !== $senderId) {
        echo json_encode(['success' => false, 'error' => 'Только автор может редактировать']);
        exit;
    }

    // Лимит 24 часа
    if ((time() - strtotime($createdAt)) > 86400) {
        echo json_encode(['success' => false, 'error' => 'Редактирование возможно только 24 часа']);
        exit;
    }

    if ($newText === $originalText) {
        echo json_encode(['success' => false, 'error' => 'Текст не изменён']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE group_messages SET message_text = ? WHERE id = ?");
    $updateStmt->execute([$newText, $messageId]);

    echo json_encode([
        'success' => true,
        'message' => 'Сообщение обновлено',
        'updated_at' => date('Y-m-d H:i:s')
    ]);

} catch (\PDOException $e) {
    error_log("Update message DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
} catch (\Exception $e) {
    error_log("Update message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Системная ошибка']);
}