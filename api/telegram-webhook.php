<?php
// telegram-webhook.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, сессии для вебхука Telegram не требуются
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

$bot_token = $config['telegram']['bot_token'];

// Получаем входящие данные от API Telegram
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Обрабатываем только callback_query (нажатия кнопок)
if (!isset($data['callback_query'])) {
    echo 'OK';
    exit;
}

$callback_query = $data['callback_query'];
$callback_data = $callback_query['data'] ?? '';
$from_id = (int)($callback_query['from']['id'] ?? 0); // Приведение к int для безопасности

// Ответ по умолчанию
$response_text = 'Произошла ошибка.';
$show_alert = true;

// Опции контекста cURL/Stream для стабильной работы SSL на локальном XAMPP (Windows)
$ssl_context = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
];

try {
    // =========================================================================
    // === 1. ОДОБРЕНИЕ ЗАЯВКИ: approve_{group_id}_{applicant_tg_id} ===
    // =========================================================================
    if (strpos($callback_data, 'approve_') === 0) {
        $parts = explode('_', $callback_data);
        if (count($parts) !== 3) {
            throw new Exception('Неверный формат данных');
        }

        $group_id = (int)$parts[1];
        $applicant_tg_id = (int)$parts[2];

        // Находим user_id заявителя
        $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$applicant_tg_id]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new Exception('Пользователь не найден');
        }
        $user_id = (int)$user['id'];

        // Находим заявку
        $stmt = $pdo->prepare("
            SELECT a.id, g.name AS group_name
            FROM applications a
            JOIN `groups` g ON a.group_id = g.id
            WHERE a.group_id = ? AND a.user_id = ? AND a.status = 'pending'
        ");
        $stmt->execute([$group_id, $user_id]);
        $app = $stmt->fetch();

        if (!$app) {
            throw new Exception('Заявка не найдена или уже обработана');
        }

        // [АРХИТЕКТУРА ИСПРАВЛЕНА] Проверяем лидера через правильную связующую таблицу group_leaders
        $stmt = $pdo->prepare("
            SELECT 1 
            FROM group_leaders gl
            JOIN users u ON gl.user_id = u.id
            WHERE gl.group_id = ? AND u.telegram_id = ?
        ");
        $stmt->execute([$group_id, $from_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Только подтвержденный лидер группы может одобрить заявку');
        }

        // Обновляем статус
        $pdo->prepare("UPDATE applications SET status = 'approved' WHERE id = ?")->execute([$app['id']]);

        // Отправляем уведомление участнику в ЛС бота
        $text_to_user = "Ваша заявка в группу «" . htmlspecialchars($app['group_name']) . "» одобрена!\n\nДобро пожаловать! 🙏";
        
        file_get_contents("https://telegram.org", false, stream_context_create([
            'http' => array_merge([
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode([
                    'chat_id' => $applicant_tg_id,
                    'text' => $text_to_user,
                    'parse_mode' => 'HTML'
                ], JSON_UNESCAPED_UNICODE)
            ], $ssl_context)
        ]));

        $response_text = 'Заявка успешно одобрена! ✅';
        $show_alert = false; 

    // =========================================================================
    // === 2. ОТКЛОНЕНИЕ ЗАЯВКИ: reject_{group_id}_{applicant_tg_id} ===
    // =========================================================================
    } elseif (strpos($callback_data, 'reject_') === 0) {
        $parts = explode('_', $callback_data);
        if (count($parts) !== 3) {
            throw new Exception('Неверный формат данных');
        }

        $group_id = (int)$parts[1];
        $applicant_tg_id = (int)$parts[2];

        $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$applicant_tg_id]);
        $user = $stmt->fetch();
        if (!$user) throw new Exception('Пользователь не найден');
        $user_id = (int)$user['id'];

        $stmt = $pdo->prepare("
            SELECT a.id, g.name AS group_name
            FROM applications a
            JOIN `groups` g ON a.group_id = g.id
            WHERE a.group_id = ? AND a.user_id = ? AND a.status = 'pending'
        ");
        $stmt->execute([$group_id, $user_id]);
        $app = $stmt->fetch();

        if (!$app) {
            throw new Exception('Заявка не найдена или уже обработана');
        }

        // [АРХИТЕКТУРА ИСПРАВЛЕНА] Проверяем лидера через правильную связующую таблицу group_leaders
        $stmt = $pdo->prepare("
            SELECT 1 
            FROM group_leaders gl
            JOIN users u ON gl.user_id = u.id
            WHERE gl.group_id = ? AND u.telegram_id = ?
        ");
        $stmt->execute([$group_id, $from_id]);

        if (!$stmt->fetch()) {
            throw new Exception('Только подтвержденный лидер группы может отклонить заявку');
        }

        $pdo->prepare("UPDATE applications SET status = 'rejected' WHERE id = ?")->execute([$app['id']]);

        $response_text = 'Заявка отклонена. ❌';
        $show_alert = false;

    } else {
        $response_text = 'Неизвестное действие.';
    }

} catch (Exception $e) {
    $response_text = '❌ Ошибка: ' . $e->getMessage();
}

// Отвечаем на callback_query, чтобы у лидера в Telegram убралась анимация загрузки на кнопке
file_get_contents("https://telegram.org", false, stream_context_create([
    'http' => array_merge([
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'callback_query_id' => $callback_query['id'],
            'text' => $response_text,
            'show_alert' => $show_alert
        ], JSON_UNESCAPED_UNICODE)
    ], $ssl_context)
 ]));

echo 'OK';
