-- ============================================================
-- Migration: Create sms_notifications_log table
-- Database : pconcio
-- Run this in phpMyAdmin or MySQL CLI BEFORE running
-- send_appointment_reminders.php for the first time.
--
-- If you already ran the old version of this file, run the
-- ALTER TABLE below to update the reminder_type column.
-- ============================================================

-- ── Fresh install (run if table does not exist yet) ─────────

CREATE TABLE IF NOT EXISTS `sms_notifications_log` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,

  -- Which appointment this SMS is for
  `source_table`     ENUM('patients_list','online_appointment')
                                    NOT NULL COMMENT 'Which table the appointment comes from',
  `appointment_id`   INT UNSIGNED   NOT NULL COMMENT 'ID of the appointment row',

  -- What kind of reminder was sent
  --   5_min_before = sent 5 minutes before appointment time
  --   on_time      = sent exactly at appointment time
  `reminder_type`    ENUM('5_min_before','on_time')
                                    NOT NULL,

  -- SMS details
  `phone_number`     VARCHAR(20)    NOT NULL,
  `patient_name`     VARCHAR(200)   NOT NULL,
  `appointment_date` DATE           NOT NULL,
  `appointment_time` VARCHAR(20)    NOT NULL,

  -- API response
  `status`           ENUM('sent','failed')
                                    NOT NULL DEFAULT 'sent',
  `api_message_id`   VARCHAR(100)   NULL     COMMENT 'Message ID returned by Semaphore',
  `error_message`    TEXT           NULL     COMMENT 'Error detail if status = failed',

  `sent_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- Prevents sending the same reminder twice for the same appointment
  UNIQUE KEY `uq_reminder` (`source_table`, `appointment_id`, `reminder_type`),

  INDEX `idx_appointment_date` (`appointment_date`),
  INDEX `idx_status`           (`status`),
  INDEX `idx_sent_at`          (`sent_at`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Tracks every SMS reminder sent to prevent duplicate sending';


-- ── Already ran the old version? Run this ALTER to upgrade ──
-- (Safe to run even if you just did the CREATE above — it will
--  only change things if the old ENUM values still exist.)

ALTER TABLE `sms_notifications_log`
  MODIFY COLUMN `reminder_type`
    ENUM('5_min_before','on_time') NOT NULL;
