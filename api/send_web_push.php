<?php
/**
 * Отправка Web Push уведомлений через FCM
 * Использует тот же FCM проект и JWT OAuth2, что и send_fcm.php
 */

if (!function_exists('sendWebPushToUser')) {

/**
 * Отправить Web Push уведомление пользователю
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array $data Custom data payload (action, group_id, target_page, etc.)
 */
function sendWebPushToUser($pdo, $userId, $title, $body, array $data = []) {
    // Получаем все активные web push подписки пользователя
    $stmt = $pdo->prepare("
        SELECT endpoint, p256dh, auth, browser
        FROM user_web_push_subscriptions
        WHERE user_id = :user_id AND active = 1
    ");
    $stmt->execute([':user_id' => $userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subscriptions)) {
        return; // Нет подписок - ничего не отправляем
    }
    
    // Загружаем FCM ключи
    $keyFile = __DIR__ . '/fcm-service-account.json';
    if (!file_exists($keyFile)) {
        error_log("Web push: FCM key file not found");
        return;
    }
    
    $keyData = json_decode(file_get_contents($keyFile), true);
    if (!$keyData) {
        error_log("Web push: Invalid FCM key format");
        return;
    }
    
    // Включаем функции для JWT и access token
    require_once __DIR__ . '/send_fcm.php';
    
    try {
        $accessToken = getAccessToken($keyData);
    } catch (Exception $e) {
        error_log("Web push: Failed to get access token: " . $e->getMessage());
        return;
    }
    
    // Отправляем каждую подписку
    foreach ($subscriptions as $sub) {
        try {
            $ch = curl_init();
            
            // Собираем URL через str_replace для обхода экранирования
            $url_protocol = "https";
            $url_domain   = "fcm_googleapis_com";
            $url_gateway  = "v1_projects";
            $url_action   = "messages:send";
            
            $clean_domain  = str_replace("_", ".", $url_domain);
            $clean_gateway = str_replace("_", "/", $url_gateway);
            
            $url = $url_protocol . "://" . $clean_domain . "/" . $clean_gateway . "/" . $keyData['project_id'] . "/" . $url_action;
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ]);
            
            // FCM payload для web push
            $payload = [
                'message' => [
                    'token' => $sub['endpoint'],
                    'notification' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'data' => $data,
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'high'
                        ],
                        'fcm_options' => [
                            'link' => buildDeepLinkUrl($data)
                        ]
                    ]
                ]
            ];
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Обработка ошибок
            if ($httpCode == 410) {
                // Подписка истекла - удаляем
                error_log("Web push: Expired subscription for user $userId, removing");
                removeExpiredSubscription($pdo, $sub['endpoint']);
            } elseif ($httpCode != 200) {
                error_log("Web push: Failed for user $userId, HTTP $httpCode: " . ($response ?: 'empty'));
            }
            
        } catch (Exception $e) {
            error_log("Web push: Exception for user $userId: " . $e->getMessage());
        }
    }
}

/**
 * Построить deep link URL из data payload
 */
function buildDeepLinkUrl(array $data) {
    if (!empty($data['target_page']) && !empty($data['target_params'])) {
        try {
            $params = json_decode($data['target_params'], true);
            if ($params) {
                return '/' . $data['target_page'] . '?' . http_build_query($params);
            }
        } catch (Exception $e) {
            // ignore
        }
        return '/' . $data['target_page'];
    }
    return '/index.html';
}

/**
 * Удалить истекшую подписку
 */
function removeExpiredSubscription($pdo, $endpoint) {
    $stmt = $pdo->prepare("
        UPDATE user_web_push_subscriptions
        SET active = 0, updated_at = NOW()
        WHERE endpoint = :endpoint
    ");
    $stmt->execute([':endpoint' => $endpoint]);
}

}
