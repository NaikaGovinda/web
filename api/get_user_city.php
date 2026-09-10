<?php
// get_user_city.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php перенесен на самый верх, ручной session_start() отсутствует
$pdo = require __DIR__ . '/db.php';

try {
    $tg_id = $_GET['tg_id'] ?? null;
    if (!$tg_id || !is_numeric($tg_id)) {
        throw new Exception('Неверный ID');
    }

    $stmt = $pdo->prepare("SELECT city FROM `users` WHERE telegram_id = ?");
    $stmt->execute([$tg_id]);
    $user = $stmt->fetch();

    $city = null;
    if ($user && isset($user['city']) && !in_array($user['city'], [null, '', 'Неизвестно', 'Не указан'])) {
        $city = $user['city'];
    }
    
    echo json_encode(['success' => true, 'city' => $city], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    // [ИСПРАВЛЕНО] Добавлен флаг сохранения читаемости кириллицы для вывода ошибок
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
