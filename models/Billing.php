<?php
require_once __DIR__ . '/../includes/business_rules.php';

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
                END,
                -- An invoice opened early by a down payment gets its 7-day due date once the job is completed
                i.due_date = CASE
                    WHEN i.due_date IS NULL AND jo.status = 'Completed'
                        THEN DATE_ADD(DATE(COALESCE(jo.date_completed, NOW())), INTERVAL " . intval(SERVICE_CREDIT_DAYS) . " DAY)
                    ELSE i.due_date
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
                NULL AS gcash_payment_amount,
                i.due_date
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
                po.gcash_payment_amount,
                NULL AS due_date
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
                    payment_status,
                    due_date
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
                    'Not Paid',
                    :due_date
                )
            ");

            // SRS 1.3: service bills follow the 7-day credit period.
            $stmtInv->execute([
                ':job_order_id' => $job_order_id,
                ':customer_id' => $customer_id,
                ':created_by' => $created_by,
                ':invoice_number' => $invoice_number,
                ':total_amount' => $total,
                ':balance_due' => $total,
                ':due_date' => serviceCreditDueDate()
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

            $created = $this->createPartInvoiceInTransaction($order_id, $created_by);

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

    /*
        Creates the invoice for a part order (and records a verified GCash payment, if any).
        Must be called inside an open transaction. Returns false if the invoice already exists.
    */
    public function createPartInvoiceInTransaction($order_id, $created_by) {
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

        if (self::isGcashPaymentMethod($paymentMethod) && $gcashStatus === 'Verified') {
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
                subtotal_parts,
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
                :subtotal_parts,
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
            ':subtotal_parts' => $total,
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

    /*
        SRS UC20 Record Down Payment: a partial payment taken while a repair job is still Pending or
        In Progress. It is saved on the job's invoice (opened now if the job has none yet, without a
        due date) and flagged is_down_payment, so the final bill only shows the remaining balance.
        The down payment must leave part of the job total unpaid. Throws Exception with a message
        for the user; returns the invoice id.
    */
    public function recordDownPayment($job_order_id, $user_id, $amount, $method, $ref) {
        $amount = round(floatval($amount), 2);
        $ref = trim((string)$ref);

        if ($amount <= 0) {
            throw new Exception("Enter a down payment greater than zero.");
        }

        if (!in_array($method, ['Cash', 'GCash', 'Bank Transfer', 'Cheque'], true)) {
            throw new Exception("Invalid payment method.");
        }

        if (in_array($method, ['GCash', 'Bank Transfer', 'Cheque'], true) && $ref === '') {
            throw new Exception("Reference number is required for {$method} payments.");
        }

        $this->conn->beginTransaction();

        try {
            $jobTotal = $this->getJobTotalExpression();
            $stmtJob = $this->conn->prepare("
                SELECT jo.job_order_id, jo.customer_id, jo.status, $jobTotal AS job_total
                FROM job_order jo
                LEFT JOIN (
                    SELECT job_order_id, SUM(subtotal) AS parts_total
                    FROM job_order_part
                    GROUP BY job_order_id
                ) parts ON jo.job_order_id = parts.job_order_id
                WHERE jo.job_order_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmtJob->execute([intval($job_order_id)]);
            $job = $stmtJob->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                throw new Exception("Job order not found.");
            }

            if (!in_array($job['status'], ['Pending', 'In Progress'], true)) {
                throw new Exception("Down payments are recorded while the job is Pending or In Progress. Use Billing for the final payment.");
            }

            $total = round(floatval($job['job_total']), 2);

            if ($total <= 0) {
                throw new Exception("Set the job's estimated cost before recording a down payment.");
            }

            $stmtInv = $this->conn->prepare("
                SELECT invoice_id, amount_paid
                FROM invoice
                WHERE job_order_id = ? AND is_voided = 0
                LIMIT 1
                FOR UPDATE
            ");
            $stmtInv->execute([intval($job_order_id)]);
            $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

            $alreadyPaid = $invoice ? round(floatval($invoice['amount_paid']), 2) : 0.0;

            // SRS UC20: the down payment must be less than the estimated total
            if ($alreadyPaid + $amount >= $total) {
                $maximum = max($total - $alreadyPaid - 0.01, 0);
                throw new Exception("A down payment must leave part of the bill unpaid. The most you can record now is ₱" . number_format($maximum, 2) . ". Collect the full amount in Billing after the job is completed.");
            }

            if (!$invoice) {
                // Opened early; the due date is set when the job is completed (syncJobInvoiceTotals)
                $stmtNew = $this->conn->prepare("
                    INSERT INTO invoice (
                        job_order_id, part_order_id, customer_id, created_by, invoice_number, invoice_type,
                        total_amount, amount_paid, balance_due, payment_status, due_date
                    ) VALUES (?, NULL, ?, ?, ?, 'Service', ?, 0, ?, 'Not Paid', NULL)
                ");
                $stmtNew->execute([
                    intval($job_order_id),
                    intval($job['customer_id']),
                    intval($user_id),
                    'INV-JOB-' . date('Ymd-His') . '-' . intval($job_order_id),
                    $total,
                    $total
                ]);
                $invoiceId = intval($this->conn->lastInsertId());
            } else {
                $invoiceId = intval($invoice['invoice_id']);
            }

            $newPaid = $alreadyPaid + $amount;

            $this->conn->prepare("
                UPDATE invoice
                SET total_amount = ?, amount_paid = ?, balance_due = ?, payment_status = 'Partial'
                WHERE invoice_id = ?
            ")->execute([$total, $newPaid, $total - $newPaid, $invoiceId]);

            $this->conn->prepare("
                INSERT INTO payment (invoice_id, received_by, amount, payment_method, reference_number, is_down_payment, payment_date, notes)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), 'Down payment')
            ")->execute([
                $invoiceId,
                intval($user_id),
                $amount,
                $method,
                $ref !== '' ? htmlspecialchars(strip_tags($ref)) : null
            ]);

            $this->conn->commit();
            return $invoiceId;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public static function isGcashPaymentMethod($paymentMethod) {
        return in_array($paymentMethod, ['GCash Down Payment', 'GCash Full Payment', 'GCash Reservation']);
    }
}
?>