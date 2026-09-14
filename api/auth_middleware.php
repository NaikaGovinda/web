<?php
// auth_middleware.php
// Универсальный middleware для проверки аутентификации
// Поддерживает два режима: сессия и токен

require_once __DIR__ . '/auth_tokens.php';

/**
 * Проверяет аутентификацию (сессия ИЛИ токен)
 * @param PDO $pdo
 * @return array ['authenticated' => bool, 'user_id' => int|null, 'error' => string|null]
 */
function checkAuth($pdo) {
    // 1. Проверяем сессию (для веб-клиентов)
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            return [
                'authenticated' => true,
                'user_id' => (int)$user['id'],
                'error' => null
            ];
        }
    }
    
    // 2. Проверяем токен (для Android и API)
    // Токен приходит в заголовке X-Auth-Token (android.js interceptor)
    // НЕ читаем php://input здесь — это безвозвратно потребляет поток, и скрипт-вызыватель не сможет прочитать тело POST
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
    
    if ($token) {
        $userId = validateAuthToken($pdo, $token);
        if ($userId) {
            return [
                'authenticated' => true,
                'user_id' => $userId,
                'error' => null
            ];
        }
    }
    
    return [
        'authenticated' => false,
        'user_id' => null,
        'error' => 'Не авторизован'
    ];
}

/**
 * Требует аутентификации, возвращает 401 если нет
 * @param PDO $pdo
 * @return int user_id
 */
function requireAuth($pdo) {
    $auth = checkAuth($pdo);
    
    if (!$auth['authenticated']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => $auth['error']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    return $auth['user_id'];
}

/**
 * Опциональная аутентификация (не требует, но возвращает user_id если есть)
 * @param PDO $pdo
 * @return int|null
 */
function optionalAuth($pdo) {
    $auth = checkAuth($pdo);
    return $auth['authenticated'] ? $auth['user_id'] : null;
}
