<?php
// verify_email.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по нашему строгому стандарту, ручной session_start() удален
$pdo = require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if (strlen($code) !== 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Код должен быть 6-значным'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Ищем код в базе с ограничением 10 минут
$stmt = $pdo->prepare("SELECT email FROM email_verification_codes WHERE code = ? AND created_at > NOW() - INTERVAL 10 MINUTE");
$stmt->execute([$code]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(400); // [ИСПРАВЛЕНО] Корректный HTTP-статус для ошибок валидации кода
    echo json_encode(['success' => false, 'error' => 'Неверный или устаревший код'], JSON_UNESCAPED_UNICODE);
    exit;
}

$email = $record['email'];

// 2. Обновляем статус подтверждения пользователя
$stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
$stmt->execute([$email]);

// 3. Достаем ID и другие данные пользователя для авторизации
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

// 4. Удаляем использованный код
$stmt = $pdo->prepare("DELETE FROM email_verification_codes WHERE code = ?");
$stmt->execute([$code]);

// 5. ГЛАВНОЕ: Устанавливаем сессию авторизации (сессия уже активна благодаря db.php)
if ($user) {
    $_SESSION['user_id'] = (int)$user['id']; // [ОПТИМИЗАЦИЯ] Строгое приведение к int по техпаспорту
    $_SESSION['user_email'] = $email;
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['logged_in'] = true; 
}

// Очищаем временную сессию регистрации
unset($_SESSION['pending_email']);

// Возвращаем успех в чистой кириллице
echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
