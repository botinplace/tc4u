<?php

namespace Core\Database;

use Core\Config\Config;
use Core\Database\DatabaseInterface;
use PDO;
use PDOException;
use Exception;

class PostgreSQLNew implements DatabaseInterface {
    private static $instance = null;
    private $dbh = null;
    private $transactionLevel = 0;

    // Приватный конструктор для Singleton
    private function __construct() {
        $this->connect();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect(): void {
        $dsn = "pgsql:host=" . Config::get('database.connections.pgsql.host') . 
               ";port=5432;dbname=" . Config::get('database.connections.pgsql.name') . 
               ";options='--client_encoding=UTF8'";
        
        try {
            $this->dbh = new PDO(
                $dsn,
                Config::get('database.connections.pgsql.user'),
                Config::get('database.connections.pgsql.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true // Для постоянного соединения
                ]
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public function beginTransaction(): bool {
        if ($this->transactionLevel === 0) {
            $this->dbh->beginTransaction();
        }
        $this->transactionLevel++;
        return true;
    }

    public function commit(): bool {
        if ($this->transactionLevel === 1) {
            $this->dbh->commit();
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
        return true;
    }

    public function rollback(): bool {
        if ($this->transactionLevel === 1) {
            $this->dbh->rollBack();
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
        return true;
    }

    public function execute(string $query, array $params = []): \PDOStatement {
        try {
            $sth = $this->dbh->prepare($query);
            $sth->execute($params);
            return $sth;
        } catch (PDOException $e) {
            throw new Exception('Query execution failed: ' . $e->getMessage() . 
                              ' [Query: ' . $query . ']');
        }
    }

    public function insert(string $query, array $params = []): string {
        $this->execute($query, $params);
        return $this->dbh->lastInsertId();
    }

    public function update(string $query, array $params = []): int {
        $sth = $this->execute($query, $params);
        return $sth->rowCount();
    }

    public function selectRow(string $query, array $params = []): ?array {
        $result = $this->execute($query, $params)->fetch();
        return $result ?: null;
    }

    public function selectAll(string $query, array $params = []): array {
        return $this->execute($query, $params)->fetchAll();
    }

    public function selectValue(string $query, array $params = [], $default = null) {
        $result = $this->selectRow($query, $params);
        return $result ? reset($result) : $default;
    }

    public function selectColumn(string $query, array $params = []): array {
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function close(): void {
        $this->dbh = null;
        self::$instance = null;
    }

    public function __destruct() {
        if ($this->transactionLevel > 0) {
            $this->rollback();
        }
        $this->close();
    }
}
