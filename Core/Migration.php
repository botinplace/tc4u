<?php
namespace Core;

use Core\Database\DatabaseInterface;
use RuntimeException;

class Migration
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function migrate(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Migration file not found: $filePath");
        }

        $sql = file_get_contents($filePath);
        $this->db->execute($sql);
    }
}
