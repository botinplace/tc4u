<?php
namespace Core;

use Core\Database\DatabaseFactory;

abstract class Model {
    protected $db;
    
    public function __construct(?string $connectionName = null) {
        $this->db = $this->resolveConnection($connectionName);
    }
    
    protected function resolveConnection(?string $connectionName): DatabaseInterface {
        try {
            return DatabaseFactory::create(
                $connectionName ?? $this->getDefaultConnectionName()
            );
        } catch (\RuntimeException $e) {
            // Попытка использовать подключение по умолчанию
            if ($connectionName !== null && $connectionName !== 'default') {
                return DatabaseFactory::create('default');
            }
            throw $e;
        }
    }
    
    protected function getDefaultConnectionName(): string {
        return 'default';
    }
}
