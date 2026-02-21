-- Table: demo_leads_user_log
-- Tracks admin/sales user login and logout times

CREATE TABLE IF NOT EXISTS `demo_leads_user_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `emp_id` INT NOT NULL COMMENT 'From roles table',
    `user_name` VARCHAR(255) NOT NULL,
    `emp_name` VARCHAR(255) DEFAULT NULL,
    `role` VARCHAR(50) NOT NULL COMMENT 'Admin or Sales',
    `action` ENUM('login', 'logout') NOT NULL,
    `logged_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_emp_id` (`emp_id`),
    KEY `idx_action` (`action`),
    KEY `idx_logged_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
