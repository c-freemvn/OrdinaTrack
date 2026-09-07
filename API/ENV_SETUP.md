# Environment Configuration Setup

This document describes the environment configuration system for the OrdinaTrack API.

## Overview

The API uses PHP dotenv to manage environment variables. This allows for secure configuration management while keeping sensitive data out of the repository.

## Files Created

### 1. `.env` (Development)
Contains default development settings. **DO NOT commit sensitive values to this file in production.**

### 2. `.env.example`
Template file showing all available configuration options. This should be committed to the repository as a reference.

### 3. `src/Config/Config.php`
Centralized configuration manager that:
- Loads environment variables from `.env`
- Provides type-safe getters (getString, getInt, getBool)
- Caches config values for performance
- Groups related configs (getDatabase(), getJwt(), getMail(), etc.)

## Configuration Variables

### Application Settings
- `APP_ENV` - Environment mode (development/production)
- `APP_DEBUG` - Debug mode (true/false)
- `APP_NAME` - Application name
- `APP_URL` - Application URL

### Database Configuration
- `DB_HOST` - MySQL host
- `DB_PORT` - MySQL port (default: 3306)
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `DB_CHARSET` - Character set (default: utf8mb4)

### JWT Configuration
- `JWT_SECRET` - Secret key for signing tokens (IMPORTANT: Change this!)
- `JWT_ALGORITHM` - Algorithm (default: HS256)
- `JWT_EXPIRATION` - Access token expiration in seconds (default: 86400 = 24 hours)
- `JWT_REFRESH_EXPIRATION` - Refresh token expiration in seconds (default: 604800 = 7 days)

### Email Configuration
- `MAIL_HOST` - SMTP host
- `MAIL_PORT` - SMTP port
- `MAIL_USERNAME` - SMTP username
- `MAIL_PASSWORD` - SMTP password
- `MAIL_FROM_ADDRESS` - Sender email address
- `MAIL_FROM_NAME` - Sender name

### Session Configuration
- `SESSION_TIMEOUT` - Session idle timeout in seconds
- `SESSION_SECURE` - Use secure cookies (1/0)
- `SESSION_HTTPONLY` - HttpOnly flag for cookies (1/0)
- `SESSION_SAMESITE` - SameSite policy (Strict/Lax/None)

### Security Settings
- `CORS_ALLOWED_ORIGINS` - Comma-separated list of allowed origins
- `BCRYPT_COST` - Bcrypt hashing cost (higher = slower but more secure, default: 12)

### Logging
- `LOG_LEVEL` - Log level (debug/info/warning/error)
- `LOG_PATH` - Directory for log files

### Rate Limiting
- `RATE_LIMIT_ENABLED` - Enable rate limiting (true/false)
- `RATE_LIMIT_REQUESTS` - Maximum requests per window
- `RATE_LIMIT_WINDOW` - Time window in seconds

## Usage Examples

### In Controllers/Models
```php
use Ordinatrack\Api\Config\Config;

// Get a single value
$dbHost = Config::getString('DB_HOST');
$jwtSecret = Config::getString('JWT_SECRET');
$batchSize = Config::getInt('BATCH_SIZE');

// Get grouped config
$dbConfig = Config::getDatabase();
$jwtConfig = Config::getJwt();

// Check environment
if (Config::isProduction()) {
    // Production-specific code
}

if (Config::isDebug()) {
    // Debug-specific code
}
```

## Composer Dependencies

The following dependencies were added:

```json
{
    "wixel/gump": "^2.3",
    "firebase/php-jwt": "^6.10",
    "vlucas/phpdotenv": "^5.6"
}
```

Run `composer install` to install these dependencies.

## Security Best Practices

1. **Never commit `.env` to the repository** - Only commit `.env.example`
2. **Change JWT_SECRET in production** - Use a strong, random key
3. **Use environment-specific configs** - Different values for dev/staging/production
4. **Restrict file permissions** - .env files should not be world-readable
5. **Use HTTPS in production** - Required for secure cookie transmission
6. **Rotate JWT secrets regularly** - When keys may be compromised

## Production Setup

1. Copy `.env.example` to `.env`
2. Update all configuration values for production
3. Ensure `.env` is not accessible via web
4. Set appropriate file permissions (e.g., `chmod 600 .env`)
5. Use a secrets manager for sensitive values if possible

## Troubleshooting

### Config values not loading
- Ensure `.env` file exists in the API root directory
- Check file permissions (must be readable by PHP)
- Verify syntax in `.env` file (no quotes needed for simple values)

### JWT token errors
- Verify `JWT_SECRET` is set and matches across environment
- Check `JWT_EXPIRATION` values are reasonable

### Database connection fails
- Verify database credentials in `.env`
- Ensure MySQL is running and accessible
- Check database name exists
