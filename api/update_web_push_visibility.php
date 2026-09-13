<?php
/**
 * Обновление состояния видимости страницы для Web Push
 * Используется для подавления уведомлений, когда пользователь уже смотрит страницу
 */

header('Content-Type: application/json');

// Подключаем базу данных
$pdo = require __DIR__ . '/db.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Получаем JSON-данные
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
    exit;
}

$page = $data['page'] ?? null;
$groupId = isset($data['group_id']) && $data['group_id'] !== null ? (int)$data['group_id'] : null;
$visible = isset($data['visible']) && $data['visible'] === true;

if (!$page) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан page']);
    exit;
}

try {
    // Проверяем, существует ли таблица
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'user_web_push_visibility'");
    $tableExists = ($tableCheck && $tableCheck->rowCount() > 0);
    
    if (!$tableExists) {
        // Таблица ещё не создана - это не критично, просто игнорируем
        echo json_encode(['success' => true, 'message' => 'Table not created yet, skipping']);
        exit;
    }
    
    // Обновляем или создаём запись о видимости
    // Используем REPLACE INTO для upsert-операции
    $sql = "REPLACE INTO user_web_push_visibility (user_id, page, group_id, is_visible, updated_at)
            VALUES (:user_id, :page, :group_id, :visible, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':page' => $page,
        ':group_id' => $groupId,
        ':visible' => $visible ? 1 : 0
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (\PDOException $e) {
    // Таблица может не существовать - это не критично
    error_log("Web push visibility update error: " . $e->getMessage());
    // Не возвращаем ошибку клиенту, так как это не критично
    echo json_encode(['success' => true, 'message' => 'Visibility tracking skipped']);
}
