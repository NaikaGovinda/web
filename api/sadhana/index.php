<?php
// api/sadhana/index.php — роутер для sadhana API
header('Content-Type: application/json; charset=utf-8');

// Перенаправляем запросы к существующим .php файлам
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Убираем '/api/sadhana/' из начала пути
$base = '/api/sadhana/';
if (strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}

// Если URI пустой — это root, возвращаем cards
if (empty($uri)) {
    $uri = 'cards';
}

// Путь к файлу
$scriptPath = __DIR__ . '/' . $uri . '.php';

if (file_exists($scriptPath) && is_file($scriptPath)) {
    require $scriptPath;
    exit;
}

// По умолчанию — 404
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Endpoint не найден'], JSON_UNESCAPED_UNICODE);
