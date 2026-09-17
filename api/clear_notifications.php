<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

$user_id = requireAuth($pdo);

try {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
