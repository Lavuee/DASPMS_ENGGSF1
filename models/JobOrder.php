<?php
class JobOrder {
    private $conn;
    private $table_name = "job_order";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Read all job orders with customer and vehicle details
    public function readAll() {
        $query = "SELECT jo.*, c.first_name, c.last_name, v.plate_number, v.make, v.model, u.first_name as creator_name
                  FROM " . $this->table_name . " jo
                  LEFT JOIN customer c ON jo.customer_id = c.customer_id
                  LEFT JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
                  LEFT JOIN user u ON jo.created_by = u.user_id
                  ORDER BY jo.date_created DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Create Job Order with nested Services and Parts
    public function createWithDetails($data, $services, $parts) {
        try {
            // Start Transaction to ensure data integrity
            $this->conn->beginTransaction();

            // 1. Insert Main Job Order
            $queryJO = "INSERT INTO " . $this->table_name . " 
                        (vehicle_id, customer_id, created_by, job_order_number, description, status, estimated_cost, requires_down_payment) 
                        VALUES (:vehicle_id, :customer_id, :created_by, :job_order_number, :description, :status, :estimated_cost, :requires_down_payment)";
            
            $stmtJO = $this->conn->prepare($queryJO);
            $stmtJO->execute([
                ':vehicle_id' => $data['vehicle_id'],
                ':customer_id' => $data['customer_id'],
                ':created_by' => $data['created_by'],
                ':job_order_number' => $data['job_order_number'],
                ':description' => htmlspecialchars(strip_tags($data['description'])),
                ':status' => 'Pending', // Default status
                ':estimated_cost' => $data['estimated_cost'],
                ':requires_down_payment' => $data['requires_down_payment']
            ]);

            $jobOrderId = $this->conn->lastInsertId();

            // 2. Insert Services (if any)
            if (!empty($services)) {
                $querySvc = "INSERT INTO job_order_service (job_order_id, service_id, quantity, unit_price, subtotal) 
                             VALUES (:job_order_id, :service_id, :quantity, :unit_price, :subtotal)";
                $stmtSvc = $this->conn->prepare($querySvc);

                foreach ($services as $svc) {
                    $stmtSvc->execute([
                        ':job_order_id' => $jobOrderId,
                        ':service_id' => $svc['id'],
                        ':quantity' => 1, // Defaulting to 1 for services
                        ':unit_price' => $svc['price'],
                        ':subtotal' => $svc['price']
                    ]);
                }
            }

            // 3. Insert Parts & Deduct Inventory (if any)
            if (!empty($parts)) {
                $queryPart = "INSERT INTO job_order_part (job_order_id, part_id, quantity_used, unit_price_at_use, subtotal) 
                              VALUES (:job_order_id, :part_id, :quantity_used, :unit_price_at_use, :subtotal)";
                $stmtPart = $this->conn->prepare($queryPart);

                $queryDeduct = "UPDATE part SET quantity_on_hand = quantity_on_hand - :qty WHERE part_id = :part_id";
                $stmtDeduct = $this->conn->prepare($queryDeduct);

                foreach ($parts as $part) {
                    $subtotal = $part['qty'] * $part['price'];
                    
                    // Link part to Job Order
                    $stmtPart->execute([
                        ':job_order_id' => $jobOrderId,
                        ':part_id' => $part['id'],
                        ':quantity_used' => $part['qty'],
                        ':unit_price_at_use' => $part['price'],
                        ':subtotal' => $subtotal
                    ]);

                    // Deduct from Inventory
                    $stmtDeduct->execute([
                        ':qty' => $part['qty'],
                        ':part_id' => $part['id']
                    ]);
                }
            }

            // Commit transaction
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            // Rollback on any failure so we don't get partial data
            $this->conn->rollBack();
            return false;
        }
    }
}
?>