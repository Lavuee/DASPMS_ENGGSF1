<?php
/*
    Customer records. Customer create/update/status actions are handled in views/customers.php.
*/
class Customer {
    private $conn;
    private $table_name = "customer";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY last_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?>
