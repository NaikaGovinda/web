<?php
header('Content-Type: application/json; charset=utf8mb4');

// Подключаем db.php. Внутри него подгружается конфигурация и стартует сессия
$pdo = require __DIR__ . '/db.php';

// Проверяем, авторизован ли пользователь.
// Если сессия пустая из-за ограничений CORS/XAMPP, мы попробуем прочитать куку напрямую!
if (!isset($_SESSION['user_id']) && isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
    @session_start();
}

$userId = $_SESSION['user_id'] ?? 0;
$isAdmin = $_SESSION['is_admin'] ?? 0;

// 🔥 БРОНЕБОЙНАЯ ПОДСТРАХОВКА: Если сессия всё равно пуста, но в базе данных этот пользователь админ,
// мы принудительно делаем прямой запрос в таблицу пользователей для проверки прав!
if ($userId > 0 && $isAdmin == 0) {
    $checkStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $checkStmt->execute([$userId]);
    $isAdmin = (int)$checkStmt->fetchColumn();
}

// Если после всех проверок мы не смогли доказать, что это главный админ — закрываем доступ
if ($userId <= 0 || $isAdmin != 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'Доступ запрещён. Сервер не смог подтвердить права администратора. Перезалейте сессию на login.html.'
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
