<?php
namespace Core;

use Core\Model;
use Core\Security\Password;
use Core\Security\Random;
use Throwable;

abstract class Repository extends Model
{
    protected string $returning = 'id';
    protected array $guarded = [];
    protected bool $softDeletes = false;
    protected string $deletedAtColumn = 'deleted_at';
    
    protected function createRecord(array $data, array $options = []): ?array
    {
        try {
            if (!$this->isDbConnected()) {
                throw new \RuntimeException('Database connection unavailable');
            }

            // Обработка данных перед вставкой
            foreach ($options['beforeCreate'] ?? [] as $callback) {
                $data = $callback($data);
            }

            // Фильтрация данных + обработка raw-выражений
            $filteredData = [];
            $rawExpressions = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $this->fillable)) {
                    // Проверяем, является ли значение raw-выражением
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
            
            $columns = implode(', ', $allColumns);
            $placeholders = implode(', ', $placeholders);
            //$placeholders = implode(', ', array_fill(0, count($filteredData), '?'));
            $values = array_values($filteredData);
            
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
            
            // Выполнение запроса
            $result = $this->db->insertWithReturn($sql, $values,$this->returning);
            
            // Обработка данных после вставки
            foreach ($options['afterCreate'] ?? [] as $callback) {
                $result = $callback($result, $data);
            }

            return $result;

        } catch (Throwable $e) {
            error_log('Database error: ' . $e->getMessage());
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
                throw new \RuntimeException('Record creation failed');
            }
            
            // Передаем результат колбэка
            $callbackResult = $callback($result);
            
            $this->db->commit();
            
            return [
                'record' => $result,
                'callback' => $callbackResult
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

  
    public function findOneBy(string|array $field, $value = null): ?array
    {
         if (is_array($field)) {
            $conditions = [];
            $params = [];
            foreach ($field as $key => $val) {
                // Добавьте проверку имени поля
                if (!in_array($key, $this->fillable)) {
                    throw new \InvalidArgumentException("Invalid field name: $key");
                }
                
                $conditions[] = "{$this->quoteIdentifier($key)} = ?";
                $params[] = $val;
            }
            $where = implode(' AND ', $conditions);
            $sql = "SELECT * FROM {$this->table} WHERE $where LIMIT 1";
            return $this->db->selectRow($sql, $params);
        }
    
        if (!in_array($field, $this->fillable)) {
            throw new \InvalidArgumentException("Invalid field name: $field");
        }
        
        return $this->db->selectRow(
            "SELECT * FROM {$this->table} WHERE $field = ? LIMIT 1", 
            [$value]
        );
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterGuarded($data);
        
        $set = [];
        $values = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $set[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (empty($set)) {
            return false;
        }
        
        $values[] = $id;
        
        $setClause = implode(', ', $set);
        $sql = "UPDATE {$this->table} SET $setClause WHERE id = ?";
        
        try {
            $stmt = $this->db->execute($sql, $values);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('Update error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function delete(int $id): bool
    {
        if ($this->softDeletes) {
            return $this->update($id, [
                $this->deletedAtColumn => new RawExpression('NOW()')
            ]);
        }
        try {
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->execute($sql, [$id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('Delete error: ' . $e->getMessage());
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
        $this->checkTable();
        
        $where = '';
        $params = [];
        
        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $field => $value) {
                // Проверяем допустимость поля
                if (!in_array($field, $this->fillable)) {
                    throw new \InvalidArgumentException("Invalid field name: $field");
                }
                
                $clauses[] = "{$this->quoteIdentifier($field)} = ?";
                $params[] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $clauses);
        }
        
        // Проверка и экранирование сортировки
        if (!preg_match('/^[a-z_]+(\s+(ASC|DESC))?$/i', $orderBy)) {
            throw new \InvalidArgumentException("Invalid order by clause: $orderBy");
        }
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table} 
                $where 
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $items = $this->db->select($sql, $params);
        
        // Получение общего количества
        $countSql = "SELECT COUNT(*) FROM {$this->table} $where";
        $total = $this->db->selectValue($countSql, $params);
        
        return [
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }
    public function with(array $relations): self
    {
        $this->relations = $relations;
        return $this;
    }
    
    protected function loadRelations(array $record): array
    {
        foreach ($this->relations as $relation) {
            if (method_exists($this, $relation)) {
                $record[$relation] = $this->$relation($record['id']);
            }
        }
        return $record;
    }
    
}
