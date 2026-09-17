<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

$user_id = requireAuth($pdo);

$data = json_decode(file_get_contents('php://input'), true);
$notification_id = (int)($data['notification_id'] ?? 0);

if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID уведомления не указан']);
    exit;
}

try {
    // [ИСПРАВЛЕНО] Обновляем только если уведомление принадлежит текущему пользователю
    $stmt = $pdo->prepare("UPDATE notifications SET `read` = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
