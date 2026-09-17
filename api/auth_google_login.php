<?php
/**
 * API endpoint: Login/Register via Google
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'namahatta');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка подключения к базе данных']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['access_token']) || !isset($input['email'])) {
        throw new Exception('Недостаточно данных для входа');
    }

    $accessToken = $input['access_token'];
    $email = trim($input['email'] ?? '');
    
    // Получаем данные пользователя из Google
    $googleUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $accessToken;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $accessToken,
            'timeout' => 30
        ]
    ]);
    
    $googleResponse = @file_get_contents($googleUrl, false, $context);
    
    if ($googleResponse === false) {
        throw new Exception('Ошибка проверки токена Google');
    }
    
    $googleData = json_decode($googleResponse, true);
    
    if (!isset($googleData['email'])) {
        throw new Exception('Недействительный токен Google');
    }
    
    $googleUser = $googleData;
    $googleId = $googleUser['id'];
    $firstName = $googleUser['given_name'] ?? '';
    $lastName = $googleUser['family_name'] ?? '';
    
    // Проверяем, есть ли пользователь с таким Google ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = :google_id LIMIT 1");
    $stmt->execute(['google_id' => $googleId]);
    $user = $stmt->fetch();
    
    $generatedEmail = $email ?: $googleId . '@google.local';
    $now = date('Y-m-d H:i:s');
    
    if (!$user) {
        // Создаем нового пользователя
        $stmt = $pdo->prepare("
            INSERT INTO users (email, first_name, last_name, google_id, login_source, created_at, updated_at)
            VALUES (:email, :first_name, :last_name, :google_id, 'google', :created_at, :updated_at)
        ");
        
        $stmt->execute([
            'email' => $generatedEmail,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'google_id' => $googleId,
            'created_at' => $now,
            'updated_at' => $now
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Создаем токен аутентификации
        $authToken = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (user_id, token, created_at, expires_at)
            VALUES (:user_id, :token, :created_at, :expires_at)
        ");
        
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt->execute([
            'user_id' => $userId,
            'token' => $authToken,
            'created_at' => $now,
            'expires_at' => $expiresAt
        ]);
        
        echo json_encode([
            'success' => true,
            'user_id' => $userId,
            'token' => $authToken,
            'is_new' => true
        ]);
        
    } else {
        // Обновляем данные пользователя
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = :first_name, 
                last_name = :last_name, 
                email = :email,
                updated_at = :updated_at
            WHERE id = :id
        ");
        
        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $generatedEmail,
            'updated_at' => $now,
            'id' => $user['id']
        ]);
        
        // Создаем новый токен
        $authToken = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (user_id, token, created_at, expires_at)
            VALUES (:user_id, :token, :created_at, :expires_at)
        ");
        
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt->execute([
            'user_id' => $user['id'],
            'token' => $authToken,
            'created_at' => $now,
            'expires_at' => $expiresAt
        ]);
        
        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'token' => $authToken,
            'is_new' => false
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
