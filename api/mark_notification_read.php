<?php
header('Content-Type: application/json; charset=utf8mb4');

// Подключаем db.php (он инициализирует сессию)
$pdo = require __DIR__ . '/db.php';

// Пытаемся получить user_id из сессии или из POST данных
$user_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
} else {
    // Fallback: получаем user_id из POST данных (для WebView/Android)
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['user_id']) && $data['user_id'] > 0) {
        $user_id = (int)$data['user_id'];
    }
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

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
