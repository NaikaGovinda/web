<?php
// get_admin_data.php
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        file_put_contents(__DIR__ . '/admin_error.log', print_r($error, true), FILE_APPEND);
    }
});

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

try {
    // Проверяем авторизацию (сессия ИЛИ токен)
    $user_id = requireAuth($pdo);

    // Проверяем, что пользователь — админ
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_admin'] !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
        exit;
    }

    // Получаем все группы + лидеров
    $stmt = $pdo->prepare("
        SELECT
            g.id,
            g.name,
            g.city,
            g.is_online,
            g.type,
            g.status,
            gl.user_id AS leader_user_id,
            u.first_name,
            u.last_name
        FROM `groups` g
        LEFT JOIN group_leaders gl ON g.id = gl.group_id
        LEFT JOIN users u ON gl.user_id = u.id
        ORDER BY g.name
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll();

    // Группируем лидеров
    $grouped = [];
    foreach ($groups as $row) {
        $id = $row['id'];
        if (!isset($grouped[$id])) {
            $grouped[$id] = [
                'id'        => (int)$row['id'],
                'name'      => $row['name'],
                'city'      => $row['city'],
                'is_online' => (int)$row['is_online'],
                'type'      => $row['type'],
                'status'    => $row['status'],
                'leaders'   => []
            ];
        }
        if ($row['leader_user_id']) {
            $grouped[$id]['leaders'][] = [
                'user_id'    => (int)$row['leader_user_id'],
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'groups' => array_values($grouped)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}