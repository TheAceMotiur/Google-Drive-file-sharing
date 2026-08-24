# Database Migrations

This folder contains database schema migration files that are executed in order.

## File Naming Convention

Migration files should follow this pattern:
- `01_tablename.sql` - SQL files for creating tables
- `02_another_table.sql` - Another SQL file
- `03_custom_logic.php` - PHP files for complex migrations

Files are executed in alphabetical order (01, 02, 03, etc.)

## SQL Files (.sql)

SQL files should contain `CREATE TABLE IF NOT EXISTS` statements and other DDL commands.

Example:
```sql
-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## PHP Files (.php)

PHP files allow you to write custom migration logic. The `$db` variable is available and contains the PDO connection.

Example:
```php
<?php
// Seed initial data
$stmt = $db->prepare("INSERT IGNORE INTO settings (key, value) VALUES (?, ?)");
$stmt->execute(['app_name', 'FreeNetly']);
?>
```

## Running Migrations

### Full Migration (Schema + Data)
```bash
php migrate.php
```
Or visit: `http://yourdomain.com/migrate.php`

This runs:
1. Schema migrations (from this folder)
2. Data migration from legacy JSON files to MySQL (if they exist)

### Automatic (via database.php)
When you call `initDatabase()` in your code, it automatically reads and executes all migration files from this folder.

## Current Migration Files

- **01_files.sql** - Creates the `files` table for storing uploaded file metadata
- **02_accounts.sql** - Creates the `accounts` table for Google Drive credentials
- **03_metadata.sql** - Creates the `metadata` table for system settings

> **Note:** Add your Google Drive accounts via the admin panel after migration.

## Adding New Migrations

1. Create a new file with the next number: `05_your_migration.sql` or `05_your_migration.php`
2. Write your SQL or PHP migration code
3. Run the migrator to apply changes

## Best Practices

- Always use `IF NOT EXISTS` in CREATE TABLE statements
- Use `INSERT IGNORE` or `ON DUPLICATE KEY UPDATE` for data seeding
- Test migrations locally before deploying to production
- Never modify existing migration files after they've been deployed
- Create new migration files for schema changes instead
