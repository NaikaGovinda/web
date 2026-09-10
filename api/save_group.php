<?php
// save_group.php
ob_start();
header('Content-Type: application/json; charset=utf8mb4');

$logFile = __DIR__ . '/save_group_debug.log';

function writeTrace($step, $data) {
    global $logFile;
    $entry = "[" . date('Y-m-d H:i:s') . "] [{$step}]: " . (is_array($data) || is_object($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data) . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

writeTrace("ШАГ 1", "Скрипт save_group.php вызван");

$pdo = require __DIR__ . '/db.php';
writeTrace("ШАГ 2", "db.php успешно подключен");

if (!isset($_SESSION['user_id'])) {
    writeTrace("ВНИМАНИЕ", "Пользователь не авторизован в сессии");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];
    writeTrace("ШАГ 3", "ID пользователя из сессии: " . $user_id);

    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || (int)$user['is_admin'] !== 1) {
        writeTrace("ВНИМАНИЕ", "У пользователя нет флага is_admin=1");
        throw new Exception('Доступ запрещён. Требуются права администратора.');
    }

    $rawInput = file_get_contents('php://input');
    writeTrace("ШАГ 4 (СЫРОЙ JSON ФРОНТЕНДА)", $rawInput);

    $input = json_decode($rawInput, true);

    $address = isset($input['address']) ? trim($input['address']) : '';
    $city = isset($input['city']) ? trim($input['city']) : '';

    $lat = null;
    $lng = null;

    // === БЛОК ГЕОКОДИРОВАНИЯ НА СЕРВЕРЕ ===
    $lat = (isset($input['lat']) && $input['lat'] !== null && $input['lat'] !== '') ? (float)$input['lat'] : null;
    $lng = (isset($input['lng']) && $input['lng'] !== null && $input['lng'] !== '') ? (float)$input['lng'] : null;
    
    $address = isset($input['address']) ? trim($input['address']) : '';
    $city = isset($input['city']) ? trim($input['city']) : '';
    
    // Подстраховка: если карты совсем лежат, ставим дефолт Красноярска, чтобы поля НЕ были NULL!
    if ($lat === null || $lng === null) {
        $lat = 56.0153;
        $lng = 92.8932;
        writeTrace("ШАГ 9 (АВАРИЙНЫЙ ДЕФОЛТ)", "Установлены базовые координаты Красноярска");
    }

    // Лидеры
    $leaderIds = isset($input['leader_ids']) && is_array($input['leader_ids']) ? $input['leader_ids'] : [];
    writeTrace("ШАГ 10 (МАССИВ ЛИДЕРОВ ИЗ ФОРМЫ)", $leaderIds);

    if (empty($leaderIds)) {
        throw new Exception('Требуется выбрать хотя бы одного лидера');
    }

    $placeholders = implode(',', array_fill(0, count($leaderIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($leaderIds);
    $validLeaderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($validLeaderIds) === 0) {
        throw new Exception('Выбранные лидеры не найдены в системе');
    }

    $pdo->beginTransaction();

    if (isset($input['id']) && is_numeric($input['id']) && (int)$input['id'] > 0) {
        $id = (int)$input['id'];
        writeTrace("ШАГ 11 (РЕЖИМ ОБНОВЛЕНИЯ)", "Редактируем группу ID: " . $id);

        $stmt = $pdo->prepare("
            UPDATE `groups` SET 
                name = ?, description = ?, type = ?, is_online = ?, city = ?, address = ?, timezone = ?, lat = ?, lng = ?
            WHERE id = ?
        ");
        $params = [$input['name'], $input['description'], $input['type'], (int)$input['is_online'], $input['city'], $address, $input['timezone'], $lat, $lng, $id];
        writeTrace("ШАГ 12 (ПАРАМЕТРЫ SQL UPDATE)", $params);
        $stmt->execute($params);
        $groupId = $id;
    } else {
        writeTrace("ШАГ 11 (РЕЖИМ СОЗДАНИЯ)", "Создаем новую Нама-Хатту");
        $stmt = $pdo->prepare("
            INSERT INTO `groups` (name, description, type, is_online, city, address, timezone, lat, lng, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $params = [$input['name'], $input['description'], $input['type'], (int)$input['is_online'], $input['city'], $address, $input['timezone'], $lat, $lng];
        writeTrace("ШАГ 12 (ПАРАМЕТРЫ SQL INSERT)", $params);
        $stmt->execute($params);
        $groupId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("DELETE FROM group_leaders WHERE group_id = ?")->execute([$groupId]);
    $stmtInsertLeader = $pdo->prepare("INSERT INTO group_leaders (group_id, user_id) VALUES (?, ?)");
    foreach ($validLeaderIds as $lid) {
        $stmtInsertLeader->execute([$groupId, (int)$lid]);
    }

    $pdo->commit();
    writeTrace("ШАГ 13 (ФИНАЛ)", "Транзакция успешно зафиксирована в MySQL!");

    ob_clean();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    writeTrace("КРИТИЧЕСКАЯ ОШИБКА PHP", $e->getMessage());
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
