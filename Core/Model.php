<?php
namespace Core;

class Model {
	// Тут подключить из конфига...
	function __construct(){
		
	}
    public static function create($dbType, $dbConfig) {
        switch ($dbType) {
            case 'mysql':
                return new MySQLModel($dbConfig);
            case 'pgsql':
                return new PostgreSQLModel($dbConfig);
            default:
                throw new Exception("Unsupported database type.");
        }
    }
}