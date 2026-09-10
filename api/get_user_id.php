<?php
// get_user_id.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной session_start() удален
$pdo = require __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    // [ОПТИМИЗАЦИЯ] Принудительное приведение ID пользователя к int для Android WebView
    echo json_encode([
        'success' => true, 
        'user_id' => (int)$_SESSION['user_id']
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'Not authenticated'
    ], JSON_UNESCAPED_UNICODE);
}
