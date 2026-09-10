<?php
// update_fcm_token.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху вне блока try-catch по стандарту проекта
$pdo = require __DIR__ . '/db.php';

try {
    // Получаем JSON из тела запроса
    $input = json_decode(file_get_contents('php://input'), true);

    $user_id = (int)($input['user_id'] ?? 0);
    $fcm_token = trim($input['fcm_token'] ?? '');

    if (!$user_id || empty($fcm_token)) {
        http_response_code(400);
        throw new Exception('Требуются user_id и fcm_token');
    }

    // Проверяем, существует ли пользователь в системе
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        throw new Exception('Пользователь не найден');
    }

    // [ИСПРАВЛЕНО] Логика переписана на правильную системную таблицу user_fcm_tokens с обработкой дубликатов
    $stmt = $pdo->prepare("
        INSERT INTO user_fcm_tokens (user_id, token, created_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE token = ?, updated_at = NOW()
    ");
    $stmt->execute([$user_id, $fcm_token, $fcm_token]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
