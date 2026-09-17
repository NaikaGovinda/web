<?php
// get_user_id.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной session_start() удален
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

$user_id = getAuthUserId($pdo);

if ($user_id) {
    echo json_encode([
        'success' => true,
        'user_id' => $user_id
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ], JSON_UNESCAPED_UNICODE);
}
