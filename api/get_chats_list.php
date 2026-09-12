<?php
// get_chats_list.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, сессии централизованы
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

try {
	unset($_SESSION['user_active_chat_' . $currentUserId]);
    // 1. Получаем список групп, где пользователь является подтвержденным участником или лидером
    $stmtGroups = $pdo->prepare("
        SELECT g.id, g.name, g.city, g.group_avatar_url 
        FROM `groups` g
        WHERE g.id IN (SELECT group_id FROM applications WHERE user_id = ? AND status = 'approved')
           OR g.id IN (SELECT group_id FROM group_leaders WHERE user_id = ?)
        ORDER BY g.name ASC
    ");
    $stmtGroups->execute([$currentUserId, $currentUserId]);
    $groups = $stmtGroups->fetchAll();

    // 2. Получаем список УНИКАЛЬНЫХ участников без повторений
    // [ИСПРАВЛЕНО] Удален инлайн-комментарий, который ломал ORDER BY u.first_name
    $stmtFriends = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.avatar_url
        FROM users u
        INNER JOIN applications app ON u.id = app.user_id
        WHERE app.status = 'approved'
          AND u.id != ? 
          AND app.group_id IN (
              SELECT group_id FROM applications WHERE user_id = ? AND status = 'approved'
              UNION
              SELECT group_id FROM group_leaders WHERE user_id = ?
          )
        GROUP BY u.id
        ORDER BY u.first_name ASC
    ");
    
    // [ОПТИМИЗАЦИЯ] Используем уже существующую переменную $currentUserId
    $stmtFriends->execute([$currentUserId, $currentUserId, $currentUserId]);
    $friends = $stmtFriends->fetchAll();

    // [ИСПРАВЛЕНО] Проверяем аватары групп и друзей
    $rootDir = $config['paths']['root_dir'];
    foreach ($groups as &$g) {
        $g['id'] = (int)$g['id'];
        if (!empty($g['group_avatar_url'])) {
            $avatarPath = $rootDir . '/' . ltrim($g['group_avatar_url'], '/');
            if (!file_exists($avatarPath)) {
                $g['group_avatar_url'] = null;
            }
        }
    }
    foreach ($friends as &$f) {
        $f['id'] = (int)$f['id'];
        if (!empty($f['avatar_url'])) {
            $avatarPath = $rootDir . '/' . ltrim($f['avatar_url'], '/');
            if (!file_exists($avatarPath)) {
                $f['avatar_url'] = null;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'groups' => $groups,
        'friends' => $friends
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Ошибка мессенджера: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Внутренняя ошибка сервера']);
}
