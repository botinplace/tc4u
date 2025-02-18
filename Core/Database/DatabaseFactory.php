<?php
namespace Core\DB;

class DatabaseFactory {
    public static function create($type) {
        switch ($type) {
            case 'pgsql':
                return new ClassPostgre\DB();
            case 'mysql':
                return new ClassMysql\DB();
            case 'mssql':
                return new ClassMssql\DB();
            case 'sqli':
                return new ClassSQLi\DB();
            case 'nodb':
                return new ClassNoDB\DB();
            default:
                throw new \Exception("Неподдерживаемый тип базы данных: $type");
        }
    }
}
