-- ══════════════════════════════════════════════════════════════════════════
-- nawris_schema.sql
-- شغّل هذا الملف في phpMyAdmin على قاعدة u495355717_ZIDON
-- ══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────── settings ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) NOT NULL,
  `value`      TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── shipments ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `shipments` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tracking_code`      VARCHAR(100) NOT NULL,
  `customer_name`      VARCHAR(255),
  `customer_phone`     VARCHAR(50),
  `driver_id`          INT UNSIGNED DEFAULT NULL,
  `branch_name`        VARCHAR(100),
  `responsible_branch` VARCHAR(100),
  `region_name`        VARCHAR(100),
  `status`             VARCHAR(50)  DEFAULT 'with_driver',
  `delay_days`         INT          DEFAULT 0,
  `upload_date`        DATE,
  `api_source`         VARCHAR(50)  DEFAULT 'manual',
  `external_id`        VARCHAR(100),
  `created_at`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY  `uq_tracking_code`   (`tracking_code`),
  INDEX       `idx_status`         (`status`),
  INDEX       `idx_delay_days`     (`delay_days` DESC),
  INDEX       `idx_driver_id`      (`driver_id`),
  -- Composite index: the most common filter pair used in the dashboard
  INDEX       `idx_status_delay`   (`status`, `delay_days` DESC),
  -- Date-range queries on upload_date
  INDEX       `idx_upload_date`    (`upload_date`),
  -- Branch-level drill-downs
  INDEX       `idx_branch_name`    (`branch_name`),
  -- Phone lookup (customer service search)
  INDEX       `idx_customer_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── contact_results ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_results` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id`   VARCHAR(100),
  `tracking_code` VARCHAR(100),
  `result`        VARCHAR(50),
  `note`          TEXT,
  `updated_by`    VARCHAR(100),
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_tracking_code`  (`tracking_code`),
  INDEX      `idx_shipment_id`   (`shipment_id`),
  -- Staff-performance queries filter by result type and employee name
  INDEX      `idx_result`        (`result`),
  INDEX      `idx_updated_by`    (`updated_by`),
  -- Monthly stats filter by updated_at
  INDEX      `idx_updated_at`    (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── drivers ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `drivers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `phone`       VARCHAR(50),
  `branch_name` VARCHAR(100),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_branch_name` (`branch_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── branches ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `branches` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── regions ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `regions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── stores ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stores` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── wa_templates ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `wa_templates` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `content`    TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── returns ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `returns` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tracking_code`  VARCHAR(100),
  `customer_name`  VARCHAR(255),
  `customer_phone` VARCHAR(50),
  `driver_name`    VARCHAR(100),
  `branch_name`    VARCHAR(100),
  `status`         VARCHAR(50),
  `delay_days`     INT DEFAULT 0,
  `note`           TEXT,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tracking_code` (`tracking_code`),
  INDEX `idx_status`        (`status`),
  INDEX `idx_delay_days`    (`delay_days` DESC),
  INDEX `idx_branch_name`   (`branch_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── transfers ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transfers` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tracking_code`  VARCHAR(100),
  `customer_name`  VARCHAR(255),
  `from_branch`    VARCHAR(100),
  `to_branch`      VARCHAR(100),
  `status`         VARCHAR(50),
  `delay_days`     INT DEFAULT 0,
  `note`           TEXT,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tracking_code` (`tracking_code`),
  INDEX `idx_status`        (`status`),
  INDEX `idx_delay_days`    (`delay_days` DESC),
  INDEX `idx_from_branch`   (`from_branch`),
  INDEX `idx_to_branch`     (`to_branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── contacted_log ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contacted_log` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id`  VARCHAR(100),
  `contacted_by` VARCHAR(100),
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_shipment_id`  (`shipment_id`),
  INDEX `idx_contacted_by` (`contacted_by`),
  INDEX `idx_created_at`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- ══════════════════════════════════════════════════════════════════════════════
-- MIGRATION: شغّل هذا القسم إذا كانت الجداول موجودة مسبقاً
-- يضيف الـ indexes الناقصة دون حذف أي بيانات
-- ══════════════════════════════════════════════════════════════════════════════

-- shipments — indexes إضافية
ALTER TABLE `shipments`
  ADD INDEX IF NOT EXISTS `idx_status_delay`   (`status`, `delay_days` DESC),
  ADD INDEX IF NOT EXISTS `idx_upload_date`    (`upload_date`),
  ADD INDEX IF NOT EXISTS `idx_branch_name`    (`branch_name`),
  ADD INDEX IF NOT EXISTS `idx_customer_phone` (`customer_phone`);

-- contact_results — indexes للبحث في أداء الموظفين
ALTER TABLE `contact_results`
  ADD INDEX IF NOT EXISTS `idx_result`      (`result`),
  ADD INDEX IF NOT EXISTS `idx_updated_by`  (`updated_by`),
  ADD INDEX IF NOT EXISTS `idx_updated_at`  (`updated_at`);

-- drivers
ALTER TABLE `drivers`
  ADD INDEX IF NOT EXISTS `idx_branch_name` (`branch_name`);

-- returns
ALTER TABLE `returns`
  ADD INDEX IF NOT EXISTS `idx_status`      (`status`),
  ADD INDEX IF NOT EXISTS `idx_delay_days`  (`delay_days` DESC),
  ADD INDEX IF NOT EXISTS `idx_branch_name` (`branch_name`);

-- transfers
ALTER TABLE `transfers`
  ADD INDEX IF NOT EXISTS `idx_status`      (`status`),
  ADD INDEX IF NOT EXISTS `idx_delay_days`  (`delay_days` DESC),
  ADD INDEX IF NOT EXISTS `idx_from_branch` (`from_branch`),
  ADD INDEX IF NOT EXISTS `idx_to_branch`   (`to_branch`);

-- contacted_log
ALTER TABLE `contacted_log`
  ADD INDEX IF NOT EXISTS `idx_contacted_by` (`contacted_by`),
  ADD INDEX IF NOT EXISTS `idx_created_at`   (`created_at`);
