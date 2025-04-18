<?php

namespace Core\Database;

use Core\Config\Config;
use Core\Database\DatabaseInterface;
use PDO;
use PDOException;
use Exception;

class MySQL implements DatabaseInterface {
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
        $dsn = "mysql:host=" . Config::get('database.connections.mysql.host') . 
               ";port=3306;dbname=" . Config::get('database.connections.mysql.name') . 
               ";charset=utf8mb4";
        
        try {
            $this->dbh = new PDO(
                $dsn,
                Config::get('database.connections.mysql.user'),
                Config::get('database.connections.mysql.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_STRINGIFY_FETCHES => false
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
    $this->execute($query, $params);
    $id = $this->dbh->lastInsertId();
    
    // Если запрос был массовой вставкой, lastInsertId() вернет только первый ID
    // Для получения всех ID нужно сделать дополнительный запрос
    if (stripos($query, 'INSERT INTO') !== false && $id) {
        $table = $this->extractTableNameFromInsertQuery($query);
        if ($table) {
            return $this->selectColumn(
                "SELECT $returnColumn FROM $table WHERE $returnColumn >= ? ORDER BY $returnColumn ASC LIMIT ?",
                [$id, $this->getAffectedRowsCount($query, $params)]
            );
        }
    }
    
    return $id ? [$id] : [];
}

// Вспомогательные методы:
private function extractTableNameFromInsertQuery(string $query): ?string {
    if (preg_match('/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $query, $matches)) {
        return $matches[1];
    }
    return null;
}

private function getAffectedRowsCount(string $query, array $params): int {
    $countQuery = "SELECT ROW_COUNT()";
    return (int)$this->selectValue($countQuery);
}

private function convertToSelectQuery(string $query, string $returnColumn): string {
    // Преобразуем UPDATE/DELETE запрос в SELECT для получения ID
    $query = preg_replace('/^\s*(UPDATE|DELETE)\s+/i', 'SELECT ' . $returnColumn . ' ', $query);
    
    // Для UPDATE заменяем SET на FROM
    $query = preg_replace('/SET\s+.+?(WHERE|$)/i', '$1', $query);
    
    // Удаляем LIMIT, если он есть (может мешать в подзапросах)
    $query = preg_replace('/LIMIT\s+\d+/i', '', $query);
    
    return $query;
}
    public function update(string $query, array $params = []): int {
        $sth = $this->execute($query, $params);
        return $sth->rowCount();
    }

public function updateWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
    // Сначала получаем ID записей, которые будут обновлены
    $idsQuery = $this->convertToSelectQuery($query, $returnColumn);
    $ids = $this->selectColumn($idsQuery, $params);
    
    // Выполняем сам UPDATE
    $this->execute($query, $params);
    
    // Возвращаем ID обновленных записей
    return $ids;
}
    
    public function delete(string $query, array $params = []): int {
        $sth = $this->execute($query, $params);
        return $sth->rowCount();
    }


public function deleteWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
    // Сначала получаем ID записей, которые будут удалены
    $idsQuery = $this->convertToSelectQuery($query, $returnColumn);
    $ids = $this->selectColumn($idsQuery, $params);
    
    // Выполняем сам DELETE
    $this->execute($query, $params);
    
    // Возвращаем ID удаленных записей
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
        $query = "SELECT COUNT(*) 
                 FROM information_schema.tables 
                 WHERE table_schema = DATABASE() 
                 AND table_name = :table";
        return (bool)$this->selectValue($query, [':table' => $tableName]);
    }
}
