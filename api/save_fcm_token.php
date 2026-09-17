<?php
// save_fcm_token.php
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$user_id = requireAuth($pdo);

// Считываем поток
$rawInput = file_get_contents('php://input');

file_put_contents(__DIR__ . '/fcm_debug.log',
    "[" . date('Y-m-d H:i:s') . "] " .
    "User ID: " . $user_id . " | " .
    "Raw input: " . $rawInput . "\n",
    FILE_APPEND
);

try {
    $input = json_decode($rawInput, true);
    $token = trim($input['fcm_token'] ?? '');

    if (empty($token)) {
        throw new Exception('FCM-токен обязателен');
    }

    // Запрос на добавление или обновление токена
    $stmt = $pdo->prepare("INSERT INTO user_fcm_tokens (user_id, token, created_at)
                           VALUES (?, ?, NOW())
                           ON DUPLICATE KEY UPDATE token = ?, updated_at = NOW()");
    $stmt->execute([$user_id, $token, $token]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}