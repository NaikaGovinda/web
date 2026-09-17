<?php
// /api/upload_group_photos.php
// Загрузка нескольких фото для группы

header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// Проверяем авторизацию (сессия ИЛИ токен)
$userId = requireAuth($pdo);

$groupId = (int)($_POST['group_id'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID группы'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 2. ПРОВЕРКА ПРАВ: Лидер или админ
    $userStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();

    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);
    $isLeader = false;

    if (!$isAdmin) {
        $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
        $leaderCheck->execute([$groupId, $userId]);
        if ((int)$leaderCheck->fetchColumn() > 0) {
            $isLeader = true;
        }
    }

    if (!$isAdmin && !$isLeader) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'У вас нет прав для редактирования этой группы'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Проверяем загрузку файлов
    if (!isset($_FILES['photos'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Файлы не найдены в $_FILES',
            'debug_keys' => array_keys($_FILES)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $files = $_FILES['photos'];

    // Проверяем структуру - файлы могут быть отправлены как photos[0], photos[1]
    if (!is_array($files) || !isset($files['name'])) {
        $fileKeys = [];
        foreach ($_FILES as $key => $value) {
            if (strpos($key, 'photos[') === 0) {
                $fileKeys[] = $key;
            }
        }

        if (empty($fileKeys)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Файлы не найдены',
                'debug' => ['keys' => array_keys($_FILES)]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $files = [
            'name' => [],
            'type' => [],
            'tmp_name' => [],
            'error' => [],
            'size' => []
        ];

        foreach ($fileKeys as $key) {
            preg_match('/photos\[(\d+)\]/', $key, $matches);
            if (!empty($matches[1])) {
                $index = (int)$matches[1];
                $files['name'][$index] = $_FILES[$key]['name'];
                $files['type'][$index] = $_FILES[$key]['type'];
                $files['tmp_name'][$index] = $_FILES[$key]['tmp_name'];
                $files['error'][$index] = $_FILES[$key]['error'];
                $files['size'][$index] = $_FILES[$key]['size'];
            }
        }

        ksort($files['name']);
        ksort($files['type']);
        ksort($files['tmp_name']);
        ksort($files['error']);
        ksort($files['size']);
    }

    if (!is_array($files) || !isset($files['name']) || !is_array($files['name']) || count($files['name']) === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Неверная структура файлов'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
    }

    $fileCount = count($files['name']);

    if ($fileCount === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Нет файлов'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($fileCount > 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Максимум 10 фото за раз'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Получаем текущий максимальный sort_order
    $stmt = $pdo->prepare("SELECT MAX(sort_order) FROM group_photos WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $maxOrder = (int)$stmt->fetchColumn();
    $startOrder = $maxOrder + 1;

    $uploadedPhotos = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 5 * 1024 * 1024;

    for ($i = 0; $i < $fileCount; $i++) {
        $error = $files['error'][$i];
        if ($error !== UPLOAD_ERR_OK) continue;

        $fileTmpPath = $files['tmp_name'][$i];
        $fileName = $files['name'][$i];
        $fileSize = $files['size'][$i];

        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        if (!in_array($fileExtension, $allowedExtensions)) continue;
        if ($fileSize > $maxFileSize) continue;
        if (!is_uploaded_file($fileTmpPath)) continue;

        $newFileName = 'group_' . $groupId . '_photo_' . time() . '_' . $i . '.' . $fileExtension;
        $dest_path = __DIR__ . '/../assets/groups/' . $newFileName;

        if (compressAndResizeImage($fileTmpPath, $dest_path, 1200, 85)) {
            $sql = "INSERT INTO group_photos (group_id, photo_url, sort_order) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $photoUrl = '/assets/groups/' . $newFileName;

            try {
                $stmt->execute([$groupId, $photoUrl, $startOrder + count($uploadedPhotos)]);
                $uploadedPhotos[] = [
                    'id' => (int)$pdo->lastInsertId(),
                    'photo_url' => $photoUrl
                ];
            } catch (\PDOException $dbEx) {
                error_log("File $i DB error: " . $dbEx->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'uploaded' => count($uploadedPhotos),
        'photos' => $uploadedPhotos
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// Функция сжатия изображений
function compressAndResizeImage($sourcePath, $destPath, $maxDimension = 1200, $quality = 85) {
    if (!file_exists($sourcePath) || !is_file($sourcePath)) return false;

    $imageSize = @getimagesize($sourcePath);
    if (!$imageSize) return false;

    list($width, $height, $imageType) = $imageSize;

    switch ($imageType) {
        case IMAGETYPE_JPEG: $srcImage = imagecreatefromjpeg($sourcePath); break;
        case IMAGETYPE_PNG:
            $srcImage = imagecreatefrompng($sourcePath);
            imagealphablending($srcImage, true);
            break;
        case IMAGETYPE_GIF: $srcImage = imagecreatefromgif($sourcePath); break;
        default: return false;
    }

    if (!$srcImage) return false;

    if ($imageType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $srcImage = imagerotate($srcImage, 180, 0); break;
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
    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $result = imagejpeg($dstImage, $destPath, $quality);
    imagedestroy($srcImage);
    imagedestroy($dstImage);

    return $result;
}