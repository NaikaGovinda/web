<?php
// api/sadhana/init.php — инициализация карточек по умолчанию только для НОВЫХ пользователей
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_helper.php';

$user_id = requireAuth($pdo);

// Проверяем, есть ли текущие активные карточки
$stmt = $pdo->prepare("
    SELECT id, name, type, target_value, unit
    FROM sadhana_cards
    WHERE user_id = ? AND is_archived = 0
    ORDER BY created_at ASC
");
$stmt->execute([$user_id]);
$cards = $stmt->fetchAll();

// Проверяем, есть ли у пользователя какая-либо история работы (сон, дневные логи)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sadhana_sleep_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$hasSleepLogs = ((int)$stmt->fetchColumn()) > 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sadhana_daily_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$hasDailyLogs = ((int)$stmt->fetchColumn()) > 0;

// Если карточки уже есть ИЛИ пользователь раньше использовал сервис (но удалил все карточки),
// НЕ пересоздаём дефолтные карточки заново.
if (count($cards) > 0 || $hasSleepLogs || $hasDailyLogs) {
    echo json_encode([
        'success' => true,
        'initialized' => false,
        'cards' => $cards
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Первичная инициализация по умолчанию ТООООЛЬКО для абсолютно нового пользователя
$defaultCards = [
    ['name' => 'Чтение книг', 'type' => 'COUNT', 'target_value' => 1, 'unit' => 'книг'],
    ['name' => 'Утренние службы', 'type' => 'COUNT', 'target_value' => 1, 'unit' => 'служб'],
    ['name' => 'Джапа медитация', 'type' => 'DURATION', 'target_value' => 24, 'unit' => 'минут'],
    ['name' => 'Служение', 'type' => 'DURATION', 'target_value' => 30, 'unit' => 'минут'],
];

$insertedCards = [];
$stmt = $pdo->prepare("
    INSERT INTO sadhana_cards (user_id, name, type, target_value, unit)
    VALUES (?, ?, ?, ?, ?)
");

foreach ($defaultCards as $card) {
    $stmt->execute([$user_id, $card['name'], $card['type'], $card['target_value'], $card['unit']]);
    $insertedCards[] = [
        'id' => (int)$pdo->lastInsertId(),
        'name' => $card['name'],
        'type' => $card['type'],
        'target_value' => $card['target_value'],
        'unit' => $card['unit']
    ];
}

echo json_encode([
    'success' => true,
    'initialized' => true,
    'cards' => $insertedCards
], JSON_UNESCAPED_UNICODE);
