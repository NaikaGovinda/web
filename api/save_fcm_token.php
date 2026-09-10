<?php
// save_fcm_token.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху. Сессия гарантированно инициализирована
$pdo = require __DIR__ . '/db.php';

// [ОПТИМИЗАЦИЯ] Считываем поток один раз во избежание сбоев повторного чтения php://input
$rawInput = file_get_contents('php://input');

// ЛОГИРУЕМ ВСЁ
file_put_contents(__DIR__ . '/fcm_debug.log', 
    "[" . date('Y-m-d H:i:s') . "] " .
    "Session ID: " . session_id() . " | " .
    "User ID: " . ($_SESSION['user_id'] ?? 'NULL') . " | " .
    "Raw input: " . $rawInput . "\n",
    FILE_APPEND
);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Пользователь не авторизован в сессии PHP');
    }

    $userId = (int)$_SESSION['user_id'];
    $input = json_decode($rawInput, true);
    $token = trim($input['fcm_token'] ?? '');

    if (empty($token)) {
        throw new Exception('FCM-токен обязателен');
    }

    // Запрос на добавление или обновление токена (переменная $pdo поднята наверх)
    $stmt = $pdo->prepare("INSERT INTO user_fcm_tokens (user_id, token, created_at) 
                           VALUES (?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE token = ?, updated_at = NOW()");
    $stmt->execute([$userId, $token, $token]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
