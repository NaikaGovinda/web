<?php
// email_helper.php

// 1. Правила импорта классов библиотеки PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. Подключаем автозагрузчик Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 🔥 ИСПРАВЛЕНО: Безопасная подгрузка конфигурации.
// Если массив $config уже был загружен ранее через db.php, мы его не перезаписываем.
if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/config.php';
}

/**
 * Отправка письма с кодом подтверждения при регистрации
 */
function sendVerificationEmail($to, $code) {
    global $config; // Даем функции доступ к общему массиву настроек проекта

    $subject = "Подтверждение email — Нама-Хатта";
    $message = "Ваш код подтверждения: {$code}\n\nЕсли вы не регистрировались — проигнорируйте это письмо.";

    $mail = new PHPMailer(true);
    try {
        // Настройки SMTP сервера Google из вашего единого config.php
        $mail->isSMTP(); 
        $mail->Host = $config['email']['host']; 
        $mail->SMTPAuth = true; 
        $mail->Username = $config['email']['username']; 
        $mail->Password = $config['email']['password']; 
        
        // Рабочая копия настроек (Порт 587 + STARTTLS)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port = $config['email']['port']; 
        
        // Включаем проверку SSL только в production
        $appEnv = getenv('APP_ENV') ?: 'production';
        if ($appEnv === 'production') {
            $mail->SMTPOptions = null; // Используем стандартную проверку SSL
        } else {
            // Отключаем строгую проверку SSL только в development (XAMPP)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }

        $mail->CharSet = 'UTF-8';

        // Настройки адресов отправителя и получателя
        $mail->setFrom($config['email']['from'], $config['email']['from_name']);
        $mail->addAddress($to); 

        $mail->isHTML(false); // Отправляем как чистый, легкий текст
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return true; // Письмо успешно улетело в сеть
    } catch (Exception $e) {
        // Записываем технический лог ошибки в Apache/XAMPP, если отправка сорвалась
        error_log("Ошибка PHPMailer при регистрации для {$to}: " . $mail->ErrorInfo);
        return false;
    }
}
?>
