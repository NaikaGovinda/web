<?php
// resend_verification.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху по нашему строгому стандарту
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/email_helper.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный email'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Проверяем, существует ли неподтвержденный пользователь
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 0");
    $stmt->execute([$email]);

    if (!$stmt->fetch()) {
        http_response_code(400); // [ИСПРАВЛЕНО] Добавлен явный код ответа для ошибок валидации
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден или уже подтвержден'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // [БЕЗОПАСНОСТЬ] Используем криптографически стойкий генератор чисел random_int
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Сохранение кода
    $stmt = $pdo->prepare("
        INSERT INTO email_verification_codes (email, code, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE code = ?, created_at = NOW()
    ");
    $stmt->execute([$email, $code, $code]);

    // Отправка email
    if (sendVerificationEmail($email, $code)) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500); // [ИСПРАВЛЕНО] Добавлен код ответа для серверной ошибки отправки
        echo json_encode(['success' => false, 'error' => 'Не удалось отправить код'], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Системная ошибка сервера'], JSON_UNESCAPED_UNICODE);
}
