<?php
// get_profile.php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // [ИСПРАВЛЕНО] Добавлено поле role в SELECT
    $stmt = $pdo->prepare("
        SELECT id, email, first_name, last_name, avatar_url, phone, city, is_admin, role
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch();

    if (!$user_info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // [ИСПРАВЛЕНО] Приоритет: is_admin → role из БД → 'user'
    $user_info['id'] = (int)$user_info['id'];
    $user_info['is_admin'] = (int)$user_info['is_admin'];

    if ($user_info['is_admin'] === 1) {
        $user_info['role'] = 'admin';
    } elseif (!empty($user_info['role'])) {
        $user_info['role'] = $user_info['role']; // user, leader, observer
    } else {
        $user_info['role'] = 'user';
    }

    // Заявки пользователя
    $stmt = $pdo->prepare("
        SELECT
            a.id, a.message, a.status,
            g.name AS group_name
        FROM applications a
        JOIN `groups` g ON a.group_id = g.id
        WHERE a.user_id = ? AND a.status != 'left'
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll();

    foreach ($applications as &$app) {
        $app['id'] = (int)$app['id'];
    }

    // Считаем непрочитанные уведомления
    $unread_count = 0;
    try {
        $stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND `read` = 0");
        $stmtNotif->execute([$user_id]);
        $unread_count = (int)$stmtNotif->fetchColumn();
    } catch (Exception $e) {
        // Таблица notifications может ещё не существовать
        $unread_count = 0;
    }

    echo json_encode([
        'success' => true,
        'user_info' => $user_info,
        'applications' => $applications,
        'unread_notifications' => (int)$unread_count
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>