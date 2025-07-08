<?php
namespace Core;

use Core\Database\DatabaseInterface;
use Core\Model;
use Core\Security\Password;
use Core\Security\Random;
use Throwable;
use RuntimeException;

abstract class Repository extends Model
{
    protected string $table = '';
    protected array $fillable = [];
    protected string $returning = 'id';
    protected array $guarded = [];
    protected bool $softDeletes = false;
    protected string $deletedAtColumn = 'deleted_at';
    protected array $relations = [];
    
    protected function checkTable(): void
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name is not set for repository');
        }
    }
    
    protected function checkDbConnection(): void
    {
        if (!$this->isDbConnected()) {
            throw new RuntimeException('Database connection unavailable');
        }
    }
    
    protected function quoteIdentifier(string $identifier): string
    {
        if ($this->db && method_exists($this->db, 'quoteIdentifier')) {
            return $this->db->quoteIdentifier($identifier);
        }
        
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
    
    protected function handleException(Throwable $e, string $method): void
    {
        $message = sprintf(
            "[%s] Error in %s::%s: %s\n%s",
            date('Y-m-d H:i:s'),
            static::class,
            $method,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        
        error_log($message);
        
        
            try {
                $this->db->close();
            } catch (\Throwable $rollbackEx) {
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
        
    }
    
    protected function createRecord(array $data, array $options = []): ?array
    {
        try {
            $this->checkDbConnection();
            $this->checkTable();

            // Обработка данных перед вставкой
            foreach ($options['beforeCreate'] ?? [] as $callback) {
                if (is_callable($callback)) {
                    $data = $callback($data);
                }
            }

            // Фильтрация данных + обработка raw-выражений
            $filteredData = [];
            $rawExpressions = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $this->fillable)) {
                    if ($value instanceof RawExpression) {
                        $rawExpressions[$key] = $value->getValue();
                    } else {
                        $filteredData[$key] = $value;
                    }
                }
            }

            // Формируем SQL
            $allColumns = array_merge(
                array_keys($filteredData),
                array_keys($rawExpressions)
            );
            
            $placeholders = array_map(
                fn($key) => isset($rawExpressions[$key]) 
                    ? $rawExpressions[$key] 
                    : '?',
                $allColumns
            );
            
            $quotedColumns = array_map([$this, 'quoteIdentifier'], $allColumns);
            $columns = implode(', ', $quotedColumns);
            $placeholders = implode(', ', $placeholders);
            $values = array_values($filteredData);
            
            $sql = "INSERT INTO {$this->quoteIdentifier($this->table)} ($columns) VALUES ($placeholders)";
            
            // Выполнение запроса
            $result = $this->db->insertWithReturn($sql, $values, $this->returning);
            
            // Обработка данных после вставки
            foreach ($options['afterCreate'] ?? [] as $callback) {
                if (is_callable($callback)) {
                    $result = $callback($result, $data);
                }
            }

            return $result;

        } catch (Throwable $e) {
            $this->handleException($e, 'createRecord');
            return null;
        }
    }

    protected function filterGuarded(array $data): array
    {
        return array_filter($data, function($key) {
            return !in_array($key, $this->guarded);
        }, ARRAY_FILTER_USE_KEY);
    }
    
    public function createWithTransaction(array $data, callable $callback, array $options = [])
    {
        $this->checkDbConnection();
        
        try {
            $this->db->beginTransaction();
            $result = $this->createRecord($data, $options);
            
            if (!$result) {
                throw new RuntimeException('Record creation failed');
            }
            
            $callbackResult = $callback($result);
            
            $this->db->commit();
            
            return [
                'record' => $result,
                'callback' => $callbackResult
            ];
        } catch (\Throwable $e) {
            if ($this->db->dbh->inTransaction()) {
                $this->db->rollBack();
            }
            $this->handleException($e, 'createWithTransaction');
            throw $e;
        }
    }

    public function findOneBy(string|array $field, $value = null): ?array
    {
        try {
            $this->checkDbConnection();
            $this->checkTable();
            
            if (is_array($field)) {
                $conditions = [];
                $params = [];
                foreach ($field as $key => $val) {
                    if (!in_array($key, $this->fillable)) {
                        throw new \InvalidArgumentException("Invalid field name: $key");
                    }
                    
                    $conditions[] = "{$this->quoteIdentifier($key)} = ?";
                    $params[] = $val;
                }
                $where = implode(' AND ', $conditions);
                $sql = "SELECT * FROM {$this->quoteIdentifier($this->table)} WHERE $where LIMIT 1";
                return $this->db->selectRow($sql, $params);
            }
        
            if (!in_array($field, $this->fillable)) {
                throw new \InvalidArgumentException("Invalid field name: $field");
            }
            
            return $this->db->selectRow(
                "SELECT * FROM {$this->quoteIdentifier($this->table)} " .
                "WHERE {$this->quoteIdentifier($field)} = ? LIMIT 1", 
                [$value]
            );
        } catch (Throwable $e) {
            $this->handleException($e, 'findOneBy');
            return null;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $this->checkDbConnection();
            $this->checkTable();
            
            $data = $this->filterGuarded($data);
            
            $set = [];
            $values = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $this->fillable)) {
                    $set[] = "{$this->quoteIdentifier($key)} = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($set)) {
                return false;
            }
            
            $values[] = $id;
            
            $setClause = implode(', ', $set);
            $sql = "UPDATE {$this->quoteIdentifier($this->table)} " .
                   "SET $setClause " .
                   "WHERE {$this->quoteIdentifier('id')} = ?";
            
            $stmt = $this->db->execute($sql, $values);
            return $stmt ? $stmt->rowCount() > 0 : false;
        } catch (Throwable $e) {
            $this->handleException($e, 'update');
            return false;
        }
    }
    
    public function delete(int $id): bool
    {
        try {
            $this->checkDbConnection();
            $this->checkTable();
            
            if ($this->softDeletes) {
                // Проверяем наличие поля в fillable
                if (!in_array($this->deletedAtColumn, $this->fillable)) {
                    throw new RuntimeException(
                        "Deleted at column '{$this->deletedAtColumn}' is not fillable"
                    );
                }
                
                return $this->update($id, [
                    $this->deletedAtColumn => new RawExpression('NOW()')
                ]);
            }
            
            $sql = "DELETE FROM {$this->quoteIdentifier($this->table)} " .
                   "WHERE {$this->quoteIdentifier('id')} = ?";
            $stmt = $this->db->execute($sql, [$id]);
            return $stmt ? $stmt->rowCount() > 0 : false;
        } catch (Throwable $e) {
            $this->handleException($e, 'delete');
            return false;
        }
    }

    public function paginate(
        int $page = 1, 
        int $perPage = 15, 
        array $conditions = [],
        string $orderBy = 'id DESC'
    ): array
    {
        try {
            $this->checkDbConnection();
            $this->checkTable();
            
            $where = '';
            $params = [];
            
            if (!empty($conditions)) {
                $clauses = [];
                foreach ($conditions as $field => $value) {
                    if (!in_array($field, $this->fillable)) {
                        throw new \InvalidArgumentException("Invalid field name: $field");
                    }
                    
                    $clauses[] = "{$this->quoteIdentifier($field)} = ?";
                    $params[] = $value;
                }
                $where = 'WHERE ' . implode(' AND ', $clauses);
            }
            
            if (!preg_match('/^[a-z_]+(\s+(ASC|DESC))?$/i', $orderBy)) {
                throw new \InvalidArgumentException("Invalid order by clause: $orderBy");
            }
            
            $offset = max(0, ($page - 1) * $perPage);
            
            $sql = "SELECT * FROM {$this->quoteIdentifier($this->table)} 
                    $where 
                    ORDER BY $orderBy
                    LIMIT ? OFFSET ?";
            
            $params[] = $perPage;
            $params[] = $offset;
            
            $items = $this->db->select($sql, $params);
            
            // Получение общего количества
            $countSql = "SELECT COUNT(*) FROM {$this->quoteIdentifier($this->table)} $where";
            $total = (int)($this->db->selectValue($countSql, $params)) ?? 0;
            
            return [
                'items' => $items ?? [],
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $perPage > 0 ? (int)ceil($total / $perPage) : 1
            ];
        } catch (Throwable $e) {
            $this->handleException($e, 'paginate');
            return [
                'items' => [],
                'total' => 0,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => 1
            ];
        }
    }
    
    public function with(array $relations): self
    {
        $this->relations = $relations;
        return $this;
    }
    
    protected function loadRelations(array $record): array
    {
        if (empty($record)) return $record;
        
        foreach ($this->relations as $relation => $config) {
            if (is_string($config)) {
                $method = $config;
                $params = [];
            } else {
                $method = $config['method'] ?? $relation;
                $params = $config['params'] ?? [];
            }
            
            if (method_exists($this, $method)) {
                $record[$relation] = $this->$method($record['id'], ...$params);
            }
        }
        return $record;
    }
    
    public function __destruct()
    {
        // Очистка конфиденциальных данных
        if (function_exists('sodium_memzero')) {
            foreach ($this->guarded as $field) {
                if (isset($this->$field)) {
                    sodium_memzero($this->$field);
                }
            }
        }
    }
}
