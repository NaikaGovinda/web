<?php
// update_user_location.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php перенесен на самый верх вне блока try-catch по стандарту проекта
$pdo = require __DIR__ . '/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $tg_id = (int)($input['tg_id'] ?? 0);
    $lat = (float)($input['lat'] ?? 0);
    $lng = (float)($input['lng'] ?? 0);

    if (!$tg_id || !$lat || !$lng) {
        http_response_code(400); // [ИСПРАВЛЕНО] Выставляем корректный статус для ошибок валидации данных
        throw new Exception('Неверные данные геолокации');
    }

    // === Определяем город через Nominatim (OpenStreetMap) ===
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&accept-language=ru";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: NamahattaApp/1.0 (contact@namahata.ru)'
        ],
        // [ИСПРАВЛЕНО] Отключаем проверку SSL-сертификатов для стабильной работы геокодера на локальном XAMPP (Windows)
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);

    $city = null;
    if ($data && isset($data['address'])) {
        // Пробуем разные поля (город может быть в разных ключах в зависимости от типа населенного пункта)
        $addr = $data['address'];
        $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['region'] ?? null;
    }

    if (!$city) {
        $city = 'Неизвестно';
    }

    // Обновляем город пользователя
    $pdo->prepare("UPDATE `users` SET city = ? WHERE telegram_id = ?")
        ->execute([$city, $tg_id]);

    echo json_encode(['success' => true, 'city' => $city], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Если статус ответа не был изменен ранее (например, на 400), выставляем системную ошибку 500
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}