<?php
class DB {
    private $host = 'your_host';
    private $database = 'your_database';
    private $username = 'your_username';
    private $password = 'your_password';
    private $pdo;

    public function __construct() {
        $dsn = "sqlsrv:Server={$this->host};Database={$this->database}";
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $fields = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        $this->query($sql, $data);
    }

    public function update($table, $data, $id) {
        $setFields = [];
        foreach ($data as $key => $value) {
            $setFields[] = "$key = :$key";
        }
        $setFields = implode(", ", $setFields);
        $sql = "UPDATE $table SET $setFields WHERE id = :id";
        $data['id'] = $id;
        $this->query($sql, $data);
    }

    public function delete($table, $id) {
        $sql = "DELETE FROM $table WHERE id = :id";
        $this->query($sql, ['id' => $id]);
    }
}



/*
// Example usage
$database = new DB();

// Select example
$users = $database->select("SELECT * FROM users");
print_r($users);

// Insert example
$newUser = ['username' => 'john.doe', 'email' => 'john.doe@example.com'];
$database->insert('users', $newUser);

// Update example
$userData = ['username' => 'jane.doe', 'email' => 'jane.doe@example.com'];
$database->update('users', $userData, 1); // Assuming the user ID is 1

// Delete example
$database->delete('users', 1); // Delete user with ID 1
*/