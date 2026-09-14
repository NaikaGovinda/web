-- ============================================
-- МИГРАЦИЯ УВЕДОМЛЕНИЙ
-- Добавляем новые поля и заполняем данные
-- ============================================

-- 1. Добавляем новые колонки (если ещё не добавлены)
ALTER TABLE `notifications` 
ADD COLUMN IF NOT EXISTS `group_id` int(11) DEFAULT NULL AFTER `message`,
ADD COLUMN IF NOT EXISTS `target_page` varchar(100) DEFAULT NULL AFTER `group_id`,
ADD COLUMN IF NOT EXISTS `target_params` text DEFAULT NULL AFTER `target_page`;

-- 2. Обновляем уведомления о сообщениях в чате с group_id
UPDATE notifications 
SET target_page = 'group.html',
    target_params = JSON_OBJECT('id', group_id, 'open_chat', 1)
WHERE type = 'chat_message' 
  AND group_id IS NOT NULL 
  AND group_id > 0;

-- 3. Обновляем уведомления о заявках
UPDATE notifications 
SET target_page = 'profile.html',
    target_params = JSON_OBJECT()
WHERE type IN ('application_approved', 'application_rejected')
  AND target_page IS NULL;

-- 4. Обновляем уведомления об отмене заявки
UPDATE notifications 
SET target_page = 'profile.html',
    target_params = JSON_OBJECT()
WHERE type = 'application_cancelled'
  AND target_page IS NULL;

-- 5. Обновляем уведомления о выходе из группы
UPDATE notifications 
SET target_page = 'profile.html',
    target_params = JSON_OBJECT()
WHERE type = 'group_removed'
  AND target_page IS NULL;

-- 6. Для старых уведомлений без target_params устанавливаем profile.html по умолчанию
UPDATE notifications 
SET target_page = 'profile.html',
    target_params = JSON_OBJECT()
WHERE target_page IS NULL;
