<?php
// api/sadhana/logs.php — сохранение и получение дневных логов
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_helper.php';

$user_id = requireAuth($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$card_id = $_GET['card_id'] ?? null;

try {
    switch ($method) {
        case 'GET':
            $date = $_GET['date'] ?? date('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT sdl.*, sc.id as card_id, sc.name as card_name, sc.type, sc.unit
                FROM sadhana_daily_logs sdl
                JOIN sadhana_cards sc ON sdl.card_id = sc.id
                WHERE sdl.user_id = ? AND sdl.date = ?
                ORDER BY sc.created_at ASC
            ");
            $stmt->execute([$user_id, $date]);
            $logs = $stmt->fetchAll();
            
            $result = [];
            foreach ($logs as $log) {
                $result[] = [
                    'cardId' => (int)$log['card_id'],
                    'cardName' => $log['card_name'],
                    'type' => $log['type'],
                    'unit' => $log['unit'],
                    'actualValue' => (int)$log['actual_value'],
                    'books' => $log['books'] ? json_decode($log['books'], true) : [],
                    'updatedAt' => $log['updated_at']
                ];
            }
            
            echo json_encode(['success' => true, 'logs' => $result], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'POST':
            if (!$card_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Не указан card_id'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $log_date = $data['date'] ?? date('Y-m-d');
            $actual_value = isset($data['actualValue']) ? (int)$data['actualValue'] : 0;
            $books = isset($data['books']) && is_array($data['books']) ? json_encode($data['books'], JSON_UNESCAPED_UNICODE) : null;
            
            $stmt = $pdo->prepare("SELECT id FROM sadhana_cards WHERE id = ? AND user_id = ? AND is_archived = 0");
            $stmt->execute([$card_id, $user_id]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Карточка не найдена'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sadhana_daily_logs (user_id, card_id, date, actual_value, books)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE actual_value = VALUES(actual_value), books = VALUES(books), updated_at = current_timestamp()
            ");
            $stmt->execute([$user_id, $card_id, $log_date, $actual_value, $books]);
            
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
