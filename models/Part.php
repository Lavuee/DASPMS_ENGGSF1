<?php
class Part {
    private $conn;
    private $table_name = "part";

    public $part_id;
    public $category;
    public $part_name;
    public $description;
    public $unit_price;
    public $quantity_on_hand;
    public $low_stock_threshold;
    public $supplier_reference;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE is_active = 1 
                  ORDER BY category ASC, part_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (category, part_name, description, unit_price, quantity_on_hand, low_stock_threshold, supplier_reference) 
                  VALUES 
                  (:category, :part_name, :description, :unit_price, :quantity_on_hand, :low_stock_threshold, :supplier_reference)";

        $stmt = $this->conn->prepare($query);

        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->part_name = htmlspecialchars(strip_tags($this->part_name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->unit_price = htmlspecialchars(strip_tags($this->unit_price));
        $this->quantity_on_hand = htmlspecialchars(strip_tags($this->quantity_on_hand));
        $this->low_stock_threshold = htmlspecialchars(strip_tags($this->low_stock_threshold));
        $this->supplier_reference = htmlspecialchars(strip_tags($this->supplier_reference));

        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":part_name", $this->part_name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":unit_price", $this->unit_price);
        $stmt->bindParam(":quantity_on_hand", $this->quantity_on_hand);
        $stmt->bindParam(":low_stock_threshold", $this->low_stock_threshold);
        $stmt->bindParam(":supplier_reference", $this->supplier_reference);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>