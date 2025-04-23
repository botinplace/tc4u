<?php
namespace Core;

use Core\Database\DatabaseFactory;

abstract class Model {
    protected $db;
    
    public function __construct(?string $connectionName = null) {
        $this->db = $this->resolveConnection($connectionName);
    }
    
    protected function resolveConnection(?string $connectionName): DatabaseInterface {
        $connection = DatabaseFactory::create(
            $connectionName ?? $this->getDefaultConnectionName()
        );
        
        if (!$connection instanceof DatabaseInterface) {
            throw new RuntimeException("Invalid database connection");
        }
        
        return $connection;
    }
    
    protected function getDefaultConnectionName(): string {
        return 'default';
    }
}
