CREATE DATABASE IF NOT EXISTS `hanzun_cms`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `hanzun_cms`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nickname` VARCHAR(64) NOT NULL,
  `email` VARCHAR(128) DEFAULT NULL,
  `mobile` VARCHAR(32) DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `password_version` INT NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `last_login_ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_user_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_user_roles` (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `admin_roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_menus` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(64) NOT NULL,
  `path` VARCHAR(128) NOT NULL,
  `route_name` VARCHAR(128) DEFAULT NULL,
  `icon` VARCHAR(64) DEFAULT NULL,
  `menu_type` VARCHAR(32) NOT NULL DEFAULT 'menu',
  `sort` INT NOT NULL DEFAULT 0,
  `is_visible` TINYINT NOT NULL DEFAULT 1,
  `status` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_action_points` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `code` VARCHAR(128) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_action_points_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_role_menus` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `menu_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_role_menus` (`role_id`, `menu_id`),
  FOREIGN KEY (`role_id`) REFERENCES `admin_roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`menu_id`) REFERENCES `admin_menus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_role_action_points` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `action_point_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_role_action_points` (`role_id`, `action_point_id`),
  FOREIGN KEY (`role_id`) REFERENCES `admin_roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`action_point_id`) REFERENCES `admin_action_points`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_login_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_code` VARCHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `refresh_token_hash` VARCHAR(255) NOT NULL,
  `login_ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `expired_at` DATETIME NOT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_login_sessions_code` (`session_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_login_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(64) NOT NULL,
  `login_ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `is_success` TINYINT NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` VARCHAR(64) NOT NULL,
  `operator_id` BIGINT UNSIGNED DEFAULT NULL,
  `operator_name` VARCHAR(64) DEFAULT NULL,
  `module` VARCHAR(64) NOT NULL,
  `action_point` VARCHAR(128) NOT NULL,
  `target_type` VARCHAR(64) DEFAULT NULL,
  `target_id` VARCHAR(64) DEFAULT NULL,
  `request_method` VARCHAR(16) DEFAULT NULL,
  `request_path` VARCHAR(255) DEFAULT NULL,
  `request_ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `request_payload_masked` JSON DEFAULT NULL,
  `before_snapshot` JSON DEFAULT NULL,
  `after_snapshot` JSON DEFAULT NULL,
  `result_code` INT NOT NULL DEFAULT 0,
  `result_message` VARCHAR(255) DEFAULT NULL,
  `is_success` TINYINT NOT NULL DEFAULT 1,
  `duration_ms` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `languages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(32) NOT NULL,
  `is_default` TINYINT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_languages_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(128) DEFAULT NULL,
  `folder_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `storage_disk` VARCHAR(32) NOT NULL DEFAULT 'local',
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_ext` VARCHAR(16) NOT NULL,
  `mime_type` VARCHAR(64) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `sha1` CHAR(40) DEFAULT NULL,
  `thumb_url` VARCHAR(500) DEFAULT NULL,
  `width` INT DEFAULT NULL,
  `height` INT DEFAULT NULL,
  `duration_seconds` INT DEFAULT NULL,
  `alt_text_zh` VARCHAR(255) DEFAULT NULL,
  `description_zh` VARCHAR(255) DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_folder_id` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_folders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(128) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_gallery` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(32) NOT NULL COMMENT 'product|solution|article|case',
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `media_asset_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_cover` TINYINT NOT NULL DEFAULT 0 COMMENT '1=封面图',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gallery_entity` (`entity_type`, `entity_id`, `sort_order`),
  FOREIGN KEY (`media_asset_id`) REFERENCES `media_assets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_field_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_key` VARCHAR(64) NOT NULL,
  `name_zh` VARCHAR(64) NOT NULL,
  `icon` VARCHAR(64) DEFAULT NULL,
  `validation_rule` VARCHAR(255) DEFAULT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_field_types_key` (`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_type_id` BIGINT UNSIGNED NOT NULL,
  `label_zh` VARCHAR(64) NOT NULL,
  `value` VARCHAR(255) NOT NULL,
  `description_zh` VARCHAR(255) DEFAULT NULL,
  `display_scope` VARCHAR(64) NOT NULL DEFAULT 'contact_page',
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`field_type_id`) REFERENCES `contact_field_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_field_type_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_type_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_field_type_translations` (`field_type_id`, `language_code`),
  FOREIGN KEY (`field_type_id`) REFERENCES `contact_field_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_item_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_item_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_item_translations` (`contact_item_id`, `language_code`),
  FOREIGN KEY (`contact_item_id`) REFERENCES `contact_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_group` VARCHAR(64) NOT NULL,
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` JSON NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_settings` (`setting_group`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `homepage_sections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_key` VARCHAR(64) NOT NULL,
  `section_type` VARCHAR(32) NOT NULL,
  `title_zh` VARCHAR(255) DEFAULT NULL,
  `subtitle_zh` VARCHAR(255) DEFAULT NULL,
  `fetch_mode` VARCHAR(32) NOT NULL DEFAULT 'fixed_config',
  `extra_config` JSON DEFAULT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_homepage_sections_key` (`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `homepage_section_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `content` TEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_homepage_section_translations` (`section_id`, `language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name_zh` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_category_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_category_translations` (`category_id`, `language_code`),
  FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `name_zh` VARCHAR(255) NOT NULL,
  `content_zh` MEDIUMTEXT DEFAULT NULL,
  `business_status` VARCHAR(32) NOT NULL DEFAULT 'on_sale',
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
  `views_count` INT NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `summary` TEXT DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_translations` (`product_id`, `language_code`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `solution_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name_zh` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_solution_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `solution_category_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_solution_category_translations` (`category_id`, `language_code`),
  FOREIGN KEY (`category_id`) REFERENCES `solution_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `solutions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `name_zh` VARCHAR(255) NOT NULL,
  `content_zh` MEDIUMTEXT DEFAULT NULL,
  `manual_asset_id` BIGINT UNSIGNED DEFAULT NULL,
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
  `views_count` INT NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_solutions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `solution_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `solution_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `summary` TEXT DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `flow_text` VARCHAR(500) DEFAULT NULL,
  `capacity_text` VARCHAR(500) DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_solution_translations` (`solution_id`, `language_code`),
  FOREIGN KEY (`solution_id`) REFERENCES `solutions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `article_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name_zh` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `content_type_scope` VARCHAR(32) NOT NULL DEFAULT 'all',
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `article_category_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_category_translations` (`category_id`, `language_code`),
  FOREIGN KEY (`category_id`) REFERENCES `article_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `content_type` VARCHAR(32) NOT NULL,
  `title_zh` VARCHAR(255) NOT NULL,
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
  `views_count` INT NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_articles_type_slug` (`content_type`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `article_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `summary` TEXT DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_translations` (`article_id`, `language_code`),
  FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_type` VARCHAR(32) NOT NULL DEFAULT 'page',
  `title_zh` VARCHAR(255) NOT NULL,
  `content_zh` MEDIUMTEXT DEFAULT NULL,
  `publish_status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `seo_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
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
  UNIQUE KEY `uk_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `page_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `summary` TEXT DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_page_translations` (`page_id`, `language_code`),
  FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `about_pages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key` VARCHAR(64) NOT NULL,
  `name_zh` VARCHAR(255) NOT NULL,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_about_pages_key` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `about_page_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `about_page_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_about_page_translations` (`about_page_id`, `language_code`),
  FOREIGN KEY (`about_page_id`) REFERENCES `about_pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `about_blocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `about_page_id` BIGINT UNSIGNED NOT NULL,
  `block_type` VARCHAR(32) NOT NULL,
  `title_zh` VARCHAR(255) DEFAULT NULL,
  `subtitle_zh` VARCHAR(255) DEFAULT NULL,
  `content_zh` MEDIUMTEXT DEFAULT NULL,
  `extra_config` JSON DEFAULT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_about_blocks_page_sort` (`about_page_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `about_block_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `block_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_about_block_translations` (`block_id`, `language_code`),
  FOREIGN KEY (`block_id`) REFERENCES `about_blocks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `team_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_zh` VARCHAR(255) NOT NULL,
  `title_zh` VARCHAR(255) DEFAULT NULL,
  `department_zh` VARCHAR(255) DEFAULT NULL,
  `bio_zh` TEXT DEFAULT NULL,
  `avatar_asset_id` BIGINT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(128) DEFAULT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `whatsapp` VARCHAR(64) DEFAULT NULL,
  `wechat` VARCHAR(64) DEFAULT NULL,
  `publish_status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `is_home_featured` TINYINT NOT NULL DEFAULT 0,
  `manual_sort` INT NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `publish_time` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `team_member_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_member_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `department` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_team_member_translations` (`team_member_id`, `language_code`),
  FOREIGN KEY (`team_member_id`) REFERENCES `team_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `certificates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_zh` VARCHAR(255) NOT NULL,
  `issuer_zh` VARCHAR(255) DEFAULT NULL,
  `certificate_no` VARCHAR(128) DEFAULT NULL,
  `certificate_type` VARCHAR(128) DEFAULT NULL,
  `description_zh` VARCHAR(500) DEFAULT NULL,
  `image_asset_id` BIGINT UNSIGNED DEFAULT NULL,
  `publish_status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `seo_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `is_home_featured` TINYINT NOT NULL DEFAULT 0,
  `manual_sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `publish_time` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `certificate_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `certificate_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `issuer` VARCHAR(255) DEFAULT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_certificate_translations` (`certificate_id`, `language_code`),
  FOREIGN KEY (`certificate_id`) REFERENCES `certificates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `navigation_menus` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_zh` VARCHAR(255) NOT NULL,
  `menu_key` VARCHAR(64) NOT NULL,
  `menu_position` VARCHAR(32) NOT NULL DEFAULT 'header',
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_navigation_menus_key` (`menu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `navigation_menu_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_navigation_menu_translations` (`menu_id`, `language_code`),
  FOREIGN KEY (`menu_id`) REFERENCES `navigation_menus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `navigation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `name_zh` VARCHAR(255) NOT NULL,
  `code` VARCHAR(64) DEFAULT NULL,
  `route_key` VARCHAR(128) DEFAULT NULL,
  `item_type` VARCHAR(32) NOT NULL DEFAULT 'manual_url',
  `link_type` VARCHAR(32) NOT NULL,
  `linked_entity_type` VARCHAR(32) DEFAULT NULL,
  `linked_entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `root_category_id` BIGINT UNSIGNED DEFAULT NULL,
  `max_depth` TINYINT NOT NULL DEFAULT 1,
  `include_children` TINYINT NOT NULL DEFAULT 0,
  `display_mode` VARCHAR(32) NOT NULL DEFAULT 'plain',
  `url` VARCHAR(255) DEFAULT NULL,
  `open_in_new_tab` TINYINT NOT NULL DEFAULT 0,
  `sort` INT NOT NULL DEFAULT 0,
  `is_enabled` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`menu_id`) REFERENCES `navigation_menus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `navigation_item_translations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `translation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_navigation_item_translations` (`item_id`, `language_code`),
  FOREIGN KEY (`item_id`) REFERENCES `navigation_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_code` VARCHAR(64) NOT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'ai',
  `source_page` VARCHAR(255) DEFAULT NULL,
  `entry_language` VARCHAR(8) DEFAULT NULL,
  `resolved_language` VARCHAR(8) DEFAULT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `device_type` VARCHAR(32) DEFAULT NULL,
  `utm_source` VARCHAR(64) DEFAULT NULL,
  `is_valid_conversation` TINYINT NOT NULL DEFAULT 0,
  `archive_status` VARCHAR(32) DEFAULT 'active',
  `inquiry_id` BIGINT UNSIGNED DEFAULT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_sessions_code` (`session_code`),
  KEY `idx_chat_sessions_archive` (`archive_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `message_role` VARCHAR(32) NOT NULL,
  `message_language` VARCHAR(8) DEFAULT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `translated_text` MEDIUMTEXT DEFAULT NULL,
  `intent_code` VARCHAR(64) DEFAULT NULL,
  `contains_contact_info` TINYINT NOT NULL DEFAULT 0,
  `extracted_entities_json` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_created_at` (`created_at`),
  KEY `idx_chat_messages_created_at_intent` (`created_at`, `intent_code`),
  FOREIGN KEY (`session_id`) REFERENCES `chat_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lead_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `snapshot_version` INT NOT NULL DEFAULT 1,
  `contact_name` VARCHAR(128) DEFAULT NULL,
  `company_name` VARCHAR(128) DEFAULT NULL,
  `email` VARCHAR(128) DEFAULT NULL,
  `phone` VARCHAR(64) DEFAULT NULL,
  `whatsapp` VARCHAR(64) DEFAULT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `product_interest` VARCHAR(255) DEFAULT NULL,
  `solution_interest` VARCHAR(255) DEFAULT NULL,
  `requirement_summary` TEXT DEFAULT NULL,
  `confidence_score` DECIMAL(5,2) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`session_id`) REFERENCES `chat_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `visitor_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_code` VARCHAR(64) NOT NULL,
  `page` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `referrer` VARCHAR(500) DEFAULT NULL,
  `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `language_code` VARCHAR(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_visitor_events_session_code` (`session_code`),
  KEY `idx_visitor_events_visited_at` (`visited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` VARCHAR(32) NOT NULL DEFAULT 'ai',
  `session_id` BIGINT UNSIGNED NOT NULL,
  `primary_contact_type` VARCHAR(32) NOT NULL,
  `primary_contact_value` VARCHAR(255) NOT NULL,
  `customer_name` VARCHAR(128) DEFAULT NULL,
  `company_name` VARCHAR(128) DEFAULT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `language_code` VARCHAR(8) DEFAULT NULL,
  `product_interest` VARCHAR(255) DEFAULT NULL,
  `solution_interest` VARCHAR(255) DEFAULT NULL,
  `requirement_summary` TEXT DEFAULT NULL,
  `inquiry_score` DECIMAL(5,2) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'new',
  `archive_status` VARCHAR(32) DEFAULT 'active',
  `assigned_to` BIGINT UNSIGNED DEFAULT NULL,
  `first_response_at` DATETIME DEFAULT NULL,
  `browse_traces` JSON DEFAULT NULL,
  `source_page` VARCHAR(255) DEFAULT NULL,
  `utm_source` VARCHAR(64) DEFAULT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  `change_logs` JSON DEFAULT NULL,
  `follow_ups` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`session_id`) REFERENCES `chat_sessions`(`id`) ON DELETE CASCADE,
  KEY `idx_inquiries_archive` (`archive_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `translation_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `retry_count` INT NOT NULL DEFAULT 0,
  `error_message` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_translation_jobs_entity_lang` (`entity_type`, `entity_id`, `language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `seo_generation_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL DEFAULT 'zh',
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `retry_count` INT NOT NULL DEFAULT 0,
  `error_message` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_seo_generation_jobs_entity_lang` (`entity_type`, `entity_id`, `language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `seo_routes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `language_code` VARCHAR(8) NOT NULL,
  `route_path` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `seo_title` VARCHAR(255) DEFAULT NULL,
  `seo_keywords` VARCHAR(255) DEFAULT NULL,
  `seo_description` VARCHAR(500) DEFAULT NULL,
  `canonical_url` VARCHAR(255) DEFAULT NULL,
  `index_status` VARCHAR(32) NOT NULL DEFAULT 'index',
  `last_generated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_seo_routes_entity_lang` (`entity_type`, `entity_id`, `language_code`),
  UNIQUE KEY `uk_seo_routes_path` (`route_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `seo_404_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_path` VARCHAR(500) NOT NULL,
  `referrer` VARCHAR(255) DEFAULT NULL,
  `language_code` VARCHAR(8) DEFAULT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `fix_status` VARCHAR(32) DEFAULT 'pending',
  `suggested_route` VARCHAR(255) DEFAULT NULL,
  `note` VARCHAR(500) DEFAULT NULL,
  `hit_count` INT UNSIGNED DEFAULT 1,
  `first_seen_at` DATETIME DEFAULT NULL,
  `last_seen_at` DATETIME DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seo_404_logs_fix_status` (`fix_status`),
  KEY `idx_seo_404_logs_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `traffic_daily_stats` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stat_date` DATE NOT NULL,
  `language_code` VARCHAR(8) DEFAULT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `source` VARCHAR(64) DEFAULT NULL,
  `landing_page` VARCHAR(255) DEFAULT NULL,
  `uv` INT NOT NULL DEFAULT 0,
  `pv` INT NOT NULL DEFAULT 0,
  `bounce_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_traffic_daily_stats` (`stat_date`, `language_code`, `country_code`, `source`, `landing_page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ai_conversation_daily_stats` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stat_date` DATE NOT NULL,
  `language_code` VARCHAR(8) DEFAULT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `source_page` VARCHAR(255) DEFAULT NULL,
  `total_sessions` INT NOT NULL DEFAULT 0,
  `valid_sessions` INT NOT NULL DEFAULT 0,
  `created_inquiries` INT NOT NULL DEFAULT 0,
  `lead_capture_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_conversation_daily_stats` (`stat_date`, `language_code`, `country_code`, `source_page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inquiry_daily_stats` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stat_date` DATE NOT NULL,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `language_code` VARCHAR(8) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL,
  `total_count` INT NOT NULL DEFAULT 0,
  `avg_first_response_minutes` DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inquiry_daily_stats` (`stat_date`, `country_code`, `language_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `languages` (`id`, `code`, `name`, `is_default`, `is_enabled`, `sort`)
VALUES
  (1, 'zh', '简体中文', 1, 1, 100),
  (2, 'en', 'English', 0, 1, 90)
ON DUPLICATE KEY UPDATE
  `code` = VALUES(`code`),
  `name` = VALUES(`name`),
  `is_default` = VALUES(`is_default`),
  `is_enabled` = VALUES(`is_enabled`),
  `sort` = VALUES(`sort`);

INSERT INTO `admin_roles` (`id`, `name`, `code`, `description`, `status`)
VALUES
  (1, '超级管理员', 'super-admin', '拥有全部后台权限', 1),
  (2, '操作员', 'operator', '按菜单与动作点授权', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `code` = VALUES(`code`),
  `description` = VALUES(`description`),
  `status` = VALUES(`status`);

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `nickname`, `email`, `status`)
VALUES
  (1, 'admin', '$2y$12$QQXVz1486hqrullSKO2SRewemZT5IiX442yelNn5qI7.E47ENhiYW', '超级管理员', 'admin@hanzunmachinery.com', 1)
ON DUPLICATE KEY UPDATE
  `username` = VALUES(`username`),
  `password_hash` = VALUES(`password_hash`),
  `nickname` = VALUES(`nickname`),
  `email` = VALUES(`email`),
  `status` = VALUES(`status`);

INSERT INTO `admin_user_roles` (`user_id`, `role_id`)
SELECT 1, `id` FROM `admin_roles` WHERE `code` = 'super-admin'
ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`);

INSERT INTO `admin_menus` (`id`, `parent_id`, `name`, `path`, `route_name`, `icon`, `menu_type`, `sort`, `is_visible`, `status`)
VALUES
  (1, 0, '数据看板', '/dashboard', 'dashboard', 'dashboard', 'menu', 100, 1, 1),
  (2, 0, '首页配置', '/homepage', 'homepage', 'home', 'menu', 99, 1, 1),
  (3, 0, '产品管理', '/products', 'products', 'appstore', 'menu', 98, 1, 1),
  (4, 0, '生产线/方案', '/solutions', 'solutions', 'deployment-unit', 'menu', 97, 1, 1),
  (5, 0, '新闻与案例', '/articles', 'articles', 'read', 'menu', 96, 1, 1),
  (12, 0, '新闻管理', '/news', 'news', 'read', 'menu', 96, 1, 1),
  (13, 0, '案例管理', '/cases', 'cases', 'deployment-unit', 'menu', 95, 1, 1),
  (6, 0, '资源管理', '/media', 'media', 'folder-open', 'menu', 95, 1, 1),
  (7, 0, '企业介绍', '/about', 'about', 'team', 'menu', 94, 1, 1),
  (8, 0, '单页/专题页', '/pages', 'pages', 'file-text', 'menu', 93, 1, 1),
  (9, 0, '询盘管理', '/inquiries', 'inquiries', 'message', 'menu', 92, 1, 1),
  (10, 0, 'SEO 管理', '/seo-center', 'seo-center', 'search', 'menu', 91, 1, 1),
  (11, 0, '系统设置', '/settings', 'settings', 'setting', 'menu', 90, 1, 1)
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
  (1, '查看数据看板', 'dashboard.view', '查看数据看板'),
  (2, '查看首页配置', 'homepage.view', '查看首页配置'),
  (3, '更新首页配置', 'homepage.update', '更新首页配置'),
  (4, '查看产品', 'product.view', '查看产品列表'),
  (5, '创建产品', 'product.create', '创建产品'),
  (6, '更新产品', 'product.update', '更新产品'),
  (7, '发布产品', 'product.publish', '发布产品'),
  (8, '查看方案', 'solution.view', '查看方案列表'),
  (9, '创建方案', 'solution.create', '创建方案'),
  (10, '更新方案', 'solution.update', '更新方案'),
  (11, '发布方案', 'solution.publish', '发布方案'),
  (12, '查看文章', 'article.view', '查看文章与案例'),
  (13, '创建文章', 'article.create', '创建文章与案例'),
  (14, '更新文章', 'article.update', '更新文章与案例'),
  (15, '发布文章', 'article.publish', '发布文章与案例'),
  (16, '查看单页', 'page.view', '查看单页与专题页'),
  (17, '创建单页', 'page.create', '创建单页与专题页'),
  (18, '更新单页', 'page.update', '更新单页与专题页'),
  (19, '发布单页', 'page.publish', '发布单页与专题页'),
  (20, '查看企业介绍', 'about.view', '查看企业介绍模块'),
  (21, '更新企业介绍', 'about.update', '更新企业介绍模块'),
  (22, '查看团队成员', 'team.view', '查看团队成员'),
  (23, '创建团队成员', 'team.create', '创建团队成员'),
  (24, '更新团队成员', 'team.update', '更新团队成员'),
  (25, '发布团队成员', 'team.publish', '发布团队成员'),
  (26, '查看证书', 'certificate.view', '查看证书'),
  (27, '创建证书', 'certificate.create', '创建证书'),
  (28, '更新证书', 'certificate.update', '更新证书'),
  (29, '发布证书', 'certificate.publish', '发布证书'),
  (30, '查看导航', 'navigation.view', '查看导航'),
  (31, '更新导航', 'navigation.update', '更新导航'),
  (32, '查看询盘', 'inquiry.view', '查看询盘'),
  (33, '更新询盘状态', 'inquiry.update', '更新询盘状态'),
  (34, '查看翻译任务', 'translation.view', '查看翻译任务'),
  (35, '重试翻译任务', 'translation.retry', '重试翻译任务'),
  (36, '查看 SEO', 'seo.view', '查看 SEO 管理'),
  (37, '重试 SEO 任务', 'seo.retry', '重试 SEO 任务'),
  (38, '查看联系方式', 'contact.view', '查看联系方式中心'),
  (39, '创建联系方式', 'contact.create', '创建联系方式'),
  (40, '更新联系方式', 'contact.update', '更新联系方式'),
  (41, '查看管理员', 'system.admin_user.view', '查看管理员列表'),
  (42, '创建管理员', 'system.admin_user.create', '创建管理员'),
  (43, '更新管理员', 'system.admin_user.update', '更新管理员'),
  (44, '查看角色', 'system.role.view', '查看角色权限'),
  (45, '更新角色权限', 'system.role.permissions.update', '更新角色权限'),
  (46, '查看权限点', 'system.permission.view', '查看菜单和权限点'),
  (47, '查看语言配置', 'system.languages.view', '查看语言配置'),
  (48, '更新语言配置', 'system.languages.update', '更新语言配置'),
  (49, '查看 DeepSeek 配置', 'system.deepseek.view', '查看 DeepSeek 配置'),
  (50, '更新 DeepSeek 配置', 'system.deepseek.update', '更新 DeepSeek 配置'),
  (51, '查看日志', 'system.logs.view', '查看操作日志和登录日志'),
  (52, '查看媒体资源', 'media.view', '查看媒体资源'),
  (53, '创建媒体资源', 'media.create', '创建媒体资源'),
  (54, '更新媒体资源', 'media.update', '更新媒体资源'),
  (55, '更新 SEO 配置', 'seo.update', '更新 SEO 404 / robots / sitemap'),
  (56, '审核翻译任务', 'translation.approve', '审核翻译任务'),
  (57, '查看站点配置', 'system.site.view', '查看站点配置'),
  (58, '更新站点配置', 'system.site.update', '更新站点配置'),
  (59, '删除管理员', 'system.admin_user.delete', '删除管理员'),
  (60, '创建角色', 'system.role.create', '创建角色'),
  (61, '更新角色', 'system.role.update', '更新角色'),
  (62, '删除角色', 'system.role.delete', '删除角色'),
  (64, '查看新闻', 'news.view', '查看新闻列表'),
  (65, '创建新闻', 'news.create', '创建新闻'),
  (66, '更新新闻', 'news.update', '更新新闻'),
  (67, '发布新闻', 'news.publish', '发布新闻'),
  (68, '查看案例', 'case.view', '查看案例列表'),
  (69, '创建案例', 'case.create', '创建案例'),
  (70, '更新案例', 'case.update', '更新案例'),
  (71, '发布案例', 'case.publish', '发布案例')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `code` = VALUES(`code`),
  `description` = VALUES(`description`);

INSERT INTO `admin_action_points` (`id`, `name`, `code`, `description`)
VALUES
  (63, 'Delete contacts', 'contact.delete', 'Delete contact items and field types')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `code` = VALUES(`code`),
  `description` = VALUES(`description`);

INSERT INTO `contact_field_types` (`id`, `field_key`, `name_zh`, `icon`, `validation_rule`, `sort`, `is_enabled`)
VALUES
  (1, 'email', '邮箱', 'mail', 'email', 100, 1),
  (2, 'phone', '电话', 'phone', 'phone', 99, 1),
  (3, 'whatsapp', 'WhatsApp', 'message', 'text', 98, 1)
ON DUPLICATE KEY UPDATE
  `field_key` = VALUES(`field_key`),
  `name_zh` = VALUES(`name_zh`),
  `icon` = VALUES(`icon`),
  `validation_rule` = VALUES(`validation_rule`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `contact_items` (`id`, `field_type_id`, `label_zh`, `value`, `description_zh`, `display_scope`, `sort`, `is_enabled`)
VALUES
  (1, 1, '商务邮箱', 'hanzunkunshanmachinery@gmail.com', '用于海外询盘联系', 'contact_page', 100, 1),
  (2, 2, '工厂总机', '+85253441653', '工作时间 09:00-18:00', 'footer', 99, 1),
  (3, 3, '海外 WhatsApp', '+85253441653', '销售团队在线接待', 'footer', 98, 1)
ON DUPLICATE KEY UPDATE
  `field_type_id` = VALUES(`field_type_id`),
  `label_zh` = VALUES(`label_zh`),
  `value` = VALUES(`value`),
  `description_zh` = VALUES(`description_zh`),
  `display_scope` = VALUES(`display_scope`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `contact_field_type_translations` (`field_type_id`, `language_code`, `name`, `translation_status`)
VALUES
  (1, 'en', 'Email', 'completed'),
  (2, 'en', 'Phone', 'completed'),
  (3, 'en', 'WhatsApp', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `contact_item_translations` (`contact_item_id`, `language_code`, `label`, `description`, `translation_status`)
VALUES
  (1, 'en', 'Business Email', 'Used for overseas inquiry contact', 'completed'),
  (2, 'en', 'Factory Switchboard', 'Working hours 09:00-18:00', 'completed'),
  (3, 'en', 'Overseas WhatsApp', 'Sales team online reception', 'completed')
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `description` = VALUES(`description`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `system_settings` (`setting_group`, `setting_key`, `setting_value`)
VALUES
  ('deepseek', 'config', JSON_OBJECT('base_url', 'https://api.deepseek.com/v1', 'model', 'deepseek-chat', 'api_key', '', 'timeout_seconds', 30, 'retry_times', 2, 'chat_enabled', 1, 'translation_enabled', 1, 'seo_enabled', 1)),
  ('site', 'basic', JSON_OBJECT('site_name', '上海涵尊实业有限公司官网', 'default_language', 'zh', 'auto_detect_language', 1)),
  ('homepage', 'publish_meta', JSON_OBJECT('draft_updated_at', NULL, 'live_updated_at', NULL, 'last_published_by', '', 'last_restored_by', '', 'has_unpublished_changes', 0, 'publish_log', JSON_ARRAY())),
  ('homepage', 'published_snapshot', JSON_OBJECT('sections', JSON_ARRAY(), 'featured_pool', JSON_OBJECT('product', JSON_ARRAY(), 'solution', JSON_ARRAY(), 'article', JSON_ARRAY())))
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `admin_role_menus` (`role_id`, `menu_id`)
SELECT r.`id`, m.`id`
FROM `admin_roles` r
JOIN `admin_menus` m
WHERE r.`code` = 'super-admin'
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

INSERT INTO `admin_role_menus` (`role_id`, `menu_id`)
SELECT r.`id`, m.`id`
FROM `admin_roles` r
JOIN `admin_menus` m
WHERE r.`code` = 'operator'
  AND m.`route_name` IN ('dashboard', 'homepage', 'products', 'solutions', 'articles', 'news', 'cases', 'media', 'about', 'pages', 'inquiries', 'seo-center', 'settings')
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

INSERT INTO `admin_role_action_points` (`role_id`, `action_point_id`)
SELECT r.`id`, a.`id`
FROM `admin_roles` r
JOIN `admin_action_points` a
WHERE r.`code` = 'super-admin'
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

INSERT INTO `admin_role_action_points` (`role_id`, `action_point_id`)
SELECT r.`id`, a.`id`
FROM `admin_roles` r
JOIN `admin_action_points` a
WHERE r.`code` = 'operator'
  AND a.`code` IN (
    'dashboard.view',
    'homepage.view',
    'homepage.update',
    'product.view',
    'product.create',
    'product.update',
    'solution.view',
    'solution.create',
    'solution.update',
    'article.view',
    'article.create',
    'article.update',
    'news.view',
    'news.create',
    'news.update',
    'news.publish',
    'case.view',
    'case.create',
    'case.update',
    'case.publish',
    'page.view',
    'page.create',
    'page.update',
    'about.view',
    'about.update',
    'team.view',
    'team.create',
    'team.update',
    'certificate.view',
    'certificate.create',
    'certificate.update',
    'navigation.view',
    'navigation.update',
    'inquiry.view',
    'inquiry.update',
    'translation.view',
    'seo.view',
    'contact.view',
    'contact.create',
    'contact.update',
    'system.languages.view',
    'system.languages.update',
    'system.deepseek.view',
    'system.deepseek.update',
    'media.view',
    'media.create',
    'media.update'
  )
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

INSERT INTO `product_categories` (`id`, `parent_id`, `name_zh`, `slug`, `sort`, `is_enabled`)
VALUES
  (1, 0, '烘焙设备', 'bakery-equipment', 100, 1),
  (2, 1, '蛋糕设备', 'cake-equipment', 99, 1),
  (3, 1, '面包设备', 'bread-equipment', 98, 1)
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name_zh` = VALUES(`name_zh`),
  `slug` = VALUES(`slug`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `product_category_translations` (`category_id`, `language_code`, `name`, `translation_status`)
VALUES
  (1, 'en', 'Bakery Equipment', 'completed'),
  (2, 'en', 'Cake Equipment', 'completed'),
  (3, 'en', 'Bread Equipment', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `products` (`id`, `category_id`, `name_zh`, `content_zh`, `business_status`, `publish_status`, `translation_status`, `seo_status`, `is_home_featured`, `manual_sort`, `slug`, `seo_title`, `seo_keywords`, `seo_description`, `publish_time`, `created_by`, `updated_by`)
VALUES
  (1, 2, '蛋糕自动灌装机', '支持多工位联动，适用于蛋糕产线的自动化投料与灌装场景。', 'on_sale', 'published', 'completed', 'generated', 1, 100, 'cake-depositor', '蛋糕自动灌装机', '蛋糕设备,灌装机', '涵尊实业蛋糕自动灌装机，适用于烘焙食品自动化生产。', NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
  `category_id` = VALUES(`category_id`),
  `name_zh` = VALUES(`name_zh`),
  `content_zh` = VALUES(`content_zh`),
  `business_status` = VALUES(`business_status`),
  `publish_status` = VALUES(`publish_status`),
  `translation_status` = VALUES(`translation_status`),
  `seo_status` = VALUES(`seo_status`),
  `is_home_featured` = VALUES(`is_home_featured`),
  `manual_sort` = VALUES(`manual_sort`),
  `slug` = VALUES(`slug`),
  `seo_title` = VALUES(`seo_title`),
  `seo_keywords` = VALUES(`seo_keywords`),
  `seo_description` = VALUES(`seo_description`),
  `publish_time` = VALUES(`publish_time`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `solution_categories` (`id`, `parent_id`, `name_zh`, `slug`, `sort`, `is_enabled`)
VALUES
  (1, 0, '整线方案', 'line-solutions', 100, 1),
  (2, 1, '蛋糕产线', 'cake-line', 99, 1),
  (3, 1, '面包产线', 'bread-line', 98, 1)
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name_zh` = VALUES(`name_zh`),
  `slug` = VALUES(`slug`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `solution_category_translations` (`category_id`, `language_code`, `name`, `translation_status`)
VALUES
  (1, 'en', 'Line Solutions', 'completed'),
  (2, 'en', 'Cake Lines', 'completed'),
  (3, 'en', 'Bread Lines', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `solutions` (`id`, `category_id`, `name_zh`, `content_zh`, `manual_asset_id`, `publish_status`, `translation_status`, `seo_status`, `is_home_featured`, `manual_sort`, `slug`, `seo_title`, `seo_keywords`, `seo_description`, `publish_time`, `created_by`, `updated_by`)
VALUES
  (1, 2, '蛋糕自动生产线', '适用于出口型蛋糕工厂，支持产能定制、现场安装和交钥匙交付。', 2, 'published', 'completed', 'generated', 1, 100, 'cake-line', '蛋糕自动生产线', '蛋糕产线,自动化方案', '涵尊蛋糕自动生产线方案，适用于海外食品工厂整线建设。', NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
  `category_id` = VALUES(`category_id`),
  `name_zh` = VALUES(`name_zh`),
  `content_zh` = VALUES(`content_zh`),
  `manual_asset_id` = VALUES(`manual_asset_id`),
  `publish_status` = VALUES(`publish_status`),
  `translation_status` = VALUES(`translation_status`),
  `seo_status` = VALUES(`seo_status`),
  `is_home_featured` = VALUES(`is_home_featured`),
  `manual_sort` = VALUES(`manual_sort`),
  `slug` = VALUES(`slug`),
  `seo_title` = VALUES(`seo_title`),
  `seo_keywords` = VALUES(`seo_keywords`),
  `seo_description` = VALUES(`seo_description`),
  `publish_time` = VALUES(`publish_time`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `article_categories` (`id`, `parent_id`, `name_zh`, `content_type_scope`, `sort`, `is_enabled`)
VALUES
  (1, 0, '展会交流', 'news', 100, 1),
  (2, 0, '客户案例', 'case', 99, 1),
  (3, 2, '中东案例', 'case', 98, 1)
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`),
  `name_zh` = VALUES(`name_zh`),
  `content_type_scope` = VALUES(`content_type_scope`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `article_category_translations` (`category_id`, `language_code`, `name`, `translation_status`)
VALUES
  (1, 'en', 'Exhibitions', 'completed'),
  (2, 'en', 'Customer Cases', 'completed'),
  (3, 'en', 'Middle East Cases', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `articles` (`id`, `category_id`, `content_type`, `title_zh`, `content_zh`, `country_code`, `case_tags`, `related_solution_ids`, `related_product_ids`, `publish_status`, `translation_status`, `seo_status`, `is_home_featured`, `manual_sort`, `slug`, `seo_title`, `seo_keywords`, `seo_description`, `publish_time`, `created_by`, `updated_by`)
VALUES
  (1, 1, 'news', '涵尊参加德国烘焙展', '本次展会重点展示整线方案和关键设备模块，面向欧洲客户演示交付能力。', NULL, NULL, JSON_ARRAY(), JSON_ARRAY(), 'published', 'completed', 'generated', 1, 100, 'germany-bakery-expo', '涵尊参加德国烘焙展', '展会,烘焙设备', '涵尊参加德国烘焙展新闻，展示海外市场整线方案。', NOW(), 1, 1),
  (2, 3, 'case', '阿联酋客户蛋糕项目交付', '项目覆盖蛋糕灌装、烘烤、冷却、包装全流程，支持海外安装调试。', 'AE', '蛋糕,出口,交钥匙', JSON_ARRAY(1), JSON_ARRAY(1), 'published', 'completed', 'generated', 1, 99, 'uae-cake-project', '阿联酋客户蛋糕项目交付', '客户案例,蛋糕项目', '涵尊阿联酋客户蛋糕项目案例，覆盖整线交付。', NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
  `category_id` = VALUES(`category_id`),
  `content_type` = VALUES(`content_type`),
  `title_zh` = VALUES(`title_zh`),
  `content_zh` = VALUES(`content_zh`),
  `country_code` = VALUES(`country_code`),
  `case_tags` = VALUES(`case_tags`),
  `related_solution_ids` = VALUES(`related_solution_ids`),
  `related_product_ids` = VALUES(`related_product_ids`),
  `publish_status` = VALUES(`publish_status`),
  `translation_status` = VALUES(`translation_status`),
  `seo_status` = VALUES(`seo_status`),
  `is_home_featured` = VALUES(`is_home_featured`),
  `manual_sort` = VALUES(`manual_sort`),
  `slug` = VALUES(`slug`),
  `seo_title` = VALUES(`seo_title`),
  `seo_keywords` = VALUES(`seo_keywords`),
  `seo_description` = VALUES(`seo_description`),
  `publish_time` = VALUES(`publish_time`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `pages` (`id`, `page_type`, `title_zh`, `content_zh`, `publish_status`, `translation_status`, `seo_status`, `slug`, `seo_title`, `seo_keywords`, `seo_description`, `publish_time`, `created_by`, `updated_by`)
VALUES
  (1, 'landing', '蛋糕产线专题页', '聚合产品、方案、案例与联系方式，服务海外线索转化。', 'published', 'completed', 'generated', 'cake-line-landing', '蛋糕产线专题页', '专题页,蛋糕产线', '蛋糕产线专题页，聚合产品方案与客户案例。', NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
  `page_type` = VALUES(`page_type`),
  `title_zh` = VALUES(`title_zh`),
  `content_zh` = VALUES(`content_zh`),
  `publish_status` = VALUES(`publish_status`),
  `translation_status` = VALUES(`translation_status`),
  `seo_status` = VALUES(`seo_status`),
  `slug` = VALUES(`slug`),
  `seo_title` = VALUES(`seo_title`),
  `seo_keywords` = VALUES(`seo_keywords`),
  `seo_description` = VALUES(`seo_description`),
  `publish_time` = VALUES(`publish_time`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `about_pages` (`id`, `page_key`, `name_zh`, `is_enabled`)
VALUES
  (1, 'company-about', '企业介绍', 1)
ON DUPLICATE KEY UPDATE
  `page_key` = VALUES(`page_key`),
  `name_zh` = VALUES(`name_zh`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `about_page_translations` (`about_page_id`, `language_code`, `name`, `translation_status`)
VALUES
  (1, 'en', 'About Hanzun', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `about_blocks` (`id`, `about_page_id`, `block_type`, `title_zh`, `subtitle_zh`, `content_zh`, `extra_config`, `sort`, `is_enabled`)
VALUES
  (1, 1, 'text', '企业概况', '专注烘焙与食品设备制造', '覆盖蛋糕、面包、饼干、油炸等产线设备，面向全球食品工厂提供定制化整线解决方案。', JSON_OBJECT(), 100, 1),
  (2, 1, 'team_list', '销售团队', '可复用团队成员实体', '', JSON_OBJECT('source', 'team_members'), 99, 1)
ON DUPLICATE KEY UPDATE
  `about_page_id` = VALUES(`about_page_id`),
  `block_type` = VALUES(`block_type`),
  `title_zh` = VALUES(`title_zh`),
  `subtitle_zh` = VALUES(`subtitle_zh`),
  `content_zh` = VALUES(`content_zh`),
  `extra_config` = VALUES(`extra_config`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `media_assets` (`id`, `folder_name`, `folder_id`, `original_name`, `storage_disk`, `file_path`, `file_name`, `file_ext`, `mime_type`, `file_size`, `sha1`, `width`, `height`, `duration_seconds`, `alt_text_zh`, `description_zh`, `uploaded_by`, `status`)
VALUES
  (1, 'products', 1, 'equipment-integrated-line.jpg', 'local', '/assets/images/home/equipment-integrated-line.jpg', 'equipment-integrated-line.jpg', 'jpg', 'image/jpeg', 183245, NULL, 1600, 1000, NULL, '设备整线图', '首页产品设备主图', 1, 1),
  (2, 'manuals', 2, 'cake-line.pdf', 'local', '/uploads/manuals/cake-line.pdf', 'cake-line.pdf', 'pdf', 'application/pdf', 4821932, NULL, NULL, NULL, NULL, '蛋糕产线 PDF 手册', '蛋糕自动生产线技术手册', 1, 1),
  (3, 'certificates', 3, 'cert-1.png', 'local', '/assets/images/certificates/cert-1.png', 'cert-1.png', 'png', 'image/png', 416650, NULL, 1280, 920, NULL, 'CE 认证证书', '企业出口资质证书', 1, 1),
  (4, 'team', 4, 'sales-amy-zhang.png', 'local', '/assets/images/team/sales-amy-zhang.png', 'sales-amy-zhang.png', 'png', 'image/png', 2026652, NULL, 1024, 1024, NULL, '销售团队头像', '销售团队成员头像', 1, 1)
ON DUPLICATE KEY UPDATE
  `folder_name` = VALUES(`folder_name`),
  `folder_id` = VALUES(`folder_id`),
  `original_name` = VALUES(`original_name`),
  `storage_disk` = VALUES(`storage_disk`),
  `file_path` = VALUES(`file_path`),
  `file_name` = VALUES(`file_name`),
  `file_ext` = VALUES(`file_ext`),
  `mime_type` = VALUES(`mime_type`),
  `file_size` = VALUES(`file_size`),
  `sha1` = VALUES(`sha1`),
  `width` = VALUES(`width`),
  `height` = VALUES(`height`),
  `duration_seconds` = VALUES(`duration_seconds`),
  `alt_text_zh` = VALUES(`alt_text_zh`),
  `description_zh` = VALUES(`description_zh`),
  `uploaded_by` = VALUES(`uploaded_by`),
  `status` = VALUES(`status`);

INSERT INTO `media_folders` (`id`, `parent_id`, `name`, `sort_order`)
VALUES
  (1, 0, 'products', 0),
  (2, 0, 'manuals', 1),
  (3, 0, 'certificates', 2),
  (4, 0, 'team', 3)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `parent_id` = VALUES(`parent_id`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `team_members` (`id`, `name_zh`, `title_zh`, `department_zh`, `bio_zh`, `avatar_asset_id`, `email`, `phone`, `whatsapp`, `wechat`, `publish_status`, `translation_status`, `is_home_featured`, `manual_sort`, `created_by`, `updated_by`)
VALUES
  (1, 'Amy Zhang', '海外销售经理', '国际销售部', '负责海外客户需求梳理、方案匹配、报价推进与交付协同。', 4, 'amy.zhang@hanzunmachinery.com', '+8615216813602', '+8615216813602', NULL, 'published', 'completed', 1, 100, 1, 1)
ON DUPLICATE KEY UPDATE
  `name_zh` = VALUES(`name_zh`),
  `title_zh` = VALUES(`title_zh`),
  `department_zh` = VALUES(`department_zh`),
  `bio_zh` = VALUES(`bio_zh`),
  `avatar_asset_id` = VALUES(`avatar_asset_id`),
  `email` = VALUES(`email`),
  `phone` = VALUES(`phone`),
  `whatsapp` = VALUES(`whatsapp`),
  `wechat` = VALUES(`wechat`),
  `publish_status` = VALUES(`publish_status`),
  `translation_status` = VALUES(`translation_status`),
  `is_home_featured` = VALUES(`is_home_featured`),
  `manual_sort` = VALUES(`manual_sort`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `certificates` (`id`, `name_zh`, `issuer_zh`, `description_zh`, `image_asset_id`, `publish_status`, `translation_status`, `seo_status`, `is_home_featured`, `manual_sort`)
VALUES
  (1, 'CE 认证', '欧盟认证机构', '用于展示设备出口相关合规资质。', 3, 'published', 'completed', 'generated', 1, 100),
  (2, 'ISO 9001', 'TUV Rheinland', '用于质量体系展示和海外项目资质支持。', NULL, 'published', 'completed', 'generated', 1, 99)
ON DUPLICATE KEY UPDATE
  `name_zh` = VALUES(`name_zh`),
  `issuer_zh` = VALUES(`issuer_zh`),
  `description_zh` = VALUES(`description_zh`),
  `image_asset_id` = VALUES(`image_asset_id`),
  `publish_status` = VALUES(`publish_status`),
  `translation_status` = VALUES(`translation_status`),
  `seo_status` = VALUES(`seo_status`),
  `is_home_featured` = VALUES(`is_home_featured`),
  `manual_sort` = VALUES(`manual_sort`);

INSERT INTO `navigation_menus` (`id`, `name_zh`, `menu_key`, `menu_position`, `sort`, `is_enabled`)
VALUES
  (1, '顶部主导航', 'main-header', 'header', 100, 1),
  (2, '页脚导航', 'footer-links', 'footer', 90, 1)
ON DUPLICATE KEY UPDATE
  `name_zh` = VALUES(`name_zh`),
  `menu_key` = VALUES(`menu_key`),
  `menu_position` = VALUES(`menu_position`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `navigation_menu_translations` (`menu_id`, `language_code`, `name`, `translation_status`)
VALUES
  (1, 'en', 'Main Navigation', 'completed'),
  (2, 'en', 'Footer Navigation', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `navigation_items` (`id`, `menu_id`, `parent_id`, `name_zh`, `code`, `route_key`, `item_type`, `link_type`, `linked_entity_type`, `linked_entity_id`, `root_category_id`, `max_depth`, `include_children`, `display_mode`, `url`, `open_in_new_tab`, `sort`, `is_enabled`)
VALUES
  (1, 1, 0, '产品中心', 'products', 'products', 'auto_category_tree', 'category_tree', 'product_category', 1, 1, 2, 1, 'dropdown', '', 0, 100, 1),
  (2, 1, 1, '蛋糕设备', 'cake-equipment', 'products/cake-equipment', 'auto_category_tree', 'category_tree', 'product_category', 2, 2, 1, 1, 'plain', '', 0, 99, 1),
  (3, 1, 0, '生产线方案', 'solutions', 'solutions', 'auto_category_tree', 'category_tree', 'solution_category', 1, 1, 3, 1, 'flyout', '', 0, 98, 1),
  (4, 1, 0, '企业介绍', 'about', 'about', 'about_page', 'page', 'about_page', 1, NULL, 1, 0, 'plain', '/about', 0, 97, 1),
  (5, 2, 0, '联系我们', 'contact', 'contact', 'manual_url', 'manual_url', 'custom_url', NULL, NULL, 1, 0, 'plain', '/contact', 0, 100, 1)
ON DUPLICATE KEY UPDATE
  `menu_id` = VALUES(`menu_id`),
  `parent_id` = VALUES(`parent_id`),
  `name_zh` = VALUES(`name_zh`),
  `code` = VALUES(`code`),
  `route_key` = VALUES(`route_key`),
  `item_type` = VALUES(`item_type`),
  `link_type` = VALUES(`link_type`),
  `linked_entity_type` = VALUES(`linked_entity_type`),
  `linked_entity_id` = VALUES(`linked_entity_id`),
  `root_category_id` = VALUES(`root_category_id`),
  `max_depth` = VALUES(`max_depth`),
  `include_children` = VALUES(`include_children`),
  `display_mode` = VALUES(`display_mode`),
  `url` = VALUES(`url`),
  `open_in_new_tab` = VALUES(`open_in_new_tab`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `navigation_item_translations` (`item_id`, `language_code`, `name`, `translation_status`)
VALUES
  (1, 'en', 'Products', 'completed'),
  (2, 'en', 'Cake Equipment', 'completed'),
  (3, 'en', 'Solutions', 'completed'),
  (4, 'en', 'About Us', 'completed'),
  (5, 'en', 'Contact', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `homepage_sections` (`id`, `section_key`, `section_type`, `title_zh`, `subtitle_zh`, `fetch_mode`, `extra_config`, `sort`, `is_enabled`)
VALUES
  (1, 'hero', 'fixed_config', '首页主视觉', '面向海外客户展示整线与单机设备能力', 'fixed_config', JSON_OBJECT('cta_text', '查看方案'), 100, 1),
  (2, 'featured_products', 'product_list', '推荐设备', '按首页推荐位自动聚合', 'auto_latest', JSON_OBJECT('limit', 6), 99, 1),
  (3, 'featured_solutions', 'solution_list', '推荐方案', '按首页推荐位自动聚合', 'auto_latest', JSON_OBJECT('limit', 4), 98, 1),
  (4, 'featured_articles', 'article_list', '新闻与案例', '自动展示重点资讯与客户案例', 'auto_latest', JSON_OBJECT('limit', 6), 97, 1)
ON DUPLICATE KEY UPDATE
  `section_key` = VALUES(`section_key`),
  `section_type` = VALUES(`section_type`),
  `title_zh` = VALUES(`title_zh`),
  `subtitle_zh` = VALUES(`subtitle_zh`),
  `fetch_mode` = VALUES(`fetch_mode`),
  `extra_config` = VALUES(`extra_config`),
  `sort` = VALUES(`sort`),
  `is_enabled` = VALUES(`is_enabled`);

INSERT INTO `homepage_section_translations` (`section_id`, `language_code`, `title`, `subtitle`, `content`, `translation_status`)
VALUES
  (1, 'en', 'Hero Banner', 'Showcase turnkey lines and standalone equipment for overseas buyers', 'View Solutions', 'completed'),
  (2, 'en', 'Featured Equipment', 'Auto aggregate homepage featured products', NULL, 'completed'),
  (3, 'en', 'Featured Solutions', 'Auto aggregate homepage featured solutions', NULL, 'completed'),
  (4, 'en', 'News and Cases', 'Auto surface featured news and customer stories', NULL, 'completed')
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `subtitle` = VALUES(`subtitle`),
  `content` = VALUES(`content`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `product_translations` (`product_id`, `language_code`, `name`, `summary`, `content`, `translation_status`)
VALUES
  (1, 'en', 'Cake Depositor', 'Core depositing equipment for precise cake batter filling.', 'Supports multi-station linkage for automated feeding and depositing in cake production lines.', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `summary` = VALUES(`summary`),
  `content` = VALUES(`content`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `solution_translations` (`solution_id`, `language_code`, `name`, `summary`, `content`, `flow_text`, `capacity_text`, `translation_status`)
VALUES
  (1, 'en', 'Cake Automatic Production Line', 'Automated turnkey line for medium and large cake factories.', 'Covers mixing, depositing, baking, cooling and packing in one coordinated workflow.', 'Feeding -> Mixing -> Depositing -> Baking -> Cooling -> Packing', '6000 pcs/h', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `summary` = VALUES(`summary`),
  `content` = VALUES(`content`),
  `flow_text` = VALUES(`flow_text`),
  `capacity_text` = VALUES(`capacity_text`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `article_translations` (`article_id`, `language_code`, `title`, `summary`, `content`, `translation_status`)
VALUES
  (1, 'en', 'Hanzun at the Germany Bakery Expo', 'Showcasing automated cake and bread line solutions.', 'The exhibition focused on turnkey line delivery and key equipment modules for overseas bakery factories.', 'completed'),
  (2, 'en', 'UAE Cake Project Delivery', 'Delivered a turnkey project from preparation to packing.', 'The project covered depositing, baking, cooling and packing for a full cake production workflow.', 'completed')
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `summary` = VALUES(`summary`),
  `content` = VALUES(`content`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `page_translations` (`page_id`, `language_code`, `title`, `summary`, `content`, `translation_status`)
VALUES
  (1, 'en', 'Cake Line Landing Page', 'Marketing page for export cake line projects.', 'Use this landing page to present process flow, capacity and delivery capabilities for overseas buyers.', 'completed')
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `summary` = VALUES(`summary`),
  `content` = VALUES(`content`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `about_block_translations` (`block_id`, `language_code`, `title`, `subtitle`, `content`, `translation_status`)
VALUES
  (1, 'en', 'Company Overview', 'Focused on bakery and food equipment manufacturing.', 'Covering cake, bread, biscuit and frying production equipment.', 'completed'),
  (2, 'en', 'Sales Team', 'Reusable team member entity.', '', 'completed')
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `subtitle` = VALUES(`subtitle`),
  `content` = VALUES(`content`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `team_member_translations` (`team_member_id`, `language_code`, `name`, `title`, `department`, `bio`, `translation_status`)
VALUES
  (1, 'en', 'Daniel Chen', 'Overseas Sales Manager', 'International Business Department', 'Responsible for consultation, inquiry follow-up and quotation communication across Middle East and European bakery projects.', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `title` = VALUES(`title`),
  `department` = VALUES(`department`),
  `bio` = VALUES(`bio`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `certificate_translations` (`certificate_id`, `language_code`, `name`, `issuer`, `description`, `translation_status`)
VALUES
  (1, 'en', 'CE Certification', 'EU Certification Body', 'Applicable to equipment projects exported to Europe.', 'completed'),
  (2, 'en', 'ISO 9001', 'TUV Rheinland', 'Used for quality system presentation and overseas project qualification support.', 'completed')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `issuer` = VALUES(`issuer`),
  `description` = VALUES(`description`),
  `translation_status` = VALUES(`translation_status`);

INSERT INTO `translation_jobs` (`id`, `entity_type`, `entity_id`, `language_code`, `status`, `retry_count`, `error_message`)
VALUES
  (1, 'product', 1, 'en', 'completed', 0, NULL),
  (2, 'article', 2, 'en', 'pending', 0, NULL)
ON DUPLICATE KEY UPDATE
  `entity_type` = VALUES(`entity_type`),
  `entity_id` = VALUES(`entity_id`),
  `language_code` = VALUES(`language_code`),
  `status` = VALUES(`status`),
  `retry_count` = VALUES(`retry_count`),
  `error_message` = VALUES(`error_message`);

INSERT INTO `seo_generation_jobs` (`id`, `entity_type`, `entity_id`, `language_code`, `status`, `retry_count`, `error_message`)
VALUES
  (1, 'product', 1, 'zh', 'completed', 0, NULL),
  (2, 'page', 1, 'zh', 'pending', 0, NULL)
ON DUPLICATE KEY UPDATE
  `entity_type` = VALUES(`entity_type`),
  `entity_id` = VALUES(`entity_id`),
  `language_code` = VALUES(`language_code`),
  `status` = VALUES(`status`),
  `retry_count` = VALUES(`retry_count`),
  `error_message` = VALUES(`error_message`);

INSERT INTO `seo_routes` (`id`, `entity_type`, `entity_id`, `language_code`, `route_path`, `slug`, `seo_title`, `seo_keywords`, `seo_description`, `canonical_url`, `index_status`, `last_generated_at`)
VALUES
  (1, 'product', 1, 'en', '/en/products/cake-depositor', 'cake-depositor', 'Cake Depositor', 'cake depositor,bakery equipment', 'Automatic cake depositor for industrial bakery lines.', '/en/products/cake-depositor', 'index', NOW()),
  (2, 'solution', 1, 'en', '/en/solutions/cake-line', 'cake-line', 'Cake Automatic Production Line', 'cake line,production line', 'Turnkey cake automatic production line for export projects.', '/en/solutions/cake-line', 'index', NOW()),
  (3, 'article', 2, 'en', '/en/cases/uae-cake-project', 'uae-cake-project', 'UAE Cake Project', 'uae,cake project', 'Customer case for UAE cake turnkey delivery.', '/en/cases/uae-cake-project', 'index', NOW()),
  (4, 'page', 1, 'en', '/en/landing/cake-line-landing', 'cake-line-landing', 'Cake Line Landing Page', 'cake line landing', 'Landing page for cake line marketing.', '/en/landing/cake-line-landing', 'index', NOW())
ON DUPLICATE KEY UPDATE
  `entity_type` = VALUES(`entity_type`),
  `entity_id` = VALUES(`entity_id`),
  `language_code` = VALUES(`language_code`),
  `route_path` = VALUES(`route_path`),
  `slug` = VALUES(`slug`),
  `seo_title` = VALUES(`seo_title`),
  `seo_keywords` = VALUES(`seo_keywords`),
  `seo_description` = VALUES(`seo_description`),
  `canonical_url` = VALUES(`canonical_url`),
  `index_status` = VALUES(`index_status`),
  `last_generated_at` = VALUES(`last_generated_at`);

INSERT INTO `products` (`id`, `category_id`, `name_zh`, `content_zh`, `business_status`, `publish_status`, `translation_status`, `seo_status`, `is_home_featured`, `manual_sort`, `slug`, `seo_title`, `seo_keywords`, `seo_description`, `publish_time`, `created_by`, `updated_by`)
VALUES
  (101, 2, '曲奇成型机', '用于饼干产线自动化成型工位。', 'on_sale', 'draft', 'pending', 'pending', 1, 100, 'batch-product-101', '曲奇成型机', '饼干设备,成型机', '曲奇成型机适用于饼干产线自动化生产。', NOW(), 1, 1),
  (102, 2, '面包切片机', '用于面包产线自动切片工位。', 'on_sale', 'draft', 'pending', 'pending', 0, 99, 'batch-product-102', '面包切片机', '面包设备,切片机', '面包切片机适用于面包产线自动化生产。', NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
  `category_id` = VALUES(`category_id`),
  `name_zh` = VALUES(`name_zh`),
  `content_zh` = VALUES(`content_zh`),
  `business_status` = VALUES(`business_status`),
  `publish_status` = VALUES(`publish_status`),
  `translation_status` = VALUES(`translation_status`),
  `seo_status` = VALUES(`seo_status`),
  `is_home_featured` = VALUES(`is_home_featured`),
  `manual_sort` = VALUES(`manual_sort`),
  `slug` = VALUES(`slug`),
  `seo_title` = VALUES(`seo_title`),
  `seo_keywords` = VALUES(`seo_keywords`),
  `seo_description` = VALUES(`seo_description`),
  `publish_time` = VALUES(`publish_time`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `product_translations` (`product_id`, `language_code`, `name`, `translation_status`)
VALUES
  (101, 'en', 'Cookie Former', 'pending'),
  (102, 'en', 'Bread Slicer', 'pending')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `translation_status` = VALUES(`translation_status`);


INSERT INTO `visitor_events` (`session_code`, `page`, `title`, `referrer`, `visited_at`, `language_code`)
VALUES
  ('session-auto-061', '/en/solutions/cake-line', 'Cake Line', 'https://www.google.com/', NOW(), 'en'),
  ('session-auto-061', '/en/contact', 'Contact', 'https://www.google.com/', NOW(), 'en'),
  ('session-auto-051', '/en/products', 'Products', '', NOW(), 'en')
ON DUPLICATE KEY UPDATE `page` = VALUES(`page`);

INSERT INTO `seo_routes` (`id`, `entity_type`, `entity_id`, `language_code`, `route_path`, `slug`, `seo_title`, `seo_keywords`, `seo_description`, `canonical_url`, `index_status`, `last_generated_at`)
VALUES
  (10, 'article', 2, 'zh', '/zh/cases/uae-cake-project', 'uae-cake-project', '阿联酋蛋糕项目', '蛋糕,案例', '阿联酋烘焙项目案例', '/zh/cases/uae-cake-project', 'index', NOW())
ON DUPLICATE KEY UPDATE
  `entity_type` = VALUES(`entity_type`),
  `entity_id` = VALUES(`entity_id`);

INSERT INTO `seo_404_logs` (`id`, `request_path`, `referrer`, `language_code`, `country_code`, `user_agent`, `fix_status`, `hit_count`, `first_seen_at`, `last_seen_at`)
VALUES
  (1, '/en/old-cake-line', 'https://google.com', 'en', 'AE', 'Mozilla/5.0', 'pending', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `request_path` = VALUES(`request_path`),
  `referrer` = VALUES(`referrer`),
  `language_code` = VALUES(`language_code`),
  `country_code` = VALUES(`country_code`),
  `user_agent` = VALUES(`user_agent`),
  `fix_status` = VALUES(`fix_status`),
  `hit_count` = VALUES(`hit_count`),
  `first_seen_at` = VALUES(`first_seen_at`),
  `last_seen_at` = VALUES(`last_seen_at`);



INSERT INTO `traffic_daily_stats` (`id`, `stat_date`, `language_code`, `country_code`, `source`, `landing_page`, `uv`, `pv`, `bounce_rate`)
VALUES
  (1, CURRENT_DATE, 'en', 'DE', 'organic', '/en', 123, 356, 38.50),
  (2, CURRENT_DATE, 'en', 'AE', 'direct', '/en/products', 76, 201, 42.10)
ON DUPLICATE KEY UPDATE
  `stat_date` = VALUES(`stat_date`),
  `language_code` = VALUES(`language_code`),
  `country_code` = VALUES(`country_code`),
  `source` = VALUES(`source`),
  `landing_page` = VALUES(`landing_page`),
  `uv` = VALUES(`uv`),
  `pv` = VALUES(`pv`),
  `bounce_rate` = VALUES(`bounce_rate`);

INSERT INTO `ai_conversation_daily_stats` (`id`, `stat_date`, `language_code`, `country_code`, `source_page`, `total_sessions`, `valid_sessions`, `created_inquiries`, `lead_capture_rate`)
VALUES
  (1, CURRENT_DATE, 'en', 'DE', '/en', 36, 18, 7, 38.89),
  (2, CURRENT_DATE, 'en', 'AE', '/en/products', 22, 11, 5, 45.45)
ON DUPLICATE KEY UPDATE
  `stat_date` = VALUES(`stat_date`),
  `language_code` = VALUES(`language_code`),
  `country_code` = VALUES(`country_code`),
  `source_page` = VALUES(`source_page`),
  `total_sessions` = VALUES(`total_sessions`),
  `valid_sessions` = VALUES(`valid_sessions`),
  `created_inquiries` = VALUES(`created_inquiries`),
  `lead_capture_rate` = VALUES(`lead_capture_rate`);

INSERT INTO `inquiry_daily_stats` (`id`, `stat_date`, `country_code`, `language_code`, `status`, `total_count`, `avg_first_response_minutes`)
VALUES
  (1, CURRENT_DATE, 'DE', 'en', 'new', 4, 26.50),
  (2, CURRENT_DATE, 'DE', 'en', 'quoting', 2, 35.00),
  (3, CURRENT_DATE, 'AE', 'en', 'won', 1, 18.00),
  (4, CURRENT_DATE, 'AE', 'en', 'closed', 1, 60.00)
ON DUPLICATE KEY UPDATE
  `stat_date` = VALUES(`stat_date`),
  `country_code` = VALUES(`country_code`),
  `language_code` = VALUES(`language_code`),
  `status` = VALUES(`status`),
  `total_count` = VALUES(`total_count`),
  `avg_first_response_minutes` = VALUES(`avg_first_response_minutes`);

CREATE TABLE IF NOT EXISTS `deepseek_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `feature_code` VARCHAR(32) NOT NULL DEFAULT '',
  `feature_name` VARCHAR(64) NOT NULL DEFAULT '',
  `model` VARCHAR(64) NOT NULL DEFAULT '',
  `is_success` TINYINT NOT NULL DEFAULT 0,
  `status_code` INT NOT NULL DEFAULT 0,
  `duration_ms` INT NOT NULL DEFAULT 0,
  `attempts` INT NOT NULL DEFAULT 1,
  `error_message` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deepseek_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
