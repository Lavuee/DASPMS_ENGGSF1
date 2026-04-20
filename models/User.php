<?php
class User {
    private $conn;
    private $table_name = "user";

    public $user_id;
    public $first_name;
    public $middle_name;
    public $last_name;
    public $username;
    public $password_hash;
    public $role;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $query = "SELECT user_id, username, password_hash, role, first_name, is_active 
                  FROM " . $this->table_name . " 
                  WHERE username = :username AND is_active = 1 LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            if (password_verify($password, $row['password_hash'])) {
                $this->user_id = $row['user_id'];
                $this->role = $row['role'];
                $this->username = $row['username'];
                $this->first_name = $row['first_name'];
                return true;
            }
        }
        return false;
    }

    public function readAll() {
        $query = "SELECT user_id, first_name, last_name, username, role, created_at 
                  FROM " . $this->table_name . " 
                  ORDER BY role ASC, last_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create($raw_password) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (first_name, middle_name, last_name, username, password_hash, role) 
                  VALUES 
                  (:first_name, :middle_name, :last_name, :username, :password_hash, :role)";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password_hash", $hashed_password);
        $stmt->bindParam(":role", $this->role);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>