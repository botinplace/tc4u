<?php
namespace Core\Database;

use Core\Config\Config;
use Core\Database\DatabaseInterface;
use PDO;
use PDOException;

class PostgreSQL implements DatabaseInterface 
{
    private static array $instances = [];
    private ?PDO $dbh = null;
    private int $transactionLevel = 0;
    private string $connectionName;
    private bool $isConnected = false;
    private array $lastError = [];

    private function __construct(array $config) 
    {
        $this->connectionName = $config['name'] ?? 'default';
        $this->connect($config);
    }

    public static function getInstance(array $config): static  
    {
        $connectionName = $config['name'] ?? 'default';
        
        if (!isset(self::$instances[$connectionName])) {
            self::$instances[$connectionName] = new self($config);
        }
        
        return self::$instances[$connectionName];
    }

    private function connect(array $config): void 
    {
        $port = $config['port'] ?? 5432;
        $dsn = "pgsql:host={$config['host']};port={$port};dbname={$config['database']};options='--client_encoding=UTF8'";
        
        try {
            $this->dbh = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => $config['persistent'] ?? false,
                    PDO::ATTR_STRINGIFY_FETCHES => false
                ]
            );
            $this->isConnected = true;
            error_log("PostgreSQL connection established: {$this->connectionName}");
        } catch (PDOException $e) {
            $this->isConnected = false;
            error_log("PostgreSQL connection FAILED [{$this->connectionName}]: " . $e->getMessage());
        }
    }

    public function beginTransaction(): bool 
    {
        if (!$this->isConnected) {
            error_log("Transaction start failed - no active connection");
            return false;
        }

        try {
            if ($this->transactionLevel === 0) {
                $this->dbh->beginTransaction();
            }
            $this->transactionLevel++;
            return true;
        } catch (PDOException $e) {
            error_log("Transaction start failed: " . $e->getMessage());
            return false;
        }
    }

    public function commit(): bool 
    {
        if (!$this->isConnected) {
            error_log("Commit failed - no active connection");
            return false;
        }

        try {
            if ($this->transactionLevel === 1) {
                $this->dbh->commit();
            }
            $this->transactionLevel = max(0, $this->transactionLevel - 1);
            return true;
        } catch (PDOException $e) {
            error_log("Commit failed: " . $e->getMessage());
            return false;
        }
    }

    public function rollback(): bool 
    {
        if (!$this->isConnected) {
            error_log("Rollback failed - no active connection");
            return false;
        }

        try {
            if ($this->transactionLevel === 1) {
                $this->dbh->rollBack();
            }
            $this->transactionLevel = max(0, $this->transactionLevel - 1);
            return true;
        } catch (PDOException $e) {
            error_log("Rollback failed: " . $e->getMessage());
            return false;
        }
    }

    private function quoteIdentifier(string $field): string {
        return '"' . str_replace('"', '""', $field) . '"';
    }
    
    public function execute(string $query, array $params = []): ?\PDOStatement 
    {
        if (!$this->isConnected) {
            error_log("Query execution failed - no active connection");
            return null;
        }
        
        $this->lastError = [];

        try {
            $sth = $this->dbh->prepare($query);
            
            foreach ($params as $key => $value) {
                $paramType = match(true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR
                };
                
                $sth->bindValue(
                    is_int($key) ? $key + 1 : $key,
                    $value,
                    $paramType
                );
            }
            
            $sth->execute();
            return $sth;
        } catch (PDOException $e) {
            $this->lastError = $e->errorInfo;            
            error_log("Query execution failed: " . $e->getMessage() . " [Query: " . substr($query, 0, 500) . "]");
            //throw new \Exception("Query failed: " . $e->getMessage(), 0, $e);
            return null;
        }
    }

    /*
    public function insert(string $query, array $params = []): ?string 
    {
        $result = $this->execute($query, $params);
        return $result ? $this->dbh->lastInsertId() : null;
    }
    */
    public function insert(string $query, array $params = []): ?string 
    {
        $result = $this->execute($query, $params);
        
        if (!$result) {
            return null;
        }
        
        // Если запрос содержит RETURNING, пытаемся получить значение
        //if (preg_match('/RETURNING\s+([\w"]+)/i', $query, $matches)) {
        if (preg_match('/RETURNING\s+(.+)/i', $query, $matches)) {
            $columns = array_map('trim', explode(',', $matches[1])); // Разбиваем на поля
            $returnedValues = $result->fetch(PDO::FETCH_ASSOC); // Получаем массив значений
        
            // Если возвращается одно поле, возвращаем значение
            if (count($columns) === 1) {
                return $returnedValues[$columns[0]] ?? null; // Возвращаем значение или null
            }
        
            // Если возвращается несколько полей, возвращаем массив
            return array_intersect_key($returnedValues, array_flip($columns));
        
            //$column = str_replace('"', '', $matches[1]);
            //return $result->fetchColumn(0) ?: null;
        }
        
        // Если RETURNING нет, возвращаем lastInsertId (для обратной совместимости)
        return $this->dbh->lastInsertId();
    }

    public function insertWithReturn(string $query, array $params = [], string $returnColumn = 'id'): ?array 
    {
        if (!preg_match('/RETURNING/i', $query)) {
            $query .= " RETURNING ".$this->quoteIdentifier($returnColumn);
        }
        $result = $this->execute($query, $params);
        return $result ? $result->fetchAll(PDO::FETCH_COLUMN) : null;
    }

    public function update(string $query, array $params = []): ?int 
    {
        $result = $this->execute($query, $params);
        return $result ? $result->rowCount() : null;
    }

    public function updateWithReturn(string $query, array $params = [], string $returnColumn = 'id'): ?array 
    {
        if (!preg_match('/RETURNING/i', $query)) {
            $query .= " RETURNING ".$this->quoteIdentifier($returnColumn);
        }
        $result = $this->execute($query, $params);
        return $result ? $result->fetchAll(PDO::FETCH_COLUMN) : null;
    }
    
    public function delete(string $query, array $params = []): ?int 
    {
        $result = $this->execute($query, $params);
        return $result ? $result->rowCount() : null;
    }

    public function deleteWithReturn(string $query, array $params = [], string $returnColumn = 'id'): ?array 
    {
        if (!preg_match('/RETURNING/i', $query)) {
            $query .= " RETURNING ".$this->quoteIdentifier($returnColumn);
        }
        $result = $this->execute($query, $params);
        return $result ? $result->fetchAll(PDO::FETCH_COLUMN) : null;
    }

    public function selectRow(string $query, array $params = []): ?array 
    {
        $result = $this->execute($query, $params);
        return $result ? $result->fetch() ?: null : null;
    }

    public function selectAll(string $query, array $params = []): ?array 
    {
        $result = $this->execute($query, $params);
        return $result ? $result->fetchAll() : null;
    }

    public function selectValue(string $query, array $params = [], $default = null) 
    {
        $result = $this->selectRow($query, $params);
        return $result ? reset($result) : $default;
    }

    public function selectColumn(string $query, array $params = []): ?array 
    {
        $result = $this->execute($query, $params);
        return $result ? $result->fetchAll(PDO::FETCH_COLUMN) : null;
    }

    public function close(): void 
    {
        if ($this->dbh !== null && $this->dbh->inTransaction()) {
            $this->rollback();
        }
        $this->dbh = null;
        $this->isConnected = false;
        unset(self::$instances[$this->connectionName]);
    }

    public function __destruct() 
    {
        $this->close();
    }

    public function tableExists(string $tableName): bool 
    {
        $query = "SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = :table
        )";
        return (bool)$this->selectValue($query, [':table' => $tableName]);
    }

    /**
    * Возвращает последнюю ошибку PDO
    * @return array [code, message, driverCode]
    */
    public function getLastError(): array 
    {
        if ($this->dbh) {
            $this->lastError = $this->dbh->errorInfo();
        }
        return $this->lastError;
    }

    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    public function ping(): bool {
        try {
            return (bool)$this->dbh->query('SELECT 1');
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function reconnect(): bool
    {
        $this->close();
        $this->connect($this->config ?? []);
        return $this->isConnected;
    }
}
