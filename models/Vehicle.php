<?php
class Vehicle {
    private $conn;
    private $table_name = "vehicle";

    public $vehicle_id;
    public $customer_id;
    public $plate_number;
    public $make;
    public $model;
    public $year;
    public $color;
    public $notes;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Read all vehicles and join with the customer table to get the owner's name
    public function readAllWithCustomer() {
        $query = "SELECT v.*, c.first_name, c.last_name 
                  FROM " . $this->table_name . " v
                  INNER JOIN customer c ON v.customer_id = c.customer_id
                  ORDER BY v.vehicle_id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Register a new vehicle
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (customer_id, plate_number, make, model, year, color, notes) 
                  VALUES 
                  (:customer_id, :plate_number, :make, :model, :year, :color, :notes)";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->customer_id = htmlspecialchars(strip_tags($this->customer_id));
        $this->plate_number = htmlspecialchars(strip_tags(strtoupper($this->plate_number))); // Ensure uppercase plates
        $this->make = htmlspecialchars(strip_tags($this->make));
        $this->model = htmlspecialchars(strip_tags($this->model));
        $this->year = htmlspecialchars(strip_tags($this->year));
        $this->color = htmlspecialchars(strip_tags($this->color));
        $this->notes = htmlspecialchars(strip_tags($this->notes));

        // Bind parameters
        $stmt->bindParam(":customer_id", $this->customer_id);
        $stmt->bindParam(":plate_number", $this->plate_number);
        $stmt->bindParam(":make", $this->make);
        $stmt->bindParam(":model", $this->model);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":color", $this->color);
        $stmt->bindParam(":notes", $this->notes);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>