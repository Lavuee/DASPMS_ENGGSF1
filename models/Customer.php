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
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY last_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readByStatus($status) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE status = :status
                  ORDER BY last_name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->execute();

        return $stmt;
    }

    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE customer_id = :customer_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":customer_id", $this->customer_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (first_name, middle_name, last_name, contact_number, address, status)
                  VALUES
                  (:first_name, :middle_name, :last_name, :contact_number, :address, 'Active')";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->middle_name = htmlspecialchars(strip_tags($this->middle_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->contact_number = htmlspecialchars(strip_tags($this->contact_number));
        $this->address = htmlspecialchars(strip_tags($this->address));

        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":contact_number", $this->contact_number);
        $stmt->bindParam(":address", $this->address);

        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    contact_number = :contact_number,
                    address = :address
                  WHERE customer_id = :customer_id";

        $stmt = $this->conn->prepare($query);

        $this->customer_id = intval($this->customer_id);
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->middle_name = htmlspecialchars(strip_tags($this->middle_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->contact_number = htmlspecialchars(strip_tags($this->contact_number));
        $this->address = htmlspecialchars(strip_tags($this->address));

        $stmt->bindParam(":customer_id", $this->customer_id, PDO::PARAM_INT);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":contact_number", $this->contact_number);
        $stmt->bindParam(":address", $this->address);

        return $stmt->execute();
    }

    public function deactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'Inactive',
                      deactivated_at = NOW()
                  WHERE customer_id = :customer_id";

        $stmt = $this->conn->prepare($query);
        $this->customer_id = intval($this->customer_id);
        $stmt->bindParam(":customer_id", $this->customer_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function reactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'Active',
                      deactivated_at = NULL,
                      archived_at = NULL
                  WHERE customer_id = :customer_id";

        $stmt = $this->conn->prepare($query);
        $this->customer_id = intval($this->customer_id);
        $stmt->bindParam(":customer_id", $this->customer_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function archive() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'Archived',
                      archived_at = NOW()
                  WHERE customer_id = :customer_id";

        $stmt = $this->conn->prepare($query);
        $this->customer_id = intval($this->customer_id);
        $stmt->bindParam(":customer_id", $this->customer_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
?>