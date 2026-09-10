<?php
// cron_test.php — тестовая задача для cron

$bot_token = '8116706372:AAFZIA2pTKEhm6dWPaf2Nw-4UrDIBAPiHGM';
$your_tg_id = 1115984144; // ← твой ID

// Логируем запуск (очень важно для отладки)
$log_message = date('Y-m-d H:i:s') . " | Запуск теста cron\n";
file_put_contents(__DIR__ . '/cron_test.log', $log_message, FILE_APPEND);

// Отправляем сообщение
$url = "https://api.telegram.org/bot$bot_token/sendMessage";
$data = [
    'chat_id' => $your_tg_id,
    'text' => "🧪 Тест cron: всё работает!\nВремя: " . date('Y-m-d H:i:s'),
    'parse_mode' => 'HTML'
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($data, JSON_UNESCAPED_UNICODE)
    ]
];

$result = file_get_contents($url, false, stream_context_create($options));

// Логируем результат
if ($result) {
    file_put_contents(__DIR__ . '/cron_test.log', "✅ Успешно отправлено\n", FILE_APPEND);
} else {
    file_put_contents(__DIR__ . '/cron_test.log', "❌ Ошибка отправки\n", FILE_APPEND);
}