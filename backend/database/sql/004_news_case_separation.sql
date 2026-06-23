-- ==========================================================================
-- Migration 004: News & Case Separation
-- 
-- 将 articles 表按照 content_type 拆分为独立的 news 和 cases 表，
-- 同时拆分 article_categories 为 news_categories 和 case_categories。
-- 原 articles 表保留不做删除，以便回滚。
-- ==========================================================================

USE `hanzun_cms`;

-- ------------------------------------------------------------------
-- 1. news 系列表
-- ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `news` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `title_zh` VARCHAR(255) NOT NULL,
  `summary_zh` VARCHAR(500) DEFAULT NULL,
  `content_zh` MEDIUMTEXT DEFAULT NULL,
  `publish_status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `seo_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `is_home_featured` TINYINT NOT NULL DEFAULT 0,
  `manual_sort` INT NOT NULL DEFAULT 0,
  `slug` VARCHAR(255) DEFAULT NULL,
  `seo_title` VARCHAR(255) DEFAULT NULL,
  `seo_keywords` VARCHAR(255) DEFAULT NULL,
  `seo_description` VARCHAR(500) DEFAULT NULL,
  `publish_time` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_news_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `news_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name_zh` VARCHAR(64) NOT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `news_category_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_news_category_translations` (`category_id`, `language_code`),
  FOREIGN KEY (`category_id`) REFERENCES `news_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `news_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `news_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `summary` VARCHAR(500) DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_news_translations` (`news_id`, `language_code`),
  FOREIGN KEY (`news_id`) REFERENCES `news`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- 2. cases 系列表
-- ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `cases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `title_zh` VARCHAR(255) NOT NULL,
  `summary_zh` VARCHAR(500) DEFAULT NULL,
  `content_zh` MEDIUMTEXT DEFAULT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `case_tags` VARCHAR(255) DEFAULT NULL,
  `related_solution_ids` JSON DEFAULT NULL,
  `related_product_ids` JSON DEFAULT NULL,
  `publish_status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `seo_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `is_home_featured` TINYINT NOT NULL DEFAULT 0,
  `manual_sort` INT NOT NULL DEFAULT 0,
  `slug` VARCHAR(255) DEFAULT NULL,
  `seo_title` VARCHAR(255) DEFAULT NULL,
  `seo_keywords` VARCHAR(255) DEFAULT NULL,
  `seo_description` VARCHAR(500) DEFAULT NULL,
  `publish_time` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cases_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `case_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name_zh` VARCHAR(64) NOT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `case_category_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_case_category_translations` (`category_id`, `language_code`),
  FOREIGN KEY (`category_id`) REFERENCES `case_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `case_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `summary` VARCHAR(500) DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_case_translations` (`case_id`, `language_code`),
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- 3. 数据迁移
-- ------------------------------------------------------------------

-- 3.1 迁移新闻分类
INSERT IGNORE INTO `news_categories` (`id`, `parent_id`, `name_zh`, `sort`, `is_enabled`)
SELECT `id`, `parent_id`, `name_zh`, `sort`, `is_enabled`
FROM `article_categories`
WHERE `content_type_scope` IN ('all', 'news');

-- 3.2 迁移新闻分类翻译
INSERT IGNORE INTO `news_category_translations` (`category_id`, `language_code`, `name`, `translation_status`)
SELECT `category_id`, `language_code`, `name`, `translation_status`
FROM `article_category_translations`
WHERE `category_id` IN (SELECT `id` FROM `article_categories` WHERE `content_type_scope` IN ('all', 'news'));

-- 3.3 迁移新闻数据
INSERT IGNORE INTO `news` (
  `id`, `category_id`, `title_zh`, `content_zh`,
  `publish_status`, `translation_status`, `seo_status`,
  `is_home_featured`, `manual_sort`, `slug`,
  `seo_title`, `seo_keywords`, `seo_description`,
  `publish_time`, `created_by`, `updated_by`, `created_at`, `updated_at`
)
SELECT
  `id`, `category_id`, `title_zh`, `content_zh`,
  `publish_status`, `translation_status`, `seo_status`,
  `is_home_featured`, `manual_sort`, `slug`,
  `seo_title`, `seo_keywords`, `seo_description`,
  `publish_time`, `created_by`, `updated_by`, `created_at`, `updated_at`
FROM `articles`
WHERE `content_type` = 'news';

