<?php
// get_user_theme.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху. Теперь сессия гарантированно инициализирована
$pdo = require __DIR__ . '/db.php';

// Если пользователь не авторизован — возвращаем тему по умолчанию
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => true,
        'theme' => ['background_type' => 'color', 'background_value' => '#f5f0e6']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];

    // Проверяем, что пользователь существует (опционально)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Пользователь не найден');
    }

    // Получаем тему
    $stmt = $pdo->prepare("SELECT background_type, background_value FROM user_themes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $theme = $stmt->fetch();

    if (!$theme) {
        $theme = ['background_type' => 'color', 'background_value' => '#f5f0e6'];
    }

    echo json_encode(['success' => true, 'theme' => $theme], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Даже при ошибке — возвращаем тему по умолчанию (чтобы интерфейс не ломался)
    echo json_encode([
        'success' => true,
        'theme' => ['background_type' => 'color', 'background_value' => '#f5f0e6']
    ], JSON_UNESCAPED_UNICODE);
}
