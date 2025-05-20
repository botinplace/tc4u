<?php

namespace Core\Database;

use Core\Config\Config;
use Core\Database\DatabaseInterface;
use PDO;
use PDOException;
use Exception;

class SQLite implements DatabaseInterface {
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
        $dbPath = Config::get('database.connections.sqlite.path');
        
        try {
            $this->dbh = new PDO(
                "sqlite:$dbPath",
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false
                ]
            );
            
            // Включение поддержки внешних ключей
            $this->dbh->exec('PRAGMA foreign_keys = ON');
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
        return $this->dbh->lastInsertId();
    }

    public function insertWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        // Для SQLite используем RETURNING (поддерживается в SQLite 3.35.0+)
        if (version_compare($this->dbh->getAttribute(PDO::ATTR_SERVER_VERSION), '3.35.0', '>=')) {
            if (!preg_match('/RETURNING/i', $query)) {
                $query .= " RETURNING $returnColumn";
            }
            return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Для старых версий SQLite
        $this->execute($query, $params);
        $id = $this->dbh->lastInsertId();
        return $id ? [$id] : [];
    }

    public function update(string $query, array $params = []): int {
        $sth = $this->execute($query, $params);
        return $sth->rowCount();
    }

    public function updateWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        // Для SQLite 3.35.0+ используем RETURNING
        if (version_compare($this->dbh->getAttribute(PDO::ATTR_SERVER_VERSION), '3.35.0', '>=')) {
            if (!preg_match('/RETURNING/i', $query)) {
                $query .= " RETURNING $returnColumn";
            }
            return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Для старых версий - сначала получаем ID, потом обновляем
        $ids = $this->selectColumn(
            $this->convertToSelectQuery($query, $returnColumn),
            $params
        );
        $this->execute($query, $params);
        return $ids;
    }
    
    public function delete(string $query, array $params = []): int {
        $sth = $this->execute($query, $params);
        return $sth->rowCount();
    }

    public function deleteWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        // Для SQLite 3.35.0+ используем RETURNING
        if (version_compare($this->dbh->getAttribute(PDO::ATTR_SERVER_VERSION), '3.35.0', '>=')) {
            if (!preg_match('/RETURNING/i', $query)) {
                $query .= " RETURNING $returnColumn";
            }
            return $this->execute($query, $params)->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Для старых версий - сначала получаем ID, потом удаляем
        $ids = $this->selectColumn(
            $this->convertToSelectQuery($query, $returnColumn),
            $params
        );
        $this->execute($query, $params);
        return $ids;
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
        $query = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = :table";
        return (bool)$this->selectValue($query, [':table' => $tableName]);
    }

    // Вспомогательные методы
    
    private function convertToSelectQuery(string $query, string $returnColumn): string {
        $query = preg_replace('/^\s*(UPDATE|DELETE)\s+/i', 'SELECT ' . $returnColumn . ' ', $query);
        $query = preg_replace('/SET\s+.+?(WHERE|$)/i', '$1', $query);
        $query = preg_replace('/LIMIT\s+\d+/i', '', $query);
        return $query;
    }
}
