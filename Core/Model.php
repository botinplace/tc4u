<?php
namespace Core;

class Model {
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