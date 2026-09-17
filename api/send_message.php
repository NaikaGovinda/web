<?php
// /api/send_message.php

// Глобальный перехватчик ошибок
set_exception_handler(function($e) {
    error_log('[send_message] FATAL ERROR: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Критическая ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$userId = requireAuth($pdo);

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

// Считываем ID сообщения, на которое отвечают (для "Ответить")
$replyToId = isset($data['reply_to_id']) && $data['reply_to_id'] !== null && $data['reply_to_id'] !== 'null' ? (int)$data['reply_to_id'] : null;

// Считываем флаг пересылки и sender_id оригинала
$isForwarded = isset($data['is_forwarded']) && $data['is_forwarded'] === true;
$forwardSenderId = isset($data['forward_sender_id']) && $data['forward_sender_id'] !== null && $data['forward_sender_id'] !== 'null' ? (int)$data['forward_sender_id'] : null;
$originalMessageId = isset($data['original_message_id']) && $data['original_message_id'] !== null && $data['original_message_id'] !== 'null' && (int)$data['original_message_id'] > 0 ? (int)$data['original_message_id'] : null;

// Если пересылаем СВОЁ сообщение — не помечаем как forwarded
if ($isForwarded && $forwardSenderId === $userId) {
    $isForwarded = false;
}

// Разрешаем отправку с recipient_id без group_id (для ЛС)
if (($groupId <= 0 && $recipientId === null) || empty($messageText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID группы или собеседник, или сообщение пустое'], JSON_UNESCAPED_UNICODE);
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
    // 4. ПРОВЕРКА ДОСТУПА ОТПРАВИТЕЛЯ
    // =========================================================================
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

    // Для ЛС проверка доступа к группе не нужна
    if ($recipientId !== null) {
        $hasAccess = true;
    } else {
        $hasAccess = false;

        if ($isAdmin) {
            $hasAccess = true;
        } else {
            $memberCheck = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE group_id = ? AND user_id = ? AND status = 'approved'");
            $memberCheck->execute([$groupId, $userId]);
            if ((int)$memberCheck->fetchColumn() > 0) {
                $hasAccess = true;
            }

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
    }

    // =========================================================================
    // 5. ПРОВЕРКА ДОСТУПА ПОЛУЧАТЕЛЯ (если это ЛС)
    // =========================================================================
    if ($recipientId !== null) {
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
    $hasReplyTo = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM group_messages LIKE 'reply_to_id'");
        $hasReplyTo = ($colCheck && $colCheck->rowCount() > 0);
    } catch (\Exception $e) { $hasReplyTo = false; }

    $hasIsForwarded = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM group_messages LIKE 'is_forwarded'");
        $hasIsForwarded = ($colCheck && $colCheck->rowCount() > 0);
    } catch (\Exception $e) { $hasIsForwarded = false; }

    $hasForwardSenderId = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM group_messages LIKE 'forward_sender_id'");
        $hasForwardSenderId = ($colCheck && $colCheck->rowCount() > 0);
    } catch (\Exception $e) { $hasForwardSenderId = false; }

    $hasOriginalMessageId = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM group_messages LIKE 'original_message_id'");
        $hasOriginalMessageId = ($colCheck && $colCheck->rowCount() > 0);
    } catch (\Exception $e) { $hasOriginalMessageId = false; }

    $fields = ['group_id', 'sender_id', 'recipient_id', 'message_text'];
    $placeholders = ['?', '?', '?', '?'];
    $values = [$groupId, $userId, $recipientId, $messageText];

    if ($hasReplyTo) {
        $fields[] = 'reply_to_id';
        $placeholders[] = '?';
        $values[] = $replyToId;
    }

    if ($hasIsForwarded) {
        $fields[] = 'is_forwarded';
        $placeholders[] = '?';
        $values[] = $isForwarded ? 1 : 0;
    }

    if ($hasForwardSenderId) {
        $fields[] = 'forward_sender_id';
        $placeholders[] = '?';
        $values[] = $forwardSenderId;
    }

    if ($hasOriginalMessageId) {
        $fields[] = 'original_message_id';
        $placeholders[] = '?';
        $values[] = $originalMessageId;
    }

    $sql = "INSERT INTO group_messages (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    // ==========================================================
    // 7. ОТПРАВКА PUSH-УВЕДОМЛЕНИЙ О НОВЫХ СООБЩЕНИЯХ
    // ==========================================================
    try {
        require_once __DIR__ . '/send_fcm.php';

        $stmtSender = $pdo->prepare("SELECT first_name FROM users WHERE id = ?");
        $stmtSender->execute([$userId]);
        $senderName = $stmtSender->fetchColumn() ?: "Участник";

        $recipientIds = [];
        $pushTitle = "";
        $pushBody = "{$senderName}: {$messageText}";

        if ($recipientId !== null) {
            $recipientIds[] = $recipientId;
            $pushTitle = "Личное сообщение 💬";
        } else {
            $stmtMembers = $pdo->prepare("SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved' AND user_id != ?");
            $stmtMembers->execute([$groupId, $userId]);
            $memberIds = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);

            $stmtLeaders = $pdo->prepare("SELECT user_id FROM group_leaders WHERE group_id = ? AND user_id != ?");
            $stmtLeaders->execute([$groupId, $userId]);
            $leaderIds = $stmtLeaders->fetchAll(PDO::FETCH_COLUMN);

            $recipientIds = array_unique(array_merge($memberIds, $leaderIds));
            $pushTitle = "Сообщение в чате группы 👥";
        }

        if (!empty($recipientIds)) {
            $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));

            $stmtTokens = $pdo->prepare("SELECT user_id, token FROM user_fcm_tokens WHERE user_id IN ($placeholders)");
            $stmtTokens->execute($recipientIds);
            $tokenRows = $stmtTokens->fetchAll(PDO::FETCH_ASSOC);

            $tokens = [];
            foreach ($tokenRows as $row) {
                $currentRecipientId = (int)$row['user_id'];

                $stmtCheckOnline = $pdo->prepare("SELECT active_context FROM user_chat_online WHERE user_id = ? AND updated_at >= NOW() - INTERVAL 10 SECOND");
                $stmtCheckOnline->execute([$currentRecipientId]);
                $currentOnlineContext = $stmtCheckOnline->fetchColumn();

                if ($recipientId !== null) {
                    $expectedMarker = "private_" . $userId;
                    if ($currentOnlineContext === $expectedMarker) {
                        continue;
                    }
                } else {
                    $expectedMarker = "group_" . $groupId;
                    if ($currentOnlineContext === $expectedMarker) {
                        continue;
                    }
                }

                $tokens[] = $row['token'];
            }

            if (!empty($tokens)) {
                $pushData = [
                    'action' => ($recipientId !== null) ? 'new_private_chat_message' : 'new_group_chat_message',
                    'group_id' => (string)$groupId,
                    'sender_id' => (string)$userId,
                    'sender_name' => $senderName,
                    'title' => $pushTitle,
                    'body' => $pushBody
                ];

                sendFcmMessages($tokens, $pushTitle, $pushBody, $pushData);
            }
        }
    } catch (Exception $fcmEx) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Message push error: " . $fcmEx->getMessage() . "\n", FILE_APPEND);
    }

    // ==========================================================
    // 8. ОТПРАВКА WEB PUSH УВЕДОМЛЕНИЙ
    // ==========================================================
    try {
        require_once __DIR__ . '/send_web_push.php';

        if ($recipientId !== null) {
            foreach ([$recipientId] as $webPushRecipientId) {
                sendWebPushToUser($pdo, $webPushRecipientId, $pushTitle, $pushBody, [
                    'action' => 'new_private_chat_message',
                    'group_id' => (string)$groupId,
                    'sender_id' => (string)$userId,
                    'sender_name' => $senderName,
                    'target_page' => 'group.html',
                    'target_params' => json_encode(['id' => $groupId, 'open_private_chat' => $userId, 'open_private_name' => $senderName], JSON_UNESCAPED_UNICODE)
                ]);
            }
        } else {
            $stmtWebMembers = $pdo->prepare("SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved' AND user_id != ?");
            $stmtWebMembers->execute([$groupId, $userId]);
            $webMemberIds = $stmtWebMembers->fetchAll(PDO::FETCH_COLUMN);

            $stmtWebLeaders = $pdo->prepare("SELECT user_id FROM group_leaders WHERE group_id = ? AND user_id != ?");
            $stmtWebLeaders->execute([$groupId, $userId]);
            $webLeaderIds = $stmtWebLeaders->fetchAll(PDO::FETCH_COLUMN);

            $webRecipientIds = array_unique(array_merge($webMemberIds, $webLeaderIds));

            foreach ($webRecipientIds as $webPushRecipientId) {
                sendWebPushToUser($pdo, $webPushRecipientId, $pushTitle, $pushBody, [
                    'action' => 'new_group_chat_message',
                    'group_id' => (string)$groupId,
                    'sender_id' => (string)$userId,
                    'sender_name' => $senderName,
                    'target_page' => 'group.html',
                    'target_params' => json_encode(['id' => $groupId, 'open_chat' => 1], JSON_UNESCAPED_UNICODE)
                ]);
            }
        }
    } catch (Exception $webPushEx) {
        error_log("Web push error in send_message: " . $webPushEx->getMessage());
    }

    // ==========================================================
    // 9. СОХРАНЕНИЕ УВЕДОМЛЕНИЙ В БАЗЕ ДАННЫХ
    // ==========================================================
    try {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, group_id, target_page, target_params, `read`) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");

        if ($recipientId !== null) {
            $targetParams = json_encode(['id' => $groupId, 'open_private_chat' => $userId, 'open_private_name' => urlencode($senderName)], JSON_UNESCAPED_UNICODE);
            $notifStmt->execute([
                $recipientId,
                'chat_message',
                'Личное сообщение 💬',
                "{$senderName}: {$messageText}",
                $groupId,
                'group.html',
                $targetParams
            ]);
        } else {
            $stmtNotifMembers = $pdo->prepare("SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved' AND user_id != ?");
            $stmtNotifMembers->execute([$groupId, $userId]);
            $memberIds = $stmtNotifMembers->fetchAll(PDO::FETCH_COLUMN);

            foreach ($memberIds as $memberId) {
                try {
                    $targetParams = json_encode(['id' => $groupId, 'open_chat' => 1], JSON_UNESCAPED_UNICODE);
                    $notifStmt->execute([
                        $memberId,
                        'chat_message',
                        'Новое сообщение в чате 💬',
                        "{$senderName}: {$messageText}",
                        $groupId,
                        'group.html',
                        $targetParams
                    ]);
                } catch (Exception $innerEx) {}
            }
        }
    } catch (Exception $notifEx) {
        error_log("[send_message] ERROR saving notification: " . $notifEx->getMessage());
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    if (http_response_code() === 200) { http_response_code(500); }
    error_log('[send_message] PDO Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    if (http_response_code() === 200) { http_response_code(500); }
    error_log('[send_message] Exception: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}