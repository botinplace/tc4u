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

    public function createWithTransaction(array $data, callable $callback, array $options = [])
    {
        try {
            $this->db->beginTransaction();
            $result = $this->createRecord($data, $options);
            $callback($result);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
  
    public function findOneBy(string $field, $value): ?array
    {
        try {
            if (!$this->isDbConnected()) {
                return null;
            }
            if (!in_array($field, $this->fillable)) {
                throw new \InvalidArgumentException("Invalid field name: $field");
            }
            return $this->db->selectRow("SELECT * FROM {$this->table} WHERE $field = ? LIMIT 1", [$value]);
        } catch (Throwable $e) {
            error_log('Database error: ' . $e->getMessage());
            return null;
        }
    }
}
