ALTER TABLE `inquiries`
    ADD COLUMN `source_page` VARCHAR(255) NOT NULL DEFAULT '' AFTER `status`,
    ADD COLUMN `utm_source` VARCHAR(64) NOT NULL DEFAULT '' AFTER `source_page`,
    ADD COLUMN `last_message_at` DATETIME NULL DEFAULT NULL AFTER `utm_source`;
