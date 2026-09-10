<?php
// delete_event.php

header('Content-Type: application/json; charset=utf8mb4');

try {
    $pdo = require __DIR__ . '/db.php';
	
	if (!isset($_SESSION['user_id'])) {
		http_response_code(403);
		echo json_encode(['success' => false, 'error' => 'Требуется вход']);
		exit;
	}
	
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $event_id = (int)($input['event_id'] ?? 0);

    if (!$event_id) {
        throw new Exception('Неверные данные');
    }

    // Проверяем, что пользователь — лидер группы этого события (через group_leaders)
    $stmt = $pdo->prepare("
        SELECT e.id
        FROM `events` e
        JOIN `groups` g ON e.group_id = g.id
        JOIN group_leaders gl ON g.id = gl.group_id
        WHERE e.id = ? AND gl.user_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch();

    if (!$event) {
        throw new Exception('Событие не найдено или доступ запрещён');
    }

    // 🔥 ИСПРАВЛЕНО: Удалили ошибочный запрос к event_notifications.
    // Благодаря связи ON DELETE CASCADE в базе данных, при удалении события 
    // MySQL сама автоматически и мгновенно очистит все связанные будильники!

    // Удаляем событие
    $pdo->prepare("DELETE FROM `events` WHERE id = ?")->execute([$event_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
