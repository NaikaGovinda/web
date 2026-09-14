<?php
// auth_tokens.php
// Таблица для хранения токенов аутентификации
// Эту таблицу нужно создать в базе данных:
// 
// CREATE TABLE IF NOT EXISTS auth_tokens (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     user_id INT NOT NULL,
//     token_hash VARCHAR(64) NOT NULL,
//     ip_address VARCHAR(45),
//     user_agent VARCHAR(255),
//     expires_at DATETIME NOT NULL,
//     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//     INDEX idx_token_hash (token_hash),
//     INDEX idx_user_id (user_id),
//     INDEX idx_expires_at (expires_at),
//     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
// );

/**
 * Генерирует случайный токен и возвращает его + хеш
 * @return array ['token' => string, 'token_hash' => string]
 */
function generateAuthToken() {
    // Генерируем 32 байта случайных данных (256 бит)
    $randomBytes = random_bytes(32);
    $token = bin2hex($randomBytes); // 64 символа hex
    
    // Хешируем токен для хранения в БД
    $tokenHash = hash('sha256', $token);
    
    return [
        'token' => $token,
        'token_hash' => $tokenHash
    ];
}

/**
 * Создаёт новый токен для пользователя
 * @param PDO $pdo
 * @param int $userId
 * @param int $lifetimeSeconds (по умолчанию 30 дней)
 * @return string Токен (только при создании, больше не появится!)
 */
function createAuthToken($pdo, $userId, $lifetimeSeconds = 2592000) {
    $generated = generateAuthToken();
    $token = $generated['token'];
    $tokenHash = $generated['token_hash'];
    
    $expiresAt = date('Y-m-d H:i:s', time() + $lifetimeSeconds);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("
        INSERT INTO auth_tokens (user_id, token_hash, ip_address, user_agent, expires_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $tokenHash, $ipAddress, $userAgent, $expiresAt]);
    
    // Возвращаем ТОЛЬКО РАЗ — больше этот токен нигде не появится!
    return $token;
}

/**
 * Проверяет токен и возвращает user_id если валиден
 * @param PDO $pdo
 * @param string $token
 * @return int|null user_id или null если невалиден
 */
function validateAuthToken($pdo, $token) {
    $tokenHash = hash('sha256', $token);
    
    // Ищем токен в БД
    $stmt = $pdo->prepare("
        SELECT user_id, expires_at 
        FROM auth_tokens 
        WHERE token_hash = ? AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $authToken = $stmt->fetch();
    
    if (!$authToken) {
        return null; // Токен не найден или истёк
    }
    
    // Обновляем время жизни токена (опционально)
    $newExpiresAt = date('Y-m-d H:i:s', time() + 2592000); // +30 дней
    $updateStmt = $pdo->prepare("
        UPDATE auth_tokens SET expires_at = ? WHERE token_hash = ?
    ");
    $updateStmt->execute([$newExpiresAt, $tokenHash]);
    
    return (int)$authToken['user_id'];
}

/**
 * Удаляет все токены пользователя (logout)
 * @param PDO $pdo
 * @param int $userId
 */
function invalidateUserTokens($pdo, $userId) {
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
}

/**
 * Очищает истёкшие токены (запускать через cron)
 * @param PDO $pdo
 */
function cleanupExpiredTokens($pdo) {
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE expires_at < NOW()");
    $stmt->execute();
}
