<?php
// /api/update_group_info.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';

// 1. Проверяем авторизацию пользователя по сессии с корректным HTTP-кодом
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// 2. Получаем JSON-данные из запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса'], JSON_UNESCAPED_UNICODE);
    exit;
}

$groupId = (int)($data['group_id'] ?? 0);
$name = trim($data['name'] ?? '');
$description = trim($data['description'] ?? '');

if ($groupId <= 0 || empty($name) || empty($description)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Заполните все обязательные поля'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 4. ПРОВЕРКА ПРАВ: Является ли пользователь лидером группы или админом
    // [ИСПРАВЛЕНО] Проверка админа переведена на системный числовой флаг is_admin по стандарту проекта
    $userStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();
    
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);
    $isLeader = false;
    
    if (!$isAdmin) {
        // [АРХИТЕКТУРА] Проверяем связь лидера только в валидной таблице group_leaders (legacy leader_id удален)
        $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
        $leaderCheck->execute([$groupId, $userId]);
        if ((int)$leaderCheck->fetchColumn() > 0) {
            $isLeader = true;
        }
    }
    
    // Если не админ и не лидер этой группы — закрываем доступ с кодом 403
    if (!$isAdmin && !$isLeader) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'У вас нет прав для редактирования этой группы'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 5. Обновляем текстовые данные группы
    $sql = "UPDATE `groups` 
            SET name = :name, 
                description = :description 
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'name'        => $name,
        'description' => $description,
        'id'          => $groupId
    ]);
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
