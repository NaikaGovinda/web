<?php
// update_leader_contact.php
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию
$user_id = getAuthUserId($pdo);
if (!$user_id || $user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем, является ли пользователь лидером или админом
$stmt = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || ((int)$user['is_admin'] !== 1 && $user['role'] !== 'admin')) {
    // Проверяем, является ли пользователь лидером этой группы
    $group_id = $_POST['group_id'] ?? null;
    if (!$group_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Нет прав'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT 1 FROM group_leaders WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Нет прав'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Получаем JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный формат данных'], JSON_UNESCAPED_UNICODE);
    exit;
}

$group_id = $input['group_id'] ?? null;
$leader_user_id = $input['leader_user_id'] ?? null;
$phone = isset($input['phone']) ? trim($input['phone']) : null;
$max_contact = isset($input['max_contact']) ? trim($input['max_contact']) : null;
$vk_contact = isset($input['vk_contact']) ? trim($input['vk_contact']) : null;

if (!$group_id || !$leader_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан group_id или leader_user_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$group_id = (int)$group_id;
$leader_user_id = (int)$leader_user_id;

try {
    // Проверяем, что лидер принадлежит группе
    $stmt = $pdo->prepare("SELECT 1 FROM group_leaders WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $leader_user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Лидер не найден в этой группе');
    }
    
    // Обновляем контакты лидера
    $stmt = $pdo->prepare("
        UPDATE group_leaders 
        SET phone = ?, max_contact = ?, vk_contact = ?
        WHERE group_id = ? AND user_id = ?
    ");
    
    $stmt->execute([
        $phone,
        $max_contact,
        $vk_contact,
        $group_id,
        $leader_user_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Контакты лидера обновлены'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
