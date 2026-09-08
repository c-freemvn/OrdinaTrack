# Database Setup and Initialization

## Overview
The OrdinaTrack API now includes **automatic database creation and initialization**. When the API starts for the first time, it will:

1. ✅ **Check if the database exists** - connects to MySQL without specifying a database
2. ✅ **Create the database if needed** - using credentials from `.env` file
3. ✅ **Initialize all tables** - creates all 18 required tables with proper schema, indexes, and relationships

## Database Credentials

Located in `/API/.env`:
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ordinatrack
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

## Tables Created (18 Total)

### Core Tables
- **users** - User accounts with authentication
- **audit_logs** - Track all system activities

### Organization Structure
- **organizations** - Church organizations (NHQ, Province, District, Branch)
- **organization_members** - Links users to organizations
- **provinces** - Geographic provinces
- **districts** - Geographic districts under provinces
- **branches** - Branches under districts

### Access Control
- **roles** - System roles (Admin, NHQ Admin, Province Lead, District Lead, Branch Admin, Secretary, Member, Guest)
- **user_roles** - Many-to-Many between users and roles
- **permissions** - System permissions
- **role_permissions** - Many-to-Many between roles and permissions

### Data Management
- **members** - Organization members/attendees
- **member_roles** - Roles members hold in organizations
- **logistics** - Track inventory and equipment
- **folders** - Document/file organization
- **requests** - Track various requests (resources, approvals, etc.)

## Automatic Features

### Database Auto-Creation
When the API is first accessed:
- Connects to MySQL as root user
- Checks if database "ordinatrack" exists
- **Creates the database if it doesn't exist** with UTF8MB4 charset
- Logs the creation to PHP error log

### Schema Auto-Initialization
After database connection:
- Checks each table with `information_schema.TABLES`
- **Creates missing tables** with full schema, indexes, and relationships
- Inserts default roles and permissions
- Logs all table creations

### Entry Points
1. **First API call** - `/OrdinaTrack/API/index.php/auth/login`
2. **On Database class instantiation** - Automatic connection with creation
3. **On Config initialization** - Environment variables loaded

## How It Works

### File Structure
```
API/
├── src/
│   ├── Connections/
│   │   ├── Database.php       (handles connection + auto-creation)
│   │   └── schemas.php        (defines all tables + initialization)
│   └── Config/
│       └── Config.php         (loads .env credentials)
├── .env                       (database credentials)
└── index.php                  (entry point for all requests)
```

### Key Code Changes

#### Database.php
- `connect()` method now:
  1. Connects without specifying database
  2. Checks if database exists
  3. Creates if missing
  4. Connects to the new/existing database
  5. ~~Initializes schema~~ (now done via Config.init only)

#### Config.php
- Removed duplicate schema initialization
- Only loads environment variables from `.env`
- Schema is initialized when Database connection is first made

#### Index.php (No changes)
- Still the main entry point
- Works transparently with auto-creation

## Testing

### Manual API Test
```bash
curl -X POST http://localhost:8888/OrdinaTrack/API/index.php/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "test@test.com", "password": "test123"}'
```

Response (expected):
```json
{"success":false,"message":"Invalid email or password"}
```

This error is expected because no user exists yet, but it proves:
- ✅ API is working
- ✅ Database connection successful
- ✅ Tables were created and queried

### Verify in phpMyAdmin
1. Open http://localhost/phpmyadmin
2. Look for "ordinatrack" database in left sidebar
3. Click to expand and see all 18 tables
4. Click on "users" table to see schema with proper indexes

## Logs

All initialization is logged to `/Applications/MAMP/logs/php_error.log`:

```
[timestamp] Database 'ordinatrack' does not exist. Creating it...
[timestamp] Database 'ordinatrack' created successfully
[timestamp] Database connection established successfully
[timestamp] Created users table
[timestamp] Created organizations table
... (all table creations logged)
[timestamp] All database schemas initialized successfully
```

## Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| DB_HOST | localhost | MySQL host |
| DB_PORT | 3306 | MySQL port |
| DB_NAME | ordinatrack | Database name |
| DB_USER | root | MySQL username |
| DB_PASS | (empty) | MySQL password |
| DB_CHARSET | utf8mb4 | Character set |

## Troubleshooting

### Database Not Created?
1. Check PHP error log: `/Applications/MAMP/logs/php_error.log`
2. Verify MySQL is running in MAMP
3. Check .env credentials match your setup
4. Ensure `root` user can create databases

### Tables Not Created?
1. Check if database exists in phpMyAdmin
2. Look for "Created [table] table" messages in PHP error log
3. Verify file permissions on `/API/src/Connections/schemas.php`

### Access Denied Errors?
1. Verify `.env` has correct `DB_USER` and `DB_PASS`
2. MAMP typically uses `root` with empty password
3. Test MySQL connection: `mysql -u root -p ""` (empty password)

## Security Note

**For Production:**
- Change `DB_USER` and `DB_PASS` in `.env`
- Use a dedicated database user with limited privileges
- Never use `root` with empty password in production
- Set `DB_PASS` to a strong password
- Update MySQL user permissions to only what's needed

## Migration

If you already have a database:
- Existing tables are preserved (check `IF NOT EXISTS` in schema)
- New tables are created
- No data loss occurs
- Run `/auth/register` to create first user
