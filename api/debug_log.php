<?php
header('Content-Type: text/plain');
header('Cache-Control: no-store');

$logFile = dirname(__DIR__) . '/vk_callback_debug.log';
$timestamp = date('Y-m-d H:i:s');
$log = $_GET['log'] ?? '';

if ($log) {
    file_put_contents(
        $logFile,
        "[$timestamp] $log\n\n",
        FILE_APPEND
    );
    echo "OK - Log saved\n";
} else {
    echo "No log data received\n";
}

// Также возвращаем последние логи если есть
if (file_exists($logFile)) {
    echo "\n\n=== PREVIOUS LOGS ===\n";
    echo file_get_contents($logFile);
}
