-- Drop legacy messages table (no longer used)
DROP TABLE IF EXISTS `messages`;

-- Rename internal email tables to messages
RENAME TABLE `emails` TO `messages`;
RENAME TABLE `email_recipients` TO `message_recipients`;
RENAME TABLE `email_attachments` TO `message_attachments`;
