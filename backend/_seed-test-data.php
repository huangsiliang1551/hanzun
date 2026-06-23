<?php
// Populate missing seed data for tests
require_once __DIR__ . '/app/common/bootstrap/Autoloader.php';
require_once __DIR__ . '/app/common/bootstrap/EnvLoader.php';
require_once __DIR__ . '/app/common/bootstrap/helpers.php';
\app\common\bootstrap\Autoloader::register(__DIR__);
\app\common\bootstrap\EnvLoader::load(__DIR__ . '/.env');
\app\common\config\ConfigRepository::instance()->load(__DIR__ . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$pdo = \app\common\database\DatabaseManager::instance()->connection();
if (!$pdo instanceof \PDO) {
    die("No DB connection\n");
}

// Ensure products 101, 102 exist
$pdo->exec("INSERT IGNORE INTO products (id, category_id, name_zh, business_status, publish_status, slug, created_by, updated_by) VALUES
  (101, 2, 'Test Product 101', 'on_sale', 'draft', 'batch-product-101', 1, 1),
  (102, 2, 'Test Product 102', 'on_sale', 'draft', 'batch-product-102', 1, 1)");

// Ensure solutions 201 exist
$pdo->exec("INSERT IGNORE INTO solutions (id, category_id, name_zh, publish_status, slug, created_by, updated_by) VALUES
  (201, 2, 'Test Solution 201', 'draft', 'test-solution-201', 1, 1)");

// Ensure articles 301 exist
$pdo->exec("INSERT IGNORE INTO articles (id, category_id, content_type, title_zh, publish_status, slug, created_by, updated_by) VALUES
  (301, 2, 'case', 'Test Article 301', 'draft', 'test-article-301', 1, 1)");

// Ensure pages 401 exist
$pdo->exec("INSERT IGNORE INTO pages (id, page_type, title_zh, publish_status, slug, created_by, updated_by) VALUES
  (401, 'landing', 'Test Page 401', 'draft', 'test-page-401', 1, 1)");

// Ensure chat_sessions 42, 51, 61, 81 exist
$pdo->exec("INSERT IGNORE INTO chat_sessions (id, session_code, source, is_valid_conversation, created_at, updated_at) VALUES
  (42, 'session-auto-042', 'ai', 1, NOW(), NOW()),
  (51, 'session-auto-051', 'ai', 1, NOW(), NOW()),
  (61, 'session-auto-061', 'ai', 1, NOW(), NOW()),
  (81, 'session-auto-081', 'ai', 1, NOW(), NOW())");

// Ensure inquiries 51, 81 exist (with source_page)
$pdo->exec("INSERT IGNORE INTO inquiries (id, source, session_id, primary_contact_type, primary_contact_value, customer_name, company_name, country_code, language_code, product_interest, solution_interest, requirement_summary, inquiry_score, status, source_page, utm_source, created_at, updated_at) VALUES
  (51, 'ai', 51, 'email', 'kunde@example.de', 'Hans Mueller', 'German Bakery GmbH', 'DE', 'en', 'Bread line', 'Bread turnkey line', 'Interested in full bread production line.', 88.00, 'new', '/en/products', 'google', NOW(), NOW()),
  (81, 'ai', 81, 'email', 'cliente@example.mx', 'Maria Lopez', 'Tortilleria MX', 'MX', 'en', 'Cake filling line', 'Cake turnkey line', 'Looking for cake production line.', 75.00, 'new', '/en/contact', 'direct', NOW(), NOW())");

// Ensure product_translations for 101, 102
$pdo->exec("INSERT IGNORE INTO product_translations (product_id, language_code, name, translation_status) VALUES
  (101, 'en', 'Cookie Former', 'pending'),
  (102, 'en', 'Bread Slicer', 'pending')");

// Ensure contact field types exist (for direct DB reads)
$pdo->exec("INSERT IGNORE INTO contact_field_types (id, field_key, name_zh, icon, validation_rule, sort, is_enabled) VALUES
  (1, 'email', '邮箱', 'mail', 'email', 100, 1),
  (2, 'phone', '电话', 'phone', 'text', 90, 1),
  (3, 'whatsapp', 'WhatsApp', 'whatsapp', 'text', 80, 1)");

// Ensure contact items exist
$pdo->exec("INSERT IGNORE INTO contact_items (id, field_type_id, label_zh, value, description_zh, display_scope, sort, is_enabled) VALUES
  (1, 1, '默认邮箱', 'hanzunkunshanmachinery@gmail.com', '默认联系邮箱', 'footer', 100, 1),
  (2, 2, '默认电话', '+85253441653', '默认联系电话', 'footer', 90, 1),
  (3, 3, '默认 WhatsApp', '+85253441653', '默认联系 WhatsApp', 'footer', 80, 1)");

// Ensure team member 1 exists in DB
$pdo->exec("INSERT IGNORE INTO team_members (id, name_zh, title_zh, department_zh, bio_zh, avatar_asset_id, email, phone, whatsapp, publish_status, translation_status, is_home_featured, manual_sort, created_by, updated_by) VALUES
  (1, 'Amy Zhang', '海外销售经理', '国际销售部', '负责海外客户需求梳理、方案匹配、报价推进与交付协同。', 4, 'amy.zhang@hanzunmachinery.com', '+8615216813602', '+8615216813602', 'published', 'completed', 1, 100, 1, 1)");

// Ensure product_categories exist
$pdo->exec("INSERT IGNORE INTO product_categories (id, name_zh, slug, sort, is_enabled) VALUES
  (1, '默认分类', 'default', 100, 1),
  (2, '烘焙设备', 'baking', 99, 1)");

// Ensure solution_categories exist
$pdo->exec("INSERT IGNORE INTO solution_categories (id, name_zh, slug, sort, is_enabled) VALUES
  (1, '默认方案分类', 'default-solution', 100, 1),
  (2, '产线方案', 'production-line', 99, 1)");

// Ensure article_categories exist
$pdo->exec("INSERT IGNORE INTO article_categories (id, name_zh, slug, content_type_scope, sort, is_enabled) VALUES
  (1, '默认', 'default', 'case', 100, 1),
  (2, '新闻', 'news', 'news', 99, 1)");

// Ensure products 101,102 + extra slugs for public tests
$pdo->exec("INSERT IGNORE INTO products (id, category_id, name_zh, business_status, publish_status, slug, seo_title, seo_keywords, seo_description, translation_status, seo_status, created_by, updated_by) VALUES
  (101, 1, 'Test Product 101', 'on_sale', 'published', 'batch-product-101', '', '', '', 'completed', 'generated', 1, 1),
  (102, 1, 'Test Product 102', 'on_sale', 'draft', 'batch-product-102', '', '', '', 'pending', 'pending', 1, 1)");
$pdo->exec("INSERT IGNORE INTO products (id, category_id, name_zh, business_status, publish_status, slug, seo_title, seo_keywords, seo_description, translation_status, seo_status, created_by, updated_by) VALUES
  (103, 1, 'Cake Depositor', 'on_sale', 'published', 'cake-depositor', 'Cake Depositor', 'cake,depositor', 'Cake depositor machine', 'completed', 'generated', 1, 1)");

// Ensure solutions 201,202 + extra slugs for public tests
$pdo->exec("INSERT IGNORE INTO solutions (id, category_id, name_zh, publish_status, slug, seo_title, seo_keywords, seo_description, translation_status, seo_status, created_by, updated_by) VALUES
  (201, 1, 'Test Solution 201', 'published', 'test-solution-201', '', '', '', 'completed', 'generated', 1, 1),
  (202, 1, 'Test Solution 202', 'draft', 'test-solution-202', '', '', '', 'pending', 'pending', 1, 1)");
$pdo->exec("INSERT IGNORE INTO solutions (id, category_id, name_zh, publish_status, slug, seo_title, seo_keywords, seo_description, created_by, updated_by) VALUES
  (203, 1, 'Cake Production Line', 'published', 'cake-line', 'Cake Line', 'cake,line', 'Cake production line', 1, 1)");

// Ensure articles 301,302 + extra slugs
$pdo->exec("INSERT IGNORE INTO articles (id, category_id, content_type, title_zh, publish_status, slug, seo_title, seo_keywords, seo_description, translation_status, seo_status, created_by, updated_by) VALUES
  (301, 1, 'case', 'Test Article 301', 'published', 'test-article-301', '', '', '', 'completed', 'generated', 1, 1),
  (302, 1, 'news', 'Test Article 302', 'draft', 'test-article-302', '', '', '', 'pending', 'pending', 1, 1)");
$pdo->exec("INSERT IGNORE INTO articles (id, category_id, content_type, title_zh, publish_status, slug, created_by, updated_by) VALUES
  (303, 1, 'case', 'UAE Cake Project', 'published', 'uae-cake-project', 1, 1)");

// Ensure pages 401,402 + extra slugs
$pdo->exec("INSERT IGNORE INTO pages (id, page_type, title_zh, publish_status, slug, seo_title, seo_keywords, seo_description, translation_status, seo_status, created_by, updated_by) VALUES
  (401, 'landing', 'Test Page 401', 'published', 'test-page-401', '', '', '', 'completed', 'generated', 1, 1),
  (402, 'landing', 'Test Page 402', 'draft', 'test-page-402', '', '', '', 'pending', 'pending', 1, 1)");
$pdo->exec("INSERT IGNORE INTO pages (id, page_type, title_zh, publish_status, slug, created_by, updated_by) VALUES
  (403, 'landing', 'Cake Line Landing', 'published', 'cake-line-landing', 1, 1)");

// Ensure chat_sessions 42,51,61,81 exist
$pdo->exec("INSERT IGNORE INTO chat_sessions (id, session_code, source, is_valid_conversation, created_at, updated_at) VALUES
  (42, 'session-auto-042', 'ai', 1, NOW(), NOW()),
  (51, 'session-auto-051', 'ai', 1, NOW(), NOW()),
  (61, 'session-auto-061', 'ai', 1, NOW(), NOW()),
  (81, 'session-auto-081', 'ai', 1, NOW(), NOW())");
$pdo->exec("INSERT IGNORE INTO chat_sessions (id, session_code, source, is_valid_conversation, created_at, updated_at) VALUES
  (21, 'sess-ai-detail', 'ai', 1, NOW(), NOW()),
  (302, 'sess-conv-302', 'ai', 1, NOW(), NOW())");

// Ensure chat_messages for session 21
$pdo->exec("INSERT IGNORE INTO chat_messages (id, session_id, message_role, content, extracted_entities_json, created_at) VALUES
  (211, 21, 'user', 'I need a cake production line', '{\"company_name\":\"Daniel Foods\"}', NOW()),
  (212, 21, 'assistant', 'Let me help you with that', NULL, NOW())");

// Ensure lead_snapshots for session 21
$pdo->exec("INSERT IGNORE INTO lead_snapshots (id, session_id, snapshot_version, email, phone, whatsapp, company_name, country_code, product_interest, created_at) VALUES
  (211, 21, 2, 'daniel@example.com', '+123456789', '+123456789', 'Daniel Foods', 'US', 'Cake Line', NOW())");

// Ensure visitor_events for session 21
$pdo->exec("INSERT IGNORE INTO visitor_events (id, session_code, page, title, referrer, visited_at) VALUES
  (211, 'sess-ai-detail', '/products/cake-line', 'Cake Line', '', NOW())");

// Ensure inquiries 31,51,81 exist
$pdo->exec("INSERT IGNORE INTO inquiries (id, source, session_id, primary_contact_type, primary_contact_value, customer_name, company_name, country_code, language_code, product_interest, solution_interest, requirement_summary, inquiry_score, status, source_page, utm_source, created_at, updated_at) VALUES
  (31, 'ai', 21, 'email', 'daniel@example.com', 'Daniel', 'Daniel Foods', 'AE', 'en', 'Cake line', 'Cake production line', 'Need quote', 88.00, 'new', '/en/products/cake-line', 'google', NOW(), NOW()),
  (51, 'ai', 51, 'email', 'kunde@example.de', 'Hans Mueller', 'German Bakery GmbH', 'DE', 'en', 'Bread line', 'Bread turnkey line', 'Interested in full bread production line.', 88.00, 'new', '/en/products', 'google', NOW(), NOW()),
  (81, 'ai', 81, 'email', 'cliente@example.mx', 'Maria Lopez', 'Tortilleria MX', 'MX', 'en', 'Cake filling line', 'Cake turnkey line', 'Looking for cake production line.', 75.00, 'new', '/en/contact', 'direct', NOW(), NOW())");

// Ensure product_translations for 101,102,103
$pdo->exec("INSERT IGNORE INTO product_translations (product_id, language_code, name, translation_status) VALUES
  (101, 'en', 'Cookie Former', 'completed'),
  (102, 'en', 'Bread Slicer', 'pending'),
  (103, 'en', 'Cake Depositor Machine', 'completed')");

// Ensure solution_translations
$pdo->exec("INSERT IGNORE INTO solution_translations (solution_id, language_code, name, translation_status) VALUES
  (201, 'en', 'Test Solution EN', 'completed'),
  (203, 'en', 'Cake Production Line', 'completed')");

// Ensure languages exist
$pdo->exec("INSERT IGNORE INTO languages (id, code, name, is_default, is_enabled, sort) VALUES
  (1, 'zh', '中文', 1, 1, 100),
  (2, 'en', 'English', 0, 1, 90)");

// Ensure navigation menus exist (for public site)
$pdo->exec("INSERT IGNORE INTO navigation_menus (id, menu_key, name_zh, menu_position, sort, is_enabled) VALUES
  (1, 'main-header', '主导航', 'header', 100, 1)");

// Ensure homepage_sections exist
$heroExtra = '{"cta_text":"立即咨询"}';
$featuredExtra = '{"limit":6}';
$pdo->exec("INSERT IGNORE INTO homepage_sections (id, section_key, section_type, title_zh, subtitle_zh, fetch_mode, extra_config, sort, is_enabled) VALUES
  (1, 'hero', 'fixed_config', '首页主视觉', '固定展示区', 'fixed_config', '$heroExtra', 100, 1),
  (2, 'featured_products', 'product_list', '推荐产品', '首页推荐池', 'auto_latest', '$featuredExtra', 90, 1)");

// Ensure media_assets exist
$pdo->exec("INSERT IGNORE INTO media_assets (id, folder_name, storage_disk, file_path, file_name, file_ext, mime_type, file_size, width, height, alt_text_zh, description_zh, status, created_at, updated_at) VALUES
  (1, 'products', 'local', '/uploads/products/free-product.jpg', 'free-product.jpg', 'jpg', 'image/jpeg', 1000, 100, 100, 'Free Product', 'unreferenced image', 1, NOW(), NOW()),
  (2, 'manuals', 'local', '/uploads/manuals/cake-line.pdf', 'cake-line.pdf', 'pdf', 'application/pdf', 5000, NULL, NULL, 'Cake Manual', 'solution manual', 1, NOW(), NOW()),
  (3, 'certificates', 'local', '/assets/images/certificates/cert-1.png', 'cert-1.png', 'png', 'image/png', 416650, 1280, 920, '证书', '证书素材', 1, NOW(), NOW()),
  (4, 'team', 'local', '/assets/images/team/amy.jpg', 'amy.jpg', 'jpg', 'image/jpeg', 50000, 400, 400, 'Amy Zhang', '团队头像', 1, NOW(), NOW()),
  (5, 'team', 'local', '/assets/images/team/sales-daniel.png', 'sales-daniel.png', 'png', 'image/png', 2026652, 1024, 1024, '团队成员', '团队头像', 1, NOW(), NOW())");

// Ensure system_settings exist
$pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
  ('site_name', '{\"zh\":\"汉尊机械\",\"en\":\"Hanzun Machinery\"}'),
  ('site_description', '{\"zh\":\"烘焙设备制造商\",\"en\":\"Bakery equipment manufacturer\"}'),
  ('language_strategy', '\"subdomain\"'),
  ('deepseek', '{\"config\":{\"base_url\":\"https://api.deepseek.com/v1\",\"model\":\"deepseek-chat\",\"api_key\":\"\",\"timeout_seconds\":30,\"retry_times\":2,\"chat_enabled\":1,\"translation_enabled\":1,\"seo_enabled\":1}}'),
  ('homepage', '{\"publish_meta\":{\"draft_updated_at\":null,\"live_updated_at\":null,\"last_published_by\":\"\",\"last_restored_by\":\"\",\"has_unpublished_changes\":0,\"publish_log\":[]}}')");

// === Additional seed data for test compatibility ===

// Admin users needed by tests
$legacyHashA = hash('sha256', 'legacy123456');
$legacyHashB = hash('sha256', 'legacypass123');
$bcryptHash = password_hash('bcrypt123456', PASSWORD_BCRYPT);
$operatorHash = password_hash('operator123', PASSWORD_BCRYPT);
$pdo->exec("INSERT IGNORE INTO admin_users (id, username, password_hash, nickname, email, status) VALUES
  (2, 'operator', '$operatorHash', '操作员', 'operator@hanzunmachinery.com', 1),
  (3, 'legacy-test', '$legacyHashB', 'Legacy Test', 'legacy-test@example.com', 1)");
$pdo->exec("INSERT IGNORE INTO admin_users (id, username, password_hash, nickname, email, status) VALUES
  (4, 'bcrypt-user', '$bcryptHash', 'Bcrypt User', 'bcrypt@example.com', 1),
  (5, 'legacy-user', '$legacyHashA', 'Legacy User', 'legacy@example.com', 1)");

// Ensure admin_user_roles for all users
$pdo->exec("INSERT IGNORE INTO admin_user_roles (user_id, role_id) VALUES
  (2, 2),
  (3, 2),
  (4, 1),
  (5, 2)");

// Team member 501 (for content-delete test)
$pdo->exec("INSERT IGNORE INTO team_members (id, name_zh, title_zh, department_zh, bio_zh, avatar_asset_id, email, phone, whatsapp, publish_status, translation_status, is_home_featured, manual_sort, created_by, updated_by) VALUES
  (501, 'Delete Test Team', '测试', '测试部', 'For delete test', 4, 'delete@test.com', '+86-00000000000', '+86-00000000000', 'published', 'completed', 0, 50, 1, 1)");

// Certificate 601 (for content-delete test)
$pdo->exec("INSERT IGNORE INTO certificates (id, name_zh, issuer_zh, certificate_no, certificate_type, description_zh, image_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort) VALUES
  (601, 'Delete Test Cert', '测试机构', 'DEL-001', '测试', 'For delete test', 3, 'published', 'completed', 'generated', 0, 50)");

// Product with slug 'live-product' and related slugs for various tests
$pdo->exec("INSERT IGNORE INTO products (id, category_id, name_zh, business_status, publish_status, slug, seo_title, seo_keywords, seo_description, translation_status, seo_status, created_by, updated_by) VALUES
  (104, 1, 'Live Product', 'on_sale', 'published', 'live-product', 'Live Product', 'live,product', 'Live product for testing', 'completed', 'generated', 1, 1),
  (105, 1, 'Keyword Test', 'on_sale', 'published', 'keyword-test', 'Keyword Test', 'keyword,test', 'Keyword filter test', 'completed', 'generated', 1, 1),
  (106, 1, 'Manual Sort A', 'on_sale', 'published', 'manual-sort-a', '', '', '', 'completed', 'generated', 1, 1)");

// Solution with slug 'live-solution'
$pdo->exec("INSERT IGNORE INTO solutions (id, category_id, name_zh, publish_status, slug, seo_title, seo_keywords, seo_description, created_by, updated_by) VALUES
  (204, 1, 'Live Solution', 'published', 'live-solution', 'Live Solution', 'live,solution', 'Live solution for testing', 1, 1)");

// Article with slug 'live-article'
$pdo->exec("INSERT IGNORE INTO articles (id, category_id, content_type, title_zh, publish_status, slug, created_by, updated_by) VALUES
  (304, 1, 'case', 'Live Article', 'published', 'live-article', 1, 1)");

// Page with slug 'live-page'
$pdo->exec("INSERT IGNORE INTO pages (id, page_type, title_zh, publish_status, slug, created_by, updated_by) VALUES
  (404, 'landing', 'Live Page', 'published', 'live-page', 1, 1)");

// Inquiry 61 for conversation-related tests
$pdo->exec("INSERT IGNORE INTO inquiries (id, source, session_id, primary_contact_type, primary_contact_value, customer_name, company_name, country_code, language_code, product_interest, solution_interest, requirement_summary, inquiry_score, status, source_page, utm_source, created_at, updated_at) VALUES
  (61, 'ai', 61, 'email', 'test61@example.com', 'Test User 61', 'Test Corp 61', 'CN', 'en', 'Test product', 'Test solution', 'Test requirement', 90.00, 'new', '/en/test', 'direct', NOW(), NOW())");

echo "Seed data populated successfully.\n";
