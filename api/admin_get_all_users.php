<?php
header('Content-Type: application/json; charset=utf8mb4');

// Подключаем db.php. Внутри него подгружается конфигурация и стартует сессия
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

$user_id = requireAuth($pdo);

// Проверяем, что пользователь — админ
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || (int)$user['is_admin'] !== 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'Доступ запрещён. Требуется права администратора.'
    ]);
    exit;
}

try {
    // Если проверка пройдена — вытаскиваем всех пользователей для выпадающего списка
    $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
