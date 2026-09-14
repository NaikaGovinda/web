-- SQL скрипт для добавления поддержки редактирования сообщений
-- Выполните этот SQL в вашей базе данных

-- Добавляем колонку updated_at в таблицу group_messages
ALTER TABLE group_messages 
ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

-- Опционально: добавляем индекс для ускорения запросов
ALTER TABLE group_messages 
ADD INDEX idx_updated_at (updated_at);
