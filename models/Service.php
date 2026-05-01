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
    public $full_description;
    public $features;
    public $image;

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
                  (
                    service_name,
                    category,
                    base_price,
                    requires_down_payment,
                    warranty_days,
                    description,
                    full_description,
                    features,
                    image,
                    is_active
                  )
                  VALUES
                  (
                    :service_name,
                    :category,
                    :base_price,
                    :requires_down_payment,
                    :warranty_days,
                    :description,
                    :full_description,
                    :features,
                    :image,
                    1
                  )";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":service_name", $this->service_name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":base_price", $this->base_price);
        $stmt->bindParam(":requires_down_payment", $this->requires_down_payment);
        $stmt->bindParam(":warranty_days", $this->warranty_days);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":full_description", $this->full_description);
        $stmt->bindParam(":features", $this->features);
        $stmt->bindParam(":image", $this->image);

        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET
                    service_name = :service_name,
                    category = :category,
                    base_price = :base_price,
                    requires_down_payment = :requires_down_payment,
                    warranty_days = :warranty_days,
                    description = :description,
                    full_description = :full_description,
                    features = :features,
                    image = :image
                  WHERE service_id = :service_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":service_id", $this->service_id);
        $stmt->bindParam(":service_name", $this->service_name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":base_price", $this->base_price);
        $stmt->bindParam(":requires_down_payment", $this->requires_down_payment);
        $stmt->bindParam(":warranty_days", $this->warranty_days);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":full_description", $this->full_description);
        $stmt->bindParam(":features", $this->features);
        $stmt->bindParam(":image", $this->image);

        return $stmt->execute();
    }

    public function deactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = 0
                  WHERE service_id = :service_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":service_id", $this->service_id);

        return $stmt->execute();
    }
}
?>