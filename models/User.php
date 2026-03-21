<?php
class User {
    private $conn;
    private $table_name = "user";

    public $user_id;
    public $username;
    public $role;
    public $first_name;

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
            // Verify the entered password against the hashed password in the database
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
}
?>