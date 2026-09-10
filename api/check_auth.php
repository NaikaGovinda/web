<?php
// check_auth.php
header('Content-Type: application/json; charset=utf8mb4');

// 🔥 ИСПРАВЛЕНО: Сначала подключаем db.php.
// Убрали session_start() со строки 3. Теперь ваша сессия на 30 дней 
// автоматически настраивается и стартует внутри db.php!
$pdo = require __DIR__ . '/db.php';

// Теперь сессия точно запущена, и мы можем безопасно проверять user_id
if (isset($_SESSION['user_id'])) {
    // Проверяем, существует ли пользователь в БД
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode([
            'is_authenticated' => true,
            'user_id' => $_SESSION['user_id']
        ]);
    } else {
        // Пользователь удалён из БД — уничтожаем сессию
        session_unset();
        session_destroy();
        echo json_encode(['is_authenticated' => false]);
    }
} else {
    echo json_encode(['is_authenticated' => false]);
}
