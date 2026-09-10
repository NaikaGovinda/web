<?php
// send_notification.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() удален
$pdo = require __DIR__ . '/db.php';

// Теперь проверка авторизации отработает корректно
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // [ИСПРАВЛЕНО] Выставляем верный HTTP-статус для системного перехватчика WebView
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $target_user_id = (int)($input['user_id'] ?? 0);
    $title = trim($input['title'] ?? 'Уведомление');
    $body = trim($input['body'] ?? '');

    if (!$target_user_id || empty($body)) {
        http_response_code(400);
        throw new Exception('Недостаточно данных для отправки');
    }

    // Получаем все FCM-токены получателя (у пользователя может быть несколько девайсов)
    $stmt = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tokens)) {
        http_response_code(404);
        throw new Exception('Получатель не найден или не подписан на уведомления');
    }

    // [ИСПРАВЛЕНО] Интегрируем нашу стандартизированную библиотеку отправки пушей
    require_once __DIR__ . '/send_fcm.php';

    // Формируем payload данных для перехвата в MyFirebaseMessagingService.kt
    $pushData = [
        'action' => 'custom_notification',
        'title'  => $title,
        'body'   => $body
    ];

    // Вызываем оптимизированную функцию отправки пачки токенов (с Keep-Alive)
    sendFcmMessages($tokens, $title, $body, $pushData);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
