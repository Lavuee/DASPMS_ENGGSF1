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
                v.model,
                CONCAT(am.first_name, ' ', am.last_name) AS assigned_mechanic_name,
                CONCAT(cb.first_name, ' ', cb.last_name) AS completed_by_name
            FROM " . $this->table_name . " jo
            INNER JOIN customer c ON jo.customer_id = c.customer_id
            INNER JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
            LEFT JOIN user am ON jo.assigned_mechanic_id = am.user_id
            LEFT JOIN user cb ON jo.completed_by = cb.user_id
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
                    assigned_mechanic_id,
                    job_order_number,
                    description,
                    request_source,
                    status,
                    estimated_cost,
                    final_cost,
                    requires_down_payment,
                    down_payment_amount,
                    expected_completion_date,
                    date_created
                ) VALUES (
                    :vehicle_id,
                    :customer_id,
                    :created_by,
                    :assigned_mechanic_id,
                    :job_order_number,
                    :description,
                    :request_source,
                    'Pending',
                    :estimated_cost,
                    :final_cost,
                    :requires_down_payment,
                    :down_payment_amount,
                    :expected_completion_date,
                    NOW()
                )
            ";

            $stmtJO = $this->conn->prepare($queryJO);

            $assignedMechanicId = !empty($data['assigned_mechanic_id']) ? intval($data['assigned_mechanic_id']) : null;
            $requestSource = !empty($data['request_source']) ? $data['request_source'] : 'Walk-in';
            $estimatedCost = round(floatval($data['estimated_cost']), 2);
            $downPaymentAmount = isset($data['down_payment_amount']) ? round(floatval($data['down_payment_amount']), 2) : 0;
            $expectedCompletionDate = !empty($data['expected_completion_date']) ? $data['expected_completion_date'] : null;

            $stmtJO->execute([
                ':vehicle_id' => intval($data['vehicle_id']),
                ':customer_id' => intval($data['customer_id']),
                ':created_by' => intval($data['created_by']),
                ':assigned_mechanic_id' => $assignedMechanicId,
                ':job_order_number' => $data['job_order_number'],
                ':description' => htmlspecialchars(strip_tags($data['description'])),
                ':request_source' => $requestSource,
                ':estimated_cost' => number_format($estimatedCost, 2, '.', ''),
                ':final_cost' => number_format($estimatedCost, 2, '.', ''),
                ':requires_down_payment' => intval($data['requires_down_payment']),
                ':down_payment_amount' => number_format($downPaymentAmount, 2, '.', ''),
                ':expected_completion_date' => $expectedCompletionDate
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
                    $price = round(floatval($svc['price']), 2);
                    $subtotal = round($quantity * $price, 2);

                    $stmtSvc->execute([
                        ':job_order_id' => $jobOrderId,
                        ':service_id' => intval($svc['id']),
                        ':quantity' => $quantity,
                        ':unit_price' => number_format($price, 2, '.', ''),
                        ':subtotal' => number_format($subtotal, 2, '.', ''),
                        ':notes' => htmlspecialchars(strip_tags($svc['notes'] ?? ''))
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
                        subtotal,
                        used_by,
                        used_at,
                        notes
                    ) VALUES (
                        :job_order_id,
                        :part_id,
                        :quantity_used,
                        :unit_price_at_use,
                        :subtotal,
                        :used_by,
                        NOW(),
                        :notes
                    )
                ";

                $stmtPart = $this->conn->prepare($queryPart);

                foreach ($parts as $part) {
                    $quantity = intval($part['qty']);
                    $price = round(floatval($part['price']), 2);
                    $subtotal = round($quantity * $price, 2);
                    $usedBy = !empty($data['created_by']) ? intval($data['created_by']) : null;

                    $stmtPart->execute([
                        ':job_order_id' => $jobOrderId,
                        ':part_id' => intval($part['id']),
                        ':quantity_used' => $quantity,
                        ':unit_price_at_use' => number_format($price, 2, '.', ''),
                        ':subtotal' => number_format($subtotal, 2, '.', ''),
                        ':used_by' => $usedBy,
                        ':notes' => htmlspecialchars(strip_tags($part['notes'] ?? ''))
                    ]);
                }

                $this->recalculateFinalCost($jobOrderId);
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
        try {
            $this->conn->beginTransaction();

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
            $estimatedCost = round(floatval($estimated_cost), 2);
            $requiresDownPayment = intval($requires_down_payment);
            $downPaymentAmount = round(floatval($down_payment_amount), 2);
            $jobOrderId = intval($job_order_id);

            $stmt->bindParam(':description', $cleanDescription);
            $stmt->bindParam(':estimated_cost', $estimatedCost);
            $stmt->bindParam(':requires_down_payment', $requiresDownPayment, PDO::PARAM_INT);
            $stmt->bindParam(':down_payment_amount', $downPaymentAmount);
            $stmt->bindParam(':job_order_id', $jobOrderId, PDO::PARAM_INT);

            $stmt->execute();

            $this->recalculateFinalCost($jobOrderId);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return false;
        }
    }

    public function addPartUsed($job_order_id, $part_id, $quantity_used, $used_by, $notes = '') {
        try {
            $this->conn->beginTransaction();

            $jobOrderId = intval($job_order_id);
            $partId = intval($part_id);
            $quantityUsed = intval($quantity_used);
            $usedBy = intval($used_by);
            $cleanNotes = htmlspecialchars(strip_tags(trim($notes)));

            if ($jobOrderId <= 0) {
                throw new Exception("Invalid job order.");
            }

            if ($partId <= 0) {
                throw new Exception("Invalid part selected.");
            }

            if ($quantityUsed <= 0) {
                throw new Exception("Quantity used must be greater than zero.");
            }

            if ($usedBy <= 0) {
                throw new Exception("Invalid user.");
            }

            if (strlen($cleanNotes) > 500) {
                throw new Exception("Part notes must not exceed 500 characters.");
            }

            $stmtJob = $this->conn->prepare("
                SELECT job_order_id, status
                FROM job_order
                WHERE job_order_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmtJob->execute([$jobOrderId]);
            $job = $stmtJob->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                throw new Exception("Job order not found.");
            }

            if (!in_array($job['status'], ['Pending', 'In Progress'], true)) {
                throw new Exception("Parts can only be added while the job order is Pending or In Progress.");
            }

            $stmtPart = $this->conn->prepare("
                SELECT part_id, part_name, unit_price, quantity_on_hand, is_active
                FROM part
                WHERE part_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmtPart->execute([$partId]);
            $part = $stmtPart->fetch(PDO::FETCH_ASSOC);

            if (!$part) {
                throw new Exception("Selected part does not exist.");
            }

            if (intval($part['is_active']) !== 1) {
                throw new Exception("Selected part is inactive and cannot be used.");
            }

            if (intval($part['quantity_on_hand']) < $quantityUsed) {
                throw new Exception(
                    "Not enough stock for " . $part['part_name'] .
                    ". Available: " . intval($part['quantity_on_hand']) .
                    ", Needed: " . $quantityUsed
                );
            }

            $unitPrice = round(floatval($part['unit_price']), 2);
            $subtotal = round($unitPrice * $quantityUsed, 2);

            $stmtInsert = $this->conn->prepare("
                INSERT INTO job_order_part (
                    job_order_id,
                    part_id,
                    quantity_used,
                    unit_price_at_use,
                    subtotal,
                    used_by,
                    used_at,
                    notes
                ) VALUES (
                    :job_order_id,
                    :part_id,
                    :quantity_used,
                    :unit_price_at_use,
                    :subtotal,
                    :used_by,
                    NOW(),
                    :notes
                )
            ");

            $stmtInsert->execute([
                ':job_order_id' => $jobOrderId,
                ':part_id' => $partId,
                ':quantity_used' => $quantityUsed,
                ':unit_price_at_use' => number_format($unitPrice, 2, '.', ''),
                ':subtotal' => number_format($subtotal, 2, '.', ''),
                ':used_by' => $usedBy,
                ':notes' => $cleanNotes !== '' ? $cleanNotes : null
            ]);

            $stmtDeduct = $this->conn->prepare("
                UPDATE part
                SET quantity_on_hand = quantity_on_hand - :quantity_used
                WHERE part_id = :part_id
                  AND quantity_on_hand >= :quantity_used
            ");

            $stmtDeduct->execute([
                ':quantity_used' => $quantityUsed,
                ':part_id' => $partId
            ]);

            if ($stmtDeduct->rowCount() === 0) {
                throw new Exception("Unable to deduct stock for " . $part['part_name'] . ".");
            }

            $this->recalculateFinalCost($jobOrderId);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function getPartsUsed($job_order_id) {
        $query = "
            SELECT
                jop.*,
                p.part_name,
                p.unit,
                CONCAT(u.first_name, ' ', u.last_name) AS used_by_name
            FROM job_order_part jop
            JOIN part p ON jop.part_id = p.part_id
            LEFT JOIN user u ON jop.used_by = u.user_id
            WHERE jop.job_order_id = ?
            ORDER BY jop.used_at DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($job_order_id)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPartsUsedTotal($job_order_id) {
        $query = "
            SELECT COALESCE(SUM(subtotal), 0)
            FROM job_order_part
            WHERE job_order_id = ?
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($job_order_id)]);

        return floatval($stmt->fetchColumn());
    }

    private function recalculateFinalCost($job_order_id) {
        $jobOrderId = intval($job_order_id);

        $stmtBase = $this->conn->prepare("
            SELECT estimated_cost
            FROM job_order
            WHERE job_order_id = ?
            LIMIT 1
        ");
        $stmtBase->execute([$jobOrderId]);
        $estimatedCost = floatval($stmtBase->fetchColumn());

        $stmtParts = $this->conn->prepare("
            SELECT COALESCE(SUM(subtotal), 0)
            FROM job_order_part
            WHERE job_order_id = ?
        ");
        $stmtParts->execute([$jobOrderId]);
        $partsTotal = floatval($stmtParts->fetchColumn());

        $finalCost = round($estimatedCost + $partsTotal, 2);

        $stmtUpdate = $this->conn->prepare("
            UPDATE job_order
            SET final_cost = ?
            WHERE job_order_id = ?
        ");

        $stmtUpdate->execute([
            number_format($finalCost, 2, '.', ''),
            $jobOrderId
        ]);
    }
}
?>