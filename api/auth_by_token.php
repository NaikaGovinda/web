<?php
// auth_by_token.php
// Аутентификация по токену (пароль не передаётся в открытом виде)
// 
// Использование:
// 1. Клиент генерирует токен (random)
// 2. Отправляет POST /api/auth_by_token.php с {"token": "...", "user_id": 123}
// 3. Сервер проверяет токен и создаёт сессию

header('Content-Type: application/json; charset=utf8mb4');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/auth_tokens.php';

$pdo = require __DIR__ . '/db.php';

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не разрешён'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$userId = (int)($input['user_id'] ?? 0);

// Валидация
if (empty($token) || $userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Необходимо указать token и user_id'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем токен
$validatedUserId = validateAuthToken($pdo, $token);

if (!$validatedUserId) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Недействительный или истёкший токен'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем, что пользователь существует и верифицирован
$stmt = $pdo->prepare("SELECT id, first_name, is_verified FROM users WHERE id = ?");
$stmt->execute([$validatedUserId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Пользователь не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int)$user['is_verified'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Email не подтверждён'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Создаём сессию
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['first_name'];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

echo json_encode([
    'success' => true,
    'user_id' => (int)$user['id'],
    'user_name' => $user['first_name']
], JSON_UNESCAPED_UNICODE);
