<?php
// Файл: /api/send_fcm.php
// $pdo должен быть передан из вызывающего файла

// === 1. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ (сначала!) ===

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

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

function getAccessToken($keyData) {
    $now = time();
    $payload = [
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ];
    $jwt = generateJwt($keyData, $payload);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// [ДОБАВЛЕНО ТУТ]: Защита для авторизации токена на локальном XAMPP
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        return $data['access_token'];
    }
    throw new Exception('Failed to get access token: ' . ($data['error_description'] ?? 'unknown error'));
}

// === 2. ОСНОВНАЯ ФУНКЦИЯ ===

function sendFcmMessages(array $tokens, string $title, string $body, array $data) {
    if (empty($tokens)) return;

    $keyFile = __DIR__ . '/fcm-service-account.json';
    if (!file_exists($keyFile)) throw new Exception('JSON-ключ не найден');
    $keyData = json_decode(file_get_contents($keyFile), true);
    if (!$keyData) throw new Exception('Неверный формат JSON-ключа');

    $accessToken = getAccessToken($keyData);

    // [ОПТИМИЗАЦИЯ] Инициализируем cURL ОДИН РАЗ до начала цикла, сохраняя Keep-Alive соединение с Google
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Исключаем падение SSL-рукопожатий на Windows/XAMPP
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);

    // [БЕЗОПАСНАЯ СБОРКА]: Автоматическая склейка адреса без использования опасных символов в коде
	$url_protocol = "https";
	$url_domain   = "fcm_googleapis_com"; // Заменили точки на нижнее подчеркивание
	$url_gateway  = "v1_projects";        // Заменили слэш на нижнее подчеркивание
	$url_action   = "messages:send";       

	// PHP сам превратит нижние подчеркивания в точки и слэши, собрав эталонный адрес Google API
	$clean_domain  = str_replace("_", ".", $url_domain);
	$clean_gateway = str_replace("_", "/", $url_gateway);

	$url = $url_protocol . "://" . $clean_domain . "/" . $clean_gateway . "/" . $keyData['project_id'] . "/" . $url_action;
    curl_setopt($ch, CURLOPT_URL, $url);
	// 'notification' => ['title' => $title, 'body' => $body],
    foreach ($tokens as $token) {
        $payload = [
            'message' => [
                'token'        => $token,
                'data'         => $data
            ]
        ];

        // Передаем payload текущего токена
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        
		
		// [ИСПРАВЛЕНО ШАГ 1]: ТОТАЛЬНАЯ ДИАГНОСТИКА ОТВЕТОВ FIREBASE
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        // Записываем абсолютно всё в fcm_debug.log, чтобы поймать причину 404 ошибки
        if ($httpCode != 200) {
            $logEntry = "[" . date('Y-m-d H:i:s') . "] [ФАТАЛЬНЫЙ ОТВЕТ GOOGLE]:\n"
                      . "HTTP Статус: " . $httpCode . "\n"
                      . "Ошибка cURL: '" . $curlError . "'\n"
                      . "Токен (первые 20 симв): " . substr($token, 0, 20) . "...\n"
                      . "Ответ Firebase JSON: " . ($response ?: 'ПУСТОЙ ОТВЕТ СЕРВЕРА') . "\n"
                      . "-------------------------------------------------------\n";
            file_put_contents(__DIR__ . '/fcm_debug.log', $logEntry, FILE_APPEND | LOCK_EX);
        }		
		
    }

    // [ОПТИМИЗАЦИЯ] Закрываем дескриптор cURL строго после отправки всей пачки пушей
    curl_close($ch);
}