<?php

namespace Core\Database;

use Core\Config\Config;
use Core\Database\DatabaseInterface;
use Exception;

class NoDB implements DatabaseInterface {
    private static $instance = null;
    private $storage = [];
    private $transactionStack = [];
    private $transactionLevel = 0;
    private $lastInsertId = 0;

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
        // Инициализация "подключения" - создаем базовые структуры
        $this->storage = [
            'tables' => [],
            'data' => []
        ];
    }

    public function beginTransaction(): bool {
        // Сохраняем текущее состояние
        $this->transactionLevel++;
        $this->transactionStack[] = [
            'storage' => unserialize(serialize($this->storage)),
            'lastInsertId' => $this->lastInsertId
        ];
        return true;
    }

    public function commit(): bool {
        if ($this->transactionLevel > 0) {
            $this->transactionLevel--;
            array_pop($this->transactionStack);
        }
        return true;
    }

    public function rollback(): bool {
        if ($this->transactionLevel > 0) {
            $state = array_pop($this->transactionStack);
            $this->storage = $state['storage'];
            $this->lastInsertId = $state['lastInsertId'];
            $this->transactionLevel--;
        }
        return true;
    }

    public function execute(string $query, array $params = []): array {
        try {
            $query = trim($query);
            $queryType = strtoupper(strtok($query, ' '));
            
            switch ($queryType) {
                case 'SELECT':
                    return $this->executeSelect($query, $params);
                case 'INSERT':
                    return $this->executeInsert($query, $params);
                case 'UPDATE':
                    return $this->executeUpdate($query, $params);
                case 'DELETE':
                    return $this->executeDelete($query, $params);
                case 'CREATE':
                    return $this->executeCreate($query, $params);
                default:
                    throw new Exception("Unsupported query type: $queryType");
            }
        } catch (Exception $e) {
            throw new Exception('Query execution failed: ' . $e->getMessage() . 
                              ' [Query: ' . $query . ']');
        }
    }

    private function executeSelect(string $query, array $params): array {
        // Простейший парсинг SELECT запроса
        if (preg_match('/FROM\s+([^\s,;]+)/i', $query, $matches)) {
            $tableName = trim($matches[1], '`\'"');
            
            if (!isset($this->storage['data'][$tableName])) {
                return ['rows' => []];
            }
            
            $rows = $this->storage['data'][$tableName];
            
            // Очень упрощенная фильтрация (без WHERE, JOIN и т.д.)
            return ['rows' => $rows];
        }
        
        return ['rows' => []];
    }

    private function executeInsert(string $query, array $params): array {
        if (preg_match('/INTO\s+([^\s(]+)/i', $query, $matches)) {
            $tableName = trim($matches[1], '`\'"');
            
            if (!isset($this->storage['data'][$tableName])) {
                $this->storage['data'][$tableName] = [];
            }
            
            $this->lastInsertId++;
            $newRow = ['id' => $this->lastInsertId] + $params;
            $this->storage['data'][$tableName][] = $newRow;
            
            return ['lastInsertId' => $this->lastInsertId, 'rowCount' => 1];
        }
        
        throw new Exception("Invalid INSERT query");
    }

    private function executeUpdate(string $query, array $params): array {
        // Упрощенная реализация UPDATE
        if (preg_match('/UPDATE\s+([^\s,;]+)/i', $query, $matches)) {
            $tableName = trim($matches[1], '`\'"');
            $updated = 0;
            
            if (isset($this->storage['data'][$tableName])) {
                foreach ($this->storage['data'][$tableName] as &$row) {
                    foreach ($params as $key => $value) {
                        $row[$key] = $value;
                    }
                    $updated++;
                }
            }
            
            return ['rowCount' => $updated];
        }
        
        throw new Exception("Invalid UPDATE query");
    }

    private function executeDelete(string $query, array $params): array {
        // Упрощенная реализация DELETE
        if (preg_match('/FROM\s+([^\s,;]+)/i', $query, $matches)) {
            $tableName = trim($matches[1], '`\'"');
            $deleted = 0;
            
            if (isset($this->storage['data'][$tableName])) {
                $deleted = count($this->storage['data'][$tableName]);
                $this->storage['data'][$tableName] = [];
            }
            
            return ['rowCount' => $deleted];
        }
        
        throw new Exception("Invalid DELETE query");
    }

    private function executeCreate(string $query, array $params): array {
        if (preg_match('/TABLE\s+([^\s(]+)/i', $query, $matches)) {
            $tableName = trim($matches[1], '`\'"');
            
            if (!isset($this->storage['data'][$tableName])) {
                $this->storage['data'][$tableName] = [];
                $this->storage['tables'][$tableName] = true;
            }
            
            return ['rowCount' => 1];
        }
        
        throw new Exception("Invalid CREATE query");
    }

    public function insert(string $query, array $params = []): string {
        $result = $this->execute($query, $params);
        return (string)$result['lastInsertId'];
    }

    public function insertWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        $result = $this->execute($query, $params);
        return [$result['lastInsertId']];
    }

    public function update(string $query, array $params = []): int {
        $result = $this->execute($query, $params);
        return $result['rowCount'];
    }

    public function updateWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        $this->update($query, $params);
        // Упрощенная реализация - возвращаем все ID таблицы
        if (preg_match('/UPDATE\s+([^\s,;]+)/i', $query, $matches)) {
            $tableName = trim($matches[1], '`\'"');
            return array_column($this->storage['data'][$tableName] ?? [], 'id');
        }
        return [];
    }
    
    public function delete(string $query, array $params = []): int {
        $result = $this->execute($query, $params);
        return $result['rowCount'];
    }

    public function deleteWithReturn(string $query, array $params = [], string $returnColumn = 'id'): array {
        $deletedIds = [];
        
        if (preg_match('/FROM\s+([^\s,;]+)/i', $query, $matches)) {
            $tableName = trim($matches[1], '`\'"');
            $deletedIds = array_column($this->storage['data'][$tableName] ?? [], 'id');
        }
        
        $this->delete($query, $params);
        return $deletedIds;
    }

    public function selectRow(string $query, array $params = []): ?array {
        $result = $this->execute($query, $params);
        return $result['rows'][0] ?? null;
    }

    public function selectAll(string $query, array $params = []): array {
        $result = $this->execute($query, $params);
        return $result['rows'];
    }

    public function selectValue(string $query, array $params = [], $default = null) {
        $row = $this->selectRow($query, $params);
        return $row ? reset($row) : $default;
    }

    public function selectColumn(string $query, array $params = []): array {
        $rows = $this->selectAll($query, $params);
        return array_column($rows, array_key_first($rows[0] ?? []));
    }

    public function close(): void {
        $this->storage = [
            'tables' => [],
            'data' => []
        ];
        $this->transactionStack = [];
        $this->transactionLevel = 0;
        $this->lastInsertId = 0;
    }

    public function __destruct() {
        $this->close();
    }

    // Проверка существование таблицы в базе данных
    public function tableExists(string $tableName): bool {
        return isset($this->storage['tables'][$tableName]);
    }

    // Очистка всех данных (для тестирования)
    public function clearAll(): void {
        $this->close();
    }
}
