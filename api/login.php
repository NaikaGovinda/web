<?php
// login.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручная настройка cookie и сессий удалена
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/rate_limiter.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email и пароль обязательны'], JSON_UNESCAPED_UNICODE);
    exit;
}

// [БЕЗОПАСНОСТЬ] Rate limiting для защиты от brute force
$rateLimit = checkRateLimit('login', 5, 900); // 5 попыток в 15 минут
if (!$rateLimit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'error' => 'Слишком много попыток. Попробуйте через ' . $rateLimit['retry_after'] . ' секунд',
        'retry_after' => $rateLimit['retry_after']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Ищем пользователя
    $stmt = $pdo->prepare("SELECT id, first_name, password_hash, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // [ИСПРАВЛЕНО] Корректный HTTP-статус 401 для ошибок аутентификации
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Неверный email или пароль'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!(int)$user['is_verified']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Email не подтверждён. Проверьте почту.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Неверный email или пароль'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Устанавливаем данные сессии, которая уже запущена внутри db.php
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['first_name'];

    // [АРХИТЕКТУРА] Регенерацию ID сессии оставляем, так как это авторизация, но оборачиваем в проверку
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Системная ошибка сервера'], JSON_UNESCAPED_UNICODE);
}
