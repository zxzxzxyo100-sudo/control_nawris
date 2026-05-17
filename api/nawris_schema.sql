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
  UNIQUE KEY `uq_tracking_code` (`tracking_code`),
  INDEX `idx_status`     (`status`),
  INDEX `idx_delay_days` (`delay_days` DESC),
  INDEX `idx_driver_id`  (`driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── contact_results ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_results` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id`  VARCHAR(100),
  `tracking_code` VARCHAR(100),
  `result`       VARCHAR(50),
  `note`         TEXT,
  `updated_by`   VARCHAR(100),
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_tracking_code` (`tracking_code`),
  INDEX `idx_shipment_id`  (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── drivers ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `drivers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `phone`       VARCHAR(50),
  `branch_name` VARCHAR(100),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
  INDEX `idx_tracking_code` (`tracking_code`)
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
  INDEX `idx_tracking_code` (`tracking_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── contacted_log ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contacted_log` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id`  VARCHAR(100),
  `contacted_by` VARCHAR(100),
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
