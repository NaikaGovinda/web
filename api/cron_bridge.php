<?php
// api/cron_bridge.php
header('Content-Type: text/plain; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по нашему строгому стандарту проекта
$pdo = require __DIR__ . '/db.php';

// Жестко задаем абсолютный путь к логу на вашем сервере Windows/XAMPP
$log_file = __DIR__ . '/reminders.log';
file_put_contents($log_file, "\n" . date('Y-m-d H:i:s') . " — ЗАПУСК ПЛАНИРОВЩИКА БУДИЛЬНИКОВ\n", FILE_APPEND);

try {
    // Подключаем конфигурацию и библиотеку отправки пушей нового стандарта FCM HTTP v1
    $config = require __DIR__ . '/config.php';
    require_once __DIR__ . '/send_fcm.php';

    // Принудительно ставим часовой пояс вашего сибирского региона
    date_default_timezone_set('Asia/Krasnoyarsk'); 
    $nowString = date('Y-m-d H:i:s');

    // 1. Выбираем напоминания, время которых подошло, но лога отправки еще нет
    $query = "SELECT ed.id as event_date_id, e.id as event_id, e.title, e.group_id, g.name as group_name, ed.notify_at 
              FROM event_dates ed
              JOIN events e ON ed.event_id = e.id
              JOIN `groups` g ON e.group_id = g.id
              LEFT JOIN event_notifications en ON ed.id = en.event_date_id
              WHERE ed.notify_at <= ? AND en.id IS NULL";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$nowString]);
    $pendingNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendingNotifications)) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " — Нет будильников для отправки.\n", FILE_APPEND);
        echo "Нет будильников для отправки.";
        exit;
    }

    foreach ($pendingNotifications as $noti) {
        $groupId = (int)$noti['group_id'];
        $eventTitle = $noti['title'];
        $groupName = $noti['group_name'];
        $eventDateId = (int)$noti['event_date_id'];

        // Находим всех одобренных участников группы
        $stmtUsers = $pdo->prepare("SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved'");
        $stmtUsers->execute([$groupId]);
        $memberIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

        // Находим всех лидеров группы
        $stmtLeaders = $pdo->prepare("SELECT user_id FROM group_leaders WHERE group_id = ?");
        $stmtLeaders->execute([$groupId]);
        $leaderIds = $stmtLeaders->fetchAll(PDO::FETCH_COLUMN);

        $recipientIds = array_unique(array_merge($memberIds, $leaderIds));

        if (!empty($recipientIds)) {
            // Извлекаем FCM-токены этих пользователей
            $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
            $stmtTokens = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id IN ($placeholders)");
            $stmtTokens->execute($recipientIds);
            $tokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($tokens)) {
                // Данные пуша нового образца
                $pushTitle = 'Напоминание о событии! ⏰';
                $pushBody = "В группе «{$groupName}» скоро начнётся: {$eventTitle}";
                
                $pushData = [
                    'type'     => 'event_reminder',
                    'event_id' => (string)$noti['event_id'],
                    'group_id' => (string)$groupId,
                    'action'   => 'view_event'
                ];

                // [ИСПРАВЛЕНО] Вместо упавшего cURL вызываем нашу готовую функцию FCM HTTP v1 с Keep-Alive
                sendFcmMessages($tokens, $pushTitle, $pushBody, $pushData);

                file_put_contents($log_file, date('Y-m-d H:i:s') . " — Отправлен пуш по будильнику ID {$eventDateId}.\n", FILE_APPEND);
            }
        }

        // Записываем лог в базу данных, чтобы Крон не отправлял это уведомление повторно каждую минуту!
        $stmtLog = $pdo->prepare("INSERT INTO event_notifications (event_date_id, type, sent_at) VALUES (?, '1h', NOW())");
        $stmtLog->execute([$eventDateId]);
    }

    echo "Успешно обработано " . count($pendingNotifications) . " будильников.";

} catch (Exception $e) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " — КРИТИЧЕСКАЯ ОШИБКА КРОНА: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Ошибка: " . $e->getMessage();
}
