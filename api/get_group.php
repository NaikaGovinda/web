<?php
// get_group.php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// [ИЗМЕНЕНО] Разрешаем просмотр без авторизации, но проверяем токен для авторизованных
$user_id = getAuthUserId($pdo); // Проверяет и сессию, и токен

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный ID группы'], JSON_UNESCAPED_UNICODE);
    exit;
}
$id = (int)$id;

try {
    // Получаем группу
    $stmt = $pdo->prepare("
        SELECT
            g.id,
            g.name,
            g.description,
            g.group_description,
            g.city,
            g.address,
            g.time,
            g.day,
            g.is_online,
            g.timezone,
            g.group_avatar_url,
            (SELECT GROUP_CONCAT(user_id) FROM group_leaders WHERE group_id = g.id) AS leader_user_ids,
            (SELECT first_name FROM users WHERE id = (SELECT user_id FROM group_leaders WHERE group_id = g.id LIMIT 1)) AS first_name,
            (SELECT last_name FROM users WHERE id = (SELECT user_id FROM group_leaders WHERE group_id = g.id LIMIT 1)) AS last_name,
            (SELECT COUNT(*) FROM `applications` a WHERE a.group_id = g.id AND a.status = 'approved') AS member_count
        FROM `groups` g
        WHERE g.id = ? AND g.status = 'active'
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $group = $stmt->fetch();

    if (!$group) {
        throw new Exception('Группа не найдена');
    }

    // Лидеры группы
    $groupLeadersList = [];
    if (!empty($group['leader_user_ids'])) {
        $leaderIdsArray = array_map('intval', explode(',', $group['leader_user_ids']));
        $placeholders = implode(',', array_fill(0, count($leaderIdsArray), '?'));

        // [БЕЗОПАСНОСТЬ] Не передаем email лидеров в открытом виде
        // Получаем контакты лидеров из group_leaders
        $stmtLeaders = $pdo->prepare("
            SELECT 
                gl.user_id as id,
                gl.phone,
                gl.max_contact,
                gl.vk_contact,
                u.first_name,
                u.last_name
            FROM group_leaders gl
            LEFT JOIN users u ON gl.user_id = u.id
            WHERE gl.group_id = ? AND gl.user_id IN ($placeholders)
        ");
        $stmtLeaders->execute(array_merge([$id], $leaderIdsArray));
        $groupLeadersList = $stmtLeaders->fetchAll();

        foreach ($groupLeadersList as &$gl) {
            $gl['id'] = (int)$gl['id'];
        }
        unset($gl);
    }

    // События группы
    $stmt = $pdo->prepare("
        SELECT id, title, event_date
        FROM `events`
        WHERE group_id = ? AND event_date >= UTC_TIMESTAMP()
        ORDER BY event_date ASC
        LIMIT 3
    ");
    $stmt->execute([$id]);
    $events = $stmt->fetchAll();

    // === Проверка прав пользователя ===
    $is_leader = false;
    $is_admin = false;
    $user_role = 'guest';
    $userStatus = null;
    $userRow = null;

    if ($user_id > 0) {
        // Проверка лидера
        $stmt = $pdo->prepare("SELECT 1 FROM group_leaders WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $is_leader = (bool)$stmt->fetch();

        // Проверка админа
        $stmt = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userRow = $stmt->fetch();

        if ($userRow) {
            $is_admin = ((int)$userRow['is_admin'] === 1);

            if ($is_admin) {
                $user_role = 'admin';
            } elseif (!empty($userRow['role'])) {
                $user_role = $userRow['role'];
            } else {
                $user_role = 'user';
            }
        }

        // Статус заявки
        $stmtStatus = $pdo->prepare("SELECT status FROM applications WHERE group_id = ? AND user_id = ? LIMIT 1");
        $stmtStatus->execute([$id, $user_id]);
        $statusRow = $stmtStatus->fetch();
        if ($statusRow) {
            $userStatus = $statusRow['status'];
        }
    }

    // === УЧАСТНИКИ ===
    $membersStmt = $pdo->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.avatar_url,
            u.role,
            (SELECT COUNT(*)
             FROM group_messages m
             WHERE m.sender_id = u.id
               AND m.recipient_id = :current_user_id
               AND m.is_read = 0
            ) as unread_count
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE a.group_id = :group_id AND a.status = 'approved'
        ORDER BY u.first_name ASC
    ");

    $membersStmt->execute([
        'current_user_id' => $user_id,
        'group_id'        => $id
    ]);
    $groupMembers = $membersStmt->fetchAll();

    // [ИСПРАВЛЕНО] Проверяем аватар группы (может быть в assets/groups/ или assets/avatars/)
    if (!empty($group['group_avatar_url'])) {
        $groupAvatarPath = $config['paths']['root_dir'] . '/' . ltrim($group['group_avatar_url'], '/');
        if (!file_exists($groupAvatarPath)) {
            $group['group_avatar_url'] = null;
        }
    }
    
    $group['id'] = (int)$group['id'];
    $group['is_online'] = (int)$group['is_online'];
    $group['member_count'] = (int)$group['member_count'];
    $group['leader_ids'] = !empty($group['leader_user_ids']) ? array_map('intval', explode(',', $group['leader_user_ids'])) : [];
    unset($group['leader_user_ids']);

    foreach ($events as &$e) {
        $e['id'] = (int)$e['id'];
    }
    unset($e);

    // [ИСПРАВЛЕНО] Проверяем аватары участников
    $rootDir = $config['paths']['root_dir'];
    foreach ($groupMembers as &$m) {
        $m['id'] = (int)$m['id'];
        $m['unread_count'] = (int)$m['unread_count'];
        
        if (!empty($m['avatar_url'])) {
            $avatarPath = $rootDir . '/' . ltrim($m['avatar_url'], '/');
            if (!file_exists($avatarPath)) {
                $m['avatar_url'] = null;
            }
        }
    }
    unset($m);

    // === ФОТО ГРУППЫ ===
    $stmt = $pdo->prepare("
        SELECT id, photo_url, sort_order, created_at
        FROM `group_photos`
        WHERE group_id = ?
        ORDER BY sort_order ASC, created_at ASC
    ");
    $stmt->execute([$id]);
    $groupPhotos = $stmt->fetchAll();

    // Проверяем существование фото на диске и формируем URL
    $rootDir = $config['paths']['root_dir'];
    
    foreach ($groupPhotos as &$p) {
        $p['id'] = (int)$p['id'];
        $p['sort_order'] = (int)$p['sort_order'];
        
        if (!empty($p['photo_url'])) {
            // Убедимся что путь начинается с /
            if (!str_starts_with($p['photo_url'], '/')) {
                $p['photo_url'] = '/' . $p['photo_url'];
            }
            
            // Проверяем существование файла
            $photoPath = $rootDir . '/' . ltrim($p['photo_url'], '/');
            if (!file_exists($photoPath)) {
                $p['photo_url'] = null;
            }
            // photo_url остаётся относительным (например /assets/groups/...)
            // клиент сам добавит domain
        }
    }
    unset($p);

    echo json_encode([
        'success' => true,
        'group' => $group,
        'leaders' => $groupLeadersList,
        'events' => $events,
        'photos' => array_values(array_filter($groupPhotos, function($p) { return !is_null($p['photo_url']); })),
        'is_leader' => (bool)$is_leader,
        'is_admin' => (bool)$is_admin,
        'user_role' => $user_role,
        'user_status' => $userStatus ? (string)$userStatus : null,
        'current_user_id' => (int)$user_id,
        'members' => $groupMembers
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>