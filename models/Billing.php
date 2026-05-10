<?php

class Billing {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function getJobTotalExpression() {
        return "
            CASE 
                WHEN COALESCE(jo.final_cost, 0) > 0 
                    THEN jo.final_cost
                ELSE COALESCE(jo.estimated_cost, 0) + COALESCE(parts.parts_total, 0)
            END
        ";
    }

    private function syncJobInvoiceTotals($job_order_id = null, $invoice_id = null) {
        $jobTotal = $this->getJobTotalExpression();

        $query = "
            UPDATE invoice i
            JOIN job_order jo ON i.job_order_id = jo.job_order_id
            LEFT JOIN (
                SELECT 
                    job_order_id,
                    SUM(subtotal) AS parts_total
                FROM job_order_part
                GROUP BY job_order_id
            ) parts ON jo.job_order_id = parts.job_order_id
            SET 
                i.total_amount = $jobTotal,
                i.balance_due = GREATEST($jobTotal - COALESCE(i.amount_paid, 0), 0),
                i.payment_status = CASE
                    WHEN COALESCE(i.amount_paid, 0) <= 0 THEN 'Not Paid'
                    WHEN COALESCE(i.amount_paid, 0) >= $jobTotal THEN 'Paid'
                    ELSE 'Partial'
                END
            WHERE i.job_order_id IS NOT NULL
        ";

        $params = [];

        if (!empty($job_order_id)) {
            $query .= " AND i.job_order_id = :job_order_id";
            $params[':job_order_id'] = $job_order_id;
        }

        if (!empty($invoice_id)) {
            $query .= " AND i.invoice_id = :invoice_id";
            $params[':invoice_id'] = $invoice_id;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
    }

    public function getPendingBills() {
        $this->syncJobInvoiceTotals();

        $jobTotal = $this->getJobTotalExpression();

        $query = "
            SELECT 
                'job' AS source,
                jo.job_order_id AS ref_id,
                jo.job_order_number AS ref_number,
                jo.customer_id,
                c.first_name,
                c.last_name,
                $jobTotal AS total_amount,
                COALESCE(i.amount_paid, 0) AS amount_paid,

                CASE 
                    WHEN i.invoice_id IS NOT NULL THEN 
                        GREATEST($jobTotal - COALESCE(i.amount_paid, 0), 0)
                    ELSE 
                        $jobTotal
                END AS balance_due,

                i.invoice_id,
                COALESCE(i.payment_status, 'No Invoice') AS payment_status,
                NULL AS payment_method,
                NULL AS gcash_reference,
                NULL AS gcash_payment_amount
            FROM job_order jo
            JOIN customer c ON jo.customer_id = c.customer_id
            LEFT JOIN (
                SELECT 
                    job_order_id,
                    SUM(subtotal) AS parts_total
                FROM job_order_part
                GROUP BY job_order_id
            ) parts ON jo.job_order_id = parts.job_order_id
            LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
            WHERE jo.status = 'Completed'
              AND (i.payment_status IS NULL OR i.payment_status != 'Paid')

            UNION ALL

            SELECT 
                'part' AS source,
                po.order_id AS ref_id,
                CONCAT('PO-', po.order_id) AS ref_number,
                po.customer_id,
                c.first_name,
                c.last_name,
                po.total_amount AS total_amount,
                COALESCE(i.amount_paid, 0) AS amount_paid,
                COALESCE(i.balance_due, po.total_amount) AS balance_due,
                i.invoice_id,
                COALESCE(i.payment_status, 'No Invoice') AS payment_status,
                po.payment_method,
                po.gcash_reference,
                po.gcash_payment_amount
            FROM part_order po
            JOIN customer c ON po.customer_id = c.customer_id
            LEFT JOIN invoice i ON po.order_id = i.part_order_id
            WHERE po.status = 'Ready for Pickup'
              AND (i.payment_status IS NULL OR i.payment_status != 'Paid')

            ORDER BY ref_id DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function createInvoice($job_order_id, $created_by) {
        try {
            $this->conn->beginTransaction();

            $check = $this->conn->prepare("
                SELECT invoice_id 
                FROM invoice 
                WHERE job_order_id = :job_order_id 
                LIMIT 1
            ");
            $check->execute([':job_order_id' => $job_order_id]);

            if ($check->rowCount() > 0) {
                $this->conn->rollBack();
                return false;
            }

            $stmtData = $this->conn->prepare("
                SELECT 
                    jo.customer_id,
                    jo.estimated_cost,
                    jo.final_cost,
                    COALESCE(parts.parts_total, 0) AS parts_total
                FROM job_order jo
                LEFT JOIN (
                    SELECT 
                        job_order_id,
                        SUM(subtotal) AS parts_total
                    FROM job_order_part
                    GROUP BY job_order_id
                ) parts ON jo.job_order_id = parts.job_order_id
                WHERE jo.job_order_id = :job_order_id
                LIMIT 1
            ");

            $stmtData->execute([':job_order_id' => $job_order_id]);
            $jobData = $stmtData->fetch(PDO::FETCH_ASSOC);

            if (!$jobData) {
                throw new Exception("Job order not found.");
            }

            $estimatedCost = floatval($jobData['estimated_cost'] ?? 0);
            $finalCost = floatval($jobData['final_cost'] ?? 0);
            $partsTotal = floatval($jobData['parts_total'] ?? 0);

            if ($finalCost > 0) {
                $total = $finalCost;
            } else {
                $total = $estimatedCost + $partsTotal;
            }

            $customer_id = intval($jobData['customer_id']);

            if ($total <= 0) {
                throw new Exception("Job order has invalid total cost.");
            }

            $invoice_number = 'INV-JOB-' . date('Ymd-His') . '-' . $job_order_id;

            $stmtInv = $this->conn->prepare("
                INSERT INTO invoice (
                    job_order_id,
                    part_order_id,
                    customer_id,
                    created_by,
                    invoice_number,
                    invoice_type,
                    total_amount,
                    amount_paid,
                    balance_due,
                    payment_status
                ) VALUES (
                    :job_order_id,
                    NULL,
                    :customer_id,
                    :created_by,
                    :invoice_number,
                    'Service',
                    :total_amount,
                    0,
                    :balance_due,
                    'Not Paid'
                )
            ");

            $stmtInv->execute([
                ':job_order_id' => $job_order_id,
                ':customer_id' => $customer_id,
                ':created_by' => $created_by,
                ':invoice_number' => $invoice_number,
                ':total_amount' => $total,
                ':balance_due' => $total
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return false;
        }
    }

    public function createPartInvoice($order_id, $created_by) {
        try {
            $this->conn->beginTransaction();

            $created = $this->createPartInvoiceInsideTransaction($order_id, $created_by);

            if (!$created) {
                $this->conn->rollBack();
                return false;
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

    private function createPartInvoiceInsideTransaction($order_id, $created_by) {
        $check = $this->conn->prepare("
            SELECT invoice_id 
            FROM invoice 
            WHERE part_order_id = :part_order_id 
            LIMIT 1
        ");
        $check->execute([':part_order_id' => $order_id]);

        if ($check->rowCount() > 0) {
            return false;
        }

        $stmtData = $this->conn->prepare("
            SELECT 
                customer_id,
                total_amount,
                payment_method,
                gcash_reference,
                gcash_payment_amount,
                gcash_verification_status
            FROM part_order
            WHERE order_id = :order_id
            LIMIT 1
        ");
        $stmtData->execute([':order_id' => $order_id]);
        $orderData = $stmtData->fetch(PDO::FETCH_ASSOC);

        if (!$orderData) {
            throw new Exception("Part order not found.");
        }

        $total = floatval($orderData['total_amount']);
        $customer_id = intval($orderData['customer_id']);
        $paymentMethod = $orderData['payment_method'] ?? 'Cash on Pickup';
        $gcashReference = trim($orderData['gcash_reference'] ?? '');
        $gcashAmount = floatval($orderData['gcash_payment_amount'] ?? 0);
        $gcashStatus = $orderData['gcash_verification_status'] ?? 'Not Required';

        if ($total <= 0) {
            throw new Exception("Part order has invalid total amount.");
        }

        $initialPaid = 0;

        if ($this->isGcashPaymentMethod($paymentMethod) && $gcashStatus === 'Verified') {
            $initialPaid = min($gcashAmount, $total);
        }

        $balance = max($total - $initialPaid, 0);

        if ($initialPaid >= $total) {
            $paymentStatus = 'Paid';
        } elseif ($initialPaid > 0) {
            $paymentStatus = 'Partial';
        } else {
            $paymentStatus = 'Not Paid';
        }

        $invoice_number = 'INV-PART-' . date('Ymd-His') . '-' . $order_id;

        $stmtInv = $this->conn->prepare("
            INSERT INTO invoice (
                job_order_id,
                part_order_id,
                customer_id,
                created_by,
                invoice_number,
                invoice_type,
                total_amount,
                amount_paid,
                balance_due,
                payment_status
            ) VALUES (
                NULL,
                :part_order_id,
                :customer_id,
                :created_by,
                :invoice_number,
                'Part',
                :total_amount,
                :amount_paid,
                :balance_due,
                :payment_status
            )
        ");

        $stmtInv->execute([
            ':part_order_id' => $order_id,
            ':customer_id' => $customer_id,
            ':created_by' => $created_by,
            ':invoice_number' => $invoice_number,
            ':total_amount' => $total,
            ':amount_paid' => $initialPaid,
            ':balance_due' => $balance,
            ':payment_status' => $paymentStatus
        ]);

        $invoice_id = $this->conn->lastInsertId();

        if ($initialPaid > 0) {
            $stmtPay = $this->conn->prepare("
                INSERT INTO payment (
                    invoice_id,
                    received_by,
                    amount,
                    payment_method,
                    reference_number,
                    payment_date
                ) VALUES (
                    :invoice_id,
                    :received_by,
                    :amount,
                    'GCash',
                    :reference_number,
                    NOW()
                )
            ");

            $stmtPay->execute([
                ':invoice_id' => $invoice_id,
                ':received_by' => $created_by,
                ':amount' => $initialPaid,
                ':reference_number' => htmlspecialchars(strip_tags($gcashReference))
            ]);
        }

        return true;
    }

    public function receivePayment($invoice_id, $user_id, $amount, $method, $ref) {
        try {
            $this->syncJobInvoiceTotals(null, $invoice_id);

            $this->conn->beginTransaction();

            if ($amount <= 0) {
                throw new Exception("Invalid payment amount.");
            }

            if (in_array($method, ['GCash', 'Bank Transfer', 'Cheque']) && trim($ref) === '') {
                throw new Exception("Reference number is required.");
            }

            $stmtInv = $this->conn->prepare("
                SELECT 
                    invoice_id, 
                    job_order_id,
                    part_order_id, 
                    total_amount, 
                    amount_paid
                FROM invoice
                WHERE invoice_id = :invoice_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtInv->execute([':invoice_id' => $invoice_id]);

            if ($stmtInv->rowCount() === 0) {
                throw new Exception("Invoice not found.");
            }

            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

            $total_amount = floatval($inv['total_amount']);
            $amount_paid = floatval($inv['amount_paid']);
            $remaining_balance = $total_amount - $amount_paid;

            if ($remaining_balance <= 0) {
                throw new Exception("Invoice is already fully paid.");
            }

            if ($amount > $remaining_balance) {
                throw new Exception("Payment exceeds remaining balance.");
            }

            $new_paid = $amount_paid + $amount;

            if ($new_paid >= $total_amount) {
                $new_paid = $total_amount;
                $status = 'Paid';
            } else {
                $status = 'Partial';
            }

            $new_balance = $total_amount - $new_paid;

            $stmtUpd = $this->conn->prepare("
                UPDATE invoice
                SET 
                    amount_paid = :amount_paid,
                    balance_due = :balance_due,
                    payment_status = :payment_status
                WHERE invoice_id = :invoice_id
            ");
            $stmtUpd->execute([
                ':amount_paid' => $new_paid,
                ':balance_due' => $new_balance,
                ':payment_status' => $status,
                ':invoice_id' => $invoice_id
            ]);

            $stmtPay = $this->conn->prepare("
                INSERT INTO payment (
                    invoice_id,
                    received_by,
                    amount,
                    payment_method,
                    reference_number,
                    payment_date
                ) VALUES (
                    :invoice_id,
                    :received_by,
                    :amount,
                    :payment_method,
                    :reference_number,
                    NOW()
                )
            ");
            $stmtPay->execute([
                ':invoice_id' => $invoice_id,
                ':received_by' => $user_id,
                ':amount' => $amount,
                ':payment_method' => $method,
                ':reference_number' => htmlspecialchars(strip_tags($ref))
            ]);

            if ($status === 'Paid' && !empty($inv['part_order_id'])) {
                $stmtOrder = $this->conn->prepare("
                    UPDATE part_order 
                    SET status = 'Completed' 
                    WHERE order_id = :order_id
                ");
                $stmtOrder->execute([':order_id' => intval($inv['part_order_id'])]);
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

    private function isGcashPaymentMethod($paymentMethod) {
        return in_array($paymentMethod, ['GCash Down Payment', 'GCash Full Payment', 'GCash Reservation']);
    }
}
?>