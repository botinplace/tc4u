<?php
namespace Core;

use Core\Model;
use Core\Session;
use Core\Security\Password;
use Core\Security\Random;
use Throwable;

abstract class BaseRepository extends Model
{
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
            $values = array_values($filteredData);
            
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders) RETURNING {$this->returning}";
            
            // Выполнение запроса
            $result = $this->db->insertWithReturn($sql, $values);
            
            // Обработка данных после вставки
            foreach ($options['afterCreate'] ?? [] as $callback) {
                $result = $callback($result, $data);
            }

            return $result;

        } catch (Throwable $e) {
            error_log('Database error: ' . $e->getMessage());
            Session::flash('error', $options['errorMessage'] ?? 'Ошибка при создании записи');
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
            return $this->db->selectRow("SELECT * FROM {$this->table} WHERE $field = ? LIMIT 1", [$value]);
        } catch (Throwable $e) {
            error_log('Database error: ' . $e->getMessage());
            return null;
        }
    }
}
