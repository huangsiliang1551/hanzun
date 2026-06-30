-- media_gallery 字段迁移
-- 为产品/方案/文章/页面四张表增加 media_gallery JSON 列
-- 用于存储多图/视频/文件画廊（关联资源管理资产）

ALTER TABLE `products` ADD COLUMN `media_gallery` JSON DEFAULT NULL AFTER `seo_description`;
ALTER TABLE `solutions` ADD COLUMN `media_gallery` JSON DEFAULT NULL AFTER `seo_description`;
ALTER TABLE `articles` ADD COLUMN `media_gallery` JSON DEFAULT NULL AFTER `seo_description`;
ALTER TABLE `pages` ADD COLUMN `media_gallery` JSON DEFAULT NULL AFTER `seo_description`;
