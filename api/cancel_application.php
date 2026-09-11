<?php
//cancel_application.php
header('Content-Type: application/json; charset=utf8mb4');

    $pdo = require __DIR__ . '/db.php';
	
	if (!isset($_SESSION['user_id'])) {
		http_response_code(403);
		echo json_encode(['success' => false, 'error' => 'Требуется вход']);
		exit;
	}

try {


    $input = json_decode(file_get_contents('php://input'), true);
    $app_id = (int)($input['application_id'] ?? 0);

    if (!$app_id) {
        throw new Exception('Требуется application_id');
    }

    // Проверяем, что заявка принадлежит текущему пользователю и в статусе 'pending'
    // 🔥 ИСПРАВЛЕНО: Теперь разрешаем удалять заявки со статусом 'pending' И 'rejected'
	$stmt = $pdo->prepare("
	SELECT id 
	FROM `applications` 
	WHERE id = ? AND user_id = ? AND status IN ('pending', 'rejected')
	");
    $stmt->execute([$app_id, $_SESSION['user_id']]);

    if (!$stmt->fetch()) {
        throw new Exception('Заявка не найдена или уже обработана');
    }

    // Удаляем заявку
    $pdo->prepare("DELETE FROM `applications` WHERE id = ?")->execute([$app_id]);

    // Сохраняем уведомление в БД
    try {
        // Получаем group_id для навигации
        $stmtGroup = $pdo->prepare("SELECT group_id FROM applications WHERE id = ?");
        // Заявка уже удалена, поэтому нужно получить group_id до удаления или использовать другой подход
        // Используем profile.html как целевую страницу
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, group_id, target_page, target_params, `read`) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $targetParams = json_encode([], JSON_UNESCAPED_UNICODE);
        $notifStmt->execute([$_SESSION['user_id'], 'application_cancelled', 'Заявка отменена ↩️', 'Вы отменили заявку на вступление в группу.', null, 'profile.html', $targetParams]);
    } catch (Exception $e) {
        error_log("Ошибка сохранения уведомления: " . $e->getMessage());
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}