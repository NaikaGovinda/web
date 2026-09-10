<?php
// info_handler.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isModerator = false;

if ($currentUserId > 0) {
    // [АРХИТЕКТУРА] Проверяем статус глобального админа строго по числовому флагу is_admin === 1
    $stmtUser = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmtUser->execute([$currentUserId]);
    $userRow = $stmtUser->fetch();
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);

    // [АРХИТЕКТУРА] Проверяем, является ли пользователь лидером хотя бы одной группы через group_leaders
    $stmtLeader = $pdo->prepare("SELECT 1 FROM group_leaders WHERE user_id = ? LIMIT 1");
    $stmtLeader->execute([$currentUserId]);
    $isLeader = (bool)$stmtLeader->fetch();

    // Модератором библиотеки является либо глобальный админ, либо любой подтвержденный лидер группы
    $isModerator = ($isAdmin || $isLeader);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // 1. ПОЛУЧЕНИЕ ВСЕХ ЗАПИСЕЙ БИБЛИОТЕКИ
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, title, description, type, content FROM info_links ORDER BY id DESC");
        $links = $stmt->fetchAll();

        // [ОПТИМИЗАЦИЯ] Принудительное приведение ID к типу int для стабильности Android WebView
        foreach ($links as &$link) {
            $link['id'] = (int)$link['id'];
        }

        echo json_encode([
            'success' => true, 
            'is_moderator' => $isModerator, 
            'links' => $links
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. УПРАВЛЕНИЕ ЗАПИСЯМИ (Модерация)
    if ($method === 'POST') {
        if (!$isModerator) {
            http_response_code(403);
            throw new Exception('Недостаточно прав доступа');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        // Создание или обновление записи
        if ($action === 'create') {
            $id = (int)($input['id'] ?? 0); // Получаем ID, если это редактирование
            $title = trim($input['title'] ?? '');
            $desc = trim($input['description'] ?? '');
            $type = trim($input['type'] ?? 'link');
            $content = trim($input['content'] ?? '');

            if (empty($title) || empty($content)) {
                throw new Exception('Заполните название и основное содержимое записи');
            }

            if (($type === 'link' || $type === 'file') && !preg_match("~^(?:f|ht)tps?://~i", $content)) {
                $content = "https://" . $content;
            }

            if ($id > 0) {
                // Если ID прилетел — обновляем существующую запись
                $stmt = $pdo->prepare("UPDATE info_links SET title = ?, description = ?, type = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $desc ?: null, $type, $content, $id]);
            } else {
                // Если ID нет — создаем новую
                $stmt = $pdo->prepare("INSERT INTO info_links (title, description, type, content) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $desc ?: null, $type, $content]);
            }

            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Удаление записи
        if ($action === 'delete') {
            $linkId = (int)($input['link_id'] ?? 0);
            if ($linkId <= 0) throw new Exception('Неверный ID записи');

            $stmt = $pdo->prepare("DELETE FROM info_links WHERE id = ?");
            $stmt->execute([$linkId]);

            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    throw new Exception('Неверный метод запроса');

} catch (Exception $e) {
    // В блоке перехвата исключений http_response_code выставляется только если он не был задан ранее (например, 403)
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
