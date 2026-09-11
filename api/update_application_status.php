<?php
// update_application_status.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Подключаем автозагрузку библиотек вендоров и FCM под ядром
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/send_fcm.php';

// Теперь проверка авторизации отработает корректно
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $app_id = (int)($input['application_id'] ?? 0);
    $status = $input['status'] ?? '';

    if (!$app_id || !in_array($status, ['approved', 'rejected'])) {
        throw new Exception('Неверные данные');
    }

    // Получаем заявку + группу
    $stmt = $pdo->prepare("
        SELECT a.id, a.user_id, a.group_id, a.status, g.name AS group_name
        FROM applications a
        JOIN `groups` g ON a.group_id = g.id
        WHERE a.id = ? AND a.status = 'pending'
    ");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch();

    if (!$app) {
        throw new Exception('Заявка не найдена или уже обработана');
    }

    // Проверяем, что текущий пользователь — лидер группы (через group_leaders)
    $stmtLeader = $pdo->prepare("
        SELECT 1 FROM group_leaders WHERE group_id = ? AND user_id = ?
    ");
    $stmtLeader->execute([$app['group_id'], (int)$_SESSION['user_id']]);

    if (!$stmtLeader->fetch()) {
        throw new Exception('Доступ запрещён: вы не лидер этой группы');
    }

    // Обновляем статус в базе данных
    $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->execute([$status, $app_id]);

    // Сохраняем уведомление в БД
    try {
        $notifTitle = $status === 'approved' ? 'Заявка одобрена! ✅' : 'Заявка отклонена ❌';
        $notifMessage = $status === 'approved' 
            ? "Ваша заявка в группу «{$app['group_name']}» одобрена!" 
            : "Ваша заявка в группу «{$app['group_name']}» отклонена.";
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, group_id, target_page, target_params, `read`) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $targetParams = json_encode(['group_id' => $app['group_id']], JSON_UNESCAPED_UNICODE);
        $notifStmt->execute([$app['user_id'], $status === 'approved' ? 'application_approved' : 'application_rejected', $notifTitle, $notifMessage, $app['group_id'], 'profile.html', $targetParams]);
    } catch (Exception $e) {
        // Таблица notifications может ещё не существовать
        error_log("Ошибка сохранения уведомления: " . $e->getMessage());
    }

    // Получаем Email и Имя участника, чью заявку мы только что обработали
    $stmtUser = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
    $stmtUser->execute([$app['user_id']]);
    $applicant = $stmtUser->fetch();

    // =========================================================================
    // БЛОК ОТПРАВКИ EMAIL-УВЕДОМЛЕНИЯ УЧАСТНИКУ (STARTTLS 587)
    // =========================================================================
    if ($applicant && !empty($applicant['email'])) {
        $to = $applicant['email'];
        $applicantName = $applicant['first_name'] ?? 'участник';

        if ($status === 'approved') {
            $subject = "Ваша заявка в группу «{$app['group_name']}» одобрена! ✅";
            $emailBody = "Харе Кришна, {$applicantName}!\n\n" .
                         "Рады сообщить, что лидер одобрил вашу заявку на вступление в группу «{$app['group_name']}».\n\n" .
                         "Теперь вы являетесь официальным участником этой группы. Вы можете зайти в свой Профиль на сайте или в мобильном приложении, чтобы следить за расписанием и ближайшими духовными событиями.\n\n" .
                         "Ссылка на сайт: https://namahata.ru";
        } else {
            $subject = "Статус заявки в группу ❌ «{$app['group_name']}»";
            $emailBody = "Харе Кришна, {$applicantName}!\n\n" .
                         "Ваша заявка на вступление в духовную группу «{$app['group_name']}» была отклонена лидером.\n\n" .
                         "Вы можете выбрать любую другую доступную группу на нашем сайте.\n\n" .
                         "Ссылка на сайт: https://namahata.ru";
        }

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
            error_log("Ошибка PHPMailer при ответе кандидату {$to}: " . $mail->ErrorInfo);
        }
    }

    // =========================================================================
    // УВЕДОМЛЕНИЕ УЧАСТНИКУ ЧЕРЕЗ FCM PUSH
    // =========================================================================
    try {
        // Получаем токен участника
        $stmtToken = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id = ?");
        $stmtToken->execute([$app['user_id']]);
        $tokens = $stmtToken->fetchAll(PDO::FETCH_COLUMN);

        // [ИСПРАВЛЕНО ШАГ 2]: ДИАГНОСТИКА И СИНХРОНИЗАЦИЯ С KOTLIN РОУТИНГОМ ЧЕРЕЗ ПЛЮСЫ
        if (!empty($tokens)) {
            $title = $status === 'approved' ? 'Заявка одобрена! ✅' : 'Статус заявки ❌';
            $body = $status === 'approved' 
                ? "Поздравляем! Вас приняли в духовную группу «{$app['group_name']}»" 
                : "Ваша заявка в группу «{$app['group_name']}» была отклонена лидером";

            // [ТОЧЕЧНО]: Пакуем заголовки прямо внутрь data, чтобы MyFirebaseMessagingService сам нарисовал шторку
            $data = [
                'type' => 'application_status',
                'group_id' => (string)$app['group_id'],
                'status' => $status,
                'action' => $status === 'approved' ? 'view_group_members' : 'view_application_rejected',
                'title' => $title,
                'body' => $body
            ];

            // Вызываем нашу Keep-Alive функцию отправки пушей
            sendFcmMessages($tokens, $title, $body, $data);
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Push error: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
