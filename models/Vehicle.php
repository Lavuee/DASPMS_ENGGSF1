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
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAllWithCustomer($status = 'Active') {
        if ($status === 'All') {
            $query = "SELECT 
                        v.*, 
                        c.first_name, 
                        c.last_name,
                        c.contact_number
                      FROM " . $this->table_name . " v
                      INNER JOIN customer c ON v.customer_id = c.customer_id
                      ORDER BY FIELD(v.status, 'Active', 'Inactive', 'Archived'), v.vehicle_id DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt;
        }

        $query = "SELECT 
                    v.*, 
                    c.first_name, 
                    c.last_name,
                    c.contact_number
                  FROM " . $this->table_name . " v
                  INNER JOIN customer c ON v.customer_id = c.customer_id
                  WHERE v.status = :status
                  ORDER BY v.vehicle_id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->execute();
        return $stmt;
    }

    public function readByCustomer($customer_id, $activeOnly = true) {
        $query = "SELECT *
                  FROM " . $this->table_name . "
                  WHERE customer_id = :customer_id";

        if ($activeOnly) {
            $query .= " AND status = 'Active'";
        }

        $query .= " ORDER BY created_at DESC, vehicle_id DESC";

        $stmt = $this->conn->prepare($query);
        $customerId = intval($customer_id);
        $stmt->bindParam(":customer_id", $customerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function readOneWithCustomer() {
        $query = "SELECT 
                    v.*, 
                    c.first_name, 
                    c.last_name,
                    c.contact_number
                  FROM " . $this->table_name . " v
                  INNER JOIN customer c ON v.customer_id = c.customer_id
                  WHERE v.vehicle_id = :vehicle_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":vehicle_id", $this->vehicle_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function plateExists($plate_number, $excludeVehicleId = null) {
        $query = "SELECT vehicle_id
                  FROM " . $this->table_name . "
                  WHERE UPPER(TRIM(plate_number)) = :plate_number";

        if ($excludeVehicleId !== null) {
            $query .= " AND vehicle_id != :exclude_vehicle_id";
        }

        $query .= " LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $cleanPlate = strtoupper(trim($plate_number));
        $stmt->bindParam(":plate_number", $cleanPlate);

        if ($excludeVehicleId !== null) {
            $excludeVehicleId = intval($excludeVehicleId);
            $stmt->bindParam(":exclude_vehicle_id", $excludeVehicleId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function belongsToCustomer($vehicle_id, $customer_id) {
        $query = "SELECT vehicle_id
                  FROM " . $this->table_name . "
                  WHERE vehicle_id = :vehicle_id
                    AND customer_id = :customer_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $vehicleId = intval($vehicle_id);
        $customerId = intval($customer_id);
        $stmt->bindParam(":vehicle_id", $vehicleId, PDO::PARAM_INT);
        $stmt->bindParam(":customer_id", $customerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function create() {
        if ($this->plateExists($this->plate_number)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " 
                  (customer_id, plate_number, make, model, year, color, notes, status) 
                  VALUES 
                  (:customer_id, :plate_number, :make, :model, :year, :color, :notes, 'Active')";

        $stmt = $this->conn->prepare($query);

        $this->customer_id = intval($this->customer_id);
        $this->plate_number = htmlspecialchars(strip_tags(strtoupper(trim($this->plate_number))));
        $this->make = htmlspecialchars(strip_tags(trim($this->make)));
        $this->model = htmlspecialchars(strip_tags(trim($this->model)));
        $this->year = htmlspecialchars(strip_tags(trim($this->year)));
        $this->color = htmlspecialchars(strip_tags(trim($this->color)));
        $this->notes = htmlspecialchars(strip_tags(trim($this->notes ?? '')));

        $notesValue = $this->notes !== '' ? $this->notes : null;

        $stmt->bindParam(":customer_id", $this->customer_id, PDO::PARAM_INT);
        $stmt->bindParam(":plate_number", $this->plate_number);
        $stmt->bindParam(":make", $this->make);
        $stmt->bindParam(":model", $this->model);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":color", $this->color);
        $stmt->bindParam(":notes", $notesValue);

        return $stmt->execute();
    }

    public function update() {
        if ($this->plateExists($this->plate_number, $this->vehicle_id)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . "
                  SET 
                    customer_id = :customer_id,
                    plate_number = :plate_number,
                    make = :make,
                    model = :model,
                    year = :year,
                    color = :color,
                    notes = :notes
                  WHERE vehicle_id = :vehicle_id";

        $stmt = $this->conn->prepare($query);

        $this->vehicle_id = intval($this->vehicle_id);
        $this->customer_id = intval($this->customer_id);
        $this->plate_number = htmlspecialchars(strip_tags(strtoupper(trim($this->plate_number))));
        $this->make = htmlspecialchars(strip_tags(trim($this->make)));
        $this->model = htmlspecialchars(strip_tags(trim($this->model)));
        $this->year = htmlspecialchars(strip_tags(trim($this->year)));
        $this->color = htmlspecialchars(strip_tags(trim($this->color)));
        $this->notes = htmlspecialchars(strip_tags(trim($this->notes ?? '')));

        $notesValue = $this->notes !== '' ? $this->notes : null;

        $stmt->bindParam(":vehicle_id", $this->vehicle_id, PDO::PARAM_INT);
        $stmt->bindParam(":customer_id", $this->customer_id, PDO::PARAM_INT);
        $stmt->bindParam(":plate_number", $this->plate_number);
        $stmt->bindParam(":make", $this->make);
        $stmt->bindParam(":model", $this->model);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":color", $this->color);
        $stmt->bindParam(":notes", $notesValue);

        return $stmt->execute();
    }

    public function updateCustomerOwnedVehicle($customer_id) {
        if (!$this->belongsToCustomer($this->vehicle_id, $customer_id)) {
            return false;
        }

        if ($this->plateExists($this->plate_number, $this->vehicle_id)) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . "
                  SET 
                    plate_number = :plate_number,
                    make = :make,
                    model = :model,
                    year = :year,
                    color = :color,
                    notes = :notes
                  WHERE vehicle_id = :vehicle_id
                    AND customer_id = :customer_id
                    AND status = 'Active'";

        $stmt = $this->conn->prepare($query);

        $this->vehicle_id = intval($this->vehicle_id);
        $customerId = intval($customer_id);
        $this->plate_number = htmlspecialchars(strip_tags(strtoupper(trim($this->plate_number))));
        $this->make = htmlspecialchars(strip_tags(trim($this->make)));
        $this->model = htmlspecialchars(strip_tags(trim($this->model)));
        $this->year = htmlspecialchars(strip_tags(trim($this->year)));
        $this->color = htmlspecialchars(strip_tags(trim($this->color)));
        $this->notes = htmlspecialchars(strip_tags(trim($this->notes ?? '')));

        $notesValue = $this->notes !== '' ? $this->notes : null;

        $stmt->bindParam(":vehicle_id", $this->vehicle_id, PDO::PARAM_INT);
        $stmt->bindParam(":customer_id", $customerId, PDO::PARAM_INT);
        $stmt->bindParam(":plate_number", $this->plate_number);
        $stmt->bindParam(":make", $this->make);
        $stmt->bindParam(":model", $this->model);
        $stmt->bindParam(":year", $this->year);
        $stmt->bindParam(":color", $this->color);
        $stmt->bindParam(":notes", $notesValue);

        return $stmt->execute();
    }

    public function deactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'Inactive',
                      deactivated_at = NOW(),
                      archived_at = NULL
                  WHERE vehicle_id = :vehicle_id";

        $stmt = $this->conn->prepare($query);
        $this->vehicle_id = intval($this->vehicle_id);
        $stmt->bindParam(":vehicle_id", $this->vehicle_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function reactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'Active',
                      deactivated_at = NULL,
                      archived_at = NULL
                  WHERE vehicle_id = :vehicle_id";

        $stmt = $this->conn->prepare($query);
        $this->vehicle_id = intval($this->vehicle_id);
        $stmt->bindParam(":vehicle_id", $this->vehicle_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function archive() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'Archived',
                      archived_at = NOW()
                  WHERE vehicle_id = :vehicle_id";

        $stmt = $this->conn->prepare($query);
        $this->vehicle_id = intval($this->vehicle_id);
        $stmt->bindParam(":vehicle_id", $this->vehicle_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
?>