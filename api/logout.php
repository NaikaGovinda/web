<?php
// logout.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';

// 1. Очищаем все переменные сессии в памяти PHP
$_SESSION = [];

// 2. Стираем куки сессии (PHPSESSID) в браузере пользователя
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Полностью уничтожаем саму сессию на сервере
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// [ИСПРАВЛЕНО] Добавлен флаг унификации вывода JSON
echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
exit;
