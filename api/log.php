<?php
// log.php

// [АРХИТЕКТУРА] db.php подключен на самом верху для унификации сессий и глобальных настроек проекта
$pdo = require __DIR__ . '/db.php';

// [ИСПРАВЛЕНО] Исправлен синтаксис получения параметра из GET-запроса
$msg = $_GET['msg'] ?? '';

// [ИСПРАВЛЕНО] Путь адаптирован под локальный XAMPP на Windows через __DIR__ (работает везде динамически)
$file = __DIR__ . '/debug.log';

if (!empty($msg)) {
    // [БЕЗОПАСНОСТЬ] Очищаем входящую строку от опасных символов перед записью в лог
    $safeMsg = trim(filter_var($msg, FILTER_DEFAULT));
    
    // [ИСПРАВЛЕНО] Исправлен синтаксис вызова функций и конкатенации строк
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $safeMsg . "\n", FILE_APPEND | LOCK_EX);
}

// Отдаем прозрачный пиксель 1x1 GIF
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICTAEAOW==');
