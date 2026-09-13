<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

// [ИСПРАВЛЕНО] Берём user_id из POST-данных (совместимо с фронтендом и Android)
$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($data['user_id'] ?? 0);

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID не указан']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
