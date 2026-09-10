<?php
header('Content-Type: application/json; charset=utf8mb4');

// Подключаем db.php. Внутри него стартует ваша сессия и настраивается PDO
$pdo = require __DIR__ . '/db.php';

// Проверяем, авторизован ли пользователь.
// Если сессия пустая из-за ограничений CORS/XAMPP, пробуем прочитать куку напрямую!
if (!isset($_SESSION['user_id']) && isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
    @session_start();
}
$userId = $_SESSION['user_id'] ?? 0;
$isAdmin = $_SESSION['is_admin'] ?? 0;

// Прямая подстраховка проверки прав админа в таблице пользователей
if ($userId > 0 && $isAdmin == 0) {
    $checkStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $checkStmt->execute([$userId]);
    $isAdmin = (int)$checkStmt->fetchColumn();
}

// Проверка безопасности
if ($userId <= 0 || $isAdmin != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Отказано в доступе. Сессия не подтверждена.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userIdToEdit = (int)($input['user_id'] ?? 0);
    $newRole = $input['role'] ?? 'user'; // Принимаем значение роли ('user', 'leader' или 'admin')
$allowedRoles = ['user', 'leader', 'admin', 'observer'];
if (!in_array($newRole, $allowedRoles)) {
    throw new Exception('Недопустимая роль: ' . $newRole);
}
    if (!$userIdToEdit) {
        throw new Exception('Не указан ID пользователя для изменения роли');
    }

    // Защита от случайного разжалования самого себя
    if ($userIdToEdit === (int)$_SESSION['user_id']) {
        throw new Exception('Вы не можете менять глобальную роль самому себе!');
    }

    // Автоматически выставляем флаг админа в зависимости от выбранной роли
    $isAdminFlag = ($newRole === 'admin') ? 1 : 0;

    // 🔥 ИСПРАВЛЕНО: обновляем и числовой флаг админа, и текстовое поле роли в вашей таблице users
    $stmtUpdate = $pdo->prepare("UPDATE users SET is_admin = ?, role = ? WHERE id = ?");
    $stmtUpdate->execute([$isAdminFlag, $newRole, $userIdToEdit]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
