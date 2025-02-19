<?php
namespace Core;

use PDO;

class Migration
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function migrate($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Migration file not found: $filePath");
        }

        $sql = file_get_contents($filePath);
        $this->db->set($sql);
    }
}
