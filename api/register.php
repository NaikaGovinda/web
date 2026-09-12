<?php
// register.php
header('Content-Type: application/json; charset=utf8mb4');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной session_start и ini_set удалены
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/email_helper.php';

// Удаляем коды, которым больше 24 часов, чтобы не захламлять базу
$pdo->exec("DELETE FROM email_verification_codes WHERE created_at < NOW() - INTERVAL 24 HOUR");

// Получаем данные из любого источника (POST или JSON)
$inputJSON = json_decode(file_get_contents('php://input'), true);

$first_name = trim($_POST['first_name'] ?? $inputJSON['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? $inputJSON['last_name'] ?? '');
$email = trim($_POST['email'] ?? $inputJSON['email'] ?? '');
$phone = trim($_POST['phone'] ?? $inputJSON['phone'] ?? '');
$password = $_POST['password'] ?? $inputJSON['password'] ?? '';

// Валидация
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный формат email: ' . $email], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Пароль должен быть не менее 6 символов'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($first_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Имя обязательно'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Проверка дубликата email
    $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($existingUser) {
        if ((int)$existingUser['is_verified'] === 1) {
            echo json_encode(['success' => false, 'error' => 'Email уже зарегистрирован'], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            // Обновляем данные неподтвержденного юзера
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, first_name = ?, last_name = ?, phone = ?, created_at = NOW() WHERE email = ?");
            $stmt->execute([$passwordHash, $first_name, $last_name, $phone, $email]);
        }
    } else {
        // Новый пользователь
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, is_verified, login_source, created_at) VALUES (?, ?, ?, ?, ?, 0, 'email', NOW())");
        $stmt->execute([$email, $passwordHash, $first_name, $last_name, $phone]);
    }

    // [БЕЗОПАСНОСТЬ] Используем криптографически стойкий генератор случайных чисел random_int
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("INSERT INTO email_verification_codes (email, code, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE code = ?, created_at = NOW()");
    $stmt->execute([$email, $code, $code]);

    // Отправка email
    if (sendVerificationEmail($email, $code)) {
        $_SESSION['pending_email'] = $email;
        
        // [БЕЗОПАСНОСТЬ] Регенерация сессии после регистрации
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Ошибка при отправке письма через sendVerificationEmail');
    }

} catch (Exception $e) {
    // В production режиме логируем ошибки на сервере
    error_log("Register error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера. Попробуйте позже.'], JSON_UNESCAPED_UNICODE);
}
