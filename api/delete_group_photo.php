<?php
// delete_group_photo.php
// Удаление фото группы

header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$userId = requireAuth($pdo);

// Получаем JSON body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$photoId = (int)($data['photo_id'] ?? 0);
$groupId = (int)($data['group_id'] ?? 0);

if ($photoId <= 0 || $groupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID фото или группы'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 2. ПРОВЕРКА ПРАВ: Лидер или админ
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

    if (!$isAdmin && !$isLeader) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'У вас нет прав для удаления фото'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Проверяем существование фото
    $stmt = $pdo->prepare("SELECT photo_url FROM group_photos WHERE id = ? AND group_id = ?");
    $stmt->execute([$photoId, $groupId]);
    $photo = $stmt->fetch();

    if (!$photo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Фото не найдено'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. Удаляем файл с диска
    $photoPath = $config['paths']['root_dir'] . '/' . ltrim($photo['photo_url'], '/');
    if (file_exists($photoPath)) {
        @unlink($photoPath);
    }

    // 5. Удаляем запись из БД
    $stmt = $pdo->prepare("DELETE FROM group_photos WHERE id = ? AND group_id = ?");
    $stmt->execute([$photoId, $groupId]);

    echo json_encode([
        'success' => true,
        'message' => 'Фото успешно удалено'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\PDOException $e) {
    error_log('delete_group_photo PDO Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    error_log('delete_group_photo Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}