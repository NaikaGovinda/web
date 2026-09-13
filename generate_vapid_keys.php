<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Генерация VAPID ключей</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        .key { background: #f4f4f4; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace; margin: 10px 0; }
        .success { color: green; }
        .error { color: red; }
        .instructions { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>Генерация VAPID ключей для Web Push</h1>
    
    <?php
    if (!extension_loaded('openssl')) {
        echo '<p class="error">Ошибка: Расширение OpenSSL не загружено</p>';
        exit;
    }
    
    // Проверяем, есть ли уже сохраненные ключи
    $keysFile = __DIR__ . '/api/get_vapid_key.php';
    $keysDataFile = __DIR__ . '/vapid_keys_data.json';
    
    $regenerate = isset($_GET['regenerate']) && $_GET['regenerate'] === '1';
    
    if (!$regenerate && file_exists($keysDataFile)) {
        // Загружаем существующие ключи
        $existing = json_decode(file_get_contents($keysDataFile), true);
        if ($existing && isset($existing['public_key']) && isset($existing['private_key'])) {
            echo '<p class="success">✓ VAPID ключи уже сгенерированы. Для перегенерации: <a href="?regenerate=1">нажмите здесь</a></p>';
            echo '<h2>Публичный ключ (для frontend):</h2>';
            echo '<div class="key">' . htmlspecialchars($existing['public_key']) . '</div>';
            echo '<h2>Приватный ключ (для backend, НЕ показывать никому):</h2>';
            echo '<div class="key">' . htmlspecialchars($existing['private_key']) . '</div>';
            echo '<div class="instructions">⚠️ Приватный ключ хранится в <code>api/get_vapid_key.php</code>. Не публикуйте его!</div>';
            exit;
        }
    }
    
    // Генерируем новые ключи
    $config = [
        "digest_alg" => "sha256",
        "private_key_bits" => 256,
        "private_key_type" => OPENSSL_KEYTYPE_EC,
        "curve_name" => "prime256v1",
    ];
    
    $result = openssl_pkey_new($config);
    if ($result === false) {
        echo '<p class="error">Ошибка генерации ключевой пары</p>';
        exit;
    }
    
    $details = openssl_pkey_get_details($result);
    
    $privateKey = $details['key'];
    $publicKeyB64 = rtrim(strtr(base64_encode($details['ec']['x']), '+/', '-_'), '=');
    $privateKeyB64 = rtrim(strtr(base64_encode($details['ec']['d']), '+/', '-_'), '=');
    
    // Сохраняем ключи в JSON для удобства
    file_put_contents($keysDataFile, json_encode([
        'public_key' => $publicKeyB64,
        'private_key' => $privateKeyB64,
        'generated_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT));
    
    // Обновляем get_vapid_key.php
    $phpCode = "<?php\n" .
        "/**\n" .
        " * VAPID ключи для Web Push уведомлений\n" .
        " * Сгенерированы: " . date('Y-m-d H:i:s') . "\n" .
        " * \n" .
        " * Для перегенерации: visit generate_vapid_keys.php?regenerate=1\n" .
        " */\n\n" .
        "header('Content-Type: application/json');\n\n" .
        "// VAPID ключи - сгенерированы один раз\n" .
        "// При необходимости перегенерировать: visit generate_vapid_keys.php?regenerate=1\n" .
        "\$vapidKeys = [\n" .
        "    'public_key' => '{$publicKeyB64}',\n" .
        "    'private_key' => '{$privateKeyB64}'\n" .
        "];\n\n" .
        "// Для frontend - возвращаем только публичный ключ\n" .
        "echo json_encode(['public_key' => \$vapidKeys['public_key']]);\n" .
        "?>\n";
    
    file_put_contents($keysFile, $phpCode);
    
    echo '<p class="success">✓ VAPID ключи успешно сгенерированы!</p>';
    echo '<h2>Публичный ключ (для frontend):</h2>';
    echo '<div class="key">' . htmlspecialchars($publicKeyB64) . '</div>';
    echo '<h2>Приватный ключ (для backend, НЕ показывать никому):</h2>';
    echo '<div class="key">' . htmlspecialchars($privateKeyB64) . '</div>';
    echo '<div class="instructions">';
    echo '<h3>✅ Что было сделано:</h3>';
    echo '<ol>';
    echo '<li>Ключи сохранены в <code>vapid_keys_data.json</code></li>';
    echo '<li>Файл <code>api/get_vapid_key.php</code> обновлен</li>';
    echo '</ol>';
    echo '<h3>📋 Следующие шаги:</h3>';
    echo '<ol>';
    echo '<li>Запустить SQL миграцию: <code>migrations/002_create_web_push_subscriptions.sql</code></li>';
    echo '<li>Проверить <code>api/get_vapid_key.php</code></li>';
    echo '<li>Открыть сайт в браузере и проверить подписку</li>';
    echo '</ol>';
    echo '</div>';
    ?>
</body>
</html>
