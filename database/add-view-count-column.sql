-- Add view_count column to leads_for_demo table
-- Tracks how many times a lead has viewed the demo video (max 2)

ALTER TABLE `leads_for_demo`
  ADD COLUMN `view_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of times demo video viewed (max 2)' AFTER `interest`;
