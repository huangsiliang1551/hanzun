-- MySQL dump 10.13  Distrib 8.4.9, for Linux (x86_64)
--
-- Host: localhost    Database: hanzun_cms
-- ------------------------------------------------------
-- Server version	8.4.9

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `hanzun_cms`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `hanzun_cms` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ ;

USE `hanzun_cms`;

--
-- Table structure for table `about_block_translations`
--

DROP TABLE IF EXISTS `about_block_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `about_block_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `block_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_about_block_translations` (`block_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `about_block_translations`
--

LOCK TABLES `about_block_translations` WRITE;
/*!40000 ALTER TABLE `about_block_translations` DISABLE KEYS */;
INSERT INTO `about_block_translations` VALUES (1,1,'en','Company Overview','Focused on bakery and food equipment manufacturing.','Covering cake, bread, biscuit and frying production equipment.','completed'),(2,2,'en','Sales Team','Reusable team member entity.','','completed');
/*!40000 ALTER TABLE `about_block_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `about_blocks`
--

DROP TABLE IF EXISTS `about_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `about_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `about_page_id` bigint unsigned NOT NULL,
  `block_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_zh` mediumtext COLLATE utf8mb4_unicode_ci,
  `extra_config` json DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `about_blocks`
--

LOCK TABLES `about_blocks` WRITE;
/*!40000 ALTER TABLE `about_blocks` DISABLE KEYS */;
INSERT INTO `about_blocks` VALUES (1,1,'text','企业概况','专注烘焙与食品设备制造','覆盖蛋糕、面包、饼干、油炸等产线设备，面向全球食品工厂提供定制化整线解决方案。','{}',100,1),(2,1,'team_list','销售团队','可复用团队成员实体','','{\"source\": \"team_members\"}',99,1);
/*!40000 ALTER TABLE `about_blocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `about_page_translations`
--

DROP TABLE IF EXISTS `about_page_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `about_page_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `about_page_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_about_page_translations` (`about_page_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `about_page_translations`
--

LOCK TABLES `about_page_translations` WRITE;
/*!40000 ALTER TABLE `about_page_translations` DISABLE KEYS */;
INSERT INTO `about_page_translations` VALUES (1,1,'en','About Hanzun','completed');
/*!40000 ALTER TABLE `about_page_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `about_pages`
--

DROP TABLE IF EXISTS `about_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `about_pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_about_pages_key` (`page_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `about_pages`
--

LOCK TABLES `about_pages` WRITE;
/*!40000 ALTER TABLE `about_pages` DISABLE KEYS */;
INSERT INTO `about_pages` VALUES (1,'company-about','企业介绍',1);
/*!40000 ALTER TABLE `about_pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_action_points`
--

DROP TABLE IF EXISTS `admin_action_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_action_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_action_points_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_action_points`
--

LOCK TABLES `admin_action_points` WRITE;
/*!40000 ALTER TABLE `admin_action_points` DISABLE KEYS */;
INSERT INTO `admin_action_points` VALUES (1,'查看数据看板','dashboard.view','查看数据看板'),(2,'查看首页配置','homepage.view','查看首页配置'),(3,'更新首页配置','homepage.update','更新首页配置'),(4,'查看产品','product.view','查看产品列表'),(5,'创建产品','product.create','创建产品'),(6,'更新产品','product.update','更新产品'),(7,'发布产品','product.publish','发布产品'),(8,'查看方案','solution.view','查看方案列表'),(9,'创建方案','solution.create','创建方案'),(10,'更新方案','solution.update','更新方案'),(11,'发布方案','solution.publish','发布方案'),(12,'查看文章','article.view','查看文章与案例'),(13,'创建文章','article.create','创建文章与案例'),(14,'更新文章','article.update','更新文章与案例'),(15,'发布文章','article.publish','发布文章与案例'),(16,'查看单页','page.view','查看单页与专题页'),(17,'创建单页','page.create','创建单页与专题页'),(18,'更新单页','page.update','更新单页与专题页'),(19,'发布单页','page.publish','发布单页与专题页'),(20,'查看企业介绍','about.view','查看企业介绍模块'),(21,'更新企业介绍','about.update','更新企业介绍模块'),(22,'查看团队成员','team.view','查看团队成员'),(23,'创建团队成员','team.create','创建团队成员'),(24,'更新团队成员','team.update','更新团队成员'),(25,'发布团队成员','team.publish','发布团队成员'),(26,'查看证书','certificate.view','查看证书'),(27,'创建证书','certificate.create','创建证书'),(28,'更新证书','certificate.update','更新证书'),(29,'发布证书','certificate.publish','发布证书'),(30,'查看导航','navigation.view','查看导航'),(31,'更新导航','navigation.update','更新导航'),(32,'查看询盘','inquiry.view','查看询盘'),(33,'更新询盘状态','inquiry.update','更新询盘状态'),(34,'查看翻译任务','translation.view','查看翻译任务'),(35,'重试翻译任务','translation.retry','重试翻译任务'),(36,'查看 SEO','seo.view','查看 SEO 管理'),(37,'重试 SEO 任务','seo.retry','重试 SEO 任务'),(38,'查看联系方式','contact.view','查看联系方式中心'),(39,'创建联系方式','contact.create','创建联系方式'),(40,'更新联系方式','contact.update','更新联系方式'),(41,'查看管理员','system.admin_user.view','查看管理员列表'),(42,'创建管理员','system.admin_user.create','创建管理员'),(43,'更新管理员','system.admin_user.update','更新管理员'),(44,'查看角色','system.role.view','查看角色权限'),(45,'更新角色权限','system.role.permissions.update','更新角色权限'),(46,'查看权限点','system.permission.view','查看菜单和权限点'),(47,'查看语言配置','system.languages.view','查看语言配置'),(48,'更新语言配置','system.languages.update','更新语言配置'),(49,'查看 DeepSeek 配置','system.deepseek.view','查看 DeepSeek 配置'),(50,'更新 DeepSeek 配置','system.deepseek.update','更新 DeepSeek 配置'),(51,'查看日志','system.logs.view','查看操作日志和登录日志'),(52,'查看媒体资源','media.view','查看媒体资源'),(53,'创建媒体资源','media.create','创建媒体资源'),(54,'更新媒体资源','media.update','更新媒体资源'),(55,'更新 SEO 配置','seo.update','更新 SEO 404 / robots / sitemap'),(56,'审核翻译任务','translation.approve','审核翻译任务'),(57,'查看站点配置','system.site.view','查看站点配置'),(58,'更新站点配置','system.site.update','更新站点配置'),(59,'删除管理员','system.admin_user.delete','删除管理员'),(60,'创建角色','system.role.create','创建角色'),(61,'更新角色','system.role.update','更新角色'),(62,'删除角色','system.role.delete','删除角色'),(63,'Delete contacts','contact.delete','Delete contact items and field types');
/*!40000 ALTER TABLE `admin_action_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_build_jobs`
--

DROP TABLE IF EXISTS `site_build_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_build_jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `scope` varchar(32) NOT NULL DEFAULT 'incremental',
  `trigger_source` varchar(64) NOT NULL DEFAULT 'manual',
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_id` int NOT NULL DEFAULT '0',
  `language_codes_json` longtext,
  `context_json` longtext,
  `status` varchar(32) NOT NULL DEFAULT 'queued',
  `total_steps` int NOT NULL DEFAULT '0',
  `completed_steps` int NOT NULL DEFAULT '0',
  `progress_percent` int NOT NULL DEFAULT '0',
  `current_step` varchar(120) NOT NULL DEFAULT 'queued',
  `error_message` text,
  `output_summary_json` longtext,
  `created_by` varchar(120) NOT NULL DEFAULT 'system',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_site_build_jobs_status_created` (`status`,`created_at`),
  KEY `idx_site_build_jobs_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_build_job_items`
--

DROP TABLE IF EXISTS `site_build_job_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_build_job_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `language_code` varchar(16) NOT NULL DEFAULT '',
  `page_type` varchar(64) NOT NULL DEFAULT '',
  `route` varchar(255) NOT NULL DEFAULT '',
  `output_file` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(32) NOT NULL DEFAULT 'queued',
  `error_message` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_build_job_items_job` (`job_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_phrase_translations`
--

DROP TABLE IF EXISTS `site_phrase_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_phrase_translations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phrase_key` varchar(128) NOT NULL,
  `language_code` varchar(16) NOT NULL,
  `text_value` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_phrase_translations` (`phrase_key`,`language_code`),
  KEY `idx_site_phrase_translations_language` (`language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_login_logs`
--

DROP TABLE IF EXISTS `admin_login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_login_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_success` tinyint NOT NULL DEFAULT '0',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_login_logs`
--

LOCK TABLES `admin_login_logs` WRITE;
/*!40000 ALTER TABLE `admin_login_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_login_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_login_sessions`
--

DROP TABLE IF EXISTS `admin_login_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_login_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `refresh_token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expired_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_login_sessions_code` (`session_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_login_sessions`
--

LOCK TABLES `admin_login_sessions` WRITE;
/*!40000 ALTER TABLE `admin_login_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_login_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_menus`
--

DROP TABLE IF EXISTS `admin_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned NOT NULL DEFAULT '0',
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menu',
  `sort` int NOT NULL DEFAULT '0',
  `is_visible` tinyint NOT NULL DEFAULT '1',
  `status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_menus`
--

LOCK TABLES `admin_menus` WRITE;
/*!40000 ALTER TABLE `admin_menus` DISABLE KEYS */;
INSERT INTO `admin_menus` VALUES (1,0,'数据看板','/dashboard','dashboard','dashboard','menu',100,1,1),(2,0,'首页配置','/homepage','homepage','home','menu',99,1,1),(3,0,'产品管理','/products','products','appstore','menu',98,1,1),(4,0,'生产线/方案','/solutions','solutions','deployment-unit','menu',97,1,1),(5,0,'新闻与案例','/articles','articles','read','menu',96,1,1),(6,0,'资源管理','/media','media','folder-open','menu',95,1,1),(7,0,'企业介绍','/about','about','team','menu',94,1,1),(8,0,'单页/专题页','/pages','pages','file-text','menu',93,1,1),(9,0,'询盘管理','/inquiries','inquiries','message','menu',92,1,1),(10,0,'SEO 管理','/seo-center','seo-center','search','menu',91,1,1),(11,0,'系统设置','/settings','settings','setting','menu',90,1,1);
/*!40000 ALTER TABLE `admin_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_role_action_points`
--

DROP TABLE IF EXISTS `admin_role_action_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_role_action_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `action_point_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_role_action_points` (`role_id`,`action_point_id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_role_action_points`
--

LOCK TABLES `admin_role_action_points` WRITE;
/*!40000 ALTER TABLE `admin_role_action_points` DISABLE KEYS */;
INSERT INTO `admin_role_action_points` VALUES (15,1,1),(17,1,2),(16,1,3),(32,1,4),(29,1,5),(31,1,6),(30,1,7),(39,1,8),(36,1,9),(38,1,10),(37,1,11),(6,1,12),(3,1,13),(5,1,14),(4,1,15),(28,1,16),(25,1,17),(27,1,18),(26,1,19),(2,1,20),(1,1,21),(60,1,22),(57,1,23),(59,1,24),(58,1,25),(10,1,26),(7,1,27),(9,1,28),(8,1,29),(24,1,30),(23,1,31),(19,1,32),(18,1,33),(63,1,34),(62,1,35),(35,1,36),(33,1,37),(14,1,38),(11,1,39),(13,1,40),(43,1,41),(40,1,42),(42,1,43),(54,1,44),(52,1,45),(49,1,46),(47,1,47),(46,1,48),(45,1,49),(44,1,50),(48,1,51),(22,1,52),(20,1,53),(21,1,54),(34,1,55),(61,1,56),(56,1,57),(55,1,58),(41,1,59),(50,1,60),(53,1,61),(51,1,62),(12,1,63),(75,2,1),(77,2,2),(76,2,3),(90,2,4),(88,2,5),(89,2,6),(94,2,8),(92,2,9),(93,2,10),(68,2,12),(66,2,13),(67,2,14),(87,2,16),(85,2,17),(86,2,18),(65,2,20),(64,2,21),(101,2,22),(99,2,23),(100,2,24),(71,2,26),(69,2,27),(70,2,28),(84,2,30),(83,2,31),(79,2,32),(78,2,33),(102,2,34),(91,2,36),(74,2,38),(72,2,39),(73,2,40),(98,2,47),(97,2,48),(96,2,49),(95,2,50),(82,2,52),(80,2,53),(81,2,54);
/*!40000 ALTER TABLE `admin_role_action_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_role_menus`
--

DROP TABLE IF EXISTS `admin_role_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_role_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `menu_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_role_menus` (`role_id`,`menu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_role_menus`
--

LOCK TABLES `admin_role_menus` WRITE;
/*!40000 ALTER TABLE `admin_role_menus` DISABLE KEYS */;
INSERT INTO `admin_role_menus` VALUES (1,1,1),(2,1,2),(3,1,3),(4,1,4),(5,1,5),(6,1,6),(7,1,7),(8,1,8),(9,1,9),(10,1,10),(11,1,11),(16,2,1),(17,2,2),(18,2,3),(19,2,4),(20,2,5),(21,2,6),(22,2,7),(23,2,8),(24,2,9),(25,2,10),(26,2,11);
/*!40000 ALTER TABLE `admin_role_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_roles`
--

DROP TABLE IF EXISTS `admin_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_roles_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_roles`
--

LOCK TABLES `admin_roles` WRITE;
/*!40000 ALTER TABLE `admin_roles` DISABLE KEYS */;
INSERT INTO `admin_roles` VALUES (1,'超级管理员','super-admin','拥有全部后台权限',1,'2026-06-14 00:47:25','2026-06-14 00:47:25'),(2,'操作员','operator','按菜单与动作点授权',1,'2026-06-14 00:47:25','2026-06-14 00:47:25');
/*!40000 ALTER TABLE `admin_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_roles`
--

DROP TABLE IF EXISTS `admin_user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_user_roles` (`user_id`,`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_roles`
--

LOCK TABLES `admin_user_roles` WRITE;
/*!40000 ALTER TABLE `admin_user_roles` DISABLE KEYS */;
INSERT INTO `admin_user_roles` VALUES (1,1,1);
/*!40000 ALTER TABLE `admin_user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `password_version` int NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$10$298i2nxr3RSpLU3/E32g0eVnYkLM4AtskYqDgrLd35Gf9s8gPPK7O','超级管理员','admin@hanzunmachinery.com',NULL,1,1,NULL,NULL,'2026-06-14 00:47:25','2026-06-15 12:47:27');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_conversation_daily_stats`
--

DROP TABLE IF EXISTS `ai_conversation_daily_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_conversation_daily_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_sessions` int NOT NULL DEFAULT '0',
  `valid_sessions` int NOT NULL DEFAULT '0',
  `created_inquiries` int NOT NULL DEFAULT '0',
  `lead_capture_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_conversation_daily_stats` (`stat_date`,`language_code`,`country_code`,`source_page`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_conversation_daily_stats`
--

LOCK TABLES `ai_conversation_daily_stats` WRITE;
/*!40000 ALTER TABLE `ai_conversation_daily_stats` DISABLE KEYS */;
INSERT INTO `ai_conversation_daily_stats` VALUES (1,'2026-06-14','en','DE','/en',36,18,7,38.89),(2,'2026-06-14','en','AE','/en/products',22,11,5,45.45);
/*!40000 ALTER TABLE `ai_conversation_daily_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_categories`
--

DROP TABLE IF EXISTS `article_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned NOT NULL DEFAULT '0',
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type_scope` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_categories`
--

LOCK TABLES `article_categories` WRITE;
/*!40000 ALTER TABLE `article_categories` DISABLE KEYS */;
INSERT INTO `article_categories` VALUES (1,0,'展会交流','news',100,1),(2,0,'客户案例','case',99,1),(3,2,'中东案例','case',98,1);
/*!40000 ALTER TABLE `article_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_category_translations`
--

DROP TABLE IF EXISTS `article_category_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_category_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_category_translations` (`category_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_category_translations`
--

LOCK TABLES `article_category_translations` WRITE;
/*!40000 ALTER TABLE `article_category_translations` DISABLE KEYS */;
INSERT INTO `article_category_translations` VALUES (1,1,'en','Exhibitions','completed'),(2,2,'en','Customer Cases','completed'),(3,3,'en','Middle East Cases','completed');
/*!40000 ALTER TABLE `article_category_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_translations`
--

DROP TABLE IF EXISTS `article_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `article_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_translations` (`article_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_translations`
--

LOCK TABLES `article_translations` WRITE;
/*!40000 ALTER TABLE `article_translations` DISABLE KEYS */;
INSERT INTO `article_translations` VALUES (1,1,'en','Hanzun at the Germany Bakery Expo','Showcasing automated cake and bread line solutions.','The exhibition focused on turnkey line delivery and key equipment modules for overseas bakery factories.','completed'),(2,2,'en','UAE Cake Project Delivery','Delivered a turnkey project from preparation to packing.','The project covered depositing, baking, cooling and packing for a full cake production workflow.','completed');
/*!40000 ALTER TABLE `article_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `content_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary_zh` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_zh` mediumtext COLLATE utf8mb4_unicode_ci,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `case_tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_solution_ids` json DEFAULT NULL,
  `related_product_ids` json DEFAULT NULL,
  `publish_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `seo_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_home_featured` tinyint NOT NULL DEFAULT '0',
  `manual_sort` int NOT NULL DEFAULT '0',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish_time` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_articles_type_slug` (`content_type`,`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles`
--

LOCK TABLES `articles` WRITE;
/*!40000 ALTER TABLE `articles` DISABLE KEYS */;
INSERT INTO `articles` VALUES (1,1,'news','涵尊参加德国烘焙展','展示蛋糕、面包自动化产线解决方案。','本次展会重点展示整线方案和关键设备模块，面向欧洲客户演示交付能力。',NULL,NULL,'[]','[]','published','completed','generated',1,100,'germany-bakery-expo','涵尊参加德国烘焙展','展会,烘焙设备','涵尊参加德国烘焙展新闻，展示海外市场整线方案。','2026-06-14 00:47:25',1,1,'2026-06-14 00:47:25','2026-06-14 00:47:25'),(2,3,'case','阿联酋客户蛋糕项目交付','完成从配料到包装的交钥匙项目。','项目覆盖蛋糕灌装、烘烤、冷却、包装全流程，支持海外安装调试。','AE','蛋糕,出口,交钥匙','[1]','[1]','published','completed','generated',1,99,'uae-cake-project','阿联酋客户蛋糕项目交付','客户案例,蛋糕项目','涵尊阿联酋客户蛋糕项目案例，覆盖整线交付。','2026-06-14 00:47:25',1,1,'2026-06-14 00:47:25','2026-06-14 00:47:25');
/*!40000 ALTER TABLE `articles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificate_translations`
--

DROP TABLE IF EXISTS `certificate_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `certificate_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_certificate_translations` (`certificate_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_translations`
--

LOCK TABLES `certificate_translations` WRITE;
/*!40000 ALTER TABLE `certificate_translations` DISABLE KEYS */;
INSERT INTO `certificate_translations` VALUES (1,1,'en','CE Certification','EU Certification Body','Applicable to equipment projects exported to Europe.','completed'),(2,2,'en','ISO 9001','TUV Rheinland','Used for quality system presentation and overseas project qualification support.','completed');
/*!40000 ALTER TABLE `certificate_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuer_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_no` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_zh` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_asset_id` bigint unsigned DEFAULT NULL,
  `publish_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `seo_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_home_featured` tinyint NOT NULL DEFAULT '0',
  `manual_sort` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,'CE 认证','欧盟认证机构',NULL,NULL,'用于展示设备出口相关合规资质。',3,'published','completed','generated',1,100,'2026-06-14 00:47:26','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `message_role` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_language` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `translated_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `intent_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contains_contact_info` tinyint NOT NULL DEFAULT '0',
  `extracted_entities_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_created_at` (`created_at`),
  KEY `idx_chat_messages_created_at_intent` (`created_at`,`intent_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_sessions`
--

DROP TABLE IF EXISTS `chat_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ai',
  `source_page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entry_language` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_language` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `utm_source` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_valid_conversation` tinyint NOT NULL DEFAULT '0',
  `inquiry_id` bigint unsigned DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_sessions_code` (`session_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_sessions`
--

LOCK TABLES `chat_sessions` WRITE;
/*!40000 ALTER TABLE `chat_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_field_type_translations`
--

DROP TABLE IF EXISTS `contact_field_type_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_field_type_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_type_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_field_type_translations` (`field_type_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_field_type_translations`
--

LOCK TABLES `contact_field_type_translations` WRITE;
/*!40000 ALTER TABLE `contact_field_type_translations` DISABLE KEYS */;
INSERT INTO `contact_field_type_translations` VALUES (1,1,'en','Email','completed'),(2,2,'en','Phone','completed'),(3,3,'en','WhatsApp','completed');
/*!40000 ALTER TABLE `contact_field_type_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_field_types`
--

DROP TABLE IF EXISTS `contact_field_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_field_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_zh` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validation_rule` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_field_types_key` (`field_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_field_types`
--

LOCK TABLES `contact_field_types` WRITE;
/*!40000 ALTER TABLE `contact_field_types` DISABLE KEYS */;
INSERT INTO `contact_field_types` VALUES (1,'email','邮箱','mail','email',100,1),(2,'phone','电话','phone','phone',99,1),(3,'whatsapp','WhatsApp','message','text',98,1);
/*!40000 ALTER TABLE `contact_field_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_item_translations`
--

DROP TABLE IF EXISTS `contact_item_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_item_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contact_item_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_item_translations` (`contact_item_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_item_translations`
--

LOCK TABLES `contact_item_translations` WRITE;
/*!40000 ALTER TABLE `contact_item_translations` DISABLE KEYS */;
INSERT INTO `contact_item_translations` VALUES (1,1,'en','Business Email','Used for overseas inquiry contact','completed'),(2,2,'en','Factory Switchboard','Working hours 09:00-18:00','completed'),(3,3,'en','Overseas WhatsApp','Sales team online reception','completed');
/*!40000 ALTER TABLE `contact_item_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_items`
--

DROP TABLE IF EXISTS `contact_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_type_id` bigint unsigned NOT NULL,
  `label_zh` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_scope` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contact_page',
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_items`
--

LOCK TABLES `contact_items` WRITE;
/*!40000 ALTER TABLE `contact_items` DISABLE KEYS */;
INSERT INTO `contact_items` VALUES (1,1,'商务邮箱','hanzunkunshanmachinery@gmail.com','用于海外询盘联系','contact_page',100,1),(2,2,'工厂总机','+85253441653','工作时间 09:00-18:00','footer',99,1),(3,3,'海外 WhatsApp','+85253441653','销售团队在线接待','footer',98,1);
/*!40000 ALTER TABLE `contact_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_section_translations`
--

DROP TABLE IF EXISTS `homepage_section_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homepage_section_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_homepage_section_translations` (`section_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_section_translations`
--

LOCK TABLES `homepage_section_translations` WRITE;
/*!40000 ALTER TABLE `homepage_section_translations` DISABLE KEYS */;
INSERT INTO `homepage_section_translations` VALUES (1,1,'en','Hero Banner','Showcase turnkey lines and standalone equipment for overseas buyers','View Solutions','completed'),(2,2,'en','Featured Equipment','Auto aggregate homepage featured products',NULL,'completed'),(3,3,'en','Featured Solutions','Auto aggregate homepage featured solutions',NULL,'completed'),(4,4,'en','News and Cases','Auto surface featured news and customer stories',NULL,'completed');
/*!40000 ALTER TABLE `homepage_section_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_sections`
--

DROP TABLE IF EXISTS `homepage_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homepage_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fetch_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed_config',
  `extra_config` json DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_homepage_sections_key` (`section_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_sections`
--

LOCK TABLES `homepage_sections` WRITE;
/*!40000 ALTER TABLE `homepage_sections` DISABLE KEYS */;
INSERT INTO `homepage_sections` VALUES (1,'hero','fixed_config','首页主视觉','面向海外客户展示整线与单机设备能力','fixed_config','{\"cta_text\": \"查看方案\"}',100,1),(2,'featured_products','product_list','推荐设备','按首页推荐位自动聚合','auto_latest','{\"limit\": 6}',99,1),(3,'featured_solutions','solution_list','推荐方案','按首页推荐位自动聚合','auto_latest','{\"limit\": 4}',98,1),(4,'featured_articles','article_list','新闻与案例','自动展示重点资讯与客户案例','auto_latest','{\"limit\": 6}',97,1);
/*!40000 ALTER TABLE `homepage_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inquiries`
--

DROP TABLE IF EXISTS `inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inquiries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ai',
  `session_id` bigint unsigned NOT NULL,
  `primary_contact_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `primary_contact_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_interest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solution_interest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requirement_summary` text COLLATE utf8mb4_unicode_ci,
  `inquiry_score` decimal(5,2) DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `assigned_to` bigint unsigned DEFAULT NULL,
  `first_response_at` datetime DEFAULT NULL,
  `browse_traces` json DEFAULT NULL,
  `change_logs` json DEFAULT NULL,
  `follow_ups` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiries`
--

LOCK TABLES `inquiries` WRITE;
/*!40000 ALTER TABLE `inquiries` DISABLE KEYS */;
/*!40000 ALTER TABLE `inquiries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inquiry_daily_stats`
--

DROP TABLE IF EXISTS `inquiry_daily_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inquiry_daily_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_count` int NOT NULL DEFAULT '0',
  `avg_first_response_minutes` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inquiry_daily_stats` (`stat_date`,`country_code`,`language_code`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiry_daily_stats`
--

LOCK TABLES `inquiry_daily_stats` WRITE;
/*!40000 ALTER TABLE `inquiry_daily_stats` DISABLE KEYS */;
INSERT INTO `inquiry_daily_stats` VALUES (1,'2026-06-14','DE','en','new',4,26.50),(2,'2026-06-14','DE','en','quoting',2,35.00),(3,'2026-06-14','AE','en','won',1,18.00),(4,'2026-06-14','AE','en','closed',1,60.00);
/*!40000 ALTER TABLE `inquiry_daily_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  `sort` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_languages_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (1,'zh','简体中文',1,1,100),(2,'en','English',0,1,90);
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_snapshots`
--

DROP TABLE IF EXISTS `lead_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `snapshot_version` int NOT NULL DEFAULT '1',
  `contact_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_interest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solution_interest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requirement_summary` text COLLATE utf8mb4_unicode_ci,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_snapshots`
--

LOCK TABLES `lead_snapshots` WRITE;
/*!40000 ALTER TABLE `lead_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media_assets`
--

DROP TABLE IF EXISTS `media_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `folder_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folder_id` bigint unsigned NOT NULL DEFAULT '0',
  `storage_disk` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumb_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_ext` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `sha1` char(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `duration_seconds` int DEFAULT NULL,
  `alt_text_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_folder_id` (`folder_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_assets`
--

LOCK TABLES `media_assets` WRITE;
/*!40000 ALTER TABLE `media_assets` DISABLE KEYS */;
INSERT INTO `media_assets` VALUES (2,'manuals',2,'local','/uploads/manuals/cake-line.pdf',NULL,'cake-line.pdf','cake-line.pdf','pdf','application/pdf',4821932,NULL,NULL,NULL,NULL,'蛋糕产线 PDF 手册','蛋糕自动生产线技术手册',1,1,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(3,'certificates',3,'local','/assets/images/certificates/cert-1.png',NULL,'cert-1.png','cert-1.png','png','image/png',416650,NULL,1280,920,NULL,'CE 认证证书','企业出口资质证书',1,1,'2026-06-14 00:47:26','2026-06-15 00:27:34'),(4,'team',4,'local','/assets/images/team/sales-amy-zhang.png',NULL,'sales-amy-zhang.png','sales-amy-zhang.png','png','image/png',2026652,NULL,1024,1024,NULL,'销售团队头像','销售团队成员头像',1,1,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(7,'test',0,'local','/uploads/videos/d3034685e93ecd52a9acd4808eacdec7.mp4',NULL,'d3034685e93ecd52a9acd4808eacdec7.mp4','test.mp4','mp4','video/mp4',27980509,'e1ae740ad33adb01e37de12e7666d5cc0dfe3a17',NULL,NULL,NULL,'','',1,1,'2026-06-15 01:04:24','2026-06-15 01:04:24'),(13,'misc',0,'local','/uploads/videos/24f44db85611202e89fbbe1b1eddacf8.mp4',NULL,'24f44db85611202e89fbbe1b1eddacf8.mp4','468da1a62fdd444eba7be36f0c4f6662.mp4','mp4','video/mp4',2517156,'78bea50d1bc335242eb67e333ce96adfd85cb48a',NULL,NULL,NULL,'','',1,1,'2026-06-15 01:05:40','2026-06-15 01:05:40');
/*!40000 ALTER TABLE `media_assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media_folders`
--

DROP TABLE IF EXISTS `media_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_folders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned NOT NULL DEFAULT '0',
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_folders`
--

LOCK TABLES `media_folders` WRITE;
/*!40000 ALTER TABLE `media_folders` DISABLE KEYS */;
INSERT INTO `media_folders` VALUES (1,0,'products',0,'2026-06-14 00:47:26'),(2,0,'manuals',1,'2026-06-14 00:47:26'),(3,0,'certificates',2,'2026-06-14 00:47:26'),(4,0,'team',3,'2026-06-14 00:47:26'),(6,1,'测试',0,'2026-06-14 23:20:03');
/*!40000 ALTER TABLE `media_folders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `navigation_item_translations`
--

DROP TABLE IF EXISTS `navigation_item_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `navigation_item_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_navigation_item_translations` (`item_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `navigation_item_translations`
--

LOCK TABLES `navigation_item_translations` WRITE;
/*!40000 ALTER TABLE `navigation_item_translations` DISABLE KEYS */;
INSERT INTO `navigation_item_translations` VALUES (1,1,'en','Products','completed'),(2,2,'en','Cake Equipment','completed'),(3,3,'en','Solutions','completed'),(4,4,'en','About Us','completed'),(5,5,'en','Contact','completed');
/*!40000 ALTER TABLE `navigation_item_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `navigation_items`
--

DROP TABLE IF EXISTS `navigation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `navigation_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned NOT NULL DEFAULT '0',
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_key` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_url',
  `link_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `linked_entity_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linked_entity_id` bigint unsigned DEFAULT NULL,
  `root_category_id` bigint unsigned DEFAULT NULL,
  `max_depth` tinyint NOT NULL DEFAULT '1',
  `include_children` tinyint NOT NULL DEFAULT '0',
  `display_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'plain',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `open_in_new_tab` tinyint NOT NULL DEFAULT '0',
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `navigation_items`
--

LOCK TABLES `navigation_items` WRITE;
/*!40000 ALTER TABLE `navigation_items` DISABLE KEYS */;
INSERT INTO `navigation_items` VALUES (1,1,0,'产品中心','products','products','auto_category_tree','category_tree','product_category',1,1,2,1,'dropdown','',0,100,1,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(2,1,1,'蛋糕设备','cake-equipment','products/cake-equipment','auto_category_tree','category_tree','product_category',2,2,1,1,'plain','',0,99,1,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(3,1,0,'生产线方案','solutions','solutions','auto_category_tree','category_tree','solution_category',1,1,3,1,'flyout','',0,98,1,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(4,1,0,'企业介绍','about','about','about_page','page','about_page',1,NULL,1,0,'plain','/about',0,97,1,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(5,2,0,'联系我们','contact','contact','manual_url','manual_url','custom_url',NULL,NULL,1,0,'plain','/contact',0,100,1,'2026-06-14 00:47:26','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `navigation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `navigation_menu_translations`
--

DROP TABLE IF EXISTS `navigation_menu_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `navigation_menu_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_navigation_menu_translations` (`menu_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `navigation_menu_translations`
--

LOCK TABLES `navigation_menu_translations` WRITE;
/*!40000 ALTER TABLE `navigation_menu_translations` DISABLE KEYS */;
INSERT INTO `navigation_menu_translations` VALUES (1,1,'en','Main Navigation','completed'),(2,2,'en','Footer Navigation','completed');
/*!40000 ALTER TABLE `navigation_menu_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `navigation_menus`
--

DROP TABLE IF EXISTS `navigation_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `navigation_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_position` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'header',
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_navigation_menus_key` (`menu_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `navigation_menus`
--

LOCK TABLES `navigation_menus` WRITE;
/*!40000 ALTER TABLE `navigation_menus` DISABLE KEYS */;
INSERT INTO `navigation_menus` VALUES (1,'顶部主导航','main-header','header',100,1,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(2,'页脚导航','footer-links','footer',90,1,'2026-06-14 00:47:26','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `navigation_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `operation_logs`
--

DROP TABLE IF EXISTS `operation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `operator_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_point` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_method` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_payload_masked` json DEFAULT NULL,
  `before_snapshot` json DEFAULT NULL,
  `after_snapshot` json DEFAULT NULL,
  `result_code` int NOT NULL DEFAULT '0',
  `result_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_success` tinyint NOT NULL DEFAULT '1',
  `duration_ms` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `operation_logs`
--

LOCK TABLES `operation_logs` WRITE;
/*!40000 ALTER TABLE `operation_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `operation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `page_translations`
--

DROP TABLE IF EXISTS `page_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_page_translations` (`page_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_translations`
--

LOCK TABLES `page_translations` WRITE;
/*!40000 ALTER TABLE `page_translations` DISABLE KEYS */;
INSERT INTO `page_translations` VALUES (1,1,'en','Cake Line Landing Page','Marketing page for export cake line projects.','Use this landing page to present process flow, capacity and delivery capabilities for overseas buyers.','completed');
/*!40000 ALTER TABLE `page_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'page',
  `title_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary_zh` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_zh` mediumtext COLLATE utf8mb4_unicode_ci,
  `publish_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `seo_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish_time` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pages_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'landing','蛋糕产线专题页','面向海外客户展示蛋糕产线能力。','聚合产品、方案、案例与联系方式，服务海外线索转化。','published','completed','generated','cake-line-landing','蛋糕产线专题页','专题页,蛋糕产线','蛋糕产线专题页，聚合产品方案与客户案例。','2026-06-14 00:47:25',1,1,'2026-06-14 00:47:25','2026-06-14 00:47:25');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned NOT NULL DEFAULT '0',
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (1,0,'中式食品加工机械','chinese-food-processing',100,1),(2,0,'蛋糕制作机','cake-making-machine',99,1),(3,0,'面包制作机','bread-making-machine',98,1),(4,0,'烘焙成品机','baked-product-machine',97,1),(5,0,'食品成型机','food-forming-machine',96,1),(6,0,'食品切片机','food-slicing-machine',95,1),(7,0,'食品充填机','food-filling-machine',94,1),(8,0,'食品摆盘机','food-plating-machine',93,1),(9,0,'食品搅拌机','food-mixing-machine',92,1),(10,0,'其它烘焙机','other-bakery-machine',91,1);
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_category_translations`
--

DROP TABLE IF EXISTS `product_category_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_category_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_category_translations` (`category_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_category_translations`
--

LOCK TABLES `product_category_translations` WRITE;
/*!40000 ALTER TABLE `product_category_translations` DISABLE KEYS */;
INSERT INTO `product_category_translations` VALUES (44,1,'en','Chinese Food Processing Machinery','completed'),(45,2,'en','Cake Making Machine','completed'),(46,3,'en','Bread Making Machine','completed'),(47,4,'en','Baked Product Machine','completed'),(48,5,'en','Food Forming Machine','completed'),(49,6,'en','Food Slicing Machine','completed'),(50,7,'en','Food Filling Machine','completed'),(51,8,'en','Food Plating Machine','completed'),(52,9,'en','Food Mixing Machine','completed'),(53,10,'en','Other Bakery Machine','completed');
/*!40000 ALTER TABLE `product_category_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_translations`
--

DROP TABLE IF EXISTS `product_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_translations` (`product_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_translations`
--

LOCK TABLES `product_translations` WRITE;
/*!40000 ALTER TABLE `product_translations` DISABLE KEYS */;
INSERT INTO `product_translations` VALUES (1,1,'en','Cake Automatic Filling Machine','<p>Core equipment for quantitative filling of cake batter. 111</p>','<p>Supports multi-station linkage, suitable for automated feeding and filling scenarios in cake production lines.</p>','completed');
/*!40000 ALTER TABLE `product_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary_zh` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_zh` mediumtext COLLATE utf8mb4_unicode_ci,
  `business_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'on_sale',
  `publish_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `seo_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_home_featured` tinyint NOT NULL DEFAULT '0',
  `manual_sort` int NOT NULL DEFAULT '0',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish_time` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_sku` (`sku`),
  UNIQUE KEY `uk_products_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,0,'HZ-CAKE-001','蛋糕自动灌装机','用于蛋糕浆料定量灌装的核心设备。','支持多工位联动，适用于蛋糕产线的自动化投料与灌装场景。','on_sale','published','completed','generated',1,100,'cake-depositor','蛋糕自动灌装机','蛋糕设备,灌装机','涵尊实业蛋糕自动灌装机，适用于烘焙食品自动化生产。','2026-06-14 00:47:25',1,1,'2026-06-14 00:47:25','2026-06-15 10:36:02');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seo_404_logs`
--

DROP TABLE IF EXISTS `seo_404_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_404_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referrer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seo_404_logs`
--

LOCK TABLES `seo_404_logs` WRITE;
/*!40000 ALTER TABLE `seo_404_logs` DISABLE KEYS */;
INSERT INTO `seo_404_logs` VALUES (1,'/en/old-cake-line','https://google.com','en','AE','Mozilla/5.0','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `seo_404_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seo_generation_jobs`
--

DROP TABLE IF EXISTS `seo_generation_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_generation_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'zh',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `retry_count` int NOT NULL DEFAULT '0',
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_seo_generation_jobs_entity_lang` (`entity_type`,`entity_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seo_generation_jobs`
--

LOCK TABLES `seo_generation_jobs` WRITE;
/*!40000 ALTER TABLE `seo_generation_jobs` DISABLE KEYS */;
INSERT INTO `seo_generation_jobs` VALUES (1,'product',1,'zh','completed',0,NULL,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(2,'page',1,'zh','pending',0,NULL,'2026-06-14 00:47:26','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `seo_generation_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seo_routes`
--

DROP TABLE IF EXISTS `seo_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_routes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `index_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'index',
  `last_generated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_seo_routes_entity_lang` (`entity_type`,`entity_id`,`language_code`),
  UNIQUE KEY `uk_seo_routes_path` (`route_path`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seo_routes`
--

LOCK TABLES `seo_routes` WRITE;
/*!40000 ALTER TABLE `seo_routes` DISABLE KEYS */;
INSERT INTO `seo_routes` VALUES (1,'product',1,'en','/en/products/cake-depositor','cake-depositor','Cake Depositor','cake depositor,bakery equipment','Automatic cake depositor for industrial bakery lines.','/en/products/cake-depositor','index','2026-06-14 00:47:26'),(2,'solution',1,'en','/en/solutions/cake-line','cake-line','Cake Automatic Production Line','cake line,production line','Turnkey cake automatic production line for export projects.','/en/solutions/cake-line','index','2026-06-14 00:47:26'),(3,'article',2,'en','/en/cases/uae-cake-project','uae-cake-project','UAE Cake Project','uae,cake project','Customer case for UAE cake turnkey delivery.','/en/cases/uae-cake-project','index','2026-06-14 00:47:26'),(4,'page',1,'en','/en/landing/cake-line-landing','cake-line-landing','Cake Line Landing Page','cake line landing','Landing page for cake line marketing.','/en/landing/cake-line-landing','index','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `seo_routes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solution_categories`
--

DROP TABLE IF EXISTS `solution_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solution_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned NOT NULL DEFAULT '0',
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `is_enabled` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solution_categories`
--

LOCK TABLES `solution_categories` WRITE;
/*!40000 ALTER TABLE `solution_categories` DISABLE KEYS */;
INSERT INTO `solution_categories` VALUES (1,0,'定制烘焙生产线','custom-bakery-line',100,1);
/*!40000 ALTER TABLE `solution_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solution_category_translations`
--

DROP TABLE IF EXISTS `solution_category_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solution_category_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_solution_category_translations` (`category_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solution_category_translations`
--

LOCK TABLES `solution_category_translations` WRITE;
/*!40000 ALTER TABLE `solution_category_translations` DISABLE KEYS */;
INSERT INTO `solution_category_translations` VALUES (6,1,'en','Custom Bakery Production Line','completed');
/*!40000 ALTER TABLE `solution_category_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solution_translations`
--

DROP TABLE IF EXISTS `solution_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solution_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `solution_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci,
  `flow_text` text COLLATE utf8mb4_unicode_ci,
  `capacity_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_solution_translations` (`solution_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solution_translations`
--

LOCK TABLES `solution_translations` WRITE;
/*!40000 ALTER TABLE `solution_translations` DISABLE KEYS */;
INSERT INTO `solution_translations` VALUES (1,1,'en','Cake Automatic Production Line','Automated turnkey line for medium and large cake factories.','Covers mixing, depositing, baking, cooling and packing in one coordinated workflow.','Feeding -> Mixing -> Depositing -> Baking -> Cooling -> Packing','6000 pcs/h','completed');
/*!40000 ALTER TABLE `solution_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solutions`
--

DROP TABLE IF EXISTS `solutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solutions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary_zh` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_zh` mediumtext COLLATE utf8mb4_unicode_ci,
  `flow_text_zh` text COLLATE utf8mb4_unicode_ci,
  `capacity_text_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manual_asset_id` bigint unsigned DEFAULT NULL,
  `publish_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `seo_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_home_featured` tinyint NOT NULL DEFAULT '0',
  `manual_sort` int NOT NULL DEFAULT '0',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish_time` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_solutions_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solutions`
--

LOCK TABLES `solutions` WRITE;
/*!40000 ALTER TABLE `solutions` DISABLE KEYS */;
INSERT INTO `solutions` VALUES (1,0,'蛋糕自动生产线','覆盖配料、灌装、烘烤、冷却与包装的整线方案。','适用于出口型蛋糕工厂，支持产能定制、现场安装和交钥匙交付。','配料 -> 打发 -> 灌装 -> 烘烤 -> 冷却 -> 包装','6000 pcs/h',2,'published','completed','generated',1,100,'cake-line','蛋糕自动生产线','蛋糕产线,自动化方案','涵尊蛋糕自动生产线方案，适用于海外食品工厂整线建设。','2026-06-14 00:47:25',1,1,'2026-06-14 00:47:25','2026-06-15 10:36:02');
/*!40000 ALTER TABLE `solutions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `setting_group` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` json NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_settings` (`setting_group`,`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'deepseek','config','{\"model\": \"deepseek-chat\", \"api_key\": \"\", \"base_url\": \"https://api.deepseek.com/v1\", \"retry_times\": 2, \"seo_enabled\": 1, \"chat_enabled\": 1, \"timeout_seconds\": 30, \"translation_enabled\": 1}','2026-06-14 00:47:25'),(2,'site','basic','{\"site_name\": \"上海涵尊实业有限公司官网\", \"default_language\": \"zh\", \"auto_detect_language\": 1}','2026-06-14 00:47:25'),(3,'homepage','publish_meta','{\"publish_log\": [], \"live_updated_at\": null, \"draft_updated_at\": null, \"last_restored_by\": \"\", \"last_published_by\": \"\", \"has_unpublished_changes\": 0}','2026-06-14 00:47:25'),(4,'homepage','published_snapshot','{\"sections\": [], \"featured_pool\": {\"article\": [], \"product\": [], \"solution\": []}}','2026-06-14 00:47:25'),(5,'content_live','product_publish_meta','{\"1\": {\"publish_log\": [{\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 20:42:03\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 20:41:57\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 20:41:46\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 20:34:19\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 20:34:09\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 20:32:49\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 15:42:46\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 15:42:42\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 15:02:57\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 14:51:43\"}], \"live_updated_at\": null, \"draft_updated_at\": \"2026-06-15 20:42:03\", \"last_restored_by\": \"\", \"last_published_by\": \"\"}, \"2\": {\"publish_log\": [{\"action\": \"draft_update\", \"message\": \"product created\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 15:33:24\"}], \"live_updated_at\": null, \"draft_updated_at\": \"2026-06-15 15:33:24\", \"last_restored_by\": \"\", \"last_published_by\": \"\"}, \"3\": {\"publish_log\": [{\"action\": \"draft_update\", \"message\": \"product created\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 15:35:36\"}], \"live_updated_at\": null, \"draft_updated_at\": \"2026-06-15 15:35:36\", \"last_restored_by\": \"\", \"last_published_by\": \"\"}, \"4\": {\"publish_log\": [{\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 16:01:32\"}, {\"action\": \"draft_update\", \"message\": \"product draft updated\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 15:57:44\"}, {\"action\": \"draft_update\", \"message\": \"product created\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 15:55:46\"}], \"live_updated_at\": null, \"draft_updated_at\": \"2026-06-15 16:01:32\", \"last_restored_by\": \"\", \"last_published_by\": \"\"}, \"5\": {\"publish_log\": [{\"action\": \"draft_update\", \"message\": \"product created\", \"operator\": \"超级管理员\", \"created_at\": \"2026-06-15 16:04:52\"}], \"live_updated_at\": null, \"draft_updated_at\": \"2026-06-15 16:04:52\", \"last_restored_by\": \"\", \"last_published_by\": \"\"}}','2026-06-15 12:42:03');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_member_translations`
--

DROP TABLE IF EXISTS `team_member_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_member_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_member_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_team_member_translations` (`team_member_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_member_translations`
--

LOCK TABLES `team_member_translations` WRITE;
/*!40000 ALTER TABLE `team_member_translations` DISABLE KEYS */;
INSERT INTO `team_member_translations` VALUES (1,1,'en','Daniel Chen','Overseas Sales Manager','International Business Department','Responsible for consultation, inquiry follow-up and quotation communication across Middle East and European bakery projects.','completed');
/*!40000 ALTER TABLE `team_member_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_members`
--

DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name_zh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_zh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio_zh` text COLLATE utf8mb4_unicode_ci,
  `avatar_asset_id` bigint unsigned DEFAULT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wechat` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `translation_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_home_featured` tinyint NOT NULL DEFAULT '0',
  `manual_sort` int NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_members`
--

LOCK TABLES `team_members` WRITE;
/*!40000 ALTER TABLE `team_members` DISABLE KEYS */;
INSERT INTO `team_members` VALUES (1,'Amy Zhang','海外销售经理','国际销售部','负责海外客户需求梳理、方案匹配、报价推进与交付协同。',4,'amy.zhang@hanzunmachinery.com','+8615216813602','+8615216813602',NULL,'published','completed',1,100,1,1,'2026-06-14 00:47:26','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `team_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `traffic_daily_stats`
--

DROP TABLE IF EXISTS `traffic_daily_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `traffic_daily_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uv` int NOT NULL DEFAULT '0',
  `pv` int NOT NULL DEFAULT '0',
  `bounce_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_traffic_daily_stats` (`stat_date`,`language_code`,`country_code`,`source`,`landing_page`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `traffic_daily_stats`
--

LOCK TABLES `traffic_daily_stats` WRITE;
/*!40000 ALTER TABLE `traffic_daily_stats` DISABLE KEYS */;
INSERT INTO `traffic_daily_stats` VALUES (1,'2026-06-14','en','DE','organic','/en',123,356,38.50),(2,'2026-06-14','en','AE','direct','/en/products',76,201,42.10);
/*!40000 ALTER TABLE `traffic_daily_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `translation_jobs`
--

DROP TABLE IF EXISTS `translation_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `translation_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `language_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `retry_count` int NOT NULL DEFAULT '0',
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_translation_jobs_entity_lang` (`entity_type`,`entity_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `translation_jobs`
--

LOCK TABLES `translation_jobs` WRITE;
/*!40000 ALTER TABLE `translation_jobs` DISABLE KEYS */;
INSERT INTO `translation_jobs` VALUES (1,'product',1,'en','completed',0,NULL,'2026-06-14 00:47:26','2026-06-14 00:47:26'),(2,'article',2,'en','pending',0,NULL,'2026-06-14 00:47:26','2026-06-14 00:47:26');
/*!40000 ALTER TABLE `translation_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visitor_events`
--

DROP TABLE IF EXISTS `visitor_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visitor_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visited_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `language_code` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_visitor_events_session_code` (`session_code`),
  KEY `idx_visitor_events_visited_at` (`visited_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitor_events`
--

LOCK TABLES `visitor_events` WRITE;
/*!40000 ALTER TABLE `visitor_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `visitor_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'hanzun_cms'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-16  7:29:20
