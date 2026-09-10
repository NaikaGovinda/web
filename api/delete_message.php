<?php
// /api/delete_message.php

header('Content-Type: application/json; charset=utf8mb4');
try {
    // 3. Подключаем БД по вашему стандарту
    $pdo = require __DIR__ . '/db.php';

	// 1. Проверяем авторизацию
	if (!isset($_SESSION['user_id'])) {
		echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
		exit;
	}

	$userId = intval($_SESSION['user_id']);

	// 2. Получаем JSON-данные из запроса
	$input = file_get_contents('php://input');
	$data = json_decode($input, true);

	if (!$data) {
		echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса']);
		exit;
	}

	$messageId = intval($data['message_id'] ?? 0);

	if ($messageId <= 0) {
		echo json_encode(['success' => false, 'error' => 'Не указан ID сообщения']);
		exit;
	}

    // 4. Выясняем, к какой группе относится это сообщение и кто его отправитель
    $msgStmt = $pdo->prepare("SELECT group_id, sender_id FROM group_messages WHERE id = ?");
    $msgStmt->execute([$messageId]);
    $messageData = $msgStmt->fetch();

    if (!$messageData) {
        echo json_encode(['success' => false, 'error' => 'Сообщение не найдено']);
        exit;
    }

    $groupId = intval($messageData['group_id']);
    $senderId = intval($messageData['sender_id']);

    // 5. ПРОВЕРКА ПРАВ: Удалить может либо САМ автор, либо ЛИДЕР этой группы, либо АДМИН
    // 🔥 ИСПРАВЛЕНО: Проверяем статус администратора по вашему стандарту — через колонку `is_admin`
    $userStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $isAdminFlag = intval($userStmt->fetchColumn());

    $isAdmin = ($isAdminFlag === 1);
    $canDelete = false;

    if ($isAdmin || $userId === $senderId) {
        // Администратор или сам автор сообщения могут удалить его всегда
        $canDelete = true;
    } else {
        // Проверяем, является ли текущий пользователь лидером этой группы в group_leaders
        $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
        $leaderCheck->execute([$groupId, $userId]);
        if ($leaderCheck->fetchColumn() > 0) {
            $canDelete = true;
        }
    }

    if (!$canDelete) {
        echo json_encode(['success' => false, 'error' => 'У вас нет прав для удаления этого сообщения']);
        exit;
    }

    // 6. Удаляем сообщение из базы данных
    $deleteStmt = $pdo->prepare("DELETE FROM group_messages WHERE id = ?");
    $deleteStmt->execute([$messageId]);
    
    echo json_encode(['success' => true]);

} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()]);
}
