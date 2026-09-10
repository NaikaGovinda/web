<?php
// /api/send_message.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() удален
$pdo = require __DIR__ . '/db.php';

// 1. Проверяем авторизацию пользователя по сессии с корректным HTTP-кодом
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// 2. Получаем JSON-данные из запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректные данные запроса'], JSON_UNESCAPED_UNICODE);
    exit;
}

$groupId = (int)($data['group_id'] ?? 0);
$messageText = trim($data['message_text'] ?? '');

// Считываем ID получателя, если он передан фронтендом (для ЛС)
$recipientId = isset($data['recipient_id']) && $data['recipient_id'] !== null && $data['recipient_id'] !== 'null' ? (int)$data['recipient_id'] : null;

if ($groupId <= 0 || empty($messageText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Сообщение не может быть пустым'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Запрещаем отправлять сообщения самому себе в ЛС
if ($recipientId !== null && $recipientId === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Нельзя отправить личное сообщение самому себе'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // =========================================================================
    // 4. ПРОВЕРКА ДОСТУПА ОТПРАВИТЕЛЯ: состоит ли автор в группе (или лидер/админ)
    // =========================================================================
    // [ИСПРАВЛЕНО] Проверка админа полностью переписана на числовой флаг is_admin === 1 по техпаспорту
    $userStmt = $pdo->prepare("SELECT role, is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();
    
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);
    $isObserver = ($userRow && $userRow['role'] === 'observer');

    if ($isObserver && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Наблюдатели не могут отправлять сообщения'], JSON_UNESCAPED_UNICODE);
        exit;
    }
        $hasAccess = false;

    if ($isAdmin) {
        $hasAccess = true;
    } else {
        // Проверяем, одобрен ли пользователь в этой группе
        $memberCheck = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE group_id = ? AND user_id = ? AND status = 'approved'");
        $memberCheck->execute([$groupId, $userId]);
        if ((int)$memberCheck->fetchColumn() > 0) {
            $hasAccess = true;
        }
        
        // [АРХИТЕКТУРА] Проверяем, является ли пользователь лидером в таблице group_leaders
        if (!$hasAccess) {
            $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
            $leaderCheck->execute([$groupId, $userId]);
            if ((int)$leaderCheck->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Вы не являетесь участником этой группы'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // =========================================================================
    // 5. ПРОВЕРКА ДОСТУПА ПОЛУЧАТЕЛЯ (если это ЛС) - СКВОЗНОЙ ВАРИАНТ
    // =========================================================================
    if ($recipientId !== null) {
        // [ИСПРАВЛЕНО] Валидация получателя ЛС также синхронизирована с системным полем is_admin
        $recipientStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $recipientStmt->execute([$recipientId]);
        $recipientRow = $recipientStmt->fetch();
        
        if (!$recipientRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Получатель не найден в системе'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // 6. Вставляем сообщение в базу данных
    $sql = "INSERT INTO group_messages (group_id, sender_id, recipient_id, message_text) 
            VALUES (:group_id, :sender_id, :recipient_id, :message_text)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'group_id'     => $groupId,
        'sender_id'    => $userId,
        'recipient_id' => $recipientId,
        'message_text' => $messageText
    ]);
    
    // ==========================================================
    // 7. ОТПРАВКА PUSH-УВЕДОМЛЕНИЙ О НОВЫХ СООБЩЕНИЯХ
    // ==========================================================
    try {
        require_once __DIR__ . '/send_fcm.php';
        
        // Получаем имя отправителя для красивого вывода в шторке мобильного телефона
        $stmtSender = $pdo->prepare("SELECT first_name FROM users WHERE id = ?");
        $stmtSender->execute([$userId]);
        $senderName = $stmtSender->fetchColumn() ?: "Участник";
        
        $recipientIds = [];
        $pushTitle = "";
        $pushBody = "{$senderName}: {$messageText}";
        
        if ($recipientId !== null) {
            // Если это личное сообщение (ЛС)
            $recipientIds[] = $recipientId;
            $pushTitle = "Личное сообщение 💬";
        } else {
            // Если сообщение в общий чат группы — находим всех подтвержденных участников, кроме себя
            $stmtMembers = $pdo->prepare("SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved' AND user_id != ?");
            $stmtMembers->execute([$groupId, $userId]);
            $memberIds = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);
            
            // Находим лидеров группы, кроме себя
            $stmtLeaders = $pdo->prepare("SELECT user_id FROM group_leaders WHERE group_id = ? AND user_id != ?");
            $stmtLeaders->execute([$groupId, $userId]);
            $leaderIds = $stmtLeaders->fetchAll(PDO::FETCH_COLUMN);
            
            $recipientIds = array_unique(array_merge($memberIds, $leaderIds));
            $pushTitle = "Сообщение в чате группы 👥";
        }
        
        if (!empty($recipientIds)) {
			$placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
			
			// [ТОЧЕЧНО] Выбираем user_id вместе с токеном, чтобы знать, кому он принадлежит
			$stmtTokens = $pdo->prepare("SELECT user_id, token FROM user_fcm_tokens WHERE user_id IN ($placeholders)");
			$stmtTokens->execute($recipientIds);
			$tokenRows = $stmtTokens->fetchAll(PDO::FETCH_ASSOC);

			// [БРОНЕБОЙНЫЙ ВАРИАНТ]: Сервер проверяет онлайн получателя напрямую по таблице СУБД
			$tokens = [];
			foreach ($tokenRows as $row) {
				$currentRecipientId = (int)$row['user_id'];

				// Запрашиваем из базы, где сейчас сидит этот получатель
				$stmtCheckOnline = $pdo->prepare("SELECT active_context FROM user_chat_online WHERE user_id = ? AND updated_at >= NOW() - INTERVAL 10 SECOND");
				$stmtCheckOnline->execute([$currentRecipientId]);
				$currentOnlineContext = $stmtCheckOnline->fetchColumn();

				// 1. ПРОВЕРКА ДЛЯ ЛИЧНЫХ СООБЩЕНИЙ (ЛС)
				if ($recipientId !== null) {
					// Мы проверяем, что у Получателя ($currentRecipientId) сейчас открыт чат с НАМИ ($userId).
					// Когда Получатель сидит в чате с нами, его get_messages.php пишет в базу маркер: "private_" + наш ID ($userId)
					$expectedMarker = "private_" . $userId; 
					
					if ($currentOnlineContext === $expectedMarker) {
						continue; // Друг читает ваше ЛС прямо сейчас! Полная тишина в шторке.
					}
				} 
				// 2. ПРОВЕРКА ДЛЯ ОБЩЕГО ЧАТА ГРУППЫ
				else {
					$expectedMarker = "group_" . $groupId;
					if ($currentOnlineContext === $expectedMarker) {
						continue; // Получатель сидит в общем чате этой группы. Пропускаем пуш.
					}
				}

				$tokens[] = $row['token'];
			}

			if (!empty($tokens)) {
                // [ИСПРАВЛЕНО ШАГ 1]: Умное разделение типов роутинга для Общих чатов и ЛС
				$pushData = [
					'action' => ($recipientId !== null) ? 'new_private_chat_message' : 'new_group_chat_message',
					'group_id' => (string)$groupId,
					'sender_id' => (string)$userId, // Передаем ID того, кто написал (нужно для ЛС)
					'sender_name' => $senderName,
					'title' => $pushTitle,
					'body' => $pushBody
				];
                
                // Триггерим асинхронную Keep-Alive отправку через наш send_fcm.php
                sendFcmMessages($tokens, $pushTitle, $pushBody, $pushData);
            }
        }
    } catch (Exception $fcmEx) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Message push error: " . $fcmEx->getMessage() . "\n", FILE_APPEND);
    }
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    if (http_response_code() === 200) { http_response_code(500); }
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    if (http_response_code() === 200) { http_response_code(500); }
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
