-- Добавить поля time и day в таблицу groups
ALTER TABLE `groups` ADD COLUMN `time` varchar(50) DEFAULT NULL AFTER `address`;
ALTER TABLE `groups` ADD COLUMN `day` varchar(50) DEFAULT NULL AFTER `time`;
