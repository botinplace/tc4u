<?php
namespace Core\Database;

use Core\Config\Config;
use PDOException;
use RuntimeException;

class DatabaseFactory 
{
    public static function create(string $connectionName): DatabaseInterface
    {
        $config = self::getValidatedConfig($connectionName);
        
        try {
            return self::createConnection($config);
        } catch (PDOException $e) {
            error_log("PDO Error [{$connectionName}]: " . $e->getMessage());
            throw new RuntimeException("Database connection failed", 0, $e);
        } catch (Throwable $e) {
            error_log("Database Error [{$connectionName}]: " . $e->getMessage());
            throw new RuntimeException("Could not create database connection", 0, $e);
        }
    }

    private static function getValidatedConfig(string $connectionName): array
    {
        $config = Config::get("database.connections.{$connectionName}");
        
        if (!$config) {
            throw new RuntimeException(
                "Database configuration not found for connection: {$connectionName}"
            );
        }
        
        if (empty($config['driver'])) {
            throw new RuntimeException(
                "Database driver not specified for connection: {$connectionName}"
            );
        }
        
        return $config;
    }

    private static function createConnection(array $config): DatabaseInterface
    {
        $driver = strtolower($config['driver']);
        
        return match($driver) {
            'mysql'     => MySQL::getInstance($config),
            'pgsql',
            'postgresql' => PostgreSQL::getInstance($config),
            'sqlite'     => SQLite::getInstance($config),
            'mssql',
            'sqlsrv'     => MSSQL::getInstance($config),
            'nodb'       => NoDB::getInstance($config),
            default      => throw new RuntimeException(
                "Unsupported database driver: {$config['driver']}"
            )
        };
    }
}
