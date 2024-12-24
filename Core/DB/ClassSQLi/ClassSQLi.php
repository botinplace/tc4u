<?php
//namespace App\Core;

class SQLiteDB implements DBInterface
{
    private $db;
    private static $instance = null;
    private $table;

    private function __construct(string $pathToDb)
    {
        $this->db = new PDO("sqlite:" .'/home/c5324/core.topsite4u.ru/core/app/'. $pathToDb);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function connect(string $dbFile):self
    {
        if (self::$instance === null) {
            self::$instance = new self($dbFile);
        }

        return self::$instance;
    }

    public function table(string $table): void
    {
        $this->table = $table;
    }

    public function get($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll(int $limit = 3): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER by id LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($data): bool
    {
        $keys = implode(", ", array_keys($data));
        $values = ":" . implode(", :", array_keys($data));
        $sql = "INSERT INTO {$this->table} ({$keys}) VALUES ({$values})";
        $stmt = $this->db->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        return $stmt->execute();
    }

    public function update($id, $data): int
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $set = implode(", ", $set);
        $sql = "UPDATE {$this->table} SET {$set} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        return $stmt->execute();
    }

    public function delete($id): int
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}