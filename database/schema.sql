-- ThemeStore Demo Access System - MySQL 8 Schema
-- Created based on SYSTEM_RULES.md

USE `tourthemestore_support`;

-- Table: leads_for_demo
-- Stores lead information
CREATE TABLE IF NOT EXISTS `leads_for_demo` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_name` VARCHAR(255) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `campaign_source` VARCHAR(255) DEFAULT NULL,
    `interest` ENUM('interested', 'not_interested') NULL DEFAULT NULL COMMENT 'User selection below demo video',
    `view_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of times demo video viewed (max 3)',
    `status` ENUM('pending', 'verified', 'active', 'expired', 'blocked') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_email` (`email`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: otp_verifications
-- Stores OTP verification attempts (OTP expiry = 10 minutes, Max attempts = 3)
CREATE TABLE IF NOT EXISTS `otp_verifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` BIGINT UNSIGNED NOT NULL,
    `otp_code` VARCHAR(10) NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `status` ENUM('pending', 'verified', 'expired', 'failed', 'blocked') NOT NULL DEFAULT 'pending',
    `expires_at` TIMESTAMP NOT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_id` (`lead_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expires_at` (`expires_at`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_otp_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads_for_demo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: demo_links
-- Stores demo access tokens (Token length >= 64, Validity = 48 hours, Max views = 1)
CREATE TABLE IF NOT EXISTS `demo_links` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` BIGINT UNSIGNED NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'expired', 'used', 'revoked') NOT NULL DEFAULT 'active',
    `views_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_views` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `expires_at` TIMESTAMP NOT NULL,
    `accessed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_token_hash` (`token_hash`),
    KEY `idx_lead_id` (`lead_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expires_at` (`expires_at`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_demo_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads_for_demo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: video_activity
-- Tracks video start, progress, and completion
CREATE TABLE IF NOT EXISTS `video_activity` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `demo_link_id` BIGINT UNSIGNED NOT NULL,
    `lead_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('started', 'progress', 'completed', 'abandoned') NOT NULL DEFAULT 'started',
    `progress_percentage` DECIMAL(5,2) UNSIGNED DEFAULT 0.00,
    `duration_watched` INT UNSIGNED DEFAULT 0 COMMENT 'Duration in seconds',
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_progress_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_demo_link_id` (`demo_link_id`),
    KEY `idx_lead_id` (`lead_id`),
    KEY `idx_status` (`status`),
    KEY `idx_started_at` (`started_at`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_video_demo_link` FOREIGN KEY (`demo_link_id`) REFERENCES `demo_links` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_video_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads_for_demo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: demo_followups
-- Tracks follow-up actions and notes for leads
CREATE TABLE IF NOT EXISTS `demo_followups` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` BIGINT UNSIGNED NOT NULL,
    `followup_type` ENUM('call', 'email', 'meeting', 'note', 'reminder', 'other') NOT NULL DEFAULT 'note',
    `subject` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `followup_date` DATETIME DEFAULT NULL COMMENT 'Scheduled follow-up date/time',
    `status` ENUM('pending', 'completed', 'cancelled', 'rescheduled') NOT NULL DEFAULT 'pending',
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL COMMENT 'Admin/user who created the follow-up',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_id` (`lead_id`),
    KEY `idx_status` (`status`),
    KEY `idx_followup_date` (`followup_date`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_followup_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads_for_demo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: demo_queries
-- Stores client queries/chat messages during video viewing
CREATE TABLE IF NOT EXISTS `demo_queries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` BIGINT UNSIGNED NOT NULL,
    `demo_link_id` BIGINT UNSIGNED DEFAULT NULL,
    `query_text` TEXT NOT NULL,
    `status` ENUM('pending', 'answered', 'scheduled', 'resolved') NOT NULL DEFAULT 'pending',
    `admin_response` TEXT DEFAULT NULL,
    `scheduled_call_date` DATETIME DEFAULT NULL,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_id` (`lead_id`),
    KEY `idx_demo_link_id` (`demo_link_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_query_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads_for_demo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_query_demo_link` FOREIGN KEY (`demo_link_id`) REFERENCES `demo_links` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

