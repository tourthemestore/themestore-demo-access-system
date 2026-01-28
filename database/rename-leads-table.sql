-- Migration Script: Rename 'leads' table to 'leads_for_demo'
-- Run this in phpMyAdmin or MySQL command line
-- IMPORTANT: Backup your database before running this script!

USE `tourthemestore_support`;

-- Step 1: Rename the table
RENAME TABLE `leads` TO `leads_for_demo`;

-- Step 2: Verify the rename was successful
-- You can check by running: SHOW TABLES LIKE 'leads_for_demo';

-- Note: Foreign key constraints will automatically update to reference the new table name
-- because MySQL updates foreign key references when a table is renamed.
