<?php
// leave_group.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху. Теперь сессия гарантированно инициализирована
$pdo = require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    $application_id = (int)($input['application_id'] ?? 0);

    if (!$application_id) {
        throw new Exception('Неверные данные');
    }

    // Получаем заявку и проверяем, что она принадлежит пользователю
    $stmt = $pdo->prepare("
        SELECT a.group_id, g.name AS group_name
        FROM `applications` a
        JOIN `groups` g ON a.group_id = g.id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $user_id]);
    $app = $stmt->fetch();

    if (!$app) {
        throw new Exception('Заявка не найдена или доступ запрещён');
    }

    // Удаляем заявку или членство
    $pdo->prepare("DELETE FROM `applications` WHERE id = ?")->execute([$application_id]);

    // Сохраняем уведомление в БД
    try {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, `read`) VALUES (?, ?, ?, ?, 0)");
        $notifStmt->execute([$user_id, 'group_removed', 'Вы покинули группу 🚪', 'Вы успешно вышли из группы «' . $app['group_name'] . '».', 0]);
    } catch (Exception $e) {
        error_log("Ошибка сохранения уведомления: " . $e->getMessage());
    }

    // [ИСПРАВЛЕНО] Добавлен флаг сохранения читаемости кириллицы для вывода названия группы
    echo json_encode([
        'success' => true,
        'message' => 'Вы успешно покинули группу «' . $app['group_name'] . '»'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    // [ИСПРАВЛЕНО] Добавлен флаг сохранения кириллицы для вывода ошибок
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
