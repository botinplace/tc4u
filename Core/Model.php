<?php
namespace Core;

use Core\Database\DatabaseFactory;
use Core\Database\DatabaseInterface;
use RuntimeException;

abstract class Model 
{
    protected ?DatabaseInterface $db = null;
    
    public function __construct(?string $connectionName = null) 
    {
        try {
            $this->db = $this->resolveConnection($connectionName);
        } catch (RuntimeException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            $this->db = null; // Явно указываем, что соединение отсутствует
        }
    }
    
    protected function resolveConnection(?string $connectionName): ?DatabaseInterface 
    {
        try {
            $connection = DatabaseFactory::create(
                $connectionName ?? $this->getDefaultConnectionName()
            );
            
            return $connection instanceof DatabaseInterface ? $connection : null;
        } catch (RuntimeException $e) {
            if ($connectionName !== null && $connectionName !== 'default') {
                return $this->resolveConnection('default');
            }
            throw $e;
        }
    }
    
    protected function getDefaultConnectionName(): string 
    {
        return 'default';
    }
    
    protected function isDbConnected(): bool
    {
        return $this->db !== null && $this->db->isConnected();
    }
}
