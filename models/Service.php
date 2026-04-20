<?php
class Service {
    private $conn;
    private $table_name = "service";

    public $service_id;
    public $service_name;
    public $category;
    public $base_price;
    public $requires_down_payment;
    public $warranty_days;
    public $description;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE is_active = 1 
                  ORDER BY category ASC, service_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (service_name, category, base_price, requires_down_payment, warranty_days, description, is_active) 
                  VALUES 
                  (:service_name, :category, :base_price, :requires_down_payment, :warranty_days, :description, 1)";

        $stmt = $this->conn->prepare($query);

        $this->service_name = htmlspecialchars(strip_tags($this->service_name));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->base_price = htmlspecialchars(strip_tags($this->base_price));
        $this->requires_down_payment = htmlspecialchars(strip_tags($this->requires_down_payment));
        $this->warranty_days = htmlspecialchars(strip_tags($this->warranty_days));
        $this->description = htmlspecialchars(strip_tags($this->description));

        $stmt->bindParam(":service_name", $this->service_name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":base_price", $this->base_price);
        $stmt->bindParam(":requires_down_payment", $this->requires_down_payment);
        $stmt->bindParam(":warranty_days", $this->warranty_days);
        $stmt->bindParam(":description", $this->description);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>