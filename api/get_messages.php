<?php
// get_messages.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

// 1. Проверяем авторизацию с корректным HTTP-статусом
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Fallback для PHP dev-сервера (register_argc_argv может быть выключен)
if (empty($_GET) && isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

$groupId = (int)($_GET['group_id'] ?? 0);

// Считываем ID собеседника, если запрашивается личный чат
$recipientId = isset($_GET['recipient_id']) && $_GET['recipient_id'] !== 'null' ? (int)$_GET['recipient_id'] : null;

// Поддержка polling: возвращаем только новые сообщения после last_message_id
$lastMessageId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

// Начальная загрузка — 50 последних сообщений, polling — 15 новых
$initialLimit = 50;
$pollLimit = 15;
$loadMoreLimit = 50; // Подгрузка при скролле вверх

if ($groupId <= 0 && $recipientId === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID группы или собеседник'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
	// =========================================================================
    // [ИСПРАВЛЕНО]: ЧИСТАЯ ФИКСАЦИЯ ОНЛАЙНА В СУБД БЕЗ БАГОВЫХ СЕССИЙ
    // =========================================================================
    // Запоминаем, в каком именно чате сидит авторизованный пользователь
    $contextMarker = ($recipientId !== null) ? "private_" . $recipientId : "group_" . $groupId;
    
    $stmtOnline = $pdo->prepare("REPLACE INTO user_chat_online (user_id, active_context, updated_at) VALUES (?, ?, NOW())");
    $stmtOnline->execute([$userId, $contextMarker]);

    // [ОПТИМИЗАЦИЯ] Проверяем колонки через SESSION кэш (не static!)
    $cacheKey = 'schema_columns_' . $groupId;
    if (!isset($_SESSION[$cacheKey])) {
        $columns = $pdo->query("SHOW COLUMNS FROM group_messages")->fetchAll(PDO::FETCH_COLUMN);
        $_SESSION[$cacheKey] = array_flip($columns);
        // Очищаем старые кэши раз в 100 запросов чтобы не засорять сессию
        if (session_id() && session_status() === PHP_SESSION_ACTIVE && rand(1, 100) === 1) {
            foreach ($_SESSION as $key => $val) {
                if (strpos($key, 'schema_columns_') === 0 && $key !== $cacheKey) {
                    unset($_SESSION[$key]);
                }
            }
        }
    }
    
    $hasUpdatedAt = isset($_SESSION[$cacheKey]['updated_at']);
    $hasReplyTo = isset($_SESSION[$cacheKey]['reply_to_id']);
    $hasForwardSenderId = isset($_SESSION[$cacheKey]['forward_sender_id']);
    $hasOriginalMessageId = isset($_SESSION[$cacheKey]['original_message_id']);
	
    // 3. ПРОВЕРКА ДОСТУПА
    // [ИСПРАВЛЕНО] Проверка админа переведена на числовой флаг is_admin по стандарту проекта
    $userStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();
    
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);
    $hasAccess = false;
    
    // Для ЛС проверка доступа не нужна - любой может читать свои ЛС
    if ($recipientId !== null) {
        $hasAccess = true;
    } else {
        // Для групповых чатов проверяем доступ
        if ($isAdmin) {
            $hasAccess = true;
        } else {
            // Проверяем одобренную заявку в группу
            $memberCheck = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE group_id = ? AND user_id = ? AND status = 'approved'");
            $memberCheck->execute([$groupId, $userId]);
            if ((int)$memberCheck->fetchColumn() > 0) {
                $hasAccess = true;
            }
            
            // [АРХИТЕКТУРА] Проверка лидера группы через связующую таблицу group_leaders
            if (!$hasAccess) {
                $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
                $leaderCheck->execute([$groupId, $userId]);
                if ((int)$leaderCheck->fetchColumn() > 0) { 
                    $hasAccess = true;
                    $isAdmin = true; // Лидер имеет права админа
                }
            }
        }
    }
    
    // [ОПТИМИЗАЦИЯ] $isLeader вычислим позже, если нужно
    
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Вы не являетесь участником этой группы'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 4. ЗАПРОС СООБЩЕНИЙ: Разделяем логику на Общий чат и Личный чат (ЛС)
    if ($recipientId === null) {
        // --- ОБЩИЙ ЧАТ ГРУППЫ ---
        $selectFields = "m.id, m.sender_id, m.message_text, m.created_at";
        if ($hasUpdatedAt) $selectFields .= ", m.updated_at";
        // reply_to_id всегда нужен для отображения ответов
        $selectFields .= ", m.reply_to_id";
        $selectFields .= ", m.is_forwarded";
        if ($hasForwardSenderId) {
            $selectFields .= ", m.forward_sender_id";
        }
        if ($hasOriginalMessageId) {
            $selectFields .= ", m.original_message_id";
        }
        $selectFields .= ", u.first_name, u.last_name, u.avatar_url";
        if ($hasForwardSenderId) {
            $selectFields .= ", forward_sender.first_name AS forward_first_name, forward_sender.last_name AS forward_last_name, forward_sender.avatar_url AS forward_avatar_url";
        }
        
        // [ОПТИМИЗАЦИЯ] Убрали LEFT JOIN original_msg — больше не нужен
        
        // Polling: только новые сообщения
        if ($lastMessageId > 0) {
            $sql = "SELECT $selectFields
                    FROM group_messages m
                    JOIN users u ON m.sender_id = u.id
                    LEFT JOIN users forward_sender ON m.forward_sender_id = forward_sender.id
                    WHERE m.group_id = :group_id AND m.recipient_id IS NULL AND m.id > :last_id
                    ORDER BY m.id ASC
                    LIMIT $pollLimit";
            $params = ['group_id' => $groupId, 'last_id' => $lastMessageId];
        } else {
            // Подгрузка старых при скролле вверх
            $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
            if ($beforeId > 0) {
                $sql = "SELECT $selectFields
                        FROM group_messages m
                        JOIN users u ON m.sender_id = u.id
                        LEFT JOIN users forward_sender ON m.forward_sender_id = forward_sender.id
                        WHERE m.group_id = :group_id AND m.recipient_id IS NULL AND m.id < :before_id
                        ORDER BY m.id DESC
                        LIMIT $loadMoreLimit";
                $params = ['group_id' => $groupId, 'before_id' => $beforeId];
            } else {
                // Начальная загрузка: последние 50 сообщений
                $sql = "SELECT $selectFields
                        FROM group_messages m
                        JOIN users u ON m.sender_id = u.id
                        LEFT JOIN users forward_sender ON m.forward_sender_id = forward_sender.id
                        WHERE m.group_id = :group_id AND m.recipient_id IS NULL
                        ORDER BY m.id DESC
                        LIMIT $initialLimit";
                $params = ['group_id' => $groupId];
            }
        }
    } else {
        // --- ЛИЧНЫЙ ЧАТ МЕЖДУ ДВУМЯ ПОЛЬЗОВАТЕЛЯМИ (СКВОЗНОЙ НА ВЕСЬ САЙТ) ---
        // Мгновенно помечаем входящие от собеседника к нам как прочитанные
        $updateReadStmt = $pdo->prepare("
            UPDATE group_messages 
            SET is_read = 1 
            WHERE sender_id = ? 
              AND recipient_id = ? 
              AND is_read = 0
        ");
        $updateReadStmt->execute([$recipientId, $userId]);
        
        $selectFields = "m.id, m.sender_id, m.message_text, m.created_at";
        if ($hasUpdatedAt) $selectFields .= ", m.updated_at";
        // reply_to_id всегда нужен для отображения ответов
        $selectFields .= ", m.reply_to_id";
        $selectFields .= ", m.is_forwarded";
        if ($hasForwardSenderId) {
            $selectFields .= ", m.forward_sender_id";
        }
        if ($hasOriginalMessageId) {
            $selectFields .= ", m.original_message_id";
        }
        $selectFields .= ", u.first_name, u.last_name, u.avatar_url";
        if ($hasForwardSenderId) {
            $selectFields .= ", forward_sender.first_name AS forward_first_name, forward_sender.last_name AS forward_last_name, forward_sender.avatar_url AS forward_avatar_url";
        }
        
        // [ОПТИМИЗАЦИЯ] Убрали LEFT JOIN original_msg из личного чата тоже
        
        // Polling: только новые сообщения
        if ($lastMessageId > 0) {
            $sql = "SELECT $selectFields
                    FROM group_messages m
                    JOIN users u ON m.sender_id = u.id
                    LEFT JOIN users forward_sender ON m.forward_sender_id = forward_sender.id
                    WHERE (
                        (m.sender_id = :user_id1 AND m.recipient_id = :recipient_id1)
                        OR
                        (m.sender_id = :recipient_id2 AND m.recipient_id = :user_id2)
                    ) AND m.id > :last_id
                    ORDER BY m.id ASC
                    LIMIT $pollLimit";
            $params = [
                'user_id1'       => $userId,
                'recipient_id1'  => $recipientId,
                'recipient_id2'  => $recipientId,
                'user_id2'       => $userId,
                'last_id'        => $lastMessageId
            ];
        } else {
            // Подгрузка старых при скролле вверх
            $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
            if ($beforeId > 0) {
                $sql = "SELECT $selectFields
                        FROM group_messages m
                        JOIN users u ON m.sender_id = u.id
                        LEFT JOIN users forward_sender ON m.forward_sender_id = forward_sender.id
                        WHERE (
                            (m.sender_id = :user_id1 AND m.recipient_id = :recipient_id1)
                            OR
                            (m.sender_id = :recipient_id2 AND m.recipient_id = :user_id2)
                        ) AND m.id < :before_id
                        ORDER BY m.id DESC
                        LIMIT $loadMoreLimit";
                $params = [
                    'user_id1'       => $userId,
                    'recipient_id1'  => $recipientId,
                    'recipient_id2'  => $recipientId,
                    'user_id2'       => $userId,
                    'before_id'      => $beforeId
                ];
            } else {
                // Начальная загрузка: последние 50 сообщений
                $sql = "SELECT $selectFields
                        FROM group_messages m
                        JOIN users u ON m.sender_id = u.id
                        LEFT JOIN users forward_sender ON m.forward_sender_id = forward_sender.id
                        WHERE (
                            (m.sender_id = :user_id1 AND m.recipient_id = :recipient_id1)
                            OR
                            (m.sender_id = :recipient_id2 AND m.recipient_id = :user_id2)
                        )
                        ORDER BY m.id DESC
                        LIMIT $initialLimit";
                $params = [
                    'user_id1'       => $userId,
                    'recipient_id1'  => $recipientId,
                    'recipient_id2'  => $recipientId,
                    'user_id2'       => $userId
                ];
            }
        }
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // Debug logging for private chat
    if ($recipientId !== null) {
        error_log('[get_messages] Private chat: userId=' . $userId . ' recipientId=' . $recipientId . ' messages_count=' . count($messages) . ' sql=' . $sql);
    }
    
    // [ОПТИМИЗАЦИЯ] Строгое приведение ID к типам данных int для WebView
    // [ОПТИМИЗАЦИЯ 2025] Убраны file_exists() и дополнительный запрос reply_to_id
    
    foreach ($messages as &$msg) {
        $msg['id'] = (int)$msg['id'];
        $msg['sender_id'] = (int)$msg['sender_id'];
        $msg['is_edited'] = $hasUpdatedAt && $msg['updated_at'] !== null && $msg['updated_at'] !== $msg['created_at'];
        $msg['is_forwarded'] = isset($msg['is_forwarded']) && (int)$msg['is_forwarded'] === 1;
        
        // Обрабатываем forward_sender_info
        if ($hasForwardSenderId && !empty($msg['forward_sender_id'])) {
            $msg['forward_sender_id'] = (int)$msg['forward_sender_id'];
            $msg['forward_sender_name'] = $msg['forward_first_name'] ?? 'Пользователь';
            $msg['forward_sender_last_name'] = $msg['forward_last_name'] ?? '';
            $msg['forward_sender_avatar_url'] = $msg['forward_avatar_url'] ?? null;
        } else {
            $msg['forward_sender_name'] = null;
            $msg['forward_sender_last_name'] = '';
            $msg['forward_sender_avatar_url'] = null;
        }
        
        // Обрабатываем original_message_id
        if ($hasOriginalMessageId && !empty($msg['original_message_id'])) {
            $msg['original_message_id'] = (int)$msg['original_message_id'];
            $msg['original_message_text'] = $msg['original_message_text'] ?? '';
        } else {
            $msg['original_message_id'] = null;
            $msg['original_message_text'] = null;
        }
        
        // [ОПТИМИЗАЦИЯ] Не проверяем file_exists() — это очень медленно!
        // Браузер сам обработает 404 на изображении
        // Если avatar_url есть в БД — возвращаем как есть
        
        // Загружаем reply_to_info с кэшированием
        $msg['reply_to_info'] = null;
        if (!empty($msg['reply_to_id'])) {
            $cacheKey = 'reply_to_' . $msg['reply_to_id'];
            if (!isset($_SESSION[$cacheKey])) {
                try {
                    $replyStmt = $pdo->prepare("SELECT gm.id, gm.sender_id, gm.message_text, u.first_name, u.last_name FROM users u JOIN group_messages gm ON gm.sender_id = u.id WHERE gm.id = ?");
                    $replyStmt->execute([$msg['reply_to_id']]);
                    $_SESSION[$cacheKey] = $replyStmt->fetch() ?: null;
                    error_log('[get_messages] reply_to_info for msg ' . $msg['id'] . ': reply_to_id=' . $msg['reply_to_id'] . ' found=' . ($_SESSION[$cacheKey] ? 'yes' : 'no'));
                } catch (\Exception $e) {
                    $_SESSION[$cacheKey] = null;
                    error_log('[get_messages] reply_to_info ERROR: ' . $e->getMessage());
                }
            }
            $msg['reply_to_info'] = $_SESSION[$cacheKey];
        }
    }
    unset($msg);
    
    // Убираем временные поля JOIN перед возвратом
    foreach ($messages as &$msg) {
        unset($msg['forward_first_name']);
        unset($msg['forward_last_name']);
        unset($msg['forward_avatar_url']);
        unset($msg['original_message_text']);
    }
    unset($msg);
    
    // [ОПТИМИЗАЦИЯ] Вычисляем isLeader с кэшированием в SESSION
    $leaderCacheKey = "is_leader_{$userId}_{$groupId}";
    if (!isset($_SESSION[$leaderCacheKey])) {
        $_SESSION[$leaderCacheKey] = $isAdmin;
        if (!$isAdmin) {
            $leaderStmt = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
            $leaderStmt->execute([$groupId, $userId]);
            $_SESSION[$leaderCacheKey] = (int)$leaderStmt->fetchColumn() > 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'current_user_id' => $userId,
        'is_admin' => $isAdmin,
        'is_leader' => $_SESSION[$leaderCacheKey]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (\PDOException $e) {
    http_response_code(500);
    error_log("get_messages PDOException: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'PDO Error: ' . $e->getMessage(), 'sqlstate' => $e->getCode()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    error_log("get_messages Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
