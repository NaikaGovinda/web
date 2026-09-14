-- SQL скрипт для добавления поддержки ответа на сообщения
-- Выполните этот SQL в вашей базе данных

-- Добавляем колонку reply_to_id в таблицу group_messages
ALTER TABLE group_messages 
ADD COLUMN reply_to_id INT NULL DEFAULT NULL AFTER recipient_id;

-- Добавляем индекс для ускорения запросов
ALTER TABLE group_messages 
ADD INDEX idx_reply_to_id (reply_to_id);