-- 3.4 迁移新闻翻译数据
INSERT IGNORE INTO `news_translations` (`news_id`, `language_code`, `title`, `summary`, `content`, `translation_status`)
SELECT `article_id`, `language_code`, `title`, `summary`, `content`, `translation_status`
FROM `article_translations`
WHERE `article_id` IN (SELECT `id` FROM `articles` WHERE `content_type` = 'news');

-- 3.5 迁移案例分类
INSERT IGNORE INTO `case_categories` (`id`, `parent_id`, `name_zh`, `sort`, `is_enabled`)
SELECT `id`, `parent_id`, `name_zh`, `sort`, `is_enabled`
FROM `article_categories`
WHERE `content_type_scope` IN ('all', 'case');

-- 3.6 迁移案例分类翻译
INSERT IGNORE INTO `case_category_translations` (`category_id`, `language_code`, `name`, `translation_status`)
SELECT `category_id`, `language_code`, `name`, `translation_status`
FROM `article_category_translations`
WHERE `category_id` IN (SELECT `id` FROM `article_categories` WHERE `content_type_scope` IN ('all', 'case'));

-- 3.7 迁移案例数据
INSERT IGNORE INTO `cases` (
  `id`, `category_id`, `title_zh`, `content_zh`,
  `country_code`, `case_tags`, `related_solution_ids`, `related_product_ids`,
  `publish_status`, `translation_status`, `seo_status`,
  `is_home_featured`, `manual_sort`, `slug`,
  `seo_title`, `seo_keywords`, `seo_description`,
  `publish_time`, `created_by`, `updated_by`, `created_at`, `updated_at`
)
SELECT
  `id`, `category_id`, `title_zh`, `content_zh`,
  `country_code`, `case_tags`, `related_solution_ids`, `related_product_ids`,
  `publish_status`, `translation_status`, `seo_status`,
  `is_home_featured`, `manual_sort`, `slug`,
  `seo_title`, `seo_keywords`, `seo_description`,
  `publish_time`, `created_by`, `updated_by`, `created_at`, `updated_at`
FROM `articles`
WHERE `content_type` = 'case';

-- 3.8 迁移案例翻译数据
INSERT IGNORE INTO `case_translations` (`case_id`, `language_code`, `title`, `summary`, `content`, `translation_status`)
SELECT `article_id`, `language_code`, `title`, `summary`, `content`, `translation_status`
FROM `article_translations`
WHERE `article_id` IN (SELECT `id` FROM `articles` WHERE `content_type` = 'case');

-- 3.9 在 articles 表添加迁移标记列，标识数据已迁移
SET @exist_check := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'hanzun_cms' AND TABLE_NAME = 'articles' AND COLUMN_NAME = 'migrated_to_new_tables');
SET @sql := IF(@exist_check = 0, 'ALTER TABLE articles ADD COLUMN migrated_to_new_tables TINYINT NOT NULL DEFAULT 0 AFTER content_type', 'SELECT "migrated_to_new_tables already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE articles SET migrated_to_new_tables = 1 WHERE content_type IN ('news', 'case');

-- ------------------------------------------------------------------
-- 4. admin_menus 和 action_points 种子数据更新
-- (这些更新也反映在 001_init_schema.sql 中)
-- ------------------------------------------------------------------

INSERT INTO `admin_menus` (`id`, `parent_id`, `name`, `path`, `route_name`, `icon`, `menu_type`, `sort`, `is_visible`, `status`)
VALUES
  (12, 0, '新闻管理', '/news', 'news', 'read', 'menu', 96, 1, 1),
  (13, 0, '案例管理', '/cases', 'cases', 'deployment-unit', 'menu', 95, 1, 1)
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name` = VALUES(`name`),
  `path` = VALUES(`path`),
  `route_name` = VALUES(`route_name`),
  `icon` = VALUES(`icon`),
  `menu_type` = VALUES(`menu_type`),
  `sort` = VALUES(`sort`),
  `is_visible` = VALUES(`is_visible`),
  `status` = VALUES(`status`);

INSERT INTO `admin_action_points` (`id`, `name`, `code`, `description`)
VALUES
  (63, '查看新闻', 'news.view', '查看新闻列表'),
  (64, '创建新闻', 'news.create', '创建新闻'),
  (65, '更新新闻', 'news.update', '更新新闻'),
  (66, '发布新闻', 'news.publish', '发布新闻'),
  (67, '查看案例', 'case.view', '查看案例列表'),
  (68, '创建案例', 'case.create', '创建案例'),
  (69, '更新案例', 'case.update', '更新案例'),
  (70, '发布案例', 'case.publish', '发布案例')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`);
