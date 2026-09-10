<?php
// verify_code.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по нашему строгому возвращающему стандарту
$pdo = require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверные данные'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем код с учетом ограничения по времени жизни (строго в пределах 1 часа)
$stmt = $pdo->prepare("
    SELECT code 
    FROM email_verification_codes 
    WHERE email = ? AND created_at > NOW() - INTERVAL 1 HOUR
");
$stmt->execute([$email]);
$row = $stmt->fetch();

if (!$row || $row['code'] !== $code) {
    http_response_code(400); // [ИСПРАВЛЕНО] Корректный HTTP-статус для ошибок валидации кода
    echo json_encode(['success' => false, 'error' => 'Неверный или устаревший код подтверждения'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подтверждаем пользователя
$stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
$stmt->execute([$email]);

// Удаляем использованный код
$stmt = $pdo->prepare("DELETE FROM email_verification_codes WHERE email = ?");
$stmt->execute([$email]);

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
