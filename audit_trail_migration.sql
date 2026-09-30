-- ============================================================
-- Migration: Create audit_trail table
-- Database : pconcio
-- Run this in phpMyAdmin or via MySQL CLI
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_trail` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `user_type`   VARCHAR(20)    NOT NULL COMMENT 'admin | dentist | staff | patient',
  `user_name`   VARCHAR(150)   NOT NULL COMMENT 'Display name of the user who performed the action',
  `action`      VARCHAR(255)   NOT NULL COMMENT 'Short action label, e.g. Logged In, Updated Record',
  `details`     TEXT           NULL     COMMENT 'Optional extra context',
  `ip_address`  VARCHAR(45)    NOT NULL DEFAULT '' COMMENT 'IPv4 or IPv6',
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_type`  (`user_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Records every significant action performed by any system user';
