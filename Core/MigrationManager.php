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

    public function migrate($migrationFiles)
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
