<?php
/**
 * API endpoint: Exchange Google authorization code for access token
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

// Google OAuth settings
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com'); // Замените на ваш Client ID
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET'); // Замените на ваш Secret
define('GOOGLE_REDIRECT_URI', 'http://yourdomain.com/auth_google_callback.html'); // Замените на ваш домен

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['code']) || empty($input['code'])) {
        throw new Exception('Код авторизации не предоставлен');
    }

    $code = $input['code'];

    // Обмен кода на токен
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $tokenData,
            'timeout' => 30
        ]
    ]);

    $tokenResponse = @file_get_contents($tokenUrl, false, $context);

    if ($tokenResponse === false) {
        throw new Exception('Ошибка при получении токена от Google');
    }

    $tokenData = json_decode($tokenResponse, true);

    if (!isset($tokenData['access_token'])) {
        $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Неизвестная ошибка Google';
        throw new Exception($errorMsg);
    }

    // Получаем данные пользователя
    $userUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenData['access_token'];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $tokenData['access_token'],
            'timeout' => 30
        ]
    ]);

    $userResponse = @file_get_contents($userUrl, false, $context);

    if ($userResponse === false) {
        throw new Exception('Ошибка при получении данных пользователя');
    }

    $userData = json_decode($userResponse, true);

    if (!isset($userData['email'])) {
        throw new Exception('Не удалось получить данные пользователя из Google');
    }

    $email = $userData['email'] ?? '';
    $firstName = $userData['given_name'] ?? '';
    $lastName = $userData['family_name'] ?? '';
    $googleId = $userData['id'] ?? null;

    // Возвращаем токен и данные пользователя
    echo json_encode([
        'success' => true,
        'access_token' => $tokenData['access_token'],
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'google_id' => $googleId
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
