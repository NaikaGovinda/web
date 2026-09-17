<?php
// submit_application.php
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/send_fcm.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$user_id = requireAuth($pdo);

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Неверный формат данных');
    }

    $group_id = (int)($input['group_id'] ?? 0);
    $message = trim($input['message'] ?? '');

    if (!$group_id) throw new Exception('Требуется group_id');
    if (strlen($message) < 5) throw new Exception('Сообщение должно содержать минимум 5 символов');
    if (strlen($message) > 500) throw new Exception('Сообщение слишком длинное (макс. 500 символов)');

    // Проверяем пользователя
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) throw new Exception('Пользователь не найден');

    // Проверяем группу
    $stmt = $pdo->prepare("SELECT id, name FROM `groups` WHERE id = ? AND status = 'active'");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    if (!$group) throw new Exception('Группа не найдена или неактивна');

    // Проверяем дубль заявки
    $stmt = $pdo->prepare("SELECT id FROM `applications` WHERE user_id = ? AND group_id = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$user_id, $group_id]);
    if ($stmt->fetch()) throw new Exception('Вы уже подавали заявку на эту группу');

    // Проверяем роль
    $stmt = $pdo->prepare("SELECT role, is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if ($user_data && $user_data['role'] === 'observer' && (int)$user_data['is_admin'] !== 1) {
        throw new Exception('Наблюдатели не могут подавать заявки на вступление');
    }

    // Сохраняем заявку
    $stmt = $pdo->prepare("INSERT INTO `applications` (user_id, group_id, message, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $group_id, $message]);

    // Получаем лидеров группы
    $stmtLeaders = $pdo->prepare("
        SELECT DISTINCT u.id, u.email, u.first_name
        FROM group_leaders gl
        JOIN users u ON gl.user_id = u.id
        WHERE gl.group_id = ? AND u.email IS NOT NULL AND u.email != ''
    ");
    $stmtLeaders->execute([$group_id]);
    $leaders = $stmtLeaders->fetchAll();

    $leaderIds = array_column($leaders, 'id');
    $applicantName = trim($user['first_name'] . ' ' . ($user['last_name'] ?? ''));

    // Email-уведомления лидерам
    if (!empty($leaders)) {
        $subject = "Новая заявка в группу 📩 «{$group['name']}»";

        foreach ($leaders as $leader) {
            $to = $leader['email'];
            if (empty($to)) continue;

            $leaderName = $leader['first_name'] ?? 'Лидер';

            $emailBody = "Харе Кришна, {$leaderName}!\n\n" .
                         "В вашу группу «{$group['name']}» поступила новая заявка.\n\n" .
                         "Имя: {$applicantName}\n" .
                         "Сообщение: «{$message}»\n\n" .
                         "Зайдите на сайт, чтобы рассмотреть заявку.\n\n" .
                         "Ссылка: https://namahata.ru";

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
                $mail->addAddress($to);
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body = $emailBody;
                $mail->send();
            } catch (Exception $e) {
                error_log("Ошибка PHPMailer для лидера {$to}: " . $mail->ErrorInfo);
            }
        }
    }

    // FCM Push лидерам и кандидату
    try {
        if (!empty($leaderIds)) {
            $title = 'Новая заявка! 📬';
            $body = $applicantName . " хочет вступить в группу «" . $group['name'] . "»";

            $data = [
                'action' => 'view_group_applications',
                'group_id' => (string)$group_id,
                'title' => $title,
                'body' => $body
            ];

            $placeholders = str_repeat('?,', count($leaderIds) - 1) . '?';
            $stmtTokens = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id IN ($placeholders) AND token IS NOT NULL AND token != ''");
            $stmtTokens->execute($leaderIds);
            $tokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($tokens)) {
                sendFcmMessages($tokens, $title, $body, $data);
            }
        }

        if ($user_id > 0) {
            $stmtUserToken = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id = ? AND token IS NOT NULL AND token != ''");
            $stmtUserToken->execute([$user_id]);
            $userTokens = $stmtUserToken->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($userTokens)) {
                $userTitle = '⏳ Заявка отправлена';
                $userBody = 'Ваш запрос в группу «' . $group['name'] . '» передан лидеру.';

                $userData = [
                    'action' => 'view_group_members',
                    'group_id' => (string)$group_id,
                    'title' => $userTitle,
                    'body' => $userBody
                ];

                sendFcmMessages($userTokens, $userTitle, $userBody, $userData);
            }
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Ошибка пушей: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    // Web Push кандидату
    try {
        require_once __DIR__ . '/send_web_push.php';

        $webUserTitle = '⏳ Заявка отправлена';
        $webUserBody = 'Ваш запрос в группу «' . $group['name'] . '» передан лидеру.';

        sendWebPushToUser($pdo, $user_id, $webUserTitle, $webUserBody, [
            'action' => 'view_group_members',
            'group_id' => (string)$group_id,
            'target_page' => 'group.html',
            'target_params' => json_encode(['id' => $group_id], JSON_UNESCAPED_UNICODE)
        ]);
    } catch (Exception $webPushEx) {
        error_log("Web push error in submit_application: " . $webPushEx->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Заявка успешно отправлена'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}