ALTER TABLE `visitor_events`
  ADD INDEX `idx_visitor_events_visited_lang_session` (`visited_at`, `language_code`, `session_code`),
  ADD INDEX `idx_visitor_events_session_visited` (`session_code`, `visited_at`),
  ADD INDEX `idx_visitor_events_page_visited` (`page`, `visited_at`);

ALTER TABLE `traffic_daily_stats`
  ADD INDEX `idx_traffic_daily_stats_date` (`stat_date`),
  ADD INDEX `idx_traffic_daily_stats_lang_date` (`language_code`, `stat_date`),
  ADD INDEX `idx_traffic_daily_stats_country_date` (`country_code`, `stat_date`),
  ADD INDEX `idx_traffic_daily_stats_landing_page_date` (`landing_page`, `stat_date`),
  ADD INDEX `idx_traffic_daily_stats_source_date` (`source`, `stat_date`);

ALTER TABLE `inquiries`
  ADD INDEX `idx_inquiries_created_at_status` (`created_at`, `status`),
  ADD INDEX `idx_inquiries_created_at_country` (`created_at`, `country_code`),
  ADD INDEX `idx_inquiries_created_at_language` (`created_at`, `language_code`),
  ADD INDEX `idx_inquiries_status_created` (`status`, `created_at`);

ALTER TABLE `chat_messages`
  ADD INDEX `idx_chat_messages_intent_created` (`intent_code`, `created_at`),
  ADD INDEX `idx_chat_messages_session_created` (`session_id`, `created_at`);

ALTER TABLE `ai_conversation_daily_stats`
  ADD INDEX `idx_ai_conversation_daily_stats_date` (`stat_date`),
  ADD INDEX `idx_ai_conversation_daily_stats_lang_date` (`language_code`, `stat_date`),
  ADD INDEX `idx_ai_conversation_daily_stats_country_date` (`country_code`, `stat_date`);

ALTER TABLE `inquiry_daily_stats`
  ADD INDEX `idx_inquiry_daily_stats_date` (`stat_date`),
  ADD INDEX `idx_inquiry_daily_stats_lang_date` (`language_code`, `stat_date`),
  ADD INDEX `idx_inquiry_daily_stats_country_date` (`country_code`, `stat_date`),
  ADD INDEX `idx_inquiry_daily_stats_status_date` (`status`, `stat_date`);
