<?php
// api/sadhana/cards.php — CRUD для карточек практик
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_helper.php';

$user_id = requireAuth($pdo);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Получаем карточки пользователя (не архивированные)
            $stmt = $pdo->prepare("
                SELECT id, name, type, target_value, unit, is_archived, created_at
                FROM sadhana_cards
                WHERE user_id = ? AND is_archived = 0
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id]);
            $cards = $stmt->fetchAll();
            
            foreach ($cards as &$card) {
                $card['id'] = (int)$card['id'];
                $card['target_value'] = (int)$card['target_value'];
                $card['is_archived'] = (bool)$card['is_archived'];
            }
            
            echo json_encode(['success' => true, 'cards' => $cards], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!empty($data['action']) && in_array($data['action'], ['archive', 'delete'])) {
                // Полностью удаляем карточку и логи из БД
                $card_id = $data['id'] ?? null;
                
                if (!$card_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID карточки не указан'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                // Проверяем что карточка принадлежит пользователю
                $stmt = $pdo->prepare("SELECT id FROM sadhana_cards WHERE id = ? AND user_id = ?");
                $stmt->execute([$card_id, $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Карточка не найдена'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                // Удаляем логи и карточку
                $stmt = $pdo->prepare("DELETE FROM sadhana_daily_logs WHERE card_id = ? AND user_id = ?");
                $stmt->execute([$card_id, $user_id]);

                $stmt = $pdo->prepare("DELETE FROM sadhana_cards WHERE id = ? AND user_id = ?");
                $stmt->execute([$card_id, $user_id]);
                
                echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Создаём новую карточку
            if (empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Введите название'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $type = in_array($data['type'], ['COUNT', 'DURATION']) ? $data['type'] : 'COUNT';
            $target = isset($data['targetValue']) ? (int)$data['targetValue'] : 1;
            $unit = $data['unit'] ?? '';
            
            $stmt = $pdo->prepare("
                INSERT INTO sadhana_cards (user_id, name, type, target_value, unit)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $data['name'], $type, $target, $unit]);
            
            $card_id = (int)$pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'card' => [
                    'id' => $card_id,
                    'name' => $data['name'],
                    'type' => $type,
                    'targetValue' => $target,
                    'unit' => $unit
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $card_id = $_GET['id'] ?? ($data['id'] ?? null);

            if (!$card_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID карточки не указан'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM sadhana_daily_logs WHERE card_id = ? AND user_id = ?");
            $stmt->execute([$card_id, $user_id]);

            $stmt = $pdo->prepare("DELETE FROM sadhana_cards WHERE id = ? AND user_id = ?");
            $stmt->execute([$card_id, $user_id]);

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
