SET @exists_products := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'views_count'
);
SET @sql := IF(
    @exists_products = 0,
    'ALTER TABLE `products` ADD COLUMN `views_count` INT NOT NULL DEFAULT 0 AFTER `manual_sort`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_solutions := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'solutions'
      AND COLUMN_NAME = 'views_count'
);
SET @sql := IF(
    @exists_solutions = 0,
    'ALTER TABLE `solutions` ADD COLUMN `views_count` INT NOT NULL DEFAULT 0 AFTER `manual_sort`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_articles := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'articles'
      AND COLUMN_NAME = 'views_count'
);
SET @sql := IF(
    @exists_articles = 0,
    'ALTER TABLE `articles` ADD COLUMN `views_count` INT NOT NULL DEFAULT 0 AFTER `manual_sort`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_news := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'news'
      AND COLUMN_NAME = 'views_count'
);
SET @sql := IF(
    @exists_news = 0,
    'ALTER TABLE `news` ADD COLUMN `views_count` INT NOT NULL DEFAULT 0 AFTER `manual_sort`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_cases := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cases'
      AND COLUMN_NAME = 'views_count'
);
SET @sql := IF(
    @exists_cases = 0,
    'ALTER TABLE `cases` ADD COLUMN `views_count` INT NOT NULL DEFAULT 0 AFTER `manual_sort`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `homepage_section_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` BIGINT UNSIGNED NOT NULL,
    `source_type` VARCHAR(32) NOT NULL,
    `source_id` BIGINT UNSIGNED NOT NULL,
    `title_override_zh` VARCHAR(255) NOT NULL DEFAULT '',
    `summary_override_zh` TEXT NULL,
    `cover_asset_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sort` INT NOT NULL DEFAULT 0,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_homepage_section_items_section_id` (`section_id`),
    KEY `idx_homepage_section_items_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `homepage_section_item_translations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `language_code` VARCHAR(16) NOT NULL,
    `title` VARCHAR(255) NOT NULL DEFAULT '',
    `summary` TEXT NULL,
    `translation_status` VARCHAR(32) NOT NULL DEFAULT 'completed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_homepage_section_item_translations_item_lang` (`item_id`, `language_code`),
    KEY `idx_homepage_section_item_translations_lang` (`language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
