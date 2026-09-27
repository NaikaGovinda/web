<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = require __DIR__ . '/db.php';
    require_once __DIR__ . '/auth_helper.php';

    $userId = getAuthUserId($pdo) ?: 1;

    $groups = [];
    $friends = [];

    if ($pdo) {
        try {
            // 1. Группы пользователя
            $stmt = $pdo->prepare("
                SELECT DISTINCT g.id, g.name, g.city, g.group_avatar_url
                FROM `groups` g
                LEFT JOIN applications a ON a.group_id = g.id AND a.user_id = ?
                LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
                WHERE a.id IS NOT NULL OR gm.id IS NOT NULL OR g.leader_id = ?
            ");
            $stmt->execute([$userId, $userId, $userId]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Если список пользователя пуст — подтягиваем все активные группы из базы
            if (empty($groups)) {
                $stmt = $pdo->query("SELECT id, name, city, group_avatar_url FROM `groups` ORDER BY id ASC");
                $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // 2. Личные собеседники
            $fStmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.first_name, u.last_name, u.avatar_url,
                       COALESCE(a.group_id, 1) as group_id
                FROM users u
                LEFT JOIN applications a ON a.user_id = u.id
                WHERE u.id != ? AND (u.first_name IS NOT NULL OR u.name IS NOT NULL)
                LIMIT 20
            ");
            $fStmt->execute([$userId]);
            $friends = $fStmt->fetchAll(PDO::FETCH_ASSOC);

            // Нормализация имени если first_name пусто
            foreach ($friends as &$f) {
                if (empty($f['first_name']) && !empty($f['name'])) {
                    $f['first_name'] = $f['name'];
                }
                if (empty($f['first_name'])) {
                    $f['first_name'] = 'Пользователь';
                }
            }
            unset($f);

        } catch (\Throwable $e) {
            error_log('[get_chats_list] Error: ' . $e->getMessage());
        }
    }

    if (empty($groups)) {
        $groups = [
            ['id' => 1, 'name' => 'Нама-Хатта «Радха-Кришна»', 'city' => 'Москва', 'group_avatar_url' => null]
        ];
    }

    echo json_encode([
        'success' => true,
        'groups' => $groups,
        'friends' => $friends
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode([
        'success' => true,
        'groups' => [
            ['id' => 1, 'name' => 'Нама-Хатта «Радха-Кришна»', 'city' => 'Москва', 'group_avatar_url' => null]
        ],
        'friends' => []
    ], JSON_UNESCAPED_UNICODE);
}
