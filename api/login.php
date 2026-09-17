<?php
// login.php
// [БЕЗОПАСНОСТЬ] Поддерживает два режима:
// 1. JSON API (для старого login.html) - возвращает JSON
// 2. Стандартная форма (для secure/login.html) - использует сессию и редирект

header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручная настройка cookie и сессий удалена
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/rate_limiter.php';

// Определяем тип запроса
$isJsonRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isJsonContentType = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

$input = json_decode(file_get_contents('php://input'), true);

// Поддерживаем и 'email', и 'username' для обратной совместимости
$email = trim($input['email'] ?? $input['username'] ?? $_POST['email'] ?? $_POST['username'] ?? '');
$password = $input['password'] ?? $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
    if ($isJsonRequest || $isJsonContentType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email и пароль обязательны'], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        header('Location: ../secure/login.html?error=' . urlencode('Email и пароль обязательны'));
        exit;
    }
}

// [БЕЗОПАСНОСТЬ] Rate limiting для защиты от brute force
$rateLimit = checkRateLimit('login', 5, 900); // 5 попыток в 15 минут
if (!$rateLimit['allowed']) {
    if ($isJsonRequest || $isJsonContentType) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'error' => 'Слишком много попыток. Попробуйте через ' . $rateLimit['retry_after'] . ' секунд',
            'retry_after' => $rateLimit['retry_after']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ../secure/login.html?error=' . urlencode('Слишком много попыток'));
    }
    exit;
}

try {
    // Ищем пользователя
    $stmt = $pdo->prepare("SELECT id, first_name, password_hash, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // [ИСПРАВЛЕНО] Корректный HTTP-статус 401 для ошибок аутентификации
    if (!$user) {
        if ($isJsonRequest || $isJsonContentType) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Неверный email или пароль'], JSON_UNESCAPED_UNICODE);
        } else {
            header('Location: ../secure/login.html?error=' . urlencode('Неверный email или пароль'));
        }
        exit;
    }

    if (!(int)$user['is_verified']) {
        if ($isJsonRequest || $isJsonContentType) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Email не подтверждён. Проверьте почту.'], JSON_UNESCAPED_UNICODE);
        } else {
            header('Location: ../secure/login.html?error=' . urlencode('Email не подтверждён'));
        }
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        if ($isJsonRequest || $isJsonContentType) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Неверный email или пароль'], JSON_UNESCAPED_UNICODE);
        } else {
            header('Location: ../secure/login.html?error=' . urlencode('Неверный email или пароль'));
        }
        exit;
    }

    // Устанавливаем данные сессии, которая уже запущена внутри db.php
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['first_name'];

    // [АРХИТЕКТУРА] Регенерацию ID сессии оставляем, так как это авторизация, но оборачиваем в проверку
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // [БЕЗОПАСНОСТЬ] Для JSON API возвращаем токен
    // Создаём auth_token для будущего использования
    $authToken = null;
    if ($isJsonRequest || $isJsonContentType) {
        // Создаём токен для API
        require_once __DIR__ . '/auth_tokens.php';
        $authToken = createAuthToken($pdo, (int)$user['id'], 2592000); // 30 дней
        
        echo json_encode([
            'success' => true,
            'user_id' => (int)$user['id'],
            'user_name' => $user['first_name'],
            'auth_token' => $authToken
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Для стандартных форм - редирект на профиль (без передачи данных через JS)
        header('Location: ../profile.html');
        exit;
    }

} catch (Exception $e) {
    if ($isJsonRequest || $isJsonContentType) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Системная ошибка сервера'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ../secure/login.html?error=' . urlencode('Системная ошибка'));
        exit;
    }
}
