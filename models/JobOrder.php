<?php
class JobOrder {
    private $conn;
    private $table_name = "job_order";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT jo.*, c.first_name, c.last_name, v.plate_number, v.make, v.model 
                  FROM " . $this->table_name . " jo
                  INNER JOIN customer c ON jo.customer_id = c.customer_id
                  INNER JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
                  ORDER BY jo.date_created DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function createWithDetails($data, $services, $parts) {
        try {
            $this->conn->beginTransaction();

            $queryJO = "INSERT INTO " . $this->table_name . " 
                        (vehicle_id, customer_id, created_by, job_order_number, description, status, estimated_cost, requires_down_payment, date_created) 
                        VALUES (:vehicle_id, :customer_id, :created_by, :job_order_number, :description, :status, :estimated_cost, :requires_down_payment, NOW())";
            
            $stmtJO = $this->conn->prepare($queryJO);
            $stmtJO->execute([
                ':vehicle_id' => $data['vehicle_id'],
                ':customer_id' => $data['customer_id'],
                ':created_by' => $data['created_by'],
                ':job_order_number' => $data['job_order_number'],
                ':description' => htmlspecialchars(strip_tags($data['description'])),
                ':status' => 'Pending',
                ':estimated_cost' => $data['estimated_cost'],
                ':requires_down_payment' => $data['requires_down_payment']
            ]);

            $jobOrderId = $this->conn->lastInsertId();

            if (!empty($services)) {
                $querySvc = "INSERT INTO job_order_service (job_order_id, service_id, quantity, unit_price, subtotal, notes) 
                             VALUES (:job_order_id, :service_id, 1, :unit_price, :subtotal, '')";
                $stmtSvc = $this->conn->prepare($querySvc);

                foreach ($services as $svc) {
                    $stmtSvc->execute([
                        ':job_order_id' => $jobOrderId,
                        ':service_id' => $svc['id'],
                        ':unit_price' => $svc['price'],
                        ':subtotal' => $svc['price']
                    ]);
                }
            }

            if (!empty($parts)) {
                $queryPart = "INSERT INTO job_order_part (job_order_id, part_id, quantity_used, unit_price_at_use, subtotal) 
                              VALUES (:job_order_id, :part_id, :quantity_used, :unit_price_at_use, :subtotal)";
                $stmtPart = $this->conn->prepare($queryPart);

                $queryDeduct = "UPDATE part SET quantity_on_hand = quantity_on_hand - :qty WHERE part_id = :part_id";
                $stmtDeduct = $this->conn->prepare($queryDeduct);

                foreach ($parts as $part) {
                    $subtotal = $part['qty'] * $part['price'];
                    
                    $stmtPart->execute([
                        ':job_order_id' => $jobOrderId,
                        ':part_id' => $part['id'],
                        ':quantity_used' => $part['qty'],
                        ':unit_price_at_use' => $part['price'],
                        ':subtotal' => $subtotal
                    ]);

                    $stmtDeduct->execute([
                        ':qty' => $part['qty'],
                        ':part_id' => $part['id']
                    ]);
                }
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>