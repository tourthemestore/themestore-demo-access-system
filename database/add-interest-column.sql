-- Add interest column to leads_for_demo (Interested / Not interested)
-- Run once: mysql -u user -p tourthemestore_support < database/add-interest-column.sql

USE `tourthemestore_support`;

ALTER TABLE `leads_for_demo`
ADD COLUMN IF NOT EXISTS `interest` ENUM('interested', 'not_interested') NULL DEFAULT NULL
COMMENT 'User selection below demo video'
AFTER `campaign_source`;
