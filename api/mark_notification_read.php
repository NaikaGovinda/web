<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['notification_id'] ?? null;

if (!$notification_id) {
    echo json_encode(['success' => false, 'error' => 'Notification ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET `read` = 1 WHERE id = ?");
    $stmt->execute([$notification_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
