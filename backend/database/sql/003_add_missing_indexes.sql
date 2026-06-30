-- Migration 003: Add missing indexes for major content tables to improve query performance
-- Applied after 002_add_indexes.sql

-- products: filter (category_id, publish_status, business_status, is_home_featured) & sort (manual_sort, created_at)
ALTER TABLE `products`
    ADD INDEX `idx_products_category_id` (`category_id`),
    ADD INDEX `idx_products_publish_status` (`publish_status`),
    ADD INDEX `idx_products_business_status` (`business_status`),
    ADD INDEX `idx_products_is_home_featured` (`is_home_featured`),
    ADD INDEX `idx_products_manual_sort` (`manual_sort`),
    ADD INDEX `idx_products_created_at` (`created_at`);

-- articles: filter (category_id, content_type, publish_status, is_home_featured, country_code) & sort (manual_sort, publish_time)
ALTER TABLE `articles`
    ADD INDEX `idx_articles_category_id` (`category_id`),
    ADD INDEX `idx_articles_content_type` (`content_type`),
    ADD INDEX `idx_articles_publish_status` (`publish_status`),
    ADD INDEX `idx_articles_is_home_featured` (`is_home_featured`),
    ADD INDEX `idx_articles_manual_sort` (`manual_sort`),
    ADD INDEX `idx_articles_publish_time` (`publish_time`);

-- solutions: filter (category_id, publish_status, is_home_featured) & sort (manual_sort)
ALTER TABLE `solutions`
    ADD INDEX `idx_solutions_category_id` (`category_id`),
    ADD INDEX `idx_solutions_publish_status` (`publish_status`),
    ADD INDEX `idx_solutions_is_home_featured` (`is_home_featured`),
    ADD INDEX `idx_solutions_manual_sort` (`manual_sort`);

-- inquiries: filter (status, assigned_to, country_code), join (session_id), sort (created_at, updated_at)
ALTER TABLE `inquiries`
    ADD INDEX `idx_inquiries_status` (`status`),
    ADD INDEX `idx_inquiries_session_id` (`session_id`),
    ADD INDEX `idx_inquiries_assigned_to` (`assigned_to`),
    ADD INDEX `idx_inquiries_created_at` (`created_at`),
    ADD INDEX `idx_inquiries_country_code` (`country_code`);

-- chat_messages: add session_id index for subquery optimization, plus intent_code composite for aiTopicSummary
ALTER TABLE `chat_messages`
    ADD INDEX `idx_chat_messages_session_id` (`session_id`),
    ADD INDEX `idx_chat_messages_intent_code` (`intent_code`, `created_at`);

-- chat_sessions: filter & sort for dashboard
ALTER TABLE `chat_sessions`
    ADD INDEX `idx_chat_sessions_created_at` (`created_at`),
    ADD INDEX `idx_chat_sessions_source` (`source`),
    ADD INDEX `idx_chat_sessions_is_valid_conversation` (`is_valid_conversation`);

-- lead_snapshots: join on session_id for InquiryRepository subquery
ALTER TABLE `lead_snapshots`
    ADD INDEX `idx_lead_snapshots_session_id` (`session_id`);

-- media_assets: filter (folder_name, uploaded_by, status) & sort (created_at)
ALTER TABLE `media_assets`
    ADD INDEX `idx_media_assets_folder_name` (`folder_name`),
    ADD INDEX `idx_media_assets_uploaded_by` (`uploaded_by`),
    ADD INDEX `idx_media_assets_created_at` (`created_at`);

-- visitor_events: add page and language_code indexes for traffic analysis
ALTER TABLE `visitor_events`
    ADD INDEX `idx_visitor_events_page` (`page`(191)),
    ADD INDEX `idx_visitor_events_language_code` (`language_code`);

-- seo_404_logs: sort by created_at
ALTER TABLE `seo_404_logs`
    ADD INDEX `idx_seo_404_logs_created_at` (`created_at`);

-- navigation_items: join on menu_id
ALTER TABLE `navigation_items`
    ADD INDEX `idx_navigation_items_menu_id` (`menu_id`);

-- operation_logs: add operator_id index (already has operator_name, module, action_point, created_at)
ALTER TABLE `operation_logs`
    ADD INDEX `idx_operation_logs_operator_id` (`operator_id`);
