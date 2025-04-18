<?php
namespace Core\Database;

class DatabaseFactory {
    public static function create($type) {
        switch ($type) {
            case 'pgsql':
                return new PostgreSQL();
            case 'mysql':
                return new MySQL();
            case 'mssql':
                return new MsSQL();
            case 'sqlite':
                return new SQLite();
            case 'nodb':
                return new NoDB();
            default:
                throw new \Exception("Неподдерживаемый тип базы данных: $type");
        }
    }
}
