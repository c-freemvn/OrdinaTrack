<?php

namespace Ordinatrack\Api\Config;

use Dotenv\Dotenv;

/**
 * Configuration Manager
 * 
 * Centralizes access to environment variables with type casting and defaults
 */
class Config
{
    /**
     * @var array Cache for loaded config values
     */
    private static array $cache = [];

    /**
     * Initialize environment variables from .env file
     * 
     * @return void
     */
    public static function init(): void
    {
        if (!isset(self::$cache['initialized'])) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
            self::$cache['initialized'] = true;

            // Initialize database schemas
            try {
                require_once __DIR__ . '/../Connections/schemas.php';
                \Ordinatrack\Api\Connections\Schema::initialize();
            } catch (\Exception $e) {
                error_log('Schema initialization error: ' . $e->getMessage());
                // Don't throw - allow app to continue even if schema creation fails
            }
        }
    }

    /**
     * Get configuration value
     * 
     * @param string $key Configuration key (supports dot notation: db.host)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function get(string $key, $default = null)
    {
        self::init();

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $value = $_ENV[$key] ?? $default;
        self::$cache[$key] = $value;
        return $value;
    }

    /**
     * Get string configuration value
     * 
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string Configuration value
     */
    public static function getString(string $key, string $default = ''): string
    {
        return (string)(self::get($key, $default) ?? $default);
    }

    /**
     * Get integer configuration value
     * 
     * @param string $key Configuration key
     * @param int $default Default value
     * @return int Configuration value
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int)(self::get($key, $default) ?? $default);
    }

    /**
     * Get boolean configuration value
     * 
     * @param string $key Configuration key
     * @param bool $default Default value
     * @return bool Configuration value
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Check if running in production
     * 
     * @return bool
     */
    public static function isProduction(): bool
    {
        return self::getString('APP_ENV') === 'production';
    }

    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    public static function isDebug(): bool
    {
        return self::getBool('APP_DEBUG');
    }

    /**
     * Get all database configuration
     * 
     * @return array
     */
    public static function getDatabase(): array
    {
        return [
            'host' => self::getString('DB_HOST', 'localhost'),
            'port' => self::getInt('DB_PORT', 3306),
            'name' => self::getString('DB_NAME', 'ordinatrack'),
            'user' => self::getString('DB_USER', 'root'),
            'pass' => self::getString('DB_PASS', ''),
            'charset' => self::getString('DB_CHARSET', 'utf8mb4'),
        ];
    }

    /**
     * Get all JWT configuration
     * 
     * @return array
     */
    public static function getJwt(): array
    {
        return [
            'secret' => self::getString('JWT_SECRET'),
            'algorithm' => self::getString('JWT_ALGORITHM', 'HS256'),
            'expiration' => self::getInt('JWT_EXPIRATION', 86400),
            'refresh_expiration' => self::getInt('JWT_REFRESH_EXPIRATION', 604800),
        ];
    }

    /**
     * Get all mail configuration
     * 
     * @return array
     */
    public static function getMail(): array
    {
        return [
            'host' => self::getString('MAIL_HOST'),
            'port' => self::getInt('MAIL_PORT', 587),
            'username' => self::getString('MAIL_USERNAME'),
            'password' => self::getString('MAIL_PASSWORD'),
            'from_address' => self::getString('MAIL_FROM_ADDRESS'),
            'from_name' => self::getString('MAIL_FROM_NAME'),
        ];
    }

    /**
     * Get all session configuration
     * 
     * @return array
     */
    public static function getSession(): array
    {
        return [
            'timeout' => self::getInt('SESSION_TIMEOUT', 3600),
            'secure' => self::getBool('SESSION_SECURE', true),
            'httponly' => self::getBool('SESSION_HTTPONLY', true),
            'samesite' => self::getString('SESSION_SAMESITE', 'Strict'),
        ];
    }

    /**
     * Get security configuration
     * 
     * @return array
     */
    public static function getSecurity(): array
    {
        return [
            'cors_origins' => array_map('trim', explode(',', self::getString('CORS_ALLOWED_ORIGINS', 'http://localhost'))),
            'bcrypt_cost' => self::getInt('BCRYPT_COST', 12),
        ];
    }

    /**
     * Get logging configuration
     * 
     * @return array
     */
    public static function getLogging(): array
    {
        return [
            'level' => self::getString('LOG_LEVEL', 'info'),
            'path' => self::getString('LOG_PATH', 'logs/'),
        ];
    }

    /**
     * Get rate limiting configuration
     * 
     * @return array
     */
    public static function getRateLimit(): array
    {
        return [
            'enabled' => self::getBool('RATE_LIMIT_ENABLED', true),
            'requests' => self::getInt('RATE_LIMIT_REQUESTS', 100),
            'window' => self::getInt('RATE_LIMIT_WINDOW', 3600),
        ];
    }

    /**
     * Get application configuration
     * 
     * @return array
     */
    public static function getApp(): array
    {
        return [
            'name' => self::getString('APP_NAME', 'OrdinaTrack'),
            'env' => self::getString('APP_ENV', 'development'),
            'debug' => self::getBool('APP_DEBUG', false),
            'url' => self::getString('APP_URL', 'http://localhost'),
        ];
    }
}
