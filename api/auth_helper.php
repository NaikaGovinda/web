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

    // 2. Проверяем токен из заголовка X-Auth-Token
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
    
    // 3. Fallback: токен из query параметра (для FormData запросов через android.js)
    if (!$token) {
        $token = $_GET['auth_token'] ?? null;
    }
    
    if ($token) {
        try {
            require_once __DIR__ . '/auth_tokens.php';
            $validatedUserId = validateAuthToken($pdo, $token);
            if ($validatedUserId) {
                return $validatedUserId;
            }
        } catch (Exception $e) {
            // Таблица auth_tokens может не существовать
            error_log('Auth token validation error: ' . $e->getMessage());
        }
    }

    // 4. Fallback: проверяем X-User-Id header
    $userId = $_SERVER['HTTP_X_USER_ID'] ?? null;
    
    // 5. Fallback: проверяем user_id из query параметра
    if (!$userId) {
        $userId = $_GET['user_id'] ?? null;
    }
    
    if ($userId) {
        $userId = (int)$userId;
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                return $userId;
            }
        } catch (Exception $e) {
            error_log('X-User-Id validation error: ' . $e->getMessage());
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