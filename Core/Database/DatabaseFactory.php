<?php
namespace Core\Database;

class DatabaseFactory {
    public static function create($type) {
        switch ($type) {
            case 'pgsql':
                return new PostgreSQL();
            case 'mysql':
                return new Mysql();
            case 'mssql':
                return new Mssql();
            case 'sqli':
                return new SQLi();
            case 'nodb':
                return new NoDB();
            default:
                throw new \Exception("Неподдерживаемый тип базы данных: $type");
        }
    }
}
