<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

$user_id = requireAuth($pdo);

// Проверяем, что пользователь — админ
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || (int)$user['is_admin'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён. Требуются права администратора.']);
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
    if ($userIdToEdit === (int)$user_id) {
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
