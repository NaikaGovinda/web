<?php
// get_group.php
header('Content-Type: application/json; charset=utf8mb4');

$pdo = require __DIR__ . '/db.php';

// [ИЗМЕНЕНО] Разрешаем просмотр без авторизации
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

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
            g.city,
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

        $stmtLeaders = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id IN ($placeholders)");
        $stmtLeaders->execute($leaderIdsArray);
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

    if ($userId > 0) {
        // Проверка лидера
        $stmt = $pdo->prepare("SELECT 1 FROM group_leaders WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $is_leader = (bool)$stmt->fetch();

        // Проверка админа
        $stmt = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
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
        $stmtStatus->execute([$id, $userId]);
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
        'current_user_id' => $userId,
        'group_id'        => $id
    ]);
    $groupMembers = $membersStmt->fetchAll();

    // Форматирование типов
    $group['id'] = (int)$group['id'];
    $group['is_online'] = (int)$group['is_online'];
    $group['member_count'] = (int)$group['member_count'];
    $group['leader_ids'] = !empty($group['leader_user_ids']) ? array_map('intval', explode(',', $group['leader_user_ids'])) : [];
    unset($group['leader_user_ids']);

    foreach ($events as &$e) {
        $e['id'] = (int)$e['id'];
    }
    unset($e);

    foreach ($groupMembers as &$m) {
        $m['id'] = (int)$m['id'];
        $m['unread_count'] = (int)$m['unread_count'];
    }
    unset($m);

    echo json_encode([
        'success' => true,
        'group' => $group,
        'leaders' => $groupLeadersList,
        'events' => $events,
        'is_leader' => (bool)$is_leader,
        'is_admin' => (bool)$is_admin,
        'user_role' => $user_role,
        'user_status' => $userStatus ? (string)$userStatus : null,
        'current_user_id' => (int)$userId,
        'members' => $groupMembers
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>