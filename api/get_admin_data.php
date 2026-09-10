<?php
// get_admin_data.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] Регистрация логирования ошибок на самый верх
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        file_put_contents(__DIR__ . '/admin_error.log', print_r($error, true), FILE_APPEND);
    }
});

// [АРХИТЕКТУРА] db.php вынесен на самый верх, до выполнения какой-либо логики
$pdo = require __DIR__ . '/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Требуется вход']);
        exit;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    // [ИСПРАВЛЕНО] Получаем числовое значение флага из БД
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // [АРХИТЕКТУРА] Строгая проверка на числовой флаг по стандарту проекта (=== 1)
    if (!$user || (int)$user['is_admin'] !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
        exit;
    }
    
    // Получаем все группы + лидеров через group_leaders
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
    
    // Группируем лидеров (с поддержкой множественного лидерства в группе)
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
