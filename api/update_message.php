<?php
// /api/update_message.php
// Редактирование сообщений пользователями

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Обработка preflight запроса
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Подключаем сессию и БД
    require __DIR__ . '/load_env.php';
    session_start();
    $pdo = require __DIR__ . '/db.php';

    // 1. Проверяем авторизацию
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
        exit;
    }

    $userId = intval($_SESSION['user_id']);

    // 2. Получаем JSON-данные из запроса
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

    // Ограничение на длину сообщения
    if (strlen($newText) > 2000) {
        echo json_encode(['success' => false, 'error' => 'Сообщение слишком длинное (макс. 2000 символов)']);
        exit;
    }

    // 3. Проверяем, существует ли сообщение
    $msgStmt = $pdo->prepare("SELECT group_id, sender_id, message_text, created_at FROM group_messages WHERE id = ?");
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

    // 3.5. ПРОВЕРКА: Запрещено редактировать пересланные сообщения
    $isForwarded = isset($messageData['is_forwarded']) && intval($messageData['is_forwarded']) === 1;
    if ($isForwarded) {
        echo json_encode(['success' => false, 'error' => 'Пересланные сообщения нельзя редактировать']);
        exit;
    }

    // 4. ПРОВЕРКА ПРАВ: Редактировать может только автор сообщения
    // (лидеры и админы могут только удалять, но не редактировать чужие тексты)
    if ($userId !== $senderId) {
        echo json_encode(['success' => false, 'error' => 'Только автор сообщения может его редактировать']);
        exit;
    }

    // 5. Ограничение по времени (редактируем в течение 24 часов)
    $createdTimestamp = strtotime($createdAt);
    $currentTimestamp = time();
    $maxEditTime = 24 * 60 * 60; // 24 часа в секундах

    if (($currentTimestamp - $createdTimestamp) > $maxEditTime) {
        echo json_encode(['success' => false, 'error' => 'Сообщение можно редактировать только в течение 24 часов после отправки']);
        exit;
    }

    // 6. Проверяем, изменился ли текст
    if ($newText === $originalText) {
        echo json_encode(['success' => false, 'error' => 'Текст сообщения не изменён']);
        exit;
    }

    // 7. Обновляем сообщение
    $updateStmt = $pdo->prepare("UPDATE group_messages SET message_text = ? WHERE id = ?");
    $updateStmt->execute([$newText, $messageId]);

    if ($updateStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Не удалось обновить сообщение']);
        exit;
    }

    // 8. Логируем действие
    error_log("Message {$messageId} edited by user {$userId} in group {$groupId}");

    echo json_encode([
        'success' => true,
        'message' => 'Сообщение успешно обновлено',
        'updated_at' => date('Y-m-d H:i:s')
    ]);

} catch (\PDOException $e) {
    error_log("Update message DB error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (\Exception $e) {
    error_log("Update message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()]);
}
