<?php
/**
 * API endpoint: Login/Register via VK OAuth
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function jsonResponse($data) {
    if (ob_get_level()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed']);
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = new PDO(
        "mysql:host=109.226.236.175;dbname=u3385428_namahatta_db;charset=utf8mb4",
        "NaikaGovinda",
        "qZ#9m!Xp4_WvK7DFksi83h%3h*",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => 'DB connection failed']);
}

if (!isset($input['access_token'])) {
    jsonResponse(['success' => false, 'error' => 'Missing access_token']);
}

$accessToken = $input['access_token'];
$idToken = $input['id_token'] ?? '';

$firstName = '';
$lastName = '';
$emailFromVk = '';
$avatarUrl = '';

// ==========================================================
// ЗАПРОС К VK ID API (POST + client_id)
// ==========================================================
try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://id.vk.ru/oauth2/user_info');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'access_token' => $accessToken,
        'client_id' => '54773774'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $userInfoResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($userInfoResponse && $httpCode === 200) {
        $userInfo = json_decode($userInfoResponse, true);

        if (isset($userInfo['user'])) {
            $vkUser = $userInfo['user'];
            $firstName = $vkUser['first_name'] ?? '';
            $lastName = $vkUser['last_name'] ?? '';
            $emailFromVk = $vkUser['email'] ?? '';
            $avatarUrl = $vkUser['avatar'] ?? '';
        }
    }
} catch (Exception $e) {
    // Игнорируем — пользователь всё равно войдёт
}

// Email из VK или из input
$email = !empty($emailFromVk) ? $emailFromVk : trim($input['email'] ?? '');

// ==========================================================
// ДЕКОДИРУЕМ id_token (получаем vk_id)
// ==========================================================
$vkId = null;

if ($idToken) {
    $parts = explode('.', $idToken);
    if (count($parts) === 3) {
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        if ($payload && is_array($payload)) {
            $vkId = $payload['sub'] ?? null;
        }
    }
}

if (!$vkId) {
    jsonResponse(['success' => false, 'error' => 'Failed to get user data']);
}

// ==========================================================
// СКАЧИВАЕМ АВАТАР ЛОКАЛЬНО (чтобы не зависеть от VK)
// ==========================================================
$localAvatarUrl = '';

if (!empty($avatarUrl)) {
    try {
        // Папка для аватаров
        $avatarDir = __DIR__ . '/../assets/avatars/';

        // Создаём папку, если нет
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }

        // Имя файла
        $avatarFileName = 'vk_' . $vkId . '_' . time() . '.jpg';
        $avatarLocalPath = $avatarDir . $avatarFileName;

        // Скачиваем изображение
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $avatarUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

        $imgContent = curl_exec($ch);
        $imgHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($imgContent && $imgHttpCode === 200 && strlen($imgContent) > 1000) {
            // Сохраняем файл
            if (file_put_contents($avatarLocalPath, $imgContent)) {
                $localAvatarUrl = '/assets/avatars/' . $avatarFileName;
            }
        }
    } catch (Exception $e) {
        // Если не удалось скачать — оставляем URL от VK
        $localAvatarUrl = $avatarUrl;
    }
}

// Если локальный аватар не получился — используем URL от VK
if (empty($localAvatarUrl) && !empty($avatarUrl)) {
    $localAvatarUrl = $avatarUrl;
}

// ==========================================================
// ПОИСК ИЛИ СОЗДАНИЕ ПОЛЬЗОВАТЕЛЯ
// ==========================================================
try {
    $user = null;

    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
    }

    if (!$user && !empty($vkId)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE vk_id = :vk_id LIMIT 1");
        $stmt->execute(['vk_id' => $vkId]);
        $user = $stmt->fetch();
    }

    $now = date('Y-m-d H:i:s');

    if (!$user) {
        // Создаём нового пользователя
        $emailValue = !empty($email) ? $email : null;
        $avatarValue = !empty($localAvatarUrl) ? $localAvatarUrl : null;

        $stmt = $pdo->prepare("
            INSERT INTO users (email, vk_id, first_name, last_name, avatar_url, auth_provider, login_source, created_at, updated_at)
            VALUES (:email, :vk_id, :first_name, :last_name, :avatar_url, 'vk', 'vk', :created_at, :updated_at)
        ");

        $stmt->execute([
            'email' => $emailValue,
            'vk_id' => $vkId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'avatar_url' => $avatarValue,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        $userId = $pdo->lastInsertId();
        $authToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $authToken);

        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");

        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt
        ]);

        jsonResponse([
            'success' => true,
            'user_id' => $userId,
            'token' => $authToken,
            'is_new' => true
        ]);

    } else {
        // Обновляем существующего
        $stmt = $pdo->prepare("
            UPDATE users
            SET first_name = :first_name,
                last_name = :last_name,
                avatar_url = COALESCE(NULLIF(:avatar_url, ''), avatar_url),
                vk_id = :vk_id,
                auth_provider = 'vk',
                login_source = 'vk',
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'avatar_url' => $localAvatarUrl,
            'vk_id' => $vkId,
            'updated_at' => $now,
            'id' => $user['id']
        ]);

        $authToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $authToken);

        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");

        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt->execute([
            'user_id' => $user['id'],
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt
        ]);

        jsonResponse([
            'success' => true,
            'user_id' => $user['id'],
            'token' => $authToken,
            'is_new' => false
        ]);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database operation failed']);
}