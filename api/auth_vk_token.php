<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);

$code = $input['code'] ?? '';
$deviceId = $input['device_id'] ?? '';
$state = $input['state'] ?? '';
$type = $input['type'] ?? '';

if (!$code || !$deviceId) {
    echo json_encode(['success' => false, 'error' => 'Missing code or device_id']);
    exit;
}

// Обмениваем код на токен через VK ID API
$ch = curl_init();
$postData = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => '54773774',
    'redirect_uri' => 'https://namahata.ru/auth_vk_callback.html',
    'device_id' => $deviceId,
    'state' => $state
];

curl_setopt($ch, CURLOPT_URL, 'https://id.vk.com/oauth2/auth/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'Curl error: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        'success' => false, 
        'error' => 'VK API error: ' . $response,
        'http_code' => $httpCode
    ]);
    exit;
}

$tokenData = json_decode($response, true);

if (!$tokenData || !isset($tokenData['access_token'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid token response',
        'data' => $tokenData
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'access_token' => $tokenData['access_token'],
    'id_token' => $tokenData['id_token'] ?? '',
    'email' => $tokenData['email'] ?? '',
    'user_id' => $tokenData['user_id'] ?? ''
]);
