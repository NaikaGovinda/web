<?php
// check_auth.php
// [БЕЗОПАСНОСТЬ] Проверяет сессию ИЛИ auth_token
header('Content-Type: application/json; charset=utf8mb4');

// 🔥 ИСПРАВЛЕНО: Сначала подключаем db.php.
// Убрали session_start() со строки 3. Теперь ваша сессия на 30 дней 
// автоматически настраивается и стартует внутри db.php!
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/auth_tokens.php';

// Проверяем аутентификацию (сессия ИЛИ токен)
$authenticated = false;
$userId = null;

// 1. Проверяем сессию
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        $authenticated = true;
        $userId = (int)$user['id'];
    } else {
        // Пользователь удалён из БД — уничтожаем сессию
        session_unset();
        session_destroy();
    }
}

// 2. Если нет сессии, проверяем токен (для API запросов из WebView)
if (!$authenticated) {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
    if ($token) {
        $validatedUserId = validateAuthToken($pdo, $token);
        if ($validatedUserId) {
            $authenticated = true;
            $userId = $validatedUserId;
        }
    }
}

if ($authenticated) {
    // [БЕЗОПАСНОСТЬ] Генерируем новый токен для WebView, если его нет
    $authToken = null;
    if (!isset($_SESSION['user_id'])) {
        // Это API запрос с токеном, а не сессия
        $authToken = null; // Токен уже есть у клиента
    }
    
    echo json_encode([
        'is_authenticated' => true,
        'user_id' => $userId,
        'auth_token' => $authToken  // Возвращаем токен только при первом входе
    ]);
} else {
    echo json_encode(['is_authenticated' => false]);
}
