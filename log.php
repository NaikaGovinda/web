<?php
$msg = $_GET['msg'] ?? '';
$file = '/var/www/u3385428/data/www/namahata.ru/save_log.txt';
if ($msg) {
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}
// Возвращаем пустой ответ
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICTAEAOw==');