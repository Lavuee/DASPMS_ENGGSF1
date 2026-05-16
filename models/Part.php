<?php
class Part {
    private $conn;
    private $table_name = "part";

    public $part_id;
    public $category;
    public $brand;
    public $part_name;
    public $description;
    public $specification;
    public $compatibility;
    public $unit;
    public $full_description;
    public $unit_price;
    public $cost_price;
    public $quantity_on_hand;
    public $low_stock_threshold;
    public $supplier_id; // MODIFIED: Replaced text fields with strictly typed supplier_id
    public $image;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function cleanForStorage($value) {
        $value = trim((string)($value ?? ''));
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);

        return trim($value);
    }

    public function readAll() {
        $query = "SELECT *
                  FROM " . $this->table_name . "
                  WHERE is_active = 1
                  ORDER BY category ASC, part_name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function partExists($part_name, $brand = '', $specification = '', $excludePartId = null) {
        $query = "SELECT part_id
                  FROM " . $this->table_name . "
                  WHERE UPPER(TRIM(part_name)) = :part_name
                    AND UPPER(TRIM(COALESCE(brand, ''))) = :brand
                    AND UPPER(TRIM(COALESCE(specification, ''))) = :specification
                    AND is_active = 1";

        if ($excludePartId !== null) {
            $query .= " AND part_id != :exclude_part_id";
        }

        $query .= " LIMIT 1";

        $stmt = $this->conn->prepare($query);

        $cleanPartName = strtoupper($this->cleanForStorage($part_name));
        $cleanBrand = strtoupper($this->cleanForStorage($brand));
        $cleanSpecification = strtoupper($this->cleanForStorage($specification));

        $stmt->bindParam(":part_name", $cleanPartName);
        $stmt->bindParam(":brand", $cleanBrand);
        $stmt->bindParam(":specification", $cleanSpecification);

        if ($excludePartId !== null) {
            $excludePartId = intval($excludePartId);
            $stmt->bindParam(":exclude_part_id", $excludePartId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function create() {
        $this->category = $this->cleanForStorage($this->category);
        $this->brand = $this->cleanForStorage($this->brand);
        $this->part_name = $this->cleanForStorage($this->part_name);
        $this->description = $this->cleanForStorage($this->description);
        $this->specification = $this->cleanForStorage($this->specification);
        $this->compatibility = $this->cleanForStorage($this->compatibility);
        $this->unit = $this->cleanForStorage($this->unit);
        $this->full_description = $this->cleanForStorage($this->full_description);

        if ($this->unit === '') {
            $this->unit = 'piece';
        }

        if ($this->partExists($this->part_name, $this->brand, $this->specification)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . "
                  (
                    category,
                    brand,
                    part_name,
                    description,
                    specification,
                    compatibility,
                    unit,
                    full_description,
                    unit_price,
                    cost_price,
                    quantity_on_hand,
                    low_stock_threshold,
                    supplier_id,
                    image,
                    is_active
                  )
                  VALUES
                  (
                    :category,
                    :brand,
                    :part_name,
                    :description,
                    :specification,
                    :compatibility,
                    :unit,
                    :full_description,
                    :unit_price,
                    :cost_price,
                    :quantity_on_hand,
                    :low_stock_threshold,
                    :supplier_id,
                    :image,
                    1
                  )";

        $stmt = $this->conn->prepare($query);

        $brandValue = $this->brand !== '' ? $this->brand : null;
        $specificationValue = $this->specification !== '' ? $this->specification : null;
        $compatibilityValue = $this->compatibility !== '' ? $this->compatibility : null;
        $fullDescriptionValue = $this->full_description !== '' ? $this->full_description : null;
        $supplierIdValue = !empty($this->supplier_id) ? intval($this->supplier_id) : null;

        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":brand", $brandValue);
        $stmt->bindParam(":part_name", $this->part_name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":specification", $specificationValue);
        $stmt->bindParam(":compatibility", $compatibilityValue);
        $stmt->bindParam(":unit", $this->unit);
        $stmt->bindParam(":full_description", $fullDescriptionValue);
        $stmt->bindParam(":unit_price", $this->unit_price);
        $stmt->bindParam(":cost_price", $this->cost_price);
        $stmt->bindParam(":quantity_on_hand", $this->quantity_on_hand);
        $stmt->bindParam(":low_stock_threshold", $this->low_stock_threshold);
        $stmt->bindParam(":supplier_id", $supplierIdValue, PDO::PARAM_INT);
        $stmt->bindParam(":image", $this->image);

        return $stmt->execute();
    }

    public function update() {
        $this->part_id = intval($this->part_id);
        $this->category = $this->cleanForStorage($this->category);
        $this->brand = $this->cleanForStorage($this->brand);
        $this->part_name = $this->cleanForStorage($this->part_name);
        $this->description = $this->cleanForStorage($this->description);
        $this->specification = $this->cleanForStorage($this->specification);
        $this->compatibility = $this->cleanForStorage($this->compatibility);
        $this->unit = $this->cleanForStorage($this->unit);
        $this->full_description = $this->cleanForStorage($this->full_description);

        if ($this->unit === '') {
            $this->unit = 'piece';
        }

        if ($this->partExists($this->part_name, $this->brand, $this->specification, $this->part_id)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . "
                  SET
                    category = :category,
                    brand = :brand,
                    part_name = :part_name,
                    description = :description,
                    specification = :specification,
                    compatibility = :compatibility,
                    unit = :unit,
                    full_description = :full_description,
                    unit_price = :unit_price,
                    cost_price = :cost_price,
                    quantity_on_hand = :quantity_on_hand,
                    low_stock_threshold = :low_stock_threshold,
                    supplier_id = :supplier_id,
                    image = :image
                  WHERE part_id = :part_id";

        $stmt = $this->conn->prepare($query);

        $brandValue = $this->brand !== '' ? $this->brand : null;
        $specificationValue = $this->specification !== '' ? $this->specification : null;
        $compatibilityValue = $this->compatibility !== '' ? $this->compatibility : null;
        $fullDescriptionValue = $this->full_description !== '' ? $this->full_description : null;
        $supplierIdValue = !empty($this->supplier_id) ? intval($this->supplier_id) : null;

        $stmt->bindParam(":part_id", $this->part_id, PDO::PARAM_INT);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":brand", $brandValue);
        $stmt->bindParam(":part_name", $this->part_name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":specification", $specificationValue);
        $stmt->bindParam(":compatibility", $compatibilityValue);
        $stmt->bindParam(":unit", $this->unit);
        $stmt->bindParam(":full_description", $fullDescriptionValue);
        $stmt->bindParam(":unit_price", $this->unit_price);
        $stmt->bindParam(":cost_price", $this->cost_price);
        $stmt->bindParam(":quantity_on_hand", $this->quantity_on_hand);
        $stmt->bindParam(":low_stock_threshold", $this->low_stock_threshold);
        $stmt->bindParam(":supplier_id", $supplierIdValue, PDO::PARAM_INT);
        $stmt->bindParam(":image", $this->image);

        return $stmt->execute();
    }

    public function archive() {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = 0
                  WHERE part_id = :part_id";

        $stmt = $this->conn->prepare($query);
        $this->part_id = intval($this->part_id);
        $stmt->bindParam(":part_id", $this->part_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deactivate() {
        return $this->archive();
    }

    public function restore() {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = 1
                  WHERE part_id = :part_id";

        $stmt = $this->conn->prepare($query);
        $this->part_id = intval($this->part_id);
        $stmt->bindParam(":part_id", $this->part_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function hasTransactionHistory() {
        $partId = intval($this->part_id);

        $queries = [
            "SELECT COUNT(*) FROM job_order_part WHERE part_id = ?",
            "SELECT COUNT(*) FROM part_order_item WHERE part_id = ?",
            "SELECT COUNT(*) FROM pos_item WHERE part_id = ?",
            "SELECT COUNT(*) FROM stock_in WHERE part_id = ?"
        ];

        foreach ($queries as $query) {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$partId]);

            if (intval($stmt->fetchColumn()) > 0) {
                return true;
            }
        }

        return false;
    }

    public function deletePermanent() {
        $this->part_id = intval($this->part_id);

        if ($this->hasTransactionHistory()) {
            return false;
        }

        $query = "DELETE FROM " . $this->table_name . "
                  WHERE part_id = :part_id
                    AND is_active = 0";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":part_id", $this->part_id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }
}
?>