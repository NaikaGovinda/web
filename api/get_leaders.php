<?php
// get_leaders.php
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$user_id = requireAuth($pdo);

try {
    // Проверяем права: список лидеров может запрашивать только админ или лидер
    $checkStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $checkStmt->execute([$user_id]);
    $userRow = $checkStmt->fetch();
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);

    $leaderCheckStmt = $pdo->prepare("SELECT 1 FROM group_leaders WHERE user_id = ? LIMIT 1");
    $leaderCheckStmt->execute([$user_id]);
    $isLeader = (bool)$leaderCheckStmt->fetch();

    if (!$isAdmin && !$isLeader) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Доступ запрещён'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Выбираем всех активных пользователей системы
    $stmt = $pdo->query("
        SELECT id, first_name, last_name
        FROM users
        WHERE is_active = 1
        ORDER BY first_name ASC
    ");
    $leaders = $stmt->fetchAll();

    // Приведение типов для стабильности WebView
    foreach ($leaders as &$leader) {
        $leader['id'] = (int)$leader['id'];
    }
    unset($leader);

    echo json_encode(['success' => true, 'leaders' => $leaders], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}