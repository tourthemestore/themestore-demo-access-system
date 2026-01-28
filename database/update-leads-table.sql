-- Update leads_for_demo table to add missing columns
-- Run this in phpMyAdmin or MySQL command line
-- Note: Check if columns exist first to avoid errors

USE `tourthemestore_support`;

-- Add missing columns to leads_for_demo table
-- Run each ALTER statement separately and skip if column already exists

-- Add company_name column (if it doesn't exist)
ALTER TABLE `leads_for_demo` 
ADD COLUMN `company_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `id`;

-- Add location column (if it doesn't exist)
ALTER TABLE `leads_for_demo` 
ADD COLUMN `location` VARCHAR(255) NOT NULL DEFAULT '' AFTER `company_name`;

-- Add mobile column (if it doesn't exist)
ALTER TABLE `leads_for_demo` 
ADD COLUMN `mobile` VARCHAR(20) NOT NULL DEFAULT '' AFTER `email`;

-- Add campaign_source column (if it doesn't exist)
ALTER TABLE `leads_for_demo` 
ADD COLUMN `campaign_source` VARCHAR(255) DEFAULT NULL AFTER `mobile`;

-- If the table has an old 'name' column, you can migrate data:
-- UPDATE leads_for_demo SET company_name = name WHERE company_name = '' AND name IS NOT NULL;

-- After migration, you can remove default values (optional):
-- ALTER TABLE `leads_for_demo` 
-- MODIFY COLUMN `company_name` VARCHAR(255) NOT NULL,
-- MODIFY COLUMN `location` VARCHAR(255) NOT NULL,
-- MODIFY COLUMN `mobile` VARCHAR(20) NOT NULL;

