# Migration: Rename 'leads' table to 'leads_for_demo'

## Overview
This migration renames the `leads` table to `leads_for_demo` across the entire codebase.

## Files Updated

### Database Schema
- ✅ `database/schema.sql` - Updated table name and all foreign key references
- ✅ `database/update-leads-table.sql` - Updated table name in ALTER statements
- ✅ `database/update-leads-table.php` - Updated table name in DESCRIBE and ALTER statements

### API Files
- ✅ `api/send-otp.php` - Updated SELECT query
- ✅ `api/verify-otp.php` - Updated SELECT and UPDATE queries
- ✅ `api/generate-demo-link.php` - Updated SELECT queries
- ✅ `api/add-followup.php` - Updated SELECT query
- ✅ `api/respond-query.php` - Updated JOIN query
- ✅ `api/bulk-respond-queries.php` - Updated JOIN query

### Admin Files
- ✅ `admin/admin-leads.php` - Updated FROM clause in main query
- ✅ `admin/admin-lead-detail.php` - Updated SELECT query

### Public Files
- ✅ `public/lead-form.php` - Updated SELECT and INSERT queries
- ✅ `public/demo-flow.php` - Updated SELECT query

## Migration Steps

### Step 1: Backup Database
**IMPORTANT:** Always backup your database before running migrations!

```sql
-- Create backup
mysqldump -u root tourthemestore_support > backup_before_rename_leads.sql
```

### Step 2: Run Migration Script
Execute the migration script in phpMyAdmin or MySQL command line:

```bash
mysql -u root tourthemestore_support < database/rename-leads-table.sql
```

Or in phpMyAdmin:
1. Go to phpMyAdmin
2. Select database: `tourthemestore_support`
3. Click "SQL" tab
4. Copy and paste contents of `database/rename-leads-table.sql`
5. Click "Go"

### Step 3: Verify Migration
After running the migration, verify:

```sql
-- Check table exists with new name
SHOW TABLES LIKE 'leads_for_demo';

-- Check foreign keys are intact
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME = 'leads_for_demo'
AND TABLE_SCHEMA = 'tourthemestore_support';
```

Expected foreign keys:
- `otp_verifications.lead_id` → `leads_for_demo.id`
- `demo_links.lead_id` → `leads_for_demo.id`
- `video_activity.lead_id` → `leads_for_demo.id`
- `demo_followups.lead_id` → `leads_for_demo.id`
- `demo_queries.lead_id` → `leads_for_demo.id`

### Step 4: Test Application
After migration, test:
1. Lead form submission
2. OTP generation and verification
3. Demo link generation
4. Admin leads listing
5. Admin lead detail view
6. Follow-up creation
7. Query submission and response

## Rollback (If Needed)

If you need to rollback:

```sql
-- Rename back to original
RENAME TABLE `leads_for_demo` TO `leads`;
```

**Note:** After rollback, you'll need to revert all code changes as well.

## Notes

- Foreign key constraints will automatically update when the table is renamed
- All code references have been updated
- No data loss occurs during this migration
- The migration is reversible

## Verification Checklist

- [ ] Database backup created
- [ ] Migration script executed
- [ ] Table renamed successfully
- [ ] Foreign keys verified
- [ ] Application tested
- [ ] All features working correctly
