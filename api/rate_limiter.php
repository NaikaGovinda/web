<?php
// rate_limiter.php
// Простой rate limiter на основе IP и действия

/**
 * Проверяет, не превышено ли ограничение попыток
 * @param string $action Название действия (login, register, etc.)
 * @param int $maxAttempts Максимальное количество попыток
 * @param int $window Время окна в секундах (по умолчанию 15 минут)
 * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
 */
function checkRateLimit($action, $maxAttempts = 5, $window = 900) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $cacheDir = __DIR__ . '/rate_limit_cache';
    
    // Создаём папку кэша если нет
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    // Ключ для этого IP и действия
    $key = md5($ip . '_' . $action);
    $file = $cacheDir . '/' . $key . '.json';
    
    $now = time();
    
    // Если файл существует, читаем данные
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        
        // Если окно истекло, сбрасываем
        if ($now - $data['start_time'] > $window) {
            $attempts = 0;
        } else {
            $attempts = $data['attempts'];
        }
    } else {
        $attempts = 0;
    }
    
    // Проверяем лимит
    if ($attempts >= $maxAttempts) {
        $data = $data ?? ['start_time' => $now];
        $retryAfter = $window - ($now - $data['start_time']);
        
        return [
            'allowed' => false,
            'remaining' => 0,
            'retry_after' => max(0, $retryAfter)
        ];
    }
    
    // Увеличиваем счётчик
    $attempts++;
    
    // Сохраняем данные
    $saveData = [
        'start_time' => $attempts === 1 ? $now : ($data['start_time'] ?? $now),
        'attempts' => $attempts
    ];
    file_put_contents($file, json_encode($saveData), LOCK_EX);
    
    return [
        'allowed' => true,
        'remaining' => $maxAttempts - $attempts,
        'retry_after' => 0
    ];
}
