<?php
// /api/update_group_info.php
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$userId = requireAuth($pdo);

// 2. Получаем данные из POST (FormData или JSON)
$groupId = (int)($_POST['group_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$groupDescription = trim($_POST['group_description'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$time = trim($_POST['time'] ?? '');
$day = trim($_POST['day'] ?? '');

if ($groupId <= 0 || empty($name) || empty($description)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Заполните все обязательные поля'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 4. ПРОВЕРКА ПРАВ: Является ли пользователь лидером группы или админом
    $userStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();

    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);
    $isLeader = false;

    if (!$isAdmin) {
        $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
        $leaderCheck->execute([$groupId, $userId]);
        if ((int)$leaderCheck->fetchColumn() > 0) {
            $isLeader = true;
        }
    }

    // Если не админ и не лидер этой группы — закрываем доступ
    if (!$isAdmin && !$isLeader) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'У вас нет прав для редактирования этой группы'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 5. Обновляем текстовые данные группы
    $sql = "UPDATE `groups`
            SET name = :name,
                description = :description,
                group_description = :group_description,
                address = :address,
                city = :city,
                time = :time,
                day = :day
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'name'              => $name,
        'description'       => $description,
        'group_description' => $groupDescription,
        'address'           => $address,
        'city'              => $city,
        'time'              => $time,
        'day'               => $day,
        'id'                => $groupId
    ]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}