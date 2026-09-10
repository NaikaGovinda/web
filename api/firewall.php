<?php
// === НАСТРОЙКИ СИСТЕМЫ БЕЗОПАСНОСТИ ===
define('MAX_REQUESTS_PER_SECOND', 10); 
define('CACHE_DIR', __DIR__ . '/ip_cache'); 
define('BAN_DIR', __DIR__ . '/ban_list'); // Папка для забаненных IP
define('CLEANUP_CHANCE', 5); 

$user_ip = $_SERVER['REMOTE_ADDR'];
$ban_file = BAN_DIR . '/' . md5($user_ip) . '.lock';

// 1. ПРОВЕРКА НА БАН С АВТО-АМНИСТИЕЙ (24 ЧАСА)
if (file_exists($ban_file)) {
    $now = time();
    // 86400 секунд = 24 часа. Если файл блокировки старше суток — удаляем его
    if ($now - @filemtime($ban_file) > 86400) {
        @unlink($ban_file);
    } else {
        // Если сутки еще не прошли — жестко сбрасываем бота / заблокированного
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>403 Forbidden</h1>Access denied by firewall. Your IP has been blacklisted.";
        exit();
    }
}

// Извлекаем путь запроса
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$is_static = preg_match('/\.(jpg|jpeg|png|gif|css|js|ico|webp|woff|woff2|ttf|svg)$/i', $request_path);

if (!$is_static) {
    // Создаем папку кэша, если её нет
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0777, true);
    }

    // АВТОМАТИЧЕСКАЯ ОЧИСТКА СТАРЫХ ФАЙЛОВ КЭША
    if (rand(1, 100) <= CLEANUP_CHANCE) {
        $files = glob(CACHE_DIR . '/*.json');
        $now = time();
        if ($files) {
            foreach ($files as $file) {
                if ($now - @filemtime($file) > 10) {
                    @unlink($file);
                }
            }
        }
    }

    // 2. ЗАЩИТА ОТ ФЛУДА (RATE LIMITING)
    $ip_file = CACHE_DIR . '/' . md5($user_ip) . '.json';
    $current_time = time();
    $ip_data = ['time' => $current_time, 'count' => 1];

    if (file_exists($ip_file)) {
        $file_content = @file_get_contents($ip_file);
        if ($file_content) {
            $decoded = json_decode($file_content, true);
            if (isset($decoded['time']) && $decoded['time'] == $current_time) {
                $ip_data['count'] = $decoded['count'] + 1;
            }
        }
    }

    @file_put_contents($ip_file, json_encode($ip_data));

    // Если флудят — отправляем в бан-лист
    if ($ip_data['count'] > MAX_REQUESTS_PER_SECOND) {
        if (!is_dir(BAN_DIR)) { @mkdir(BAN_DIR, 0777, true); }
        @file_put_contents($ban_file, json_encode(['reason' => 'flood', 'date' => date('Y-m-d H:i:s')]));
        
        header('HTTP/1.1 429 Too Many Requests');
        echo "<h1>429 Too Many Requests</h1>Слишком много запросов. Вы заблокированы.";
        exit();
    }
}

// 3. ЗАЩИТА ОТ ВЗЛОМА (ЧЕРНЫЙ СПИСОК ПАТТЕРНОВ)
$bad_patterns = [
    '/\.\.\//', 
    '/\.%2e/i', 
    '/%2e%2e/i', 
    '/cgi-bin/i', 
    '/\/bin\/(sh|bash|cmd)/i', 
    '/(union|select|insert|delete|drop)/i', 
    '/<script/i' 
];

$is_attack = false;

// 🔥 ИСПРАВЛЕНО: Проверяем только параметры запроса (QUERY_STRING), исключая имена самих скриптов
$query_string = $_SERVER['QUERY_STRING'] ?? '';
$decoded_query = urldecode($query_string);

// Сканируем на атаки только адресную строку с параметрами (все, что после знака ?)
foreach ($bad_patterns as $pattern) {
    if (preg_match($pattern, $query_string) || preg_match($pattern, $decoded_query)) {
        $is_attack = true;
        break;
    }
}

// Дополнительно сканируем входящий массив GET-параметров
if (!$is_attack && !empty($_GET)) {
    foreach ($_GET as $key => $value) {
        if (is_string($value)) {
            foreach ($bad_patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $is_attack = true;
                    break 2;
                }
            }
        }
    }
}

// ЕСЛИ ОБНАРУЖЕНА НАСТОЯЩАЯ АТАКA — ВНОСИМ IP В БАН И ОСТАНАВЛИВАЕМ
if ($is_attack) {
    // Создаем папку банов, если скрипт её не нашел
    if (!is_dir(BAN_DIR)) {
        @mkdir(BAN_DIR, 0777, true);
    }

    // Создаем файл блокировки для этого IP
    $full_request = $_SERVER['REQUEST_URI'];
    $ban_info = ['reason' => 'exploit_scan', 'request' => $full_request, 'date' => date('Y-m-d H:i:s')];
    @file_put_contents($ban_file, json_encode($ban_info));

    // Пишем запись в общий лог файлов безопасности
    $log_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'firewall_blocked.log';
    $log_message = "[" . date('Y-m-d H:i:s') . "] PERM BAN: IP " . $user_ip . " заблокирован за запрос " . $full_request . "\n";
    @file_put_contents($log_file, $log_message, FILE_APPEND);

    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>403 Forbidden</h1>Access denied by firewall. Your IP has been blacklisted.";
    exit();
}
