<?php
// save_user.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной session_start() отсутствует
$pdo = require __DIR__ . '/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id'])) {
        http_response_code(400); // [ИСПРАВЛЕНО] Корректный код для ошибок валидации входных данных
        throw new Exception('Отсутствует Telegram ID');
    }

    $telegram_id = (int)$input['id'];
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $telegram_username = !empty($input['username']) ? substr(trim($input['username']), 0, 32) : null;
    $phone = !empty($input['phone']) ? preg_replace('/[^0-9+]/', '', $input['phone']) : null;
    $email = null;
    $city = 'Красноярск';
    $avatar_url = null;

    if (empty($first_name)) {
        $first_name = 'Пользователь';
    }

    $stmt = $pdo->prepare("SELECT id FROM `users` WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Пользователь уже существует. Обновляем данные активности и профиля
        $sql = "
            UPDATE `users` SET
                telegram_username = ?,
                first_name = ?,
                last_name = ?,
                phone = ?,
                city = ?,
                last_active_at = NOW(),
                updated_at = NOW()
            WHERE telegram_id = ?
        ";
        $pdo->prepare($sql)->execute([
            $telegram_username, 
            $first_name, 
            $last_name, 
            $phone, 
            $city, 
            $telegram_id
        ]);

        // [ОПТИМИЗАЦИЯ] Принудительное приведение ID к int для стабильности на клиенте
        echo json_encode([
            'success' => true, 
            'created' => false, 
            'user_id' => (int)$existing['id']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Пользователь не найден. Создаём нового участника системы
        try {
            $sql = "
                INSERT INTO `users` (
                    telegram_id, telegram_username, first_name, last_name, phone, email, 
                    city, avatar_url, role, is_active, is_blocked, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 'user', TRUE, FALSE, NOW(), NOW()
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $telegram_id, 
                $telegram_username, 
                $first_name, 
                $last_name, 
                $phone, 
                $email, 
                $city, 
                $avatar_url
            ]);
            $new_id = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true, 
                'created' => true, 
                'user_id' => $new_id
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            throw $e; // Перенаправляем ошибку записи в общий catch
        }
    }

} catch (Exception $e) {
    // Если статус ответа не был перехвачен и изменен ранее (например, на 400), выставляем 500
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
