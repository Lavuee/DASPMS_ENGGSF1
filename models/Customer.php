<?php
class Customer {
    private $conn;
    private $table_name = "customer";

    public $customer_id;
    public $first_name;
    public $middle_name;
    public $last_name;
    public $contact_number;
    public $address;
    public $credit_balance;
    public $credit_due_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Retrieve all customers for the directory
    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY last_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Register a new customer
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (first_name, middle_name, last_name, contact_number, address) 
                  VALUES 
                  (:first_name, :middle_name, :last_name, :contact_number, :address)";

        $stmt = $this->conn->prepare($query);

        // Clean the inputs to prevent malicious code
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->middle_name = htmlspecialchars(strip_tags($this->middle_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->contact_number = htmlspecialchars(strip_tags($this->contact_number));
        $this->address = htmlspecialchars(strip_tags($this->address));
        
        // Bind the parameters
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":contact_number", $this->contact_number);
        $stmt->bindParam(":address", $this->address);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>