-- AI customer service knowledge base (MySQL only)

CREATE TABLE IF NOT EXISTS `knowledge_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `source_type` ENUM('upload', 'product', 'solution', 'article', 'manual') NOT NULL DEFAULT 'manual',
  `source_id` INT UNSIGNED NULL DEFAULT NULL,
  `file_path` VARCHAR(500) NOT NULL DEFAULT '',
  `language_code` VARCHAR(10) NOT NULL DEFAULT 'zh',
  `status` ENUM('pending', 'indexed', 'failed', 'disabled') NOT NULL DEFAULT 'pending',
  `chunk_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_message` TEXT NULL,
  `tags` JSON NULL,
  `content_hash` VARCHAR(64) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_knowledge_documents_source` (`source_type`, `source_id`),
  KEY `idx_knowledge_documents_status` (`status`),
  KEY `idx_knowledge_documents_language` (`language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `knowledge_chunks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` INT UNSIGNED NOT NULL,
  `chunk_index` INT UNSIGNED NOT NULL DEFAULT 0,
  `content` TEXT NOT NULL,
  `token_estimate` INT UNSIGNED NOT NULL DEFAULT 0,
  `keywords` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_knowledge_chunks_document` (`document_id`),
  CONSTRAINT `fk_knowledge_chunks_document`
    FOREIGN KEY (`document_id`) REFERENCES `knowledge_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
