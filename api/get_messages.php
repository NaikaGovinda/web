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
$groupId = (int)($_GET['group_id'] ?? 0);

// Считываем ID собеседника, если запрашивается личный чат
$recipientId = isset($_GET['recipient_id']) && $_GET['recipient_id'] !== 'null' ? (int)$_GET['recipient_id'] : null;

if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID группы'], JSON_UNESCAPED_UNICODE);
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

    // Проверяем, существует ли колонка updated_at
    $hasUpdatedAt = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM group_messages LIKE 'updated_at'");
        $hasUpdatedAt = ($colCheck && $colCheck->rowCount() > 0);
    } catch (\Exception $e) {
        $hasUpdatedAt = false;
    }
	
    // 3. ПРОВЕРКА ДОСТУПА (писать и читать чаты этой группы могут только её участники, лидеры или админы)
    // [ИСПРАВЛЕНО] Проверка админа переведена на числовой флаг is_admin по стандарту проекта
    $userStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();
    
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);
    $hasAccess = false;
    
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
            }
        }
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Вы не являетесь участником этой группы'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 4. ЗАПРОС СООБЩЕНИЙ: Разделяем логику на Общий чат и Личный чат (ЛС)
    if ($recipientId === null) {
        // --- ОБЩИЙ ЧАТ ГРУППЫ ---
        $selectFields = $hasUpdatedAt
            ? "m.id, m.sender_id, m.message_text, m.created_at, m.updated_at, u.first_name, u.last_name, u.avatar_url"
            : "m.id, m.sender_id, m.message_text, m.created_at, u.first_name, u.last_name, u.avatar_url";
        
        $sql = "SELECT $selectFields
                FROM group_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.group_id = :group_id AND m.recipient_id IS NULL
                ORDER BY m.id ASC 
                LIMIT 100";
        $params = ['group_id' => $groupId];
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
        
        $selectFields = $hasUpdatedAt
            ? "m.id, m.sender_id, m.message_text, m.created_at, m.updated_at, u.first_name, u.last_name, u.avatar_url"
            : "m.id, m.sender_id, m.message_text, m.created_at, u.first_name, u.last_name, u.avatar_url";
        
        $sql = "SELECT $selectFields
                FROM group_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE (
                    (m.sender_id = :user_id1 AND m.recipient_id = :recipient_id1) 
                    OR 
                    (m.sender_id = :recipient_id2 AND m.recipient_id = :user_id2)
                )
                ORDER BY m.id ASC 
                LIMIT 100";
        
        $params = [
            'user_id1'       => $userId,
            'recipient_id1'  => $recipientId,
            'recipient_id2'  => $recipientId,
            'user_id2'       => $userId
        ];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // [ОПТИМИЗАЦИЯ] Строгое приведение ID к типам данных int для WebView
    // [ИСПРАВЛЕНО] Проверяем существование файла аватара — убираем 404
    $rootDir = $config['paths']['root_dir'];
    foreach ($messages as &$msg) {
        $msg['id'] = (int)$msg['id'];
        $msg['sender_id'] = (int)$msg['sender_id'];
        $msg['is_edited'] = $hasUpdatedAt && $msg['updated_at'] !== null && $msg['updated_at'] !== $msg['created_at'];
        
        // Проверяем существование файла аватара
        if (!empty($msg['avatar_url'])) {
            $avatarPath = $rootDir . '/' . ltrim($msg['avatar_url'], '/');
            if (!file_exists($avatarPath)) {
                $msg['avatar_url'] = null;
            }
        }
    }
    unset($msg);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'current_user_id' => $userId,
        'is_admin' => $isAdmin,
        'is_leader' => ($userRow && (int)$userRow['is_admin'] === 1)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
