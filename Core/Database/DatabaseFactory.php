<?php
namespace Core\Database;

class DatabaseFactory {
    public static function create($type) {
        switch ($type) {
            case 'pgsql':
                return new Core\Database\PostgreSQL();
            case 'mysql':
                return new Core\Database\Mysql();
            case 'mssql':
                return new Core\Database\Mssql();
            case 'sqli':
                return new Core\Database\SQLi();
            case 'nodb':
                return new  Core\Database\NoDB();
            default:
                throw new \Exception("Неподдерживаемый тип базы данных: $type");
        }
    }
}
