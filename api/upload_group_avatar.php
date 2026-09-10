<?php
// /api/upload_group_avatar.php
header('Content-Type: application/json; charset=utf8mb4');

// [АРХИТЕКТУРА] db.php подключен на самом верху, ручной вызов session_start() полностью удален
$pdo = require __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

// 1. Проверяем авторизацию пользователя по сессии с корректным HTTP-кодом
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$groupId = (int)($_POST['group_id'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID группы'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 3. ПРОВЕРКА ПРАВ: Выясняем, является ли пользователь лидером этой группы или админом
    // [ИСПРАВЛЕНО] Проверка админа переведена на системный числовой флаг is_admin по стандарту проекта
    $userStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();
    
    $isAdmin = ($userRow && (int)$userRow['is_admin'] === 1);
    $isLeader = false;
    
    if (!$isAdmin) {
        // [АРХИТЕКТУРА] Проверяем связь лидера только в валидной таблице group_leaders (legacy leader_id удален)
        $leaderCheck = $pdo->prepare("SELECT COUNT(*) FROM group_leaders WHERE group_id = ? AND user_id = ?");
        $leaderCheck->execute([$groupId, $userId]);
        if ((int)$leaderCheck->fetchColumn() > 0) {
            $isLeader = true;
        }
    }
    
    // Если не админ и не лидер этой группы — закрываем доступ с кодом 403
    if (!$isAdmin && !$isLeader) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'У вас нет прав для редактирования этой группы'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 4. Обработка загрузки файла аватара группы
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Файл не был загружен или произошла ошибка'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $fileTmpPath = $_FILES['avatar']['tmp_name'];
    $fileName = $_FILES['avatar']['name'];
    $fileSize = $_FILES['avatar']['size'];
    
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    
    // Разрешенные расширения картинок
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Неверный формат файла. Разрешены только JPG, JPEG, PNG и GIF'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Ограничиваем размер файла до 5 МБ
    if ($fileSize > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Файл слишком большой. Максимальный размер 5МБ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // === [ИСПРАВЛЕНО] УДАЛЕНИЕ СТАРОЙ АВАТАРКИ ИЗ ПРАВИЛЬНОЙ ПАПКИ ГРУПП ===
    $oldImgStmt = $pdo->prepare("SELECT group_avatar_url FROM `groups` WHERE id = ?");
    $oldImgStmt->execute([$groupId]);
    $oldAvatarUrl = $oldImgStmt->fetchColumn();
    
    if (!empty($oldAvatarUrl)) {
        // [ИСПРАВЛЕНО] Путь собирается динамически через корень из конфига проекта для Windows/XAMPP и Linux
        $oldFilePath = $config['paths']['root_dir'] . $oldAvatarUrl;
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            unlink($oldFilePath);
        }
    }
    
    // 1. Формируем уникальное имя файла (Папка изменена на /assets/groups/)
    $newFileName = 'group_' . $groupId . '_' . time() . '.jpeg'; 
    $dest_path = __DIR__ . '/../assets/groups/' . $newFileName; 
    
    // 2. Вызываем функцию сжатия
    if (!compressAndResizeImage($fileTmpPath, $dest_path, 800, 78)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка при обработке файла'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 3. Обновляем путь в базе данных (Папка изменена на /assets/groups/)
    $sql = "UPDATE `groups` SET group_avatar_url = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['/assets/groups/' . $newFileName, $groupId]); 
    
    // 4. Формируем финальный ответ для фронтенда
    echo json_encode([
        'success' => true,
        'group_avatar_url' => '/assets/groups/' . $newFileName 
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
    list($width, $height, $imageType) = getimagesize($sourcePath);
    
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
    
    // Автоповорот по EXIF-данным для JPEG со смартфонов
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
