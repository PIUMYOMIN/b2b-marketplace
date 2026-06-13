/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `content` text DEFAULT NULL,
  `type` varchar(191) NOT NULL DEFAULT 'announcement',
  `display_style` varchar(191) NOT NULL DEFAULT 'popup_card',
  `banner_link_url` varchar(191) DEFAULT NULL,
  `banner_aspect_ratio` varchar(191) NOT NULL DEFAULT '16:9',
  `image` varchar(191) DEFAULT NULL,
  `cta_label` varchar(191) DEFAULT NULL,
  `cta_url` varchar(191) DEFAULT NULL,
  `cta_style` varchar(191) NOT NULL DEFAULT 'primary',
  `badge_label` varchar(191) DEFAULT NULL,
  `badge_color` varchar(191) NOT NULL DEFAULT 'green',
  `target_audience` varchar(191) NOT NULL DEFAULT 'all',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_once` tinyint(1) NOT NULL DEFAULT 1,
  `delay_seconds` int(11) NOT NULL DEFAULT 1,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_is_active_starts_at_ends_at_index` (`is_active`,`starts_at`,`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `title_en` varchar(191) NOT NULL,
  `title_mm` varchar(191) DEFAULT NULL,
  `slug` varchar(191) NOT NULL,
  `excerpt_en` text DEFAULT NULL,
  `excerpt_mm` text DEFAULT NULL,
  `content_en` longtext NOT NULL,
  `content_mm` longtext DEFAULT NULL,
  `featured_image` varchar(191) DEFAULT NULL,
  `category` varchar(191) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `status` varchar(191) NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `seo_title_en` varchar(191) DEFAULT NULL,
  `seo_title_mm` varchar(191) DEFAULT NULL,
  `seo_description_en` text DEFAULT NULL,
  `seo_description_mm` text DEFAULT NULL,
  `views` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_slug_unique` (`slug`),
  KEY `blog_posts_author_id_foreign` (`author_id`),
  KEY `blog_posts_category_index` (`category`),
  KEY `blog_posts_status_index` (`status`),
  KEY `blog_posts_is_featured_index` (`is_featured`),
  KEY `blog_posts_published_at_index` (`published_at`),
  CONSTRAINT `blog_posts_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `business_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(191) NOT NULL,
  `name_mm` varchar(191) DEFAULT NULL,
  `slug_en` varchar(191) NOT NULL,
  `slug_mm` varchar(191) NOT NULL,
  `description_en` text DEFAULT NULL,
  `description_mm` text DEFAULT NULL,
  `requires_registration` tinyint(1) NOT NULL DEFAULT 0,
  `requires_tax_document` tinyint(1) NOT NULL DEFAULT 0,
  `requires_identity_document` tinyint(1) NOT NULL DEFAULT 1,
  `requires_business_certificate` tinyint(1) NOT NULL DEFAULT 0,
  `additional_requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_requirements`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(191) DEFAULT NULL,
  `color` varchar(191) DEFAULT '#3B82F6',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `monthly_fee` decimal(12,2) DEFAULT 0.00,
  `transaction_fee` decimal(5,2) DEFAULT 0.00,
  `minimum_sale_amount` decimal(12,2) DEFAULT 0.00,
  `verification_level` varchar(191) NOT NULL DEFAULT 'basic',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_types_slug_en_unique` (`slug_en`),
  UNIQUE KEY `business_types_slug_mm_unique` (`slug_mm`),
  KEY `business_types_slug_en_index` (`slug_en`),
  KEY `business_types_slug_mm_index` (`slug_mm`),
  KEY `business_types_is_active_index` (`is_active`),
  KEY `business_types_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(191) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(191) NOT NULL,
  `owner` varchar(191) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned DEFAULT NULL,
  `selected_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selected_options`)),
  `quantity` decimal(14,3) NOT NULL DEFAULT 1.000,
  `quantity_unit` varchar(191) NOT NULL DEFAULT 'piece',
  `price` decimal(12,2) NOT NULL,
  `product_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`product_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `carts_user_id_product_id_variant_id_unique` (`user_id`,`product_id`,`variant_id`),
  KEY `carts_product_id_foreign` (`product_id`),
  KEY `carts_variant_id_foreign` (`variant_id`),
  KEY `carts_user_id_product_id_index` (`user_id`,`product_id`),
  CONSTRAINT `carts_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `carts_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(191) NOT NULL,
  `slug_en` varchar(191) NOT NULL,
  `name_mm` varchar(191) DEFAULT NULL,
  `slug_mm` varchar(191) DEFAULT NULL,
  `image` varchar(191) DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_mm` text DEFAULT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  `_lft` int(10) unsigned NOT NULL DEFAULT 0,
  `_rgt` int(10) unsigned NOT NULL DEFAULT 0,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_en_unique` (`slug_en`),
  UNIQUE KEY `categories_name_mm_unique` (`name_mm`),
  UNIQUE KEY `categories_slug_mm_unique` (`slug_mm`),
  KEY `categories__lft__rgt_parent_id_index` (`_lft`,`_rgt`,`parent_id`),
  KEY `categories_slug_en_index` (`slug_en`),
  KEY `categories_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cod_commission_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cod_commission_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `invoice_number` varchar(191) NOT NULL,
  `order_subtotal` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,4) NOT NULL,
  `commission_amount` decimal(12,2) NOT NULL,
  `status` enum('outstanding','overdue','paid','waived') NOT NULL DEFAULT 'outstanding',
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `warning_sent_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `admin_confirmed_at` timestamp NULL DEFAULT NULL,
  `confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `payment_reference` varchar(191) DEFAULT NULL,
  `payment_method` varchar(191) DEFAULT NULL,
  `seller_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cod_commission_invoices_invoice_number_unique` (`invoice_number`),
  KEY `cod_commission_invoices_order_id_foreign` (`order_id`),
  KEY `cod_commission_invoices_confirmed_by_foreign` (`confirmed_by`),
  KEY `cod_commission_invoices_seller_id_status_index` (`seller_id`,`status`),
  KEY `cod_commission_invoices_status_due_date_index` (`status`,`due_date`),
  CONSTRAINT `cod_commission_invoices_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `cod_commission_invoices_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cod_commission_invoices_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commission_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commission_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('default','account_level','category','business_type') NOT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `reference_label` varchar(191) DEFAULT NULL,
  `rate` decimal(5,4) NOT NULL,
  `min_rate` decimal(5,4) DEFAULT NULL,
  `max_rate` decimal(5,4) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `notes` varchar(191) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commission_rules_type_ref_unique` (`type`,`reference_id`),
  KEY `commission_rules_type_is_active_index` (`type`,`is_active`),
  KEY `commission_rules_created_by_foreign` (`created_by`),
  CONSTRAINT `commission_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,4) NOT NULL DEFAULT 0.0500,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,4) NOT NULL DEFAULT 0.0500,
  `platform_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `seller_payout` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(191) DEFAULT NULL,
  `commission_rule_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','collected','due','waived') NOT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `collected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_order_id_foreign` (`order_id`),
  KEY `commissions_seller_id_foreign` (`seller_id`),
  CONSTRAINT `commissions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `subject` varchar(191) NOT NULL,
  `message` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupon_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupon_usages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_usages_coupon_id_user_id_order_id_unique` (`coupon_id`,`user_id`,`order_id`),
  KEY `coupon_usages_user_id_foreign` (`user_id`),
  KEY `coupon_usages_order_id_foreign` (`order_id`),
  KEY `coupon_usages_coupon_id_user_id_index` (`coupon_id`,`user_id`),
  CONSTRAINT `coupon_usages_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_usages_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `coupon_usages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','fixed') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(12,2) DEFAULT NULL,
  `applicable_product_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applicable_product_ids`)),
  `max_uses` int(10) unsigned DEFAULT NULL,
  `used_count` int(10) unsigned NOT NULL DEFAULT 0,
  `max_uses_per_user` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_one_time_use` tinyint(1) NOT NULL DEFAULT 0,
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`),
  KEY `coupons_seller_id_is_active_index` (`seller_id`,`is_active`),
  KEY `coupons_code_is_active_index` (`code`,`is_active`),
  KEY `coupons_expires_at_index` (`expires_at`),
  CONSTRAINT `coupons_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `delivery_method` enum('supplier','platform') NOT NULL DEFAULT 'supplier',
  `supplier_id` bigint(20) unsigned NOT NULL,
  `platform_courier_id` bigint(20) unsigned DEFAULT NULL,
  `platform_delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee_status` enum('not_applicable','outstanding','collected') NOT NULL DEFAULT 'not_applicable',
  `delivery_fee_collected_at` timestamp NULL DEFAULT NULL,
  `delivery_fee_collected_by` bigint(20) unsigned DEFAULT NULL,
  `delivery_fee_collection_ref` varchar(191) DEFAULT NULL COMMENT 'Bank transfer ref or receipt number for this payment',
  `assigned_driver_name` varchar(191) DEFAULT NULL,
  `assigned_driver_phone` varchar(191) DEFAULT NULL,
  `assigned_vehicle_type` varchar(191) DEFAULT NULL,
  `assigned_vehicle_number` varchar(191) DEFAULT NULL,
  `pickup_address` text NOT NULL,
  `delivery_address` text NOT NULL,
  `pickup_scheduled_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `in_transit_at` timestamp NULL DEFAULT NULL,
  `out_for_delivery_at` timestamp NULL DEFAULT NULL,
  `estimated_delivery_date` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `tracking_number` varchar(191) DEFAULT NULL,
  `carrier_name` varchar(191) DEFAULT NULL,
  `status` enum('pending','awaiting_pickup','picked_up','in_transit','out_for_delivery','delivered','failed','cancelled','returned') NOT NULL DEFAULT 'pending',
  `package_weight` decimal(8,2) DEFAULT NULL,
  `package_dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`package_dimensions`)),
  `package_count` int(11) NOT NULL DEFAULT 1,
  `delivery_proof_image` varchar(191) DEFAULT NULL,
  `recipient_name` varchar(191) DEFAULT NULL,
  `recipient_phone` varchar(191) DEFAULT NULL,
  `recipient_signature` varchar(191) DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `actual_delivery_cost` decimal(10,2) DEFAULT NULL,
  `delivery_cost_paid` tinyint(1) NOT NULL DEFAULT 0,
  `delivery_cost_paid_at` timestamp NULL DEFAULT NULL,
  `fee_submission_note` varchar(191) DEFAULT NULL,
  `fee_submitted_at` timestamp NULL DEFAULT NULL,
  `fee_confirmed_at` timestamp NULL DEFAULT NULL,
  `fee_confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `fee_confirmation_note` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deliveries_tracking_number_unique` (`tracking_number`),
  KEY `deliveries_delivery_fee_collected_by_foreign` (`delivery_fee_collected_by`),
  KEY `deliveries_fee_confirmed_by_foreign` (`fee_confirmed_by`),
  KEY `deliveries_order_id_index` (`order_id`),
  KEY `deliveries_supplier_id_index` (`supplier_id`),
  KEY `deliveries_platform_courier_id_index` (`platform_courier_id`),
  KEY `deliveries_status_index` (`status`),
  KEY `deliveries_delivery_fee_status_index` (`delivery_fee_status`),
  CONSTRAINT `deliveries_delivery_fee_collected_by_foreign` FOREIGN KEY (`delivery_fee_collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deliveries_fee_confirmed_by_foreign` FOREIGN KEY (`fee_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deliveries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliveries_platform_courier_id_foreign` FOREIGN KEY (`platform_courier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliveries_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_updates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `delivery_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` varchar(191) NOT NULL,
  `location` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_updates_delivery_id_foreign` (`delivery_id`),
  KEY `delivery_updates_user_id_foreign` (`user_id`),
  CONSTRAINT `delivery_updates_delivery_id_foreign` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_updates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discount_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discount_usage` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `discount_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discount_usage_discount_id_user_id_order_id_unique` (`discount_id`,`user_id`,`order_id`),
  KEY `discount_usage_user_id_foreign` (`user_id`),
  KEY `discount_usage_order_id_foreign` (`order_id`),
  CONSTRAINT `discount_usage_discount_id_foreign` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discount_usage_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discount_usage_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `code` varchar(191) DEFAULT NULL,
  `type` enum('percentage','fixed','free_shipping') NOT NULL,
  `value` decimal(10,2) DEFAULT NULL,
  `min_order_amount` decimal(12,2) DEFAULT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `applicable_to` enum('all_products','specific_products','specific_categories','specific_sellers') NOT NULL,
  `applicable_product_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applicable_product_ids`)),
  `applicable_category_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applicable_category_ids`)),
  `applicable_seller_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applicable_seller_ids`)),
  `max_uses_per_user` int(11) DEFAULT NULL,
  `is_one_time_use` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discounts_code_unique` (`code`),
  KEY `discounts_code_is_active_index` (`code`,`is_active`),
  KEY `discounts_expires_at_index` (`expires_at`),
  KEY `discounts_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `subject` varchar(191) NOT NULL,
  `body_html` text NOT NULL,
  `body_text` text DEFAULT NULL,
  `audience` enum('newsletter_subscribers','all_buyers','all_sellers','buyers_by_city','sellers_by_tier','custom_ids') NOT NULL DEFAULT 'newsletter_subscribers',
  `audience_filter` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`audience_filter`)),
  `status` enum('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `recipients_count` int(10) unsigned NOT NULL DEFAULT 0,
  `delivered_count` int(10) unsigned NOT NULL DEFAULT 0,
  `opened_count` int(10) unsigned NOT NULL DEFAULT 0,
  `clicked_count` int(10) unsigned NOT NULL DEFAULT 0,
  `bounced_count` int(10) unsigned NOT NULL DEFAULT 0,
  `unsubscribed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_campaigns_created_by_foreign` (`created_by`),
  CONSTRAINT `email_campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `follows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `follows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `follows_user_id_seller_id_unique` (`user_id`,`seller_id`),
  KEY `follows_seller_id_foreign` (`seller_id`),
  CONSTRAINT `follows_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `follows_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter_subscribers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `confirm_token` varchar(64) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `unsubscribe_token` varchar(64) NOT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `pref_promotions` tinyint(1) NOT NULL DEFAULT 1,
  `pref_new_sellers` tinyint(1) NOT NULL DEFAULT 1,
  `pref_product_updates` tinyint(1) NOT NULL DEFAULT 1,
  `pref_platform_news` tinyint(1) NOT NULL DEFAULT 1,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(191) NOT NULL DEFAULT 'website',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `newsletter_subscribers_email_unique` (`email`),
  UNIQUE KEY `newsletter_subscribers_unsubscribe_token_unique` (`unsubscribe_token`),
  UNIQUE KEY `newsletter_subscribers_confirm_token_unique` (`confirm_token`),
  KEY `newsletter_subscribers_user_id_foreign` (`user_id`),
  KEY `newsletter_subscribers_confirmed_at_unsubscribed_at_index` (`confirmed_at`,`unsubscribed_at`),
  CONSTRAINT `newsletter_subscribers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(191) NOT NULL,
  `notifiable_type` varchar(191) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `variant_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(191) NOT NULL,
  `product_sku` varchar(191) DEFAULT NULL,
  `variant_sku` varchar(191) DEFAULT NULL,
  `selected_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selected_options`)),
  `quantity_unit` varchar(191) NOT NULL DEFAULT 'piece',
  `price` decimal(12,2) NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `product_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`product_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_index` (`order_id`),
  KEY `order_items_product_id_index` (`product_id`),
  KEY `order_items_variant_id_index` (`variant_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(191) NOT NULL,
  `buyer_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal_amount` decimal(10,2) NOT NULL,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.05,
  `commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.10,
  `coupon_id` bigint(20) unsigned DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` enum('mmqr','aya_pay','kbz_pay','wave_pay','cb_pay','cash_on_delivery') NOT NULL DEFAULT 'cash_on_delivery',
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_gateway` varchar(191) DEFAULT NULL,
  `transaction_id` varchar(191) DEFAULT NULL,
  `payment_reference` varchar(191) DEFAULT NULL,
  `payment_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_data`)),
  `payment_initiated_at` timestamp NULL DEFAULT NULL,
  `payment_confirmed_at` timestamp NULL DEFAULT NULL,
  `payment_failed_at` timestamp NULL DEFAULT NULL,
  `escrow_status` enum('not_applicable','held','released','reversed','refunded') NOT NULL DEFAULT 'not_applicable',
  `shipping_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_address`)),
  `billing_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`billing_address`)),
  `order_notes` text DEFAULT NULL,
  `order_otp` varchar(6) DEFAULT NULL,
  `order_otp_expires_at` timestamp NULL DEFAULT NULL,
  `order_otp_verified` tinyint(1) NOT NULL DEFAULT 0,
  `tracking_number` varchar(191) DEFAULT NULL,
  `shipping_carrier` varchar(191) DEFAULT NULL,
  `estimated_delivery` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `refund_status` enum('none','requested','approved','processed','rejected') NOT NULL DEFAULT 'none',
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `refund_reason` text DEFAULT NULL,
  `refund_approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_coupon_id_foreign` (`coupon_id`),
  KEY `orders_refund_approved_by_foreign` (`refund_approved_by`),
  KEY `orders_escrow_status_index` (`escrow_status`),
  KEY `orders_buyer_id_status_index` (`buyer_id`,`status`),
  KEY `orders_seller_id_status_index` (`seller_id`,`status`),
  KEY `orders_order_number_index` (`order_number`),
  KEY `orders_created_at_index` (`created_at`),
  KEY `orders_transaction_id_index` (`transaction_id`),
  KEY `orders_payment_reference_index` (`payment_reference`),
  CONSTRAINT `orders_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_refund_approved_by_foreign` FOREIGN KEY (`refund_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `method` varchar(191) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `label` varchar(191) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_settings_method_unique` (`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transaction_id` varchar(191) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL,
  `gateway` varchar(191) NOT NULL,
  `gateway_response` text DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_order_id_foreign` (`order_id`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_option_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_option_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_id` bigint(20) unsigned NOT NULL,
  `label` varchar(191) NOT NULL,
  `value` varchar(191) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `position` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_option_values_option_id_value_unique` (`option_id`,`value`),
  KEY `product_option_values_option_id_index` (`option_id`),
  CONSTRAINT `product_option_values_option_id_foreign` FOREIGN KEY (`option_id`) REFERENCES `product_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `type` enum('color','size','text','image','input') NOT NULL DEFAULT 'text',
  `position` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_options_product_id_name_unique` (`product_id`,`name`),
  KEY `product_options_product_id_index` (`product_id`),
  CONSTRAINT `product_options_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `comment` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_reviews_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `product_reviews_product_id_status_index` (`product_id`,`status`),
  KEY `product_reviews_rating_index` (`rating`),
  KEY `product_reviews_created_at_index` (`created_at`),
  CONSTRAINT `product_reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variant_option_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variant_option_values` (
  `variant_id` bigint(20) unsigned NOT NULL,
  `option_value_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`variant_id`,`option_value_id`),
  KEY `product_variant_option_values_option_value_id_index` (`option_value_id`),
  CONSTRAINT `product_variant_option_values_option_value_id_foreign` FOREIGN KEY (`option_value_id`) REFERENCES `product_option_values` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_variant_option_values_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(191) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `quantity_unit` varchar(191) DEFAULT NULL COMMENT 'Overrides products.quantity_unit when set. e.g. piece, kg, meter, liter',
  `moq` int(11) DEFAULT NULL COMMENT 'Variant-level MOQ override. Falls back to products.moq when null.',
  `quantity_step` smallint(5) unsigned DEFAULT NULL COMMENT 'Variant-level step override. Falls back to products.quantity_step when null.',
  `image` varchar(191) DEFAULT NULL,
  `position` smallint(5) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  KEY `product_variants_product_id_index` (`product_id`),
  KEY `product_variants_product_id_is_active_index` (`product_id`,`is_active`),
  KEY `product_variants_sku_index` (`sku`),
  CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_wholesale_tiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_wholesale_tiers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned DEFAULT NULL,
  `min_qty` int(10) unsigned NOT NULL,
  `price_per_unit` decimal(12,2) NOT NULL,
  `discount_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `label` varchar(191) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tier_per_product_variant_qty` (`product_id`,`variant_id`,`min_qty`),
  KEY `product_wholesale_tiers_product_id_is_active_index` (`product_id`,`is_active`),
  KEY `product_wholesale_tiers_variant_id_is_active_index` (`variant_id`,`is_active`),
  CONSTRAINT `product_wholesale_tiers_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_wholesale_tiers_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(191) NOT NULL,
  `name_mm` varchar(191) DEFAULT NULL,
  `slug_en` varchar(191) NOT NULL,
  `slug_mm` varchar(191) DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_mm` text DEFAULT NULL,
  `product_type` enum('physical','digital','service') NOT NULL DEFAULT 'physical',
  `price` decimal(12,2) NOT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `category_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `average_rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `review_count` int(11) NOT NULL DEFAULT 0,
  `specifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications`)),
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `sku` varchar(191) DEFAULT NULL,
  `barcode` varchar(191) DEFAULT NULL,
  `brand` varchar(191) DEFAULT NULL,
  `model` varchar(191) DEFAULT NULL,
  `material` varchar(191) DEFAULT NULL,
  `origin` varchar(191) DEFAULT NULL,
  `discount_price` decimal(12,2) DEFAULT NULL,
  `discount_type` enum('percentage','fixed','none') NOT NULL DEFAULT 'none',
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `sale_badge` varchar(191) DEFAULT 'Sale',
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `sale_quantity` int(11) DEFAULT NULL,
  `sale_sold` int(11) NOT NULL DEFAULT 0,
  `discount_start` date DEFAULT NULL,
  `discount_end` date DEFAULT NULL,
  `is_on_sale` tinyint(1) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `sales` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_new` tinyint(1) NOT NULL DEFAULT 1,
  `condition` enum('new','used_like_new','used_good','used_fair') NOT NULL DEFAULT 'new',
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `warranty` varchar(191) DEFAULT NULL,
  `warranty_type` varchar(191) DEFAULT NULL,
  `warranty_period` varchar(191) DEFAULT NULL,
  `warranty_conditions` text DEFAULT NULL,
  `return_policy` varchar(191) DEFAULT NULL,
  `return_conditions` text DEFAULT NULL,
  `shipping_details` text DEFAULT NULL,
  `shipping_cost` decimal(12,2) DEFAULT NULL,
  `shipping_time` varchar(191) DEFAULT NULL,
  `shipping_origin` varchar(191) DEFAULT NULL,
  `customs_info` varchar(191) DEFAULT NULL,
  `hs_code` varchar(191) DEFAULT NULL,
  `quantity_unit` varchar(191) NOT NULL DEFAULT 'piece',
  `moq` int(11) NOT NULL DEFAULT 1,
  `quantity_step` smallint(5) unsigned NOT NULL DEFAULT 1 COMMENT 'Buyers must order in multiples of this. 1 = no restriction.',
  `min_order_unit` varchar(191) NOT NULL DEFAULT 'piece',
  `lead_time` varchar(191) DEFAULT NULL,
  `packaging_details` text DEFAULT NULL,
  `additional_info` text DEFAULT NULL,
  `file_url` varchar(191) DEFAULT NULL,
  `file_type` varchar(191) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `listed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL COMMENT 'Admin rejection reason shown to the seller',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_en_unique` (`slug_en`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  UNIQUE KEY `products_barcode_unique` (`barcode`),
  KEY `products_sku_is_active_index` (`sku`,`is_active`),
  KEY `products_is_active_index` (`is_active`),
  KEY `products_created_at_index` (`created_at`),
  KEY `products_is_on_sale_index` (`is_on_sale`),
  KEY `products_discount_end_index` (`discount_end`),
  KEY `products_product_type_index` (`product_type`),
  KEY `products_category_active_idx` (`category_id`,`is_active`),
  KEY `products_seller_active_idx` (`seller_id`,`is_active`),
  KEY `products_featured_active_created_idx` (`is_featured`,`is_active`,`created_at`),
  FULLTEXT KEY `products_name_en_description_en_fulltext` (`name_en`,`description_en`),
  FULLTEXT KEY `products_name_mm_description_mm_fulltext` (`name_mm`,`description_mm`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `author_type` enum('reporter','admin','system') NOT NULL DEFAULT 'reporter',
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_comments_user_id_foreign` (`user_id`),
  KEY `report_comments_report_id_index` (`report_id`),
  CONSTRAINT `report_comments_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` varchar(20) NOT NULL,
  `reporter_id` bigint(20) unsigned DEFAULT NULL,
  `guest_name` varchar(191) DEFAULT NULL,
  `guest_email` varchar(191) DEFAULT NULL,
  `category` enum('bug','payment','order','seller','product','account','content','billing','delivery','safety','suggestion','other') NOT NULL DEFAULT 'other',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `subject` varchar(191) NOT NULL,
  `description` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `related_order_id` bigint(20) unsigned DEFAULT NULL,
  `related_seller_id` bigint(20) unsigned DEFAULT NULL,
  `related_product_id` bigint(20) unsigned DEFAULT NULL,
  `related_url` varchar(191) DEFAULT NULL,
  `status` enum('open','in_review','waiting','resolved','closed','rejected') NOT NULL DEFAULT 'open',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `resolution` varchar(191) DEFAULT NULL,
  `reporter_ip` varchar(45) DEFAULT NULL,
  `reporter_locale` varchar(10) DEFAULT NULL,
  `first_response_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `duplicate_of` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reports_ticket_id_unique` (`ticket_id`),
  KEY `reports_related_order_id_foreign` (`related_order_id`),
  KEY `reports_related_seller_id_foreign` (`related_seller_id`),
  KEY `reports_ticket_id_index` (`ticket_id`),
  KEY `reports_status_index` (`status`),
  KEY `reports_category_index` (`category`),
  KEY `reports_priority_index` (`priority`),
  KEY `reports_reporter_id_status_index` (`reporter_id`,`status`),
  KEY `reports_assigned_to_status_index` (`assigned_to`,`status`),
  KEY `reports_created_at_index` (`created_at`),
  CONSTRAINT `reports_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_related_order_id_foreign` FOREIGN KEY (`related_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_related_seller_id_foreign` FOREIGN KEY (`related_seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_reporter_id_foreign` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rfq_quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfq_quotes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `unit_price` decimal(16,2) NOT NULL,
  `total_price` decimal(16,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'MMK',
  `delivery_days` int(10) unsigned NOT NULL,
  `validity_days` int(10) unsigned NOT NULL DEFAULT 7,
  `valid_until` date NOT NULL,
  `notes` text DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `status` enum('pending','accepted','rejected','withdrawn','expired') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfq_quotes_rfq_id_seller_id_unique` (`rfq_id`,`seller_id`),
  KEY `rfq_quotes_seller_id_foreign` (`seller_id`),
  KEY `rfq_quotes_status_index` (`status`),
  KEY `rfq_quotes_valid_until_index` (`valid_until`),
  CONSTRAINT `rfq_quotes_rfq_id_foreign` FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfq_quotes_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rfq_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfq_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `viewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfq_recipients_rfq_id_seller_id_unique` (`rfq_id`,`seller_id`),
  KEY `rfq_recipients_seller_id_index` (`seller_id`),
  CONSTRAINT `rfq_recipients_rfq_id_foreign` FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfq_recipients_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rfqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfqs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(24) NOT NULL,
  `buyer_id` bigint(20) unsigned NOT NULL,
  `product_name` varchar(191) NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(191) DEFAULT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `specifications` text DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `budget_min` decimal(16,2) DEFAULT NULL,
  `budget_max` decimal(16,2) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'MMK',
  `deadline` date NOT NULL,
  `notes` text DEFAULT NULL,
  `broadcast` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('draft','open','quoted','accepted','closed','cancelled','expired') NOT NULL DEFAULT 'open',
  `accepted_quote_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfqs_rfq_number_unique` (`rfq_number`),
  KEY `rfqs_order_id_foreign` (`order_id`),
  KEY `rfqs_status_index` (`status`),
  KEY `rfqs_category_id_index` (`category_id`),
  KEY `rfqs_deadline_index` (`deadline`),
  KEY `rfqs_buyer_id_status_index` (`buyer_id`,`status`),
  KEY `rfqs_broadcast_index` (`broadcast`),
  KEY `rfqs_category_index` (`category`),
  KEY `rfqs_accepted_quote_id_foreign` (`accepted_quote_id`),
  CONSTRAINT `rfqs_accepted_quote_id_foreign` FOREIGN KEY (`accepted_quote_id`) REFERENCES `rfq_quotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfqs_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfqs_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfqs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seller_delivery_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_delivery_areas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seller_profile_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `area_type` enum('country','state','city','township','specific_address') NOT NULL DEFAULT 'city',
  `country` varchar(80) NOT NULL DEFAULT 'Myanmar',
  `state` varchar(80) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `township` varchar(150) DEFAULT NULL,
  `specific_location` varchar(200) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `is_deliverable` tinyint(1) NOT NULL DEFAULT 1,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `free_shipping_threshold` decimal(10,2) DEFAULT NULL,
  `estimated_delivery_days_min` int(11) DEFAULT NULL,
  `estimated_delivery_days_max` int(11) DEFAULT NULL,
  `standard_shipping_available` tinyint(1) NOT NULL DEFAULT 1,
  `express_shipping_available` tinyint(1) NOT NULL DEFAULT 0,
  `pickup_available` tinyint(1) NOT NULL DEFAULT 0,
  `pickup_location` varchar(150) DEFAULT NULL,
  `has_weight_limit` tinyint(1) NOT NULL DEFAULT 0,
  `max_weight_kg` decimal(8,2) DEFAULT NULL,
  `has_size_limit` tinyint(1) NOT NULL DEFAULT 0,
  `size_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`size_restrictions`)),
  `product_category_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`product_category_restrictions`)),
  `excluded_dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`excluded_dates`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seller_delivery_areas_user_id_foreign` (`user_id`),
  KEY `seller_delivery_areas_seller_profile_id_area_type_index` (`seller_profile_id`,`area_type`),
  KEY `seller_delivery_areas_country_state_city_index` (`country`,`state`,`city`),
  KEY `seller_delivery_areas_is_active_is_deliverable_index` (`is_active`,`is_deliverable`),
  CONSTRAINT `seller_delivery_areas_seller_profile_id_foreign` FOREIGN KEY (`seller_profile_id`) REFERENCES `seller_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_delivery_areas_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seller_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `order_number` varchar(191) NOT NULL,
  `subtotal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `delivery_method` enum('platform','seller','pickup') NOT NULL DEFAULT 'seller',
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(191) DEFAULT NULL,
  `zone_matched` tinyint(1) NOT NULL DEFAULT 0,
  `zone_name` varchar(191) DEFAULT NULL,
  `fee_source` varchar(191) NOT NULL DEFAULT 'platform_default',
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `seller_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seller_orders_order_number_unique` (`order_number`),
  KEY `seller_orders_seller_id_status_index` (`seller_id`,`status`),
  KEY `seller_orders_order_id_seller_id_index` (`order_id`,`seller_id`),
  CONSTRAINT `seller_orders_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_orders_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seller_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `store_name` varchar(191) NOT NULL,
  `store_slug` varchar(191) NOT NULL,
  `store_description` text DEFAULT NULL,
  `store_id` varchar(191) NOT NULL,
  `business_type` varchar(191) DEFAULT NULL,
  `business_type_id` bigint(20) unsigned DEFAULT NULL,
  `business_registration_number` varchar(191) DEFAULT NULL,
  `certificate` varchar(191) DEFAULT NULL,
  `tax_id` varchar(191) DEFAULT NULL,
  `contact_email` varchar(191) NOT NULL,
  `contact_phone` varchar(191) NOT NULL,
  `website` varchar(191) DEFAULT NULL,
  `account_number` varchar(191) DEFAULT NULL,
  `social_facebook` varchar(191) DEFAULT NULL,
  `social_twitter` varchar(191) DEFAULT NULL,
  `social_instagram` varchar(191) DEFAULT NULL,
  `social_linkedin` varchar(191) DEFAULT NULL,
  `social_youtube` varchar(191) DEFAULT NULL,
  `address` varchar(191) DEFAULT NULL,
  `city` varchar(191) DEFAULT NULL,
  `township` varchar(150) DEFAULT NULL,
  `state` varchar(191) DEFAULT NULL,
  `country` varchar(191) NOT NULL,
  `postal_code` varchar(191) DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `store_logo` varchar(191) DEFAULT NULL,
  `store_banner` varchar(191) DEFAULT NULL,
  `business_registration_document` varchar(191) DEFAULT NULL,
  `business_certificate` varchar(191) DEFAULT NULL,
  `tax_registration_document` varchar(191) DEFAULT NULL,
  `identity_document_front` varchar(191) DEFAULT NULL,
  `identity_document_back` varchar(191) DEFAULT NULL,
  `additional_documents` longtext DEFAULT NULL,
  `identity_document_type` enum('national_id','passport','driving_license','other') DEFAULT NULL,
  `nrc_division` varchar(2) DEFAULT NULL,
  `nrc_township_code` varchar(20) DEFAULT NULL,
  `nrc_township_mm` varchar(30) DEFAULT NULL,
  `nrc_type` varchar(10) DEFAULT NULL,
  `nrc_number` varchar(10) DEFAULT NULL,
  `nrc_verification_status` enum('unverified','pending','verified','mismatch','rejected') NOT NULL DEFAULT 'unverified',
  `nrc_verified_at` timestamp NULL DEFAULT NULL,
  `nrc_verified_by` bigint(20) unsigned DEFAULT NULL,
  `nrc_verification_notes` text DEFAULT NULL,
  `status` enum('setup_pending','pending','approved','active','rejected','suspended','closed') NOT NULL DEFAULT 'setup_pending',
  `shipping_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `verification_status` enum('pending','under_review','verified','rejected') NOT NULL DEFAULT 'pending',
  `verification_level` enum('unverified','basic','verified','premium') NOT NULL DEFAULT 'unverified',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `seller_tier` enum('bronze','silver','gold') NOT NULL DEFAULT 'bronze',
  `completed_orders_count` int(10) unsigned NOT NULL DEFAULT 0,
  `tier_promoted_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `documents_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `documents_submitted_at` timestamp NULL DEFAULT NULL,
  `badge_type` varchar(191) DEFAULT NULL,
  `badge_expires_at` timestamp NULL DEFAULT NULL,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `document_status` enum('not_submitted','pending','under_review','approved','rejected') NOT NULL DEFAULT 'not_submitted',
  `onboarding_status` enum('pending','in_progress','completed','rejected') NOT NULL DEFAULT 'pending',
  `current_step` varchar(191) DEFAULT NULL,
  `document_rejection_reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `return_policy` text DEFAULT NULL,
  `shipping_policy` text DEFAULT NULL,
  `warranty_policy` text DEFAULT NULL,
  `privacy_policy` text DEFAULT NULL,
  `terms_of_service` text DEFAULT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  `auto_withdrawal` tinyint(1) NOT NULL DEFAULT 0,
  `withdrawal_threshold` decimal(15,2) NOT NULL DEFAULT 100000.00,
  `preferred_payment_method` varchar(191) NOT NULL DEFAULT 'bank_transfer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `vacation_mode` tinyint(1) NOT NULL DEFAULT 0,
  `vacation_message` text DEFAULT NULL,
  `vacation_start_date` date DEFAULT NULL,
  `vacation_end_date` date DEFAULT NULL,
  `currency` varchar(191) NOT NULL DEFAULT 'MMK',
  `business_hours_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `business_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`business_hours`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seller_profiles_store_slug_unique` (`store_slug`),
  UNIQUE KEY `seller_profiles_store_id_unique` (`store_id`),
  KEY `seller_profiles_user_id_foreign` (`user_id`),
  KEY `seller_profiles_nrc_verified_by_foreign` (`nrc_verified_by`),
  KEY `seller_profiles_verified_by_foreign` (`verified_by`),
  KEY `seller_profiles_store_slug_index` (`store_slug`),
  KEY `seller_profiles_status_index` (`status`),
  KEY `seller_profiles_verification_status_index` (`verification_status`),
  KEY `seller_profiles_business_type_id_index` (`business_type_id`),
  KEY `seller_profiles_status_verification_status_index` (`status`,`verification_status`),
  CONSTRAINT `seller_profiles_business_type_id_foreign` FOREIGN KEY (`business_type_id`) REFERENCES `business_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `seller_profiles_nrc_verified_by_foreign` FOREIGN KEY (`nrc_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `seller_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_profiles_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seller_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `comment` text DEFAULT NULL,
  `review` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seller_reviews_user_id_foreign` (`user_id`),
  KEY `seller_reviews_seller_id_foreign` (`seller_id`),
  CONSTRAINT `seller_reviews_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `seller_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seller_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `status` enum('active','expired','cancelled','pending_payment') NOT NULL DEFAULT 'active',
  `starts_at` date NOT NULL,
  `ends_at` date DEFAULT NULL,
  `next_billing_at` date DEFAULT NULL,
  `amount_paid_mmk` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_reference` varchar(191) DEFAULT NULL,
  `payment_method` varchar(191) DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seller_subscriptions_plan_id_foreign` (`plan_id`),
  KEY `seller_subscriptions_changed_by_foreign` (`changed_by`),
  KEY `seller_subscriptions_user_id_status_index` (`user_id`,`status`),
  KEY `seller_subscriptions_ends_at_index` (`ends_at`),
  KEY `seller_subscriptions_payment_method_index` (`payment_method`),
  CONSTRAINT `seller_subscriptions_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `seller_subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`),
  CONSTRAINT `seller_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seller_wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_wallets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `escrow_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `available_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_earned` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_commission_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cod_commission_outstanding` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seller_wallets_user_id_unique` (`user_id`),
  CONSTRAINT `seller_wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipping_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seller_profile_id` bigint(20) unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `processing_time` enum('same_day','1_2_days','3_5_days','5_7_days','custom') NOT NULL DEFAULT '3_5_days',
  `custom_processing_time` varchar(191) DEFAULT NULL,
  `free_shipping_threshold` decimal(10,2) DEFAULT NULL,
  `free_shipping_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `shipping_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_methods`)),
  `delivery_areas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`delivery_areas`)),
  `shipping_rates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_rates`)),
  `international_shipping` tinyint(1) NOT NULL DEFAULT 0,
  `international_rates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`international_rates`)),
  `package_weight_unit` varchar(191) NOT NULL DEFAULT 'kg',
  `default_package_weight` decimal(8,2) NOT NULL DEFAULT 1.00,
  `shipping_policy` text DEFAULT NULL,
  `return_policy` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipping_settings_seller_profile_id_unique` (`seller_profile_id`),
  CONSTRAINT `shipping_settings_seller_profile_id_foreign` FOREIGN KEY (`seller_profile_id`) REFERENCES `seller_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `price_mmk` decimal(12,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` varchar(191) NOT NULL DEFAULT 'monthly',
  `product_limit` int(11) NOT NULL DEFAULT 20,
  `commission_rate` decimal(6,4) NOT NULL DEFAULT 0.0500,
  `analytics_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `bulk_import_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `priority_support` tinyint(1) NOT NULL DEFAULT 0,
  `custom_storefront` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `user_id` varchar(191) NOT NULL,
  `ref_code` varchar(12) DEFAULT NULL,
  `referred_by` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `type` enum('buyer','seller','courier','admin','pending') NOT NULL DEFAULT 'buyer',
  `address` text DEFAULT NULL,
  `city` varchar(191) DEFAULT NULL,
  `township` varchar(150) DEFAULT NULL,
  `state` varchar(191) DEFAULT NULL,
  `country` varchar(191) DEFAULT NULL,
  `postal_code` varchar(191) DEFAULT NULL,
  `profile_photo` varchar(191) DEFAULT NULL,
  `social_id` varchar(191) DEFAULT NULL,
  `social_provider` varchar(191) DEFAULT NULL,
  `identity_document_front` varchar(191) DEFAULT NULL,
  `identity_document_back` varchar(191) DEFAULT NULL,
  `identity_document_type` varchar(191) DEFAULT NULL,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`)),
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `verification_code` varchar(6) DEFAULT NULL,
  `verification_code_expires_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive','suspended','disabled','restricted') NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_user_id_unique` (`user_id`),
  UNIQUE KEY `users_ref_code_unique` (`ref_code`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  KEY `users_referred_by_foreign` (`referred_by`),
  KEY `users_social_index` (`social_provider`,`social_id`),
  CONSTRAINT `users_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('escrow_hold','escrow_release','escrow_reverse','commission_deduct','refund_hold','withdrawal','cod_invoice','cod_payment','adjustment') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `escrow_balance_after` decimal(14,2) NOT NULL DEFAULT 0.00,
  `available_balance_after` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_transactions_created_by_foreign` (`created_by`),
  KEY `wallet_transactions_wallet_id_created_at_index` (`wallet_id`,`created_at`),
  KEY `wallet_transactions_order_id_index` (`order_id`),
  CONSTRAINT `wallet_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `seller_wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wishlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wishlists_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `wishlists_product_id_foreign` (`product_id`),
  CONSTRAINT `wishlists_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_07_28_123417_create_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_07_28_123631_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_07_28_123632_create_product_options_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_07_28_123633_create_product_option_values_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_07_28_123634_create_product_variants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_07_28_123635_create_product_variant_option_values_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_07_28_123710_create_coupons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_07_28_123711_create_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_07_28_123745_create_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_07_28_123746_create_coupon_usages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_07_28_123820_create_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_07_28_123846_create_commission_rules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_07_28_123847_create_commissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_07_28_123923_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_07_28_131124_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_07_28_154739_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_07_28_181100_create_product_reviews_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_08_27_210720_create_wishlists_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_08_28_155518_create_business_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_09_11_110133_create_seller_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_09_11_115042_create_seller_reviews_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_09_30_122638_create_carts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_11_17_153001_create_deliveries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_11_17_160604_create_delivery_updates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_11_24_183302_create_follows_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_01_27_031938_create_discounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_01_27_121408_create_seller_delivery_areas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_01_27_122400_create_shipping_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_03_13_000839_create_contact_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_03_27_211025_create_newsletter_subscribers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_03_27_211101_create_email_campaigns_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_04_02_215728_create_announcements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_04_06_152954_create_seller_wallets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_04_06_153030_create_wallet_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_04_06_153059_create_cod_commission_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_04_11_200949_create_seller_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_04_21_081026_create_reports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_04_24_102443_create_rfqs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_05_01_070747_create_payment_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_05_10_095933_create_product_wholesale_tiers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_05_16_212421_create_subscription_plans_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_05_16_212936_create_seller_subscriptions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_05_17_184644_drop_unique_user_id_on_seller_subscriptions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_05_27_000000_create_blog_posts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_06_01_000001_add_common_product_composite_indexes',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_06_01_000002_add_payment_method_to_seller_subscriptions',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_06_03_000001_add_quantity_to_products_table',6);
