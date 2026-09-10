<?php
// write_log.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по нашему строгому стандарту проекта
$pdo = require __DIR__ . '/db.php';

$fileName = $_POST['file'] ?? '';
$msg = $_POST['msg'] ?? '';

// [БЕЗОПАСНОСТЬ] Извлекаем только чистое имя файла. Злоумышленник не сможет передать пути типа "../../"
$safeFileName = basename($fileName);

// Разрешаем писать логи только с расширением .log или .txt
$fileExtension = strtolower(pathinfo($safeFileName, PATHINFO_EXTENSION));
$isAllowedExtension = in_array($fileExtension, ['log', 'txt']);

if (empty($safeFileName) || empty($msg) || !$isAllowedExtension) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid path or data'], JSON_UNESCAPED_UNICODE);
    exit;
}

// [ИСПРАВЛЕНО] Путь собирается динамически относительно текущей папки /api/ через __DIR__ (работает на Windows и Linux)
$absoluteFilePath = __DIR__ . '/' . $safeFileName;

// Пишем в файл с защитной блокировкой данных
file_put_contents($absoluteFilePath, date('[Y-m-d H:i:s] ') . trim($msg) . "\n", FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
