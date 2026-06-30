-- Update language names and workspace metadata for language packs used by phrases settings page.
-- Run this file after your database is loaded.

START TRANSACTION;

INSERT INTO `languages` (`code`, `name`, `is_default`, `is_enabled`, `sort`)
VALUES
  ('es', 'Spanish', 0, 0, 80),
  ('hi', 'Hindi', 0, 0, 79),
  ('ar', 'Arabic', 0, 0, 78),
  ('fr', 'French', 0, 0, 77),
  ('de', 'German', 0, 0, 76),
  ('ja', 'Japanese', 0, 0, 75),
  ('pt', 'Portuguese', 0, 0, 74),
  ('ru', 'Russian', 0, 0, 73),
  ('it', 'Italian', 0, 0, 72),
  ('ko', 'Korean', 0, 0, 71),
  ('tr', 'Turkish', 0, 0, 70),
  ('nl', 'Dutch', 0, 0, 69),
  ('pl', 'Polish', 0, 0, 68),
  ('vi', 'Vietnamese', 0, 0, 67),
  ('th', 'Thai', 0, 0, 66),
  ('sv', 'Swedish', 0, 0, 65),
  ('id', 'Indonesian', 0, 0, 64),
  ('el', 'Greek', 0, 0, 63),
  ('cs', 'Czech', 0, 0, 62),
  ('hu', 'Hungarian', 0, 0, 61),
  ('ro', 'Romanian', 0, 0, 60),
  ('uk', 'Ukrainian', 0, 0, 59),
  ('ms', 'Malay', 0, 0, 58)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `is_default` = VALUES(`is_default`),
  `is_enabled` = VALUES(`is_enabled`),
  `sort` = VALUES(`sort`);

INSERT INTO `system_settings` (`setting_group`, `setting_key`, `setting_value`)
VALUES
  (
    'language_workspace',
    'meta',
    JSON_OBJECT(
      'es', JSON_OBJECT('native_name', 'Español', 'english_name', 'Spanish', 'zh_name', '西班牙语'),
      'hi', JSON_OBJECT('native_name', 'हिन्दी', 'english_name', 'Hindi', 'zh_name', '印地语'),
      'ar', JSON_OBJECT('native_name', 'العربية', 'english_name', 'Arabic', 'zh_name', '阿拉伯语'),
      'fr', JSON_OBJECT('native_name', 'Français', 'english_name', 'French', 'zh_name', '法语'),
      'de', JSON_OBJECT('native_name', 'Deutsch', 'english_name', 'German', 'zh_name', '德语'),
      'ja', JSON_OBJECT('native_name', '日本語', 'english_name', 'Japanese', 'zh_name', '日语'),
      'pt', JSON_OBJECT('native_name', 'Português', 'english_name', 'Portuguese', 'zh_name', '葡萄牙语'),
      'ru', JSON_OBJECT('native_name', 'Русский', 'english_name', 'Russian', 'zh_name', '俄语'),
      'it', JSON_OBJECT('native_name', 'Italiano', 'english_name', 'Italian', 'zh_name', '意大利语'),
      'ko', JSON_OBJECT('native_name', '한국어', 'english_name', 'Korean', 'zh_name', '韩语'),
      'tr', JSON_OBJECT('native_name', 'Türkçe', 'english_name', 'Turkish', 'zh_name', '土耳其语'),
      'nl', JSON_OBJECT('native_name', 'Nederlands', 'english_name', 'Dutch', 'zh_name', '荷兰语'),
      'pl', JSON_OBJECT('native_name', 'Polski', 'english_name', 'Polish', 'zh_name', '波兰语'),
      'vi', JSON_OBJECT('native_name', 'Tiếng Việt', 'english_name', 'Vietnamese', 'zh_name', '越南语'),
      'th', JSON_OBJECT('native_name', 'ไทย', 'english_name', 'Thai', 'zh_name', '泰语'),
      'sv', JSON_OBJECT('native_name', 'Svenska', 'english_name', 'Swedish', 'zh_name', '瑞典语'),
      'id', JSON_OBJECT('native_name', 'Bahasa Indonesia', 'english_name', 'Indonesian', 'zh_name', '印尼语'),
      'el', JSON_OBJECT('native_name', 'Ελληνικά', 'english_name', 'Greek', 'zh_name', '希腊语'),
      'cs', JSON_OBJECT('native_name', 'Čeština', 'english_name', 'Czech', 'zh_name', '捷克语'),
      'hu', JSON_OBJECT('native_name', 'Magyar', 'english_name', 'Hungarian', 'zh_name', '匈牙利语'),
      'ro', JSON_OBJECT('native_name', 'Română', 'english_name', 'Romanian', 'zh_name', '罗马尼亚语'),
      'uk', JSON_OBJECT('native_name', 'Українська', 'english_name', 'Ukrainian', 'zh_name', '乌克兰语'),
      'ms', JSON_OBJECT('native_name', 'Bahasa Melayu', 'english_name', 'Malay', 'zh_name', '马来语')
    )
  )
ON DUPLICATE KEY UPDATE
  `setting_value` = JSON_MERGE_PATCH(COALESCE(`setting_value`, JSON_OBJECT()), VALUES(`setting_value`));

COMMIT;
