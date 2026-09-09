<?php

namespace Ordinatrack\Api\Connections;

use PDO;
use PDOException;
use Ordinatrack\Api\Config\Config;

/**
 * Database Connection Manager
 * 
 * Handles database connections using PDO with singleton pattern
 * for efficient resource management and consistent configuration.
 */
class Database
{
    /**
     * @var PDO|null
     */
    private static ?PDO $connection = null;

    /**
     * PDO connection options
     */
    private const PDO_OPTIONS = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ];

    /**
     * Get singleton database connection
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::connect();
        }

        return self::$connection;
    }

    /**
     * Establish database connection
     * 
     * @return void
     * @throws PDOException
     */
    private static function connect(): void
    {
        try {
            Config::init();
            $dbConfig = Config::getDatabase();

            // First, try to connect WITHOUT specifying the database
            // This allows us to create the database if it doesn't exist
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['charset']
            );

            $tempConnection = new PDO(
                $dsn,
                $dbConfig['user'],
                $dbConfig['pass'],
                self::PDO_OPTIONS
            );

            // Check if database exists
            $dbName = $dbConfig['name'];
            $checkDb = $tempConnection->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
            
            if ($checkDb->rowCount() === 0) {
                // Database doesn't exist, create it
                error_log("Database '$dbName' does not exist. Creating it...");
                $tempConnection->exec("CREATE DATABASE `$dbName` CHARACTER SET {$dbConfig['charset']} COLLATE utf8mb4_unicode_ci");
                error_log("Database '$dbName' created successfully");
            }

            // Close the temporary connection
            $tempConnection = null;

            // Now connect to the actual database
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['name'],
                $dbConfig['charset']
            );

            self::$connection = new PDO(
                $dsn,
                $dbConfig['user'],
                $dbConfig['pass'],
                self::PDO_OPTIONS
            );

            error_log('Database connection established successfully');
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Unable to connect to database', (int)$e->getCode(), $e);
        }
    }

    /**
     * Close database connection
     * 
     * @return void
     */
    public static function closeConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Execute a prepared statement
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return \PDOStatement
     * @throws PDOException
     */
    public static function query(string $query, array $params = []): \PDOStatement
    {
        try {
            $connection = self::getConnection();
            $statement = $connection->prepare($query);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $e) {
            error_log('Query execution failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch a single row
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return array|false
     */
    public static function fetch(string $query, array $params = [])
    {
        try {
            $statement = self::query($query, $params);
            return $statement->fetch();
        } catch (PDOException $e) {
            error_log('Fetch failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch all rows
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return array|false
     */
    public static function fetchAll(string $query, array $params = [])
    {
        try {
            $statement = self::query($query, $params);
            return $statement->fetchAll();
        } catch (PDOException $e) {
            error_log('Fetch all failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute insert/update/delete and get affected rows
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return int Number of affected rows
     */
    public static function execute(string $query, array $params = []): int
    {
        try {
            $statement = self::query($query, $params);
            return $statement->rowCount();
        } catch (PDOException $e) {
            error_log('Execute failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get the ID of the last inserted row
     * 
     * @return string|false
     */
    public static function lastInsertId()
    {
        try {
            return self::getConnection()->lastInsertId();
        } catch (PDOException $e) {
            error_log('Last insert ID retrieval failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Begin a transaction
     * 
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        try {
            return self::getConnection()->beginTransaction();
        } catch (PDOException $e) {
            error_log('Begin transaction failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public static function commit(): bool
    {
        try {
            return self::getConnection()->commit();
        } catch (PDOException $e) {
            error_log('Commit failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public static function rollback(): bool
    {
        try {
            return self::getConnection()->rollBack();
        } catch (PDOException $e) {
            error_log('Rollback failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct()
    {
    }

    /**
     * Private clone to prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize to prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
