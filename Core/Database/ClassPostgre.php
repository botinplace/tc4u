<?php 
namespace Core\Database\ClassPostgre;

use Core\Database\DatabaseInterface;
use \PDO;

class DB implements DatabaseInterface{
    private static $dsn = "pgsql:host=" . POSTGRESQL_HOST . ";port=5432;dbname=" . POSTGRESQL_NAME . "; options='--client_encoding=UTF8'";
    private static $user = POSTGRESQL_USER;
    private static $pass = POSTGRESQL_PASS;
    private static $dbh = null;

    private static function getDbh()
    {
        if (!self::$dbh) {
            try {
                self::$dbh = new PDO(self::$dsn, self::$user, self::$pass);
                self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                throw new Exception('Error connecting to database: ' . $e->getMessage());
            }
        }
        return self::$dbh;
    }

    public static function execute($query, $params = [])
    {
        self::$sth = self::getDbh()->prepare($query);
        if (self::$sth->execute((array) $params)) {
            return self::$sth;
        }
        throw new Exception('Query execution failed: ' . implode(', ', self::$sth->errorInfo()));
    }

    public static function add($query, $params = [])
    {
        self::execute($query, $params);
        return self::getDbh()->lastInsertId();
    }

    public static function set($query, $params = [])
    {
        self::execute($query, $params);
        return self::$sth->rowCount();
    }

    public static function getRow($query, $params = [])
    {
        self::execute($query, $params);
        return self::$sth->fetch(PDO::FETCH_ASSOC);
    }

    public static function getAll($query, $params = [])
    {
        self::execute($query, $params);
        return self::$sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getValue($query, $params = [], $default = null)
    {
        $result = self::getRow($query, $params);
        return !empty($result) ? reset($result) : $default; 
    }

    public static function getColumn($query, $params = [])
    {
        self::execute($query, $params);
        return self::$sth->fetchAll(PDO::FETCH_COLUMN);
    }
}
