-- Добавление тестовых фото для групп
-- Используйте ЭТОТ файл для добавления фото
-- ВАЖНО: Файлы должны существовать в assets/groups/

-- Проверьте, какие файлы существуют:
-- ls assets/groups/

-- Фото для группы ID=5 (используем существующие файлы)
INSERT INTO `group_photos` (`group_id`, `photo_url`, `sort_order`) VALUES
(5, 'assets/groups/group_5_1785311575.jpeg', 1);

-- Фото для группы ID=21
INSERT INTO `group_photos` (`group_id`, `photo_url`, `sort_order`) VALUES
(21, 'assets/groups/group_21_1785334529.jpeg', 1),
(21, 'assets/groups/group_21_1786017696.jpeg', 2);
