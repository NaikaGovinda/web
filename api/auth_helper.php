<?php
// auth_helper.php — универсальная проверка авторизации

/**
 * Получает user_id из сессии ИЛИ из токена X-Auth-Token
 * @param PDO $pdo
 * @return int|null
 */
function getAuthUserId($pdo) {
    // 1. Проверяем сессию
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    // 2. Проверяем токен из заголовка
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
    
    // 3. Fallback: токен из query параметра (для FormData запросов через android.js)
    if (!$token) {
        $token = $_GET['auth_token'] ?? null;
    }
    
    if ($token) {
        require_once __DIR__ . '/auth_tokens.php';
        $validatedUserId = validateAuthToken($pdo, $token);
        if ($validatedUserId) {
            return $validatedUserId;
        }
    }

    return null;
}

/**
 * Проверяет авторизацию и завершает выполнение с 403, если не авторизован
 * @param PDO $pdo
 * @return int user_id
 */
function requireAuth($pdo) {
    $user_id = getAuthUserId($pdo);
    if (!$user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $user_id;
}