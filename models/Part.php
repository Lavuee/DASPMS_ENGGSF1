<?php
class Part {
    private $conn;
    private $table_name = "part";

    public $part_id;
    public $category;
    public $part_name;
    public $description;
    public $full_description;
    public $unit_price;
    public $cost_price;
    public $quantity_on_hand;
    public $low_stock_threshold;
    public $supplier_reference;
    public $image;

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
                  (
                    category,
                    part_name,
                    description,
                    full_description,
                    unit_price,
                    cost_price,
                    quantity_on_hand,
                    low_stock_threshold,
                    supplier_reference,
                    image,
                    is_active
                  )
                  VALUES
                  (
                    :category,
                    :part_name,
                    :description,
                    :full_description,
                    :unit_price,
                    :cost_price,
                    :quantity_on_hand,
                    :low_stock_threshold,
                    :supplier_reference,
                    :image,
                    1
                  )";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":part_name", $this->part_name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":full_description", $this->full_description);
        $stmt->bindParam(":unit_price", $this->unit_price);
        $stmt->bindParam(":cost_price", $this->cost_price);
        $stmt->bindParam(":quantity_on_hand", $this->quantity_on_hand);
        $stmt->bindParam(":low_stock_threshold", $this->low_stock_threshold);
        $stmt->bindParam(":supplier_reference", $this->supplier_reference);
        $stmt->bindParam(":image", $this->image);

        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET
                    category = :category,
                    part_name = :part_name,
                    description = :description,
                    full_description = :full_description,
                    unit_price = :unit_price,
                    cost_price = :cost_price,
                    quantity_on_hand = :quantity_on_hand,
                    low_stock_threshold = :low_stock_threshold,
                    supplier_reference = :supplier_reference,
                    image = :image
                  WHERE part_id = :part_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":part_id", $this->part_id);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":part_name", $this->part_name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":full_description", $this->full_description);
        $stmt->bindParam(":unit_price", $this->unit_price);
        $stmt->bindParam(":cost_price", $this->cost_price);
        $stmt->bindParam(":quantity_on_hand", $this->quantity_on_hand);
        $stmt->bindParam(":low_stock_threshold", $this->low_stock_threshold);
        $stmt->bindParam(":supplier_reference", $this->supplier_reference);
        $stmt->bindParam(":image", $this->image);

        return $stmt->execute();
    }

    public function deactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = 0
                  WHERE part_id = :part_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":part_id", $this->part_id);

        return $stmt->execute();
    }
}
?>