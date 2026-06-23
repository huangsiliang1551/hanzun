-- Migration 002: Add indexes for large tables to improve query performance
-- Applied after 001_init_schema.sql

-- operation_logs: filter & sort indexes
ALTER TABLE `operation_logs`
    ADD INDEX `idx_operator_name` (`operator_name`),
    ADD INDEX `idx_module` (`module`),
    ADD INDEX `idx_action_point` (`action_point`),
    ADD INDEX `idx_created_at` (`created_at`);

-- admin_login_logs: filter & sort indexes
ALTER TABLE `admin_login_logs`
    ADD INDEX `idx_username` (`username`),
    ADD INDEX `idx_created_at` (`created_at`);

-- contact_items: foreign key & scope filter indexes
ALTER TABLE `contact_items`
    ADD INDEX `idx_field_type_id` (`field_type_id`),
    ADD INDEX `idx_display_scope` (`display_scope`);
