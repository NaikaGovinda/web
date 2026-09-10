<?php
// forgot_password.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php перенесен на самый верх, ручной session_start() удален
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Подключаем автозагрузку библиотек
require_once __DIR__ . '/../vendor/autoload.php';

// Каждые сутки удаляем старые коды, чтобы очищать базу
$pdo->exec("DELETE FROM email_verification_codes WHERE created_at < NOW() - INTERVAL 1 DAY");

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный формат email']);
    exit;
}

try {
    // -------------------------------------------------------------------------
    // ЭТАП 1: ГЕНЕРАЦИЯ И ОТПРАВКА КОДА
    // -------------------------------------------------------------------------
    if ($action === 'send_code') {
        // Проверяем, существует ли активный пользователь с таким email
        $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Пользователь с таким Email не найден');
        }

        // [БЕЗОПАСНОСТЬ] Используем криптографически стойкий генератор чисел
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // [ИСПРАВЛЕНО] Исправлен баг с количеством параметров в execute (добавлен 4-й элемент)
        // [ИСПРАВЛЕНО] В SQL-запросе 3 знака ?, значит в execute должно быть 3 элемента
        $stmt = $pdo->prepare("INSERT INTO email_verification_codes (email, code, created_at)
                               VALUES (?, ?, NOW())
                               ON DUPLICATE KEY UPDATE code = ?, created_at = NOW()");
        $stmt->execute([$email, $code, $code]);

        // Формируем текст письма
        $subject = "Сброс пароля на сайте Нама-Хатта";
        $userName = $user['first_name'] ?? 'участник';
        $emailBody = "Харе Кришна, {$userName}!\n\nВы запросили восстановление пароля на сайте https://namahata.ru.\n\nКод подтверждения для сброса: {$code}\n\nЕсли вы не запрашивали смену пароля, просто проигнорируйте это письмо — ваш аккаунт в полной безопасности.";

        // Наш проверенный PHPMailer (STARTTLS 587)
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP(); 
            $mail->Host = $config['email']['host']; 
            $mail->SMTPAuth = true; 
            $mail->Username = $config['email']['username']; 
            $mail->Password = $config['email']['password']; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port = $config['email']['port']; 
            
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($config['email']['from'], $config['email']['from_name']);
            $mail->addAddress($email); 

            $mail->isHTML(false); 
            $mail->Subject = $subject;
            $mail->Body = $emailBody;

            $mail->send();
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $mailEx) {
            throw new Exception('Не удалось доставить письмо с кодом. Попробуйте позже.');
        }
    }

    // -------------------------------------------------------------------------
    // ЭТАП 2: СВЕРКА КОДА И ИЗМЕНЕНИЕ ПАРОЛЯ
    // -------------------------------------------------------------------------
    if ($action === 'reset_password') {
        $code = trim($input['code'] ?? '');
        $newPassword = $input['password'] ?? '';

        if (strlen($code) !== 6) throw new Exception('Код должен содержать строго 6 цифр');
        if (strlen($newPassword) < 6) throw new Exception('Пароль должен быть не менее 6 символов');

        // Проверяем наличие кода в таблице (ограничение 1 час)
        $stmt = $pdo->prepare("SELECT id FROM email_verification_codes WHERE email = ? AND code = ? AND created_at > NOW() - INTERVAL 1 HOUR");
        $stmt->execute([$email, $code]);

        if (!$stmt->fetch()) {
            throw new Exception('Неверный или просроченный код подтверждения');
        }

        // Хешируем новый пароль
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // [БЕЗОПАСНОСТЬ] Обновляем пароль только для активных пользователей
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ? AND is_active = 1");
        $stmt->execute([$passwordHash, $email]);

        // Удаляем использованный код из таблицы
        $stmtDelete = $pdo->prepare("DELETE FROM email_verification_codes WHERE email = ?");
        $stmtDelete->execute([$email]);

        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Неверное действие');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
