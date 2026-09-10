<?php
// get_leader_applications.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] Сначала подключаем db.php. Ручной старт сессии удален, централизован в ядре
$pdo = require __DIR__ . '/db.php';

// [АРХИТЕКТУРА] Возвращаем корректный http-статус 403 для триггера роутинга в WebView/фронтенде
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход']);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];

    // [АРХИТЕКТУРА] Проверяем, является ли пользователь глобальным админом (is_admin === 1)
    $adminStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $adminStmt->execute([$user_id]);
    $userRow = $adminStmt->fetch();
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);

    if ($isAdmin) {
        // Глобальный Администратор видит абсолютно все ожидающие заявки в системе
        $stmt = $pdo->prepare("
            SELECT 
                a.id, a.message, a.status,
                g.name AS group_name,
                u.first_name, u.last_name
            FROM `applications` a
            JOIN `groups` g ON a.group_id = g.id
            JOIN `users` u ON a.user_id = u.id
            WHERE a.status = 'pending'
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
    } else {
        // Локальный Лидер видит только заявки в свои группы (через group_leaders)
        $stmt = $pdo->prepare("
            SELECT 
                a.id, a.message, a.status,
                g.name AS group_name,
                u.first_name, u.last_name
            FROM `applications` a
            JOIN `groups` g ON a.group_id = g.id
            JOIN `users` u ON a.user_id = u.id
            JOIN group_leaders gl ON g.id = gl.group_id
            WHERE gl.user_id = ? AND a.status = 'pending'
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$user_id]);
    }

    $applications = $stmt->fetchAll();

    foreach ($applications as &$app) {
        // [ОПТИМИЗАЦИЯ] Строгое приведение ID заявки к int для Android WebView
        $app['id'] = (int)$app['id'];
        $app['applicant_name'] = trim($app['first_name'] . ' ' . ($app['last_name'] ?? ''));
    }

    echo json_encode(['success' => true, 'applications' => $applications], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
