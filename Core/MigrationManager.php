<?php
namespace Core;

use PDO;

class MigrationManager
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->createMigrationTable();
    }

    private function createMigrationTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->exec($sql);
    }

    public function run()
    {
        // Получаем все файлы миграций
        $migrationFiles = glob('migrations/*.sql');

        // Получаем уже выполненные миграции
        $appliedMigrations = $this->getMigrations();

        // Фильтруем только новые миграции
        $newMigrations = array_diff(array_map('basename', $migrationFiles), $appliedMigrations);

        if (!empty($newMigrations)) {
            $this->migrate(array_map(function($file) {
                return __DIR__ . '/' . $file;
            }, $newMigrations));

            echo "Миграции выполнены успешно!";
        } else {
            echo "Нет новых миграций для выполнения.";
        }
    }

    private function migrate($migrationFiles)
    {
        foreach ($migrationFiles as $file) {
            $migration = new Migration($this->db);
            $migration->migrate($file);
            $this->logMigration($file);
        }
    }

    private function logMigration($file)
    {
        $sql = "INSERT INTO migrations (migration) VALUES (:migration)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['migration' => basename($file)]);
    }

    public function getMigrations()
    {
        $sql = "SELECT migration FROM migrations";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
