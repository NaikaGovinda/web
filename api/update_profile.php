<?php
// update_profile.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php и config.php подключены на самом верху. Сырой вывод ini_set('display_errors') удален
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php'; 

// Проверяем авторизацию пользователя по сессии с корректным HTTP-кодом
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Оставляем системное логирование для отладки кроппера и FormData
error_log("=== ОТЛАДКА ЗАГРУЗКИ АВАТАРКИ ===");
error_log("ID пользователя в сессии: " . $userId);
error_log("Всего прилетело в ПОСТ: " . print_r($_POST, true));
error_log("Всего прилетело файлов: " . print_r($_FILES, true));

try {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Узнаем, пришел ли файл аватарки
    $isAvatarUpload = isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK;

    if (!$isAvatarUpload) {
        // Проверяем текстовые поля ТОЛЬКО при обычном сохранении профиля
        if (empty($firstName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Поле Имя обязательно для заполнения'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $avatarUrl = null;

    // 3. Обработка загрузки файла аватара, если он передан
    if ($isAvatarUpload) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];

        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Разрешенные расширения картинок
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExtension, $allowedExtensions)) {
            // Ограничиваем размер файла до 5 МБ
            if ($fileSize <= 5 * 1024 * 1024) {

                // === УДАЛЕНИЕ СТАРОЙ АВАТАРКИ ===
                try {
                    $oldAvatarStmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
                    $oldAvatarStmt->execute([$userId]);
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

                // Создаем уникальное имя файла: user_ID_timestamp.ext
                $newFileName = 'user_' . $userId . '_' . time() . '.' . $fileExtension;

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

    // 4. Формируем SQL-запрос. Обновляем avatar_url только если загружен новый файл
    if ($avatarUrl !== null && empty($firstName)) {
        // Сценарий 1: Запрос пришел из кроппера (имя пустое, есть только фото)
        $sql = "UPDATE users SET avatar_url = :avatar_url WHERE id = :id";
        $params = [
            'avatar_url' => $avatarUrl,
            'id' => $userId
        ];
    } elseif ($avatarUrl !== null) {
        // Сценарий 2: Обычное сохранение формы профиля с новой аватаркой
        $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, city = :city, phone = :phone, avatar_url = :avatar_url WHERE id = :id";
        $params = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'city'       => $city,
            'phone'      => $phone,
            'avatar_url' => $avatarUrl,
            'id'         => $userId
        ];
    } else {
        // Сценарий 3: Обычное сохранение формы профиля без изменения фотографии
        $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, city = :city, phone = :phone WHERE id = :id";
        $params = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'city'       => $city,
            'phone'      => $phone,
            'id'         => $userId
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

/**
 * Функция сжатия и оптимизации изображения до 100-200 КБ
 */
function compressAndResizeImage($sourcePath, $destPath, $maxDimension = 800, $quality = 78) {
    list($width, $height, $imageType) = getimagesize($sourcePath);

    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $srcImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $srcImage = imagecreatefrompng($sourcePath);
            imagealphablending($srcImage, true);
            break;
        case IMAGETYPE_GIF:
            $srcImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$srcImage) return false;

    // === АВТОПОВОРОТ ПО EXIF ДАННЫМ ===
    if ($imageType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $srcImage = imagerotate($srcImage, 180, 0);
                    break;
                case 6:
                    $srcImage = imagerotate($srcImage, -90, 0);
                    $tmp = $width; $width = $height; $height = $tmp;
                    break;
                case 8:
                    $srcImage = imagerotate($srcImage, 90, 0);
                    $tmp = $width; $width = $height; $height = $tmp;
                    break;
            }
        }
    }

    // Вычисляем новые пропорциональные размеры
    $newWidth = $width;
    $newHeight = $height;

    if ($width > $maxDimension || $height > $maxDimension) {
        if ($width > $height) {
            $newWidth = $maxDimension;
            $newHeight = floor($height * ($maxDimension / $width));
        } else {
            $newHeight = $maxDimension;
            $newWidth = floor($width * ($maxDimension / $height));
        }
    }

    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Сохраняем прозрачность для PNG/GIF перед ресайзом
    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
    }

    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $result = imagejpeg($dstImage, $destPath, $quality);

    imagedestroy($srcImage);
    imagedestroy($dstImage);

    return $result;
}
