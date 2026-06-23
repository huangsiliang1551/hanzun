ALTER TABLE `inquiries`
  ADD INDEX `idx_inquiries_updated_id` (`updated_at`, `id`),
  ADD INDEX `idx_inquiries_status_updated` (`status`, `updated_at`),
  ADD INDEX `idx_inquiries_country_language` (`country_code`, `language_code`),
  ADD INDEX `idx_inquiries_session_id` (`session_id`),
  ADD INDEX `idx_inquiries_assigned_to` (`assigned_to`);

ALTER TABLE `chat_sessions`
  ADD INDEX `idx_chat_sessions_updated_id` (`updated_at`, `id`),
  ADD INDEX `idx_chat_sessions_inquiry_id` (`inquiry_id`),
  ADD INDEX `idx_chat_sessions_country_language` (`country_code`, `resolved_language`);

ALTER TABLE `chat_messages`
  ADD INDEX `idx_chat_messages_session_id_id` (`session_id`, `id`);

ALTER TABLE `lead_snapshots`
  ADD INDEX `idx_lead_snapshots_session_version_id` (`session_id`, `snapshot_version`, `id`);

ALTER TABLE `visitor_events`
  ADD INDEX `idx_visitor_events_language_visited` (`language_code`, `visited_at`);

ALTER TABLE `translation_jobs`
  ADD INDEX `idx_translation_jobs_status_updated` (`status`, `updated_at`);

ALTER TABLE `seo_generation_jobs`
  ADD INDEX `idx_seo_generation_jobs_status_updated` (`status`, `updated_at`);

ALTER TABLE `media_assets`
  ADD INDEX `idx_media_assets_updated_id` (`updated_at`, `id`),
  ADD INDEX `idx_media_assets_status_updated` (`status`, `updated_at`);

ALTER TABLE `media_folders`
  ADD INDEX `idx_media_folders_parent_sort_id` (`parent_id`, `sort_order`, `id`);

ALTER TABLE `products`
  ADD INDEX `idx_products_publish_sort_id` (`publish_status`, `manual_sort`, `id`),
  ADD INDEX `idx_products_business_sort_id` (`business_status`, `manual_sort`, `id`),
  ADD INDEX `idx_products_category_sort_id` (`category_id`, `manual_sort`, `id`),
  ADD INDEX `idx_products_home_sort_id` (`is_home_featured`, `manual_sort`, `id`);

ALTER TABLE `solutions`
  ADD INDEX `idx_solutions_publish_sort_id` (`publish_status`, `manual_sort`, `id`),
  ADD INDEX `idx_solutions_category_sort_id` (`category_id`, `manual_sort`, `id`),
  ADD INDEX `idx_solutions_home_sort_id` (`is_home_featured`, `manual_sort`, `id`),
  ADD INDEX `idx_solutions_manual_asset_id` (`manual_asset_id`);

ALTER TABLE `articles`
  ADD INDEX `idx_articles_publish_sort_id` (`publish_status`, `manual_sort`, `id`),
  ADD INDEX `idx_articles_type_sort_id` (`content_type`, `manual_sort`, `id`),
  ADD INDEX `idx_articles_country_sort_id` (`country_code`, `manual_sort`, `id`),
  ADD INDEX `idx_articles_category_sort_id` (`category_id`, `manual_sort`, `id`),
  ADD INDEX `idx_articles_home_sort_id` (`is_home_featured`, `manual_sort`, `id`);
