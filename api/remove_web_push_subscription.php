<?php
/**
 * Удаление Web Push подписки (soft delete)
 * POST: { endpoint }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Session auth
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['endpoint'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing endpoint']);
    exit;
}

$endpoint = $input['endpoint'];

// Include DB connection
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->prepare("
        UPDATE user_web_push_subscriptions
        SET active = 0, updated_at = NOW()
        WHERE endpoint = :endpoint
    ");
    
    $stmt->execute([':endpoint' => $endpoint]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Web push subscription remove error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
