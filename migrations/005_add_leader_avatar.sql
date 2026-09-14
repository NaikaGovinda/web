-- Миграция: добавление avatar_url в таблицу group_leaders
-- Дата: 2026-09-13

ALTER TABLE `group_leaders` 
ADD COLUMN `avatar_url` varchar(1024) DEFAULT NULL AFTER `created_at`;
