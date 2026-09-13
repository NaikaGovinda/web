<?php
// send_event_reminders.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// [АРХИТЕКТУРА] Подключаем ядро и автозагрузчик Composer на самом верху файла
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/config.php';
if (function_exists('opcache_reset')) { opcache_reset(); }

// Абсолютный путь к логу на вашем сервере Windows/XAMPP
$log_file = __DIR__ . '/reminders.log';
$fcm_log  = __DIR__ . '/fcm_debug.log';

// Массив всех лог-файлов, которые нужно контролировать
$logs_to_clean = [$log_file, $fcm_log];

foreach ($logs_to_clean as $file) {
    if (file_exists($file)) {
        // Вычисляем, сколько секунд назад файл лога обновлялся в последний раз
        $file_age_seconds = time() - filemtime($file);
        
        // 86400 секунд — это ровно 24 часа (одни сутки)
        if ($file_age_seconds > 86400) {
            // Флаг FILE_APPEND НЕ ставим! Записываем чистую строку, полностью обнуляя старый мусор
            file_put_contents($file, "[" . date('Y-m-d H:i:s') . "] — Лог автоматически очищен планировщиком (удален мусор старше суток)\n");
        }
    }
}

// Теперь спокойно продолжаем дописывать текущий запуск Крона в свежий файл лога
file_put_contents($log_file, "\n=========================================\n" . 
date('Y-m-d H:i:s') . " — СКРИПТ КРОНА ЗАПУЩЕН (ПЛАНИРОВЩИК БУДИЛЬНИКОВ)\n", 
FILE_APPEND);

try {
    // [ИСПРАВЛЕНО] Комментарии из тела SQL-запроса вычищены во избежание синтаксических сбоев
    $stmt = $pdo->prepare("
        SELECT 
            ed.id AS date_id, 
            ed.notify_at,
            e.id AS event_id, 
            e.title, 
            e.event_date, 
            e.group_id,
            g.name AS group_name,
            g.timezone
        FROM `event_dates` ed
        JOIN `events` e ON ed.event_id = e.id
        JOIN `groups` g ON e.group_id = g.id
        LEFT JOIN `event_notifications` en ON ed.id = en.event_date_id
        WHERE g.status = 'active'
          AND ed.notify_at <= NOW()
          AND en.id IS NULL
    ");
    $stmt->execute();
    $pending_notifications = $stmt->fetchAll();
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " — Найдено будильников к отправке прямо сейчас: " . count($pending_notifications) . "\n", FILE_APPEND);
    
    foreach ($pending_notifications as $notif) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " — [Будильник ID {$notif['date_id']}] Сработал таймер для события ID {$notif['event_id']} («{$notif['title']}»).\n", FILE_APPEND);
        
        // Фиксируем часовой пояс группы для красивого отображения времени в письме
        $groupTz = $notif['timezone'] ?? 'Asia/Krasnoyarsk';
        try { 
            $tz = new DateTimeZone($groupTz); 
        } catch (Exception $e) { 
            $tz = new DateTimeZone('Asia/Krasnoyarsk'); 
        }
        
        // Форматируем локальное время начала программы
        $eventLocal = new DateTime($notif['event_date'], new DateTimeZone('UTC'));
        $eventLocal->setTimezone($tz);
        
        // СРАЗУ блокируем будильник в базе данных, чтобы при зависании скрипт не отправил дубли
        $stmtInsert = $pdo->prepare("INSERT INTO `event_notifications` (event_date_id, type) VALUES (?, '1h')");
        $stmtInsert->execute([$notif['date_id']]);
        
        // Пакет данных для передачи в функции отправки
        $eventData = [
            'id' => (int)$notif['event_id'],
            'group_id' => (int)$notif['group_id'],
            'title' => $notif['title'],
            'group_name' => $notif['group_name']
        ];
        
        // Запуск FCM Push уведомления
        sendEventReminderFcm($pdo, $eventData);
        
        // Запуск Web Push уведомления
        sendEventReminderWebPush($pdo, $eventData);
        
        // Запуск Email уведомления
        sendEmailNotificationProvenCLI($pdo, $eventData, $eventLocal, $log_file);
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " — [Будильник ID {$notif['date_id']}] Все уведомления успешно отстрелялись.\n", FILE_APPEND);
    }

} catch (Exception $e) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " — КРИТИЧЕСКИЙ СБОЙ PHP В КРОНЕ: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ==========================================================
// НАДЕЖНАЯ ПРОВЕРЕННАЯ ФУНКЦИЯ ОТПРАВКИ ИЗ-ПОД CLI (CRON)
// ==========================================================
function sendEmailNotificationProvenCLI($pdo, $event, $eventLocal, $log_file) {
    global $config; 
    
    // [ИСПРАВЛЕНО] Исправлен баг с несоответствием числа знаков '?' и передаваемых параметров в execute
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.email, u.first_name 
        FROM `users` u 
        WHERE u.email IS NOT NULL 
          AND u.email != '' 
          AND (
            u.id IN (SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved') 
            OR u.id IN (SELECT user_id FROM group_leaders WHERE group_id = ?)
          )
    ");
    $stmt->execute([$event['group_id'], $event['group_id']]);
    $recipients = $stmt->fetchAll();
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " — [Mail] Найдено получателей в группе: " . count($recipients) . "\n", FILE_APPEND);
    if (empty($recipients)) return;
    
    $timeFormatted = $eventLocal->format('H:i');
    $dateFormatted = $eventLocal->format('d.m.Y');
    $subject = "Напоминание о духовной программе в группе 📅 «{$event['group_name']}»";
    
    foreach ($recipients as $r) {
        $to = $r['email'];
        $name = $r['first_name'] ?? 'участник';
        $body = "Харе Кришна, {$name}!\n\nНапоминаем, что скоро состоится духовная программа «{$event['title']}» в группе «{$event['group_name']}».\n\n Дата 📅 проведения: {$dateFormatted}\n Время начала: {$timeFormatted} (по времени ⏰ группы).\n\nЖдем вас! Ссылка на сайт: https://namahata.ru";
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP(); 
            $mail->Host = $config['email']['host']; 
            $mail->SMTPAuth = true; 
            $mail->Username = $config['email']['username']; 
            $mail->Password = $config['email']['password']; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port = $config['email']['port']; 
            
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($config['email']['from'], $config['email']['from_name']);
            $mail->addAddress($to); 
            
            $mail->isHTML(false); 
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            $mailResult = true;
        } catch (Exception $e) {
            $mailResult = false;
            error_log("Крон ошибка для {$to}: " . $mail->ErrorInfo);
        }
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " — [Mail] Отправка на {$to} -> " . ($mailResult ? "УСПЕШНО ДОСТАВЛЕНО" : "СБОЙ PHPMailer") . "\n", FILE_APPEND);
        sleep(1); 
    }
}

