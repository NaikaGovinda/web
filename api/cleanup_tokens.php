<?php
// cleanup_tokens.php
// Cron job для очистки истёкших токенов
// Запускать: php cleanup_tokens.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_tokens.php';

$pdo = require __DIR__ . '/db.php';

echo "[" . date('Y-m-d H:i:s') . "] Очистка истёкших токенов...\n";

$stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE expires_at < NOW()");
$deleted = $stmt->execute();

echo "[" . date('Y-m-d H:i:s') . "] Удалено {$deleted} истёкших токенов.\n";
