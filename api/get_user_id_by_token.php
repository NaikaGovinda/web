<?php
// get_user_id_by_token.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по нашему строгому стандарту
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_tokens.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = validateAuthToken($pdo, $token);

if ($userId) {
    echo json_encode(['success' => true, 'user_id' => $userId], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
}
