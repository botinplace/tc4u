<?php 

namespace Core\Database;

use Core\Config\Config;
use Core\Database\DatabaseInterface;
use \PDO;
use \PDOException;
use \Exception;

class PostgreSQL implements DatabaseInterface{
    private static $dsn = "pgsql:host=" . Config::get('database.connections.pgsql.host') . ";port=5432;dbname=" . Config::get('database.connections.pgsql.name') . "; options='--client_encoding=UTF8'";
    private static $user = Config::get('database.connections.pgsql.user');
    private static $pass = Config::get('database.connections.pgsql.pass');
    private static $dbh = null;

    private static function initialize()
    {
        self::$dsn = "pgsql:host=" . Config::get('database.connections.pgsql.host') . ";port=5432;dbname=" . Config::get('database.connections.pgsql.name') . "; options='--client_encoding=UTF8'";
        self::$user = Config::get('database.connections.pgsql.user');
        self::$pass = Config::get('database.connections.pgsql.pass');
    }
    private static function getDbh()
    {
        self::initialize();
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
