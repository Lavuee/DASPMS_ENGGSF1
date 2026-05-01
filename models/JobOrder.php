<?php

class JobOrder {
    private $conn;
    private $table_name = "job_order";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "
            SELECT 
                jo.*, 
                c.first_name, 
                c.last_name, 
                v.plate_number, 
                v.make, 
                v.model
            FROM " . $this->table_name . " jo
            INNER JOIN customer c ON jo.customer_id = c.customer_id
            INNER JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
            ORDER BY jo.date_created DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function createWithDetails($data, $services, $parts) {
        try {
            $this->conn->beginTransaction();

            $queryJO = "
                INSERT INTO " . $this->table_name . " (
                    vehicle_id,
                    customer_id,
                    created_by,
                    job_order_number,
                    description,
                    status,
                    estimated_cost,
                    requires_down_payment,
                    date_created
                ) VALUES (
                    :vehicle_id,
                    :customer_id,
                    :created_by,
                    :job_order_number,
                    :description,
                    'Pending',
                    :estimated_cost,
                    :requires_down_payment,
                    NOW()
                )
            ";

            $stmtJO = $this->conn->prepare($queryJO);
            $stmtJO->execute([
                ':vehicle_id' => $data['vehicle_id'],
                ':customer_id' => $data['customer_id'],
                ':created_by' => $data['created_by'],
                ':job_order_number' => $data['job_order_number'],
                ':description' => htmlspecialchars(strip_tags($data['description'])),
                ':estimated_cost' => $data['estimated_cost'],
                ':requires_down_payment' => $data['requires_down_payment']
            ]);

            $jobOrderId = $this->conn->lastInsertId();

            if (!empty($services)) {
                $querySvc = "
                    INSERT INTO job_order_service (
                        job_order_id,
                        service_id,
                        quantity,
                        unit_price,
                        subtotal,
                        notes
                    ) VALUES (
                        :job_order_id,
                        :service_id,
                        :quantity,
                        :unit_price,
                        :subtotal,
                        :notes
                    )
                ";

                $stmtSvc = $this->conn->prepare($querySvc);

                foreach ($services as $svc) {
                    $quantity = isset($svc['qty']) ? intval($svc['qty']) : 1;
                    $price = floatval($svc['price']);
                    $subtotal = $quantity * $price;

                    $stmtSvc->execute([
                        ':job_order_id' => $jobOrderId,
                        ':service_id' => intval($svc['id']),
                        ':quantity' => $quantity,
                        ':unit_price' => $price,
                        ':subtotal' => $subtotal,
                        ':notes' => ''
                    ]);
                }
            }

            if (!empty($parts)) {
                $queryPart = "
                    INSERT INTO job_order_part (
                        job_order_id,
                        part_id,
                        quantity_used,
                        unit_price_at_use,
                        subtotal
                    ) VALUES (
                        :job_order_id,
                        :part_id,
                        :quantity_used,
                        :unit_price_at_use,
                        :subtotal
                    )
                ";

                $stmtPart = $this->conn->prepare($queryPart);

                foreach ($parts as $part) {
                    $quantity = intval($part['qty']);
                    $price = floatval($part['price']);
                    $subtotal = $quantity * $price;

                    $stmtPart->execute([
                        ':job_order_id' => $jobOrderId,
                        ':part_id' => intval($part['id']),
                        ':quantity_used' => $quantity,
                        ':unit_price_at_use' => $price,
                        ':subtotal' => $subtotal
                    ]);
                }
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return false;
        }
    }

    public function updateBasicDetails($job_order_id, $description, $estimated_cost, $requires_down_payment, $down_payment_amount) {
        $query = "
            UPDATE " . $this->table_name . "
            SET 
                description = :description,
                estimated_cost = :estimated_cost,
                requires_down_payment = :requires_down_payment,
                down_payment_amount = :down_payment_amount
            WHERE job_order_id = :job_order_id
              AND status IN ('Pending', 'In Progress')
        ";

        $stmt = $this->conn->prepare($query);

        $cleanDescription = htmlspecialchars(strip_tags(trim($description)));
        $estimatedCost = floatval($estimated_cost);
        $requiresDownPayment = intval($requires_down_payment);
        $downPaymentAmount = floatval($down_payment_amount);
        $jobOrderId = intval($job_order_id);

        $stmt->bindParam(':description', $cleanDescription);
        $stmt->bindParam(':estimated_cost', $estimatedCost);
        $stmt->bindParam(':requires_down_payment', $requiresDownPayment, PDO::PARAM_INT);
        $stmt->bindParam(':down_payment_amount', $downPaymentAmount);
        $stmt->bindParam(':job_order_id', $jobOrderId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
?>