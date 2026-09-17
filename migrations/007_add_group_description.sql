-- Добавить поле group_description в таблицу groups
ALTER TABLE `groups` ADD COLUMN `group_description` text DEFAULT NULL AFTER `description`;
