<?php
// api/sadhana/sleep.php — сохранение и получение логов сна
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_helper.php';

$user_id = requireAuth($pdo);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Получаем лог сна за определённую дату
            $date = $_GET['date'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT bed_time, wake_time, updated_at
                FROM sadhana_sleep_logs
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$user_id, $date]);
            $log = $stmt->fetch();
            
            if ($log) {
                echo json_encode([
                    'success' => true,
                    'sleep' => [
                        'bedTime' => $log['bed_time'],
                        'wakeTime' => $log['wake_time'],
                        'updatedAt' => $log['updated_at']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => true, 'sleep' => null], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'POST':
            // Сохраняем лог сна
            $data = json_decode(file_get_contents('php://input'), true);
            $log_date = $data['date'] ?? date('Y-m-d');
            $bed_time = $data['bedTime'] ?? null;
            $wake_time = $data['wakeTime'] ?? null;
            
            if (!$bed_time || !$wake_time) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Укажите время отбоя и подъёма'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // INSERT ... ON DUPLICATE KEY UPDATE
            $stmt = $pdo->prepare("
                INSERT INTO sadhana_sleep_logs (user_id, date, bed_time, wake_time)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE bed_time = VALUES(bed_time), wake_time = VALUES(wake_time), updated_at = current_timestamp()
            ");
            $stmt->execute([$user_id, $log_date, $bed_time, $wake_time]);
            
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
