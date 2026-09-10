<?php
// Записывает текущую дату и время в файл cron_log.txt
file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Cron works!\n", FILE_APPEND);
?>