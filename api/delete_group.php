<?php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход']);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];

    // Проверяем админский флаг (по стандарту проекта is_admin === 1)
    $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user || (int)$user['is_admin'] !== 1) {
        throw new Exception('Доступ запрещён');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $group_id = (int)($input['group_id'] ?? 0);

    if (!$group_id) {
        throw new Exception('Неверные данные');
    }

    // Проверяем существование группы
    $stmt = $pdo->prepare("SELECT id FROM `groups` WHERE id = ?");
    $stmt->execute([$group_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Группа не найдена');
    }

    // Удаляем группу
    $pdo->prepare("DELETE FROM `groups` WHERE id = ?")->execute([$group_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}