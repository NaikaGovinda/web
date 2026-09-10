<?php
// get_leaders.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по стандарту проекта
$pdo = require __DIR__ . '/db.php';

// [БЕЗОПАСНОСТЬ] Добавлена обязательная проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // [БЕЗОПАСНОСТЬ] Добавлена проверка прав: список лидеров может запрашивать только админ или другой лидер
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

    // [ИСПРАВЛЕНО] Теперь выбираем ВСЕХ активных пользователей системы из таблицы users, 
    // чтобы обычного человека можно было сразу выбрать и назначить лидером группы
    $stmt = $pdo->query("
        SELECT id, first_name, last_name 
        FROM users 
        WHERE is_active = 1
        ORDER BY first_name ASC
    ");
    $leaders = $stmt->fetchAll();

    // [ОПТИМИЗАЦИЯ] Принудительное приведение типов для стабильности WebView
    foreach ($leaders as &$leader) {
        $leader['id'] = (int)$leader['id'];
    }

    // [ИСПРАВЛЕНО] Ключ называется строго 'leaders', как и раньше — JS-код админки заполнит список без ошибок
    echo json_encode(['success' => true, 'leaders' => $leaders], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
