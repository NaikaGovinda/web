<?php
// save_user_theme.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху. Сессия гарантированно инициализирована
$pdo = require __DIR__ . '/db.php';

// Теперь проверка авторизации отработает корректно
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['background_type'] ?? 'color';
    $value = $input['background_value'] ?? '#f5f0e6';

    if (!in_array($type, ['color', 'image'])) {
        throw new Exception('Неверный тип фона');
    }

    // Защита от произвольных путей (Local File Inclusion)
    if ($type === 'image') {
        $allowedImages = ['stars.jpg', 'beach.jpg', 'flowers.jpg', 'mountains.jpg'];
        if (!in_array($value, $allowedImages)) {
            throw new Exception('Недопустимое изображение');
        }
    }

    $user_id = (int)$_SESSION['user_id'];

    // Проверяем, что пользователь существует
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Пользователь не найден');
    }

    // [ИСПРАВЛЕНО] Убран устаревший синтаксис VALUES(), запрос переписан на безопасные именованные параметры
    $stmt = $pdo->prepare("
        INSERT INTO user_themes (user_id, background_type, background_value)
        VALUES (:user_id, :background_type, :background_value)
        ON DUPLICATE KEY UPDATE
            background_type = :update_type,
            background_value = :update_value,
            updated_at = NOW()
    ");
    
    $stmt->execute([
        'user_id'          => $user_id,
        'background_type'  => $type,
        'background_value' => $value,
        'update_type'      => $type,
        'update_value'     => $value
    ]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
