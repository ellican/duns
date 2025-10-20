# Database Migrations

This folder contains SQL migration files to update the database schema.

## How to Apply Migrations

Execute the SQL files in numerical order using your MySQL/MariaDB client:

### Using Command Line
```bash
# Apply migration 001
mysql -u your_username -p your_database_name < 001_rename_phone_to_responsible.sql

# Apply migration 002
mysql -u your_username -p your_database_name < 002_add_tin_column.sql

# Apply migration 003
mysql -u your_username -p your_database_name < 003_create_login_attempts_table.sql
```

### Using phpMyAdmin or Similar Tools
1. Log in to your database management tool
2. Select the `duns` database
3. Go to the SQL tab
4. Copy and paste the contents of each migration file
5. Execute them in order (001, 002, 003)

## Migration Details

### 001_rename_phone_to_responsible.sql
- **Purpose**: Renames the `phone_number` column to `Responsible` in the `clients` table
- **Impact**: All references to `phone_number` should now use `Responsible`

### 002_add_tin_column.sql
- **Purpose**: Adds a `TIN` (Tax Identification Number) column to the `clients` table
- **Type**: VARCHAR(9) - accepts up to 9 numeric digits
- **Index**: Creates an index on the TIN column for faster lookups

### 003_create_login_attempts_table.sql
- **Purpose**: Creates a new table to track login attempts for security monitoring
- **Features**: Stores device, IP address, location, and country code for each login
- **Foreign Key**: Links to the `users` table via `user_id`

## Important Notes

⚠️ **Before Running Migrations**:
1. **Backup your database** - Always create a backup before running migrations
2. **Test on development** - Test migrations on a development/staging environment first
3. **Check dependencies** - Ensure the `users` table exists before running migration 003
4. **Application updates** - The application code has been updated to work with these schema changes

## Rollback Instructions

If you need to rollback the changes:

### Rollback 001
```sql
ALTER TABLE `clients` CHANGE COLUMN `Responsible` `phone_number` VARCHAR(20) DEFAULT NULL;
```

### Rollback 002
```sql
ALTER TABLE `clients` DROP COLUMN `TIN`, DROP INDEX `idx_tin`;
```

### Rollback 003
```sql
DROP TABLE IF EXISTS `login_attempts`;
```

## Support

For issues or questions, contact the development team.
