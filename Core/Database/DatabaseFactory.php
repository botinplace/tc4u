<?php

namespace Core\Database;

class DatabaseFactory {
    public static function create(string $type): DatabaseInterface {
        return match(strtolower($type)) {
            'mysql' => MySQL::getInstance(),
            'pgsql', 'postgresql' => PostgreSQL::getInstance(),
            'sqlite' => SQLite::getInstance(),
            'mssql', 'sqlsrv' => MSSQL::getInstance(),
            'nodb' => NoDB::getInstance(),
            default => throw new \InvalidArgumentException(
                "Неподдерживаемый тип базы данных: {$type}. " .
                "Поддерживаемые: mysql, pgsql, sqlite, mssql, nodb"
            )
        };
    }
}
