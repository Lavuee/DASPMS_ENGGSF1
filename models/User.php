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
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

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
        $query = "SELECT user_id, first_name, middle_name, last_name, username, role, is_active, created_at 
                  FROM " . $this->table_name . " 
                  WHERE role IN ('Owner', 'Cashier', 'Head Mechanic')
                  ORDER BY role ASC, last_name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function create($raw_password) {
        if ($this->usernameExists($this->username)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " 
                  (first_name, middle_name, last_name, username, password_hash, role, is_active) 
                  VALUES 
                  (:first_name, :middle_name, :last_name, :username, :password_hash, :role, 1)";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags(trim($this->first_name)));
        $this->middle_name = htmlspecialchars(strip_tags(trim($this->middle_name ?? '')));
        $this->last_name = htmlspecialchars(strip_tags(trim($this->last_name)));
        $this->username = htmlspecialchars(strip_tags(trim($this->username)));
        $this->role = htmlspecialchars(strip_tags(trim($this->role)));

        $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password_hash", $hashed_password);
        $stmt->bindParam(":role", $this->role);

        return $stmt->execute();
    }

    public function update() {
        if ($this->usernameExists($this->username, $this->user_id)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . "
                  SET 
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    username = :username,
                    role = :role
                  WHERE user_id = :user_id
                    AND role != 'Owner'";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags(trim($this->first_name)));
        $this->middle_name = htmlspecialchars(strip_tags(trim($this->middle_name ?? '')));
        $this->last_name = htmlspecialchars(strip_tags(trim($this->last_name)));
        $this->username = htmlspecialchars(strip_tags(trim($this->username)));
        $this->role = htmlspecialchars(strip_tags(trim($this->role)));
        $this->user_id = intval($this->user_id);

        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":user_id", $this->user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = 0
                  WHERE user_id = :user_id
                    AND role != 'Owner'";

        $stmt = $this->conn->prepare($query);
        $this->user_id = intval($this->user_id);

        $stmt->bindParam(":user_id", $this->user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function reactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = 1
                  WHERE user_id = :user_id
                    AND role != 'Owner'";

        $stmt = $this->conn->prepare($query);
        $this->user_id = intval($this->user_id);

        $stmt->bindParam(":user_id", $this->user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function usernameExists($username, $excludeUserId = null) {
        $query = "SELECT user_id
                  FROM " . $this->table_name . "
                  WHERE username = :username";

        if ($excludeUserId !== null) {
            $query .= " AND user_id != :exclude_user_id";
        }

        $query .= " LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $cleanUsername = htmlspecialchars(strip_tags(trim($username)));

        $stmt->bindParam(":username", $cleanUsername);

        if ($excludeUserId !== null) {
            $excludeUserId = intval($excludeUserId);
            $stmt->bindParam(":exclude_user_id", $excludeUserId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
?>