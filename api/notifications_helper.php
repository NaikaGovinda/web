<?php
/**
 * Helper для отправки уведомлений пользователям
 * 
 * Примеры использования:
 * 
 * // Уведомление об одобрении заявки
 * sendNotification($user_id, 'application_approved', 'Заявка одобрена', 'Ваша заявка в группу "Кружок йоги" одобрена!');
 * 
 * // Уведомление о новом сообщении в чате
 * sendNotification($user_id, 'chat_message', 'Новое сообщение', 'Алексей написал в чате "Кружок йоги": Привет!');
 * 
 * // Уведомление о удалении из группы
 * sendNotification($user_id, 'group_removed', 'Вы покинули группу', 'Вы были удалены из группы "Кружок йоги"');
 */

function sendNotification($user_id, $type, $title, $message, $group_id = null, $target_page = null, $target_params = null) {
    if (!$user_id || !$type || !$title || !$message) {
        return false;
    }

    // Сохраняем уведомление в БД с новыми полями
    $sql = "INSERT INTO notifications (user_id, type, title, message, group_id, target_page, target_params, read) VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
    
    // Подключение к БД (адаптируйте под вашу конфигурацию)
    $host = 'localhost';
    $dbname = 'u3385428_namahatta_db';
    $dbuser = 'u3385428_namahatta';
    $dbpass = 'your_password'; // Замените на ваш пароль
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $type, $title, $message, $group_id, $target_page, $target_params]);
        
        $notification_id = $pdo->lastInsertId();
        
        // Отправляем пуш-уведомление если есть FCM токен
        sendPushNotification($user_id, $title, $message, $type, $notification_id);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Ошибка отправки уведомления: " . $e->getMessage());
        // Не критично — уведомление всё равно появится локально
        return false;
    }
}

function sendPushNotification($user_id, $title, $message, $type, $notification_id) {
    // Получаем FCM токен пользователя
    $host = 'localhost';
    $dbname = 'u3385428_namahatta_db';
    $dbuser = 'u3385428_namahatta';
    $dbpass = 'your_password';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $token = $stmt->fetchColumn();
        
        if (!$token) return;
        
        // Отправляем через FCM
        $payload = json_encode([
            'notification' => [
                'title' => $title,
                'body' => $message,
                'icon' => 'ic_notification',
                'badge' => '1'
            ],
            'data' => [
                'type' => $type,
                'notification_id' => $notification_id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ],
            'to' => $token
        ]);
        
        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=YOUR_SERVER_KEY',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_exec($ch);
        curl_close($ch);
        
    } catch (Exception $e) {
        error_log("Ошибка push-уведомления: " . $e->getMessage());
    }
}
