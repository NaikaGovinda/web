<?php
// load_env.php
// Загрузка переменных окружения из .env файла

/**
 * Загружает переменные из .env файла
 * @return array Массив переменных окружения
 */
function loadEnv($path = null) {
    if (!file_exists($path ?? __DIR__ . '/../.env')) {
        return [];
    }
    
    if (!$path) {
        $path = __DIR__ . '/../.env';
    }
    
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Пропускаем комментарии
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Парсим ключ=значение
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Удаляем кавычки если есть
        if ((strpos($value, '"') === 0 && strpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
        }
        
        // Устанавливаем переменную окружения
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $env[$key] = $value;
    }
    
    return $env;
}

/**
 * Получает переменную окружения с значением по умолчанию
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    
    // Возвращаем типизированное значение
    switch (strtolower($value)) {
        case 'true':
            return true;
        case 'false':
            return false;
        case 'null':
            return null;
        case '':
            return '';
        default:
            return $value;
    }
}
