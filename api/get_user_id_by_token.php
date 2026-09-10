<?php
// get_user_id_by_token.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по нашему строгому стандарту
$pdo = require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE auth_token = ? AND is_active = 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if ($user) {
    // [ОПТИМИЗАЦИЯ] Принудительное приведение ID пользователя к int для Android WebView
    echo json_encode([
        'success' => true, 
        'user_id' => (int)$user['id']
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
}
