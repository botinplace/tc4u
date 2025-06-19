<?php
namespace Core;

use Core\Model;
use RuntimeException;

class MigrationManager extends Model
{
    private string $migrationsPath;

    public function __construct(string $migrationsPath, ?string $connectionName = null)
    {
        // Вызываем родительский конструктор для подключения к БД
        parent::__construct($connectionName);
        
        if (!$this->isDbConnected()) {
            throw new RuntimeException('Database connection failed');
        }

        $this->migrationsPath = rtrim($migrationsPath, '/');
        $this->createMigrationTable();
    }

    private function createMigrationTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->execute($sql);
    }

    public function run(): void
    {
        try {
            // Создаем папку для миграций если её нет
            if (!is_dir($this->migrationsPath)) {
                mkdir($this->migrationsPath, 0755, true);
            }

            $migrationFiles = glob($this->migrationsPath . '/*.sql');
            $appliedMigrations = $this->getMigrations();
            
            $newMigrations = array_diff(
                array_map('basename', $migrationFiles),
                $appliedMigrations
            );

            if (empty($newMigrations)) {
                echo "No new migrations to execute.\n";
                return;
            }

            foreach ($newMigrations as $migration) {
                $filePath = $this->migrationsPath . '/' . $migration;
                (new Migration($this->db))->migrate($filePath);
                $this->logMigration($migration);
                echo "Executed migration: $migration\n";
            }

            echo "All migrations executed successfully!\n";
        } catch (\Throwable $e) {
            throw new RuntimeException("Migration failed: " . $e->getMessage());
        }
    }

    private function logMigration(string $migration): void
    {
        $sql = "INSERT INTO migrations (migration) VALUES (:migration)";
        $this->db->execute($sql, ['migration' => $migration]);
    }

    private function getMigrations(): array
    {
        $sql = "SELECT migration FROM migrations";
        return $this->db->selectColumn($sql);
    }
}
