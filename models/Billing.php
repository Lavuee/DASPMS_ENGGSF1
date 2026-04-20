<?php
class Billing {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getPendingBills() {
        $query = "SELECT jo.job_order_id, jo.job_order_number, jo.estimated_cost as total_amount, 
                         jo.customer_id, c.first_name, c.last_name, 
                         COALESCE(i.amount_paid, 0) as amount_paid, 
                         (jo.estimated_cost - COALESCE(i.amount_paid, 0)) as balance_due,
                         i.invoice_id, i.payment_status
                  FROM job_order jo
                  JOIN customer c ON jo.customer_id = c.customer_id
                  LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
                  WHERE jo.status = 'Completed' 
                  AND (i.payment_status IS NULL OR i.payment_status != 'Paid')
                  ORDER BY jo.date_created ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function receivePayment($job_order_id, $customer_id, $user_id, $amount, $method, $ref) {
        try {
            $this->conn->beginTransaction();

            $queryInv = "SELECT invoice_id, total_amount, amount_paid FROM invoice WHERE job_order_id = :jo_id";
            $stmtInv = $this->conn->prepare($queryInv);
            $stmtInv->execute([':jo_id' => $job_order_id]);
            
            $invoice_id = null;

            if ($stmtInv->rowCount() > 0) {
                $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
                $invoice_id = $inv['invoice_id'];
                $new_paid = $inv['amount_paid'] + $amount;
                $status = ($new_paid >= $inv['total_amount']) ? 'Paid' : 'Partial';

                $updInv = "UPDATE invoice SET amount_paid = :paid, balance_due = total_amount - :paid, payment_status = :status WHERE invoice_id = :inv_id";
                $stmtUpd = $this->conn->prepare($updInv);
                $stmtUpd->execute([':paid' => $new_paid, ':status' => $status, ':inv_id' => $invoice_id]);
            } else {
                $joQuery = "SELECT estimated_cost FROM job_order WHERE job_order_id = :jo_id";
                $joStmt = $this->conn->prepare($joQuery);
                $joStmt->execute([':jo_id' => $job_order_id]);
                $total = $joStmt->fetchColumn();

                $status = ($amount >= $total) ? 'Paid' : 'Partial';
                $invNum = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);

                $insInv = "INSERT INTO invoice (customer_id, job_order_id, created_by, invoice_number, invoice_type, total_amount, amount_paid, balance_due, payment_status)
                           VALUES (:cid, :joid, :uid, :invnum, 'Service', :total, :paid, :bal, :status)";
                $stmtIns = $this->conn->prepare($insInv);
                $stmtIns->execute([
                    ':cid' => $customer_id, ':joid' => $job_order_id, ':uid' => $user_id, 
                    ':invnum' => $invNum, ':total' => $total, ':paid' => $amount, 
                    ':bal' => ($total - $amount), ':status' => $status
                ]);
                $invoice_id = $this->conn->lastInsertId();
            }

            $insPay = "INSERT INTO payment (invoice_id, received_by, amount, payment_method, reference_number)
                       VALUES (:inv_id, :uid, :amt, :method, :ref)";
            $stmtPay = $this->conn->prepare($insPay);
            $stmtPay->execute([
                ':inv_id' => $invoice_id, ':uid' => $user_id, ':amt' => $amount,
                ':method' => $method, ':ref' => htmlspecialchars(strip_tags($ref))
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>