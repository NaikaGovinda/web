<?php
// send_test_notification.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной session_start() здесь не требуется
$pdo = require __DIR__ . '/db.php';

// === 1. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ (Объявлены до основного рантайма во избежание фатальных ошибок) ===

/**
 * URL-кодирование в формате Base64
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Генерирует JWT-токен для авторизации в Google API
 */
function generateJwt($keyData, $payload) {
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($payload))
    ];

    $signature = '';
    openssl_sign(implode('.', $segments), $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

/**
 * Получает access token для аутентификации в FCM через OAuth2
 */
function getAccessToken($keyData) {
    $now = time();
    $payload = [
        'iss'   => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now
    ];

    $jwt = generateJwt($keyData, $payload);
    $tokenUrl = 'https://oauth2.googleapis.com/token';

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // [ИСПРАВЛЕНО] Совместимость с локальным XAMPP на Windows
    
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        return $data['access_token'];
    }

    throw new Exception('Failed to get access token: ' . ($data['error_description'] ?? 'unknown error'));
}

// === 2. ОСНОВНОЙ ИСПОЛНЯЕМЫЙ КОД ТЕСТИРОВАНИЯ ===

try {
    $keyFile = __DIR__ . '/fcm-service-account.json';

    if (!file_exists($keyFile)) {
        throw new Exception('JSON-ключ не найден. Загрузите файл fcm-service-account.json в /api/');
    }

    $keyData = json_decode(file_get_contents($keyFile), true);
    if (!$keyData) {
        throw new Exception('Неверный формат JSON-ключа');
    }

    // Теперь метод гарантированно объявлен строками выше
    $accessToken = getAccessToken($keyData);
    $user_id = 48;

    // Получаем токен из базы данных
    $stmt = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $token = $stmt->fetchColumn();

    if (!$token) {
        throw new Exception('FCM-токен не найден для тестового пользователя ID 48');
    }

    // Все значения в секции data приведены к текстовому типу string
    $payload = [
        'message' => [
            'token'        => $token,
            'notification' => [
                'title' => 'Тестовое уведомление',
                'body'  => 'Это тестовое сообщение от системы Нама-Хатта'
            ],
            'data' => [
                'type'    => 'test',
                'user_id' => (string)$user_id
            ]
        ]
    ];

    $ch = curl_init('https://fcm.googleapis.com/v1/projects/' . $keyData['project_id'] . '/messages:send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // [ИСПРАВЛЕНО] Совместимость с локальным XAMPP на Windows
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        throw new Exception("FCM request failed with HTTP status $httpCode. Response: $result");
    }

    echo json_encode([
        'success' => true,
        'result'  => json_decode($result, true)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