// ==========================================================
// [ИСПРАВЛЕНО ШАГ 1]: УМНЫЙ FCM ПУШ ДЛЯ УЧАСТНИКОВ И ЛИДЕРОВ ГРУППЫ
// ==========================================================
function sendEventReminderFcm($pdo, $event) {
    // [ТОЧЕЧНО]: Выбираем DISTINCT user_id и участников, и лидеров группы, чтобы пуш прилетал ВСЕМ
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id 
        FROM `users` u
        WHERE u.is_active = 1
        AND (
            u.id IN (SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved')
            OR u.id IN (SELECT user_id FROM group_leaders WHERE group_id = ?)
        )
    ");
    $stmt->execute([$event['group_id'], $event['group_id']]);
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userIds)) return;

    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id IN ($placeholders) AND token IS NOT NULL AND token != ''");
    $stmt->execute($userIds);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tokens)) return;

    require_once __DIR__ . '/send_fcm.php';

    $titleNotif = "Напоминание о встрече! 📅";
    $bodyNotif = "Скоро начнется: " . $event['title'] . " (группа «" . $event['group_name'] . "»)";
    
    // [ТОЧЕЧНО]: Добавляем 'title' и 'body' прямо в $data для обработки в Kotlin
    $data = [
        'type' => 'event_reminder',
        'event_id' => (string)$event['id'],
        'group_id' => (string)$event['group_id'],
        'action' => 'view_event',
        'title' => $titleNotif,
        'body' => $bodyNotif
    ];

    sendFcmMessages($tokens, $titleNotif, $bodyNotif, $data);
}

// ==========================================================
// [НОВОЕ] WEB PUSH НАПОМИНАНИЕ О СОБЫТИИ
// ==========================================================
function sendEventReminderWebPush($pdo, $event) {
    require_once __DIR__ . '/send_web_push.php';
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id
        FROM `users` u
        WHERE u.is_active = 1
        AND (
            u.id IN (SELECT user_id FROM applications WHERE group_id = ? AND status = 'approved')
            OR u.id IN (SELECT user_id FROM group_leaders WHERE group_id = ?)
        )
    ");
    $stmt->execute([$event['group_id'], $event['group_id']]);
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($userIds)) return;
    
    $titleNotif = "Напоминание о встрече! 📅";
    $bodyNotif = "Скоро начнется: " . $event['title'] . " (группа «" . $event['group_name'] . "»)";
    
    foreach ($userIds as $userId) {
        try {
            sendWebPushToUser($pdo, $userId, $titleNotif, $bodyNotif, [
                'type' => 'event_reminder',
                'event_id' => (string)$event['id'],
                'group_id' => (string)$event['group_id'],
                'action' => 'view_event',
                'target_page' => 'group.html',
                'target_params' => json_encode(['id' => $event['group_id'], 'tab' => 'events'], JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Exception $e) {
            error_log("Web push event reminder error for user $userId: " . $e->getMessage());
        }
    }
}
