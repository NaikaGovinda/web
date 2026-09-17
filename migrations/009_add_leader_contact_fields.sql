-- Миграция: добавление полей контактов для лидеров групп
-- Дата: 2026-09-17

ALTER TABLE `group_leaders`
    ADD COLUMN `phone` varchar(50) DEFAULT NULL AFTER `created_at`,
    ADD COLUMN `max_contact` varchar(255) DEFAULT NULL AFTER `phone`,
    ADD COLUMN `vk_contact` varchar(255) DEFAULT NULL AFTER `max_contact`;
