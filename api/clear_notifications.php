<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
