<?php
// submit_application.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Подключаем автозагрузку библиотек вендоров под ядром
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/send_fcm.php';

// Теперь проверка авторизации отработает корректно
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется войти в аккаунт'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Неверный формат данных');
    }

    $user_id = (int)$_SESSION['user_id'];
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
    // Сохраняем заявку в базу данных
    $stmt = $pdo->prepare("INSERT INTO `applications` (user_id, group_id, message, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $group_id, $message]);

    // Получаем ID и данные всех лидеров этой группы для рассылки через group_leaders
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

    // =========================================================================
    // БЛОК ОТПРАВКИ EMAIL-УВЕДОМЛЕНИЙ ЛИДЕРАМ ГРУППЫ (STARTTLS 587)
    // =========================================================================
    if (!empty($leaders)) {
        $subject = "Новая заявка в группу 📩 «{$group['name']}»";

        foreach ($leaders as $leader) {
            $to = $leader['email'];
            if (empty($to)) continue;

            $leaderName = $leader['first_name'] ?? 'Лидер';

            $emailBody = "Харе Кришна, {$leaderName}!\n\n" .
                         "В вашу группу «{$group['name']}» поступила новая заявка на вступление.\n\n" .
                         " Имя кандидата: {$applicantName}\n" .
                         " Сообщение: «{$message}»\n\n" .
                         "Пожалуйста, зайдите на сайт в личный кабинет (раздел Профиль) или мобильное приложение, чтобы рассмотреть и одобрить эту заявку.\n\n" .
                         "Ссылка на сайт: https://namahata.ru";

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

    // =========================================================================
    // УВЕДОМЛЕНИЕ ВСЕХ ЛИДЕРОВ ГРУППЫ ЧЕРЕЗ FCM PUSH
    // =========================================================================
    // =========================================================================
	// [ИСПРАВЛЕНО]: АВТОНОМНЫЕ ДВУХСТОРОННИЕ ПУШИ С ЗАЩИТОЙ ОТ ПУСТЫХ ТОКЕНОВ
	// =========================================================================
	try {
		// 1. ОТПРАВЛЯЕМ УВЕДОМЛЕНИЕ ЛИДЕРАМ ГРУППЫ (У кого ЕСТЬ Android)
		if (!empty($leaderIds)) {
			$title = 'Новая заявка! 📬';
			$body = $applicantName . " хочет вступить в группу «" . $group['name'] . "»";

			$data = [
				'action' => 'view_group_applications', 
				'group_id' => (string)$group_id, 
				'title' => $title, 
				'body' => $body
			];

			// Достаем токены только тех лидеров, у кого они физически есть
			$placeholders = str_repeat('?,', count($leaderIds) - 1) . '?';
			$stmtTokens = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id IN ($placeholders) AND token IS NOT NULL AND token != ''");
			$stmtTokens->execute($leaderIds);
			$tokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN);

			// Если у Лидеров есть хоть один рабочий токен — принудительно шлем пуш!
			if (!empty($tokens)) {
				sendFcmMessages($tokens, $title, $body, $data);
				file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Пуш лидеру (ID: " . implode(',', $leaderIds) . ") успешно передан в очередь отправки\n", FILE_APPEND);
			}
		}

		// 2. ОТПРАВЛЯЕМ ПОДТВЕРЖДЕНИЕ КАНДИДАТУ (Только если он зашел через Android и у него есть токен!)
		if ($user_id > 0) {
			$stmtUserToken = $pdo->prepare("SELECT token FROM user_fcm_tokens WHERE user_id = ? AND token IS NOT NULL AND token != ''");
			$stmtUserToken->execute([$user_id]);
			$userTokens = $stmtUserToken->fetchAll(PDO::FETCH_COLUMN);

			// Если кандидат зашел с сайта и токена нет — PHP просто пропустит этот блок без ошибок!
			if (!empty($userTokens)) {
				$userTitle = '⏳ Заявка отправлена';
				$userBody = 'Ваш запрос на вступление в группу «' . $group['name'] . '» успешно передан лидеру.';
				
				$userData = [
					'action' => 'view_group_members',
					'group_id' => (string)$group_id,
					'title' => $userTitle,
					'body' => $userBody
				];

				sendFcmMessages($userTokens, $userTitle, $userBody, $userData);
			} else {
				// Тихонько пишем в лог для отладки, что пуш кандидату пропущен, так как он сидит через браузер
				file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Кандидат (ID: $user_id) без Android. Пуш пропущен, отправлено только Email.\n", FILE_APPEND);
			}
		}

	} catch (Exception $e) {
		file_put_contents(__DIR__ . '/fcm_debug.log', "[" . date('Y-m-d H:i:s') . "] Ошибка в блоке распределения пушей: " . $e->getMessage() . "\n", FILE_APPEND);
	}

    // =========================================================================
    // УВЕДОМЛЕНИЕ КАНДИДАТУ ЧЕРЕЗ WEB PUSH
    // =========================================================================
    try {
        require_once __DIR__ . '/send_web_push.php';
        
        $webUserTitle = '⏳ Заявка отправлена';
        $webUserBody = 'Ваш запрос на вступление в группу «' . $group['name'] . '» успешно передан лидеру.';
        
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
