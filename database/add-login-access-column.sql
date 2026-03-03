-- Add login_access column to roles table
-- Controls which users can login to the demo leads dashboard
-- Values: 'Yes' / 'No'

ALTER TABLE `roles` ADD COLUMN `login_access` VARCHAR(10) NOT NULL DEFAULT 'No' AFTER `active_flag`;

-- After running this, set login_access = 'Yes' for users who should have access:
-- UPDATE roles SET login_access = 'Yes' WHERE emp_id = 0;  -- Admin
-- UPDATE roles SET login_access = 'Yes' WHERE user_name = 'your_sales_user';
