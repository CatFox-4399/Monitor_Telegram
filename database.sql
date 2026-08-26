-- ============================================================
-- Website Monitoring System with Telegram Alerts
-- Database: monitoring_db
-- Compatible with: MySQL 5.7+ / MariaDB 10+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `monitoring_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `monitoring_db`;

-- ============================================================
-- Table: admin
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account: username=admin  password=admin123
-- (bcrypt hash of "admin123")
INSERT INTO `admin` (`username`, `password`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
-- Table: websites
-- ============================================================
CREATE TABLE IF NOT EXISTS `websites` (
  `id`               INT(11)                        NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(255)                   NOT NULL,
  `url`              VARCHAR(500)                   NOT NULL,
  `interval_seconds` INT(11)                        NOT NULL DEFAULT 15,
  `status`           ENUM('UP','DOWN','UNKNOWN')    NOT NULL DEFAULT 'UNKNOWN',
  `response_time`    FLOAT                          NULL     DEFAULT NULL COMMENT 'milliseconds',
  `last_checked`     DATETIME                       NULL     DEFAULT NULL,
  `created_at`       DATETIME                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `logs` (
  `id`            INT(11)              NOT NULL AUTO_INCREMENT,
  `website_id`    INT(11)              NOT NULL,
  `status`        ENUM('UP','DOWN')    NOT NULL,
  `response_time` FLOAT                NULL     DEFAULT NULL COMMENT 'milliseconds',
  `checked_at`    DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_website_id`  (`website_id`),
  KEY `idx_checked_at`  (`checked_at`),
  CONSTRAINT `fk_logs_website`
    FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: alerts
-- ============================================================
CREATE TABLE IF NOT EXISTS `alerts` (
  `id`         INT(11)   NOT NULL AUTO_INCREMENT,
  `website_id` INT(11)   NOT NULL,
  `message`    TEXT      NOT NULL,
  `sent_at`    DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_website` (`website_id`),
  CONSTRAINT `fk_alerts_website`
    FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: settings  (Telegram config editable from UI)
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_val` TEXT         NOT NULL,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_val`) VALUES
('telegram_bot_token', 'YOUR_BOT_TOKEN_HERE'),
('telegram_chat_id',   'YOUR_CHAT_ID_HERE'),
('slow_threshold_ms',  '3000');
