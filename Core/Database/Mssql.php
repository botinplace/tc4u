<?php

namespace Core\Database;

use Core\Config\Config;
use Core\Database\DatabaseInterface;
use PDO;
use PDOException;
use Exception;

class MSSQL implements DatabaseInterface {
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
        $dsn = "sqlsrv:Server=" . Config::get('database.connections.mssql.host') . 
               "," . Config::get('database.connections.mssql.port', 1433) . 
               ";Database=" . Config::get('database.connections.mssql.name');
        
        try {
            $this->dbh = new PDO(
                $dsn,
                Config::get('database.connections.mssql.user'),
                Config::get('database.connections.mssql.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
                ]
            );
            
            // Установка параметров соединения
            $this->dbh->exec("SET ANSI_NULLS ON");
            $this->dbh->exec("SET QUOTED_IDENTIFIER ON");
            $this->dbh->exec("SET CONCAT_NULL_YIELDS_NULL ON");
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
            
            foreach ($params as $key => $value) {
                $paramType = PDO::PARAM_STR;
                if (is_int($value)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $paramType = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $paramType = PDO::PARAM_NULL;
                }
                
                $sth->bindValue(
                    is_int($key) ? $key + 1 : $key,
                    $value,
                    $paramType
                );
            }
            
            $sth->execute();
            return $sth;
        } catch (PDOException $e) {
            throw new Exception('Query execution failed: ' . $e->getMessage() . 
                              ' [Query: ' . $query . ']', (int)$e->getCode());
        }
    }

    public function insert(string $query, array $params = []): string {
        $this->execute($query, $params);
        return $this->selectValue("SELECT SCOPE_IDENTITY()");
    }

    public function insertWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        // Для MSSQL используем OUTPUT вместо RETURNING
        if (!preg_match('/OUTPUT\s+(INSERTED\.)?/i', $query)) {
            $query = preg_replace('/^INSERT/i', 'INSERT OUTPUT INSERTED.' . $returnColumn, $query);
        }
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function update(string $query, array $params = []): int {
        $sth = $this->execute($query, $params);
        return $sth->rowCount();
    }

    public function updateWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        // Для MSSQL используем OUTPUT
        if (!preg_match('/OUTPUT\s+(DELETED\.|INSERTED\.)?/i', $query)) {
            $query = preg_replace('/^UPDATE/i', 'UPDATE OUTPUT INSERTED.' . $returnColumn, $query);
        }
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function delete(string $query, array $params = []): int {
        $sth = $this->execute($query, $params);
        return $sth->rowCount();
    }

    public function deleteWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        // Для MSSQL используем OUTPUT
        if (!preg_match('/OUTPUT\s+(DELETED\.)?/i', $query)) {
            $query = preg_replace('/^DELETE/i', 'DELETE OUTPUT DELETED.' . $returnColumn, $query);
        }
        return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
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
        if ($this->dbh !== null && $this->dbh->inTransaction()) {
            $this->dbh->rollBack();
        }
        $this->dbh = null;
        self::$instance = null;
    }

    public function __destruct() {
        if ($this->transactionLevel > 0) {
            $this->rollback();
        }
        $this->close();
    }

     // Проверка существование таблицы в базе данных
    public function tableExists(string $tableName): bool {
        $query = "SELECT COUNT(*) 
                 FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = 'dbo' 
                 AND TABLE_NAME = :table";
        return (bool)$this->selectValue($query, [':table' => $tableName]);
    }
}
