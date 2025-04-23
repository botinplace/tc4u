<?php

namespace Core\Database;

use PDOStatement;

interface DatabaseInterface {
    // Singleton pattern methods
    public static function getInstance(array $config): static;
    
    // Connection management
    public function close(): void;
    
    // Transaction management
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollback(): bool;
    
    // Query execution
    public function execute(string $query, array $params = []): ?PDOStatement;
    
    // Insert operations
    public function insert(string $query, array $params = []): ?string;
    public function insertWithReturn(string $query, array $params = [], string $returnColumn = 'id'): ?array;
    
    // Update operations
    public function update(string $query, array $params = []): ?int;
    public function updateWithReturn(string $query, array $params = [], string $returnColumn = 'id'): ?array;
    
    // Delete operations
    public function delete(string $query, array $params = []): ?int;
    public function deleteWithReturn(string $query, array $params = [], string $returnColumn = 'id'): ?array;
    
    // Select operations
    public function selectRow(string $query, array $params = []): ?array;
    public function selectAll(string $query, array $params = []): ?array;
    public function selectValue(string $query, array $params = [], $default = null);
    public function selectColumn(string $query, array $params = []): ?array;
    
    // Schema operations
    public function tableExists(string $tableName): bool;
}
