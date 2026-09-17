<?php
// update_profile.php
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$user_id = requireAuth($pdo);

// Логирование для отладки
error_log("=== ОТЛАДКА ЗАГРУЗКИ АВАТАРКИ ===");
error_log("ID пользователя: " . $user_id);
error_log("Всего прилетело в ПОСТ: " . print_r($_POST, true));
error_log("Всего прилетело файлов: " . print_r($_FILES, true));

try {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $isAvatarUpload = isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK;

    if (!$isAvatarUpload) {
        if (empty($firstName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Поле Имя обязательно для заполнения'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $avatarUrl = null;

    if ($isAvatarUpload) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];

        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExtension, $allowedExtensions)) {
            if ($fileSize <= 5 * 1024 * 1024) {
                // Удаление старой аватарки
                try {
                    $oldAvatarStmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
                    $oldAvatarStmt->execute([$user_id]);
                    $userData = $oldAvatarStmt->fetch();

                    if ($userData && !empty($userData['avatar_url'])) {
                        $oldAvatarUrl = $userData['avatar_url'];
                        $oldFilePath = $config['paths']['root_dir'] . $oldAvatarUrl;

                        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }
                } catch (\Exception $ex) {
                    error_log("Не удалось удалить старый аватар: " . $ex->getMessage());
                }

                $newFileName = 'user_' . $user_id . '_' . time() . '.' . $fileExtension;
                $uploadFileDir = $config['paths']['avatar_upload_dir'];
                $dest_path = $uploadFileDir . $newFileName;

                if (compressAndResizeImage($fileTmpPath, $dest_path, 600, 75)) {
                    $avatarUrl = '/assets/avatars/' . $newFileName;
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Не удалось обработать и сжать изображение'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Файл слишком большой. Максимальный размер 5МБ'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Неверный формат файла. Разрешены только JPG, JPEG, PNG и GIF'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Формируем SQL-запрос
    if ($avatarUrl !== null && empty($firstName)) {
        $sql = "UPDATE users SET avatar_url = :avatar_url WHERE id = :id";
        $params = [
            'avatar_url' => $avatarUrl,
            'id' => $user_id
        ];
    } elseif ($avatarUrl !== null) {
        $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, city = :city, phone = :phone, avatar_url = :avatar_url WHERE id = :id";
        $params = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'city'       => $city,
            'phone'      => $phone,
            'avatar_url' => $avatarUrl,
            'id'         => $user_id
        ];
    } else {
        $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, city = :city, phone = :phone WHERE id = :id";
        $params = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'city'       => $city,
            'phone'      => $phone,
            'id'         => $user_id
        ];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success'    => true,
        'avatar_url' => $avatarUrl
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Системная ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function compressAndResizeImage($sourcePath, $destPath, $maxDimension = 800, $quality = 78) {
    // ... ваш код функции без изменений
}