-- Migration: Add forward_sender_id and original_message_id columns to group_messages
-- Purpose: Store original sender ID and original message ID for forwarded messages (Telegram-style forwarding)

ALTER TABLE group_messages 
ADD COLUMN forward_sender_id INT NULL DEFAULT NULL AFTER is_forwarded;

ALTER TABLE group_messages 
ADD INDEX idx_forward_sender_id (forward_sender_id);

ALTER TABLE group_messages 
ADD COLUMN original_message_id INT NULL DEFAULT NULL AFTER forward_sender_id;

ALTER TABLE group_messages 
ADD INDEX idx_original_message_id (original_message_id);
