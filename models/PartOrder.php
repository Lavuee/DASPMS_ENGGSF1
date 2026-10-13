<?php
require_once __DIR__ . '/../controllers/RestockAlert.php';
require_once __DIR__ . '/Billing.php';
require_once __DIR__ . '/PaymentTransaction.php';
require_once __DIR__ . '/../includes/business_rules.php';
require_once __DIR__ . '/../includes/activity_log.php';

class PartOrder {
    private $conn;

    // Reason the last updateStatus()/updateGcashVerification() call failed, safe to show staff.
    public $lastError = '';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllOrders() {
        $query = "
            SELECT 
                po.order_id,
                po.order_date,
                po.preferred_pickup_date,
                po.preferred_pickup_time,
                po.pickup_notes,
                po.payment_method,
                po.gcash_reference,
                po.gcash_payment_amount,
                po.gcash_verification_status,
                po.gcash_verified_at,
                po.gcash_verified_by,
                po.payment_notes,
                po.status,
                po.total_amount,
                po.customer_id,
                c.first_name,
                c.last_name,
                gu.first_name AS gcash_verifier_first_name,
                gu.last_name AS gcash_verifier_last_name,
                GROUP_CONCAT(
                    CONCAT(p.part_name, ' x', poi.quantity)
                    ORDER BY p.part_name ASC
                    SEPARATOR '<br>'
                ) AS items,
                GROUP_CONCAT(
                    p.part_id
                    ORDER BY p.part_id ASC
                    SEPARATOR ','
                ) AS part_ids,
                i.invoice_id,
                i.amount_paid,
                i.payment_status,
                i.balance_due
            FROM part_order po
            JOIN customer c ON po.customer_id = c.customer_id
            JOIN part_order_item poi ON po.order_id = poi.order_id
            JOIN part p ON poi.part_id = p.part_id
            LEFT JOIN invoice i ON po.order_id = i.part_order_id
            LEFT JOIN `user` gu ON po.gcash_verified_by = gu.user_id
            GROUP BY
                po.order_id,
                po.order_date,
                po.preferred_pickup_date,
                po.preferred_pickup_time,
                po.pickup_notes,
                po.payment_method,
                po.gcash_reference,
                po.gcash_payment_amount,
                po.gcash_verification_status,
                po.gcash_verified_at,
                po.gcash_verified_by,
                po.payment_notes,
                po.status,
                po.total_amount,
                po.customer_id,
                c.first_name,
                c.last_name,
                gu.first_name,
                gu.last_name,
                i.invoice_id,
                i.amount_paid,
                i.payment_status,
                i.balance_due
            ORDER BY 
                CASE po.status
                    WHEN 'Pending' THEN 1
                    WHEN 'Approved' THEN 2
                    WHEN 'Ready for Pickup' THEN 3
                    WHEN 'Completed' THEN 4
                    WHEN 'Cancelled' THEN 5
                    ELSE 6
                END,
                po.order_date DESC,
                po.order_id DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function updateStatus($order_id, $new_status, $user_id, $cancellation_reason = '') {
        try {
            $order_id = intval($order_id);
            $user_id = intval($user_id);
            $new_status = trim($new_status);
            $cancellation_reason = trim((string) $cancellation_reason);

            $allowed_statuses = ['Pending', 'Approved', 'Ready for Pickup', 'Completed', 'Cancelled'];

            if ($order_id <= 0 || $user_id <= 0) {
                throw new Exception("Invalid order update request.");
            }

            if (!in_array($new_status, $allowed_statuses)) {
                throw new Exception("Invalid status.");
            }

            $this->conn->beginTransaction();

            $stmtCurrent = $this->conn->prepare("
                SELECT 
                    status,
                    customer_id,
                    total_amount,
                    payment_method,
                    gcash_payment_amount,
                    gcash_verification_status
                FROM part_order
                WHERE order_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmtCurrent->execute([$order_id]);
            $order = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $current_status = $order['status'];
            $paymentMethod = $order['payment_method'] ?? 'Cash on Pickup';
            $gcashVerificationStatus = $order['gcash_verification_status'] ?? 'Not Required';
            $gcashPaymentAmount = floatval($order['gcash_payment_amount'] ?? 0);
            $invoice = $this->getPartOrderInvoice($order_id);

            if ($current_status === 'Completed') {
                throw new Exception("Completed orders can no longer be modified.");
            }

            if ($current_status === 'Cancelled') {
                throw new Exception("Cancelled orders can no longer be modified.");
            }

            if ($new_status === $current_status) {
                $this->conn->commit();
                return true;
            }

            if (!$this->isAllowedStatusTransition($current_status, $new_status)) {
                throw new Exception("Invalid status flow.");
            }

            if (
                ($new_status === 'Approved' || $new_status === 'Ready for Pickup') &&
                Billing::isGcashPaymentMethod($paymentMethod) &&
                $gcashVerificationStatus !== 'Verified'
            ) {
                throw new Exception("GCash payment must be verified before approving or preparing the order.");
            }

            if ($new_status === 'Cancelled') {
                // SRS UC16: a cancellation reason is mandatory.
                if ($cancellation_reason === '') {
                    throw new Exception("Please enter the reason for cancelling this order.");
                }

                if (mb_strlen($cancellation_reason) > 500) {
                    throw new Exception("Cancellation reason must be 500 characters or fewer.");
                }

                if (Billing::isGcashPaymentMethod($paymentMethod) && $gcashVerificationStatus === 'Verified' && $gcashPaymentAmount > 0) {
                    throw new Exception("Orders with verified GCash payment cannot be cancelled through the normal flow. Process refund/void manually first.");
                }

                $this->restoreStockIfAlreadyReserved($order_id);
            }

            $deductedPartIds = [];

            if ($new_status === 'Ready for Pickup') {
                if (!$invoice) {
                    $deductedPartIds = $this->validateAndDeductStock($order_id);

                    (new Billing($this->conn))->createPartInvoiceInTransaction($order_id, $user_id);
                }
            }

            if ($new_status === 'Completed') {
                if (!$invoice) {
                    throw new Exception("Invoice must exist before completing the order.");
                }

                if (($invoice['payment_status'] ?? '') !== 'Paid') {
                    throw new Exception("Only fully paid ready-for-pickup orders can be completed.");
                }
            }

            if ($new_status === 'Cancelled') {
                $stmtUpdate = $this->conn->prepare("
                    UPDATE part_order
                    SET status = 'Cancelled',
                        cancellation_reason = ?,
                        cancelled_at = NOW(),
                        cancelled_by = ?
                    WHERE order_id = ?
                ");
                $stmtUpdate->execute([htmlspecialchars(strip_tags($cancellation_reason)), $user_id, $order_id]);
            } else {
                $stmtUpdate = $this->conn->prepare("UPDATE part_order SET status = ? WHERE order_id = ?");
                $stmtUpdate->execute([$new_status, $order_id]);
            }

            $this->conn->commit();

            RestockAlert::checkParts($this->conn, $deductedPartIds);
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            // Database errors are logged, not shown.
            if ($e instanceof PDOException) {
                error_log("DASPMS PART ORDER ERROR: " . $e->getMessage());
            } else {
                $this->lastError = $e->getMessage();
            }
            return false;
        }
    }

    public function updateGcashVerification($order_id, $verification_status, $user_id) {
        try {
            $order_id = intval($order_id);
            $user_id = intval($user_id);
            $verification_status = trim($verification_status);
            $allowedStatuses = ['Pending Verification', 'Verified', 'Rejected'];

            if ($order_id <= 0 || $user_id <= 0) {
                throw new Exception("Invalid GCash verification request.");
            }

            if (!in_array($verification_status, $allowedStatuses)) {
                throw new Exception("Invalid GCash verification status.");
            }

            $this->conn->beginTransaction();

            $stmtOrder = $this->conn->prepare("
                SELECT 
                    order_id,
                    payment_method,
                    gcash_reference,
                    gcash_payment_amount,
                    gcash_verification_status,
                    status
                FROM part_order
                WHERE order_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmtOrder->execute([$order_id]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Order not found.");
            }

            if (!Billing::isGcashPaymentMethod($order['payment_method'] ?? '')) {
                throw new Exception("GCash verification is only available for GCash payments.");
            }

            // Online (PayMongo) payments are verified by the gateway, never by hand.
            if ($order['gcash_verification_status'] === 'Awaiting Payment' || (new PaymentTransaction($this->conn))->hasAnyForOrder($order_id)) {
                throw new Exception("Online GCash payments are confirmed automatically by PayMongo.");
            }

            if (in_array($order['status'], ['Completed', 'Cancelled'])) {
                throw new Exception("Cannot update GCash verification for completed or cancelled orders.");
            }

            if ($verification_status === 'Verified') {
                if (trim($order['gcash_reference'] ?? '') === '') {
                    throw new Exception("GCash reference is missing.");
                }

                if (floatval($order['gcash_payment_amount'] ?? 0) <= 0) {
                    throw new Exception("GCash payment amount is missing or invalid.");
                }
            }

            if ($verification_status === 'Pending Verification') {
                $stmtUpdate = $this->conn->prepare("
                    UPDATE part_order
                    SET gcash_verification_status = ?,
                        gcash_verified_at = NULL,
                        gcash_verified_by = NULL
                    WHERE order_id = ?
                ");
                $stmtUpdate->execute([$verification_status, $order_id]);
            } else {
                $stmtUpdate = $this->conn->prepare("
                    UPDATE part_order
                    SET gcash_verification_status = ?,
                        gcash_verified_at = NOW(),
                        gcash_verified_by = ?
                    WHERE order_id = ?
                ");
                $stmtUpdate->execute([$verification_status, $user_id, $order_id]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            // Database errors are logged, not shown.
            if ($e instanceof PDOException) {
                error_log("DASPMS PART ORDER ERROR: " . $e->getMessage());
            } else {
                $this->lastError = $e->getMessage();
            }
            return false;
        }
    }

    /*
        Reservation expiry (SRS). A web order holds its parts from "Ready for Pickup" (when its
        invoice is made). If nothing has been paid RESERVATION_HOLD_DAYS days later, the order is
        cancelled, its parts go back into stock and its unpaid invoice is voided.
        Orders with any recorded payment (including verified GCash) never expire.
        Runs when staff open the dashboards, Web Orders or Billing. Returns the number expired.
    */
    public function expireStaleReservations() {
        $expired = 0;

        try {
            $stmt = $this->conn->prepare("
                SELECT po.order_id, i.invoice_id, i.date_issued
                FROM part_order po
                JOIN invoice i ON i.part_order_id = po.order_id AND i.is_voided = 0
                WHERE po.status = 'Ready for Pickup'
                  AND COALESCE(i.amount_paid, 0) = 0
                  AND i.date_issued < (NOW() - INTERVAL " . intval(RESERVATION_HOLD_DAYS) . " DAY)
            ");
            $stmt->execute();
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Reservation expiry lookup: ' . $e->getMessage());
            return 0;
        }

        foreach ($candidates as $row) {
            $orderId = intval($row['order_id']);

            try {
                $this->conn->beginTransaction();

                // Re-check inside the transaction so a payment made a moment ago wins.
                $stmtCheck = $this->conn->prepare("
                    SELECT po.status, COALESCE(i.amount_paid, 0) AS amount_paid
                    FROM part_order po
                    JOIN invoice i ON i.invoice_id = ?
                    WHERE po.order_id = ?
                    FOR UPDATE
                ");
                $stmtCheck->execute([intval($row['invoice_id']), $orderId]);
                $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$current || $current['status'] !== 'Ready for Pickup' || floatval($current['amount_paid']) > 0) {
                    $this->conn->rollBack();
                    continue;
                }

                $stmtItems = $this->conn->prepare("SELECT part_id, quantity FROM part_order_item WHERE order_id = ?");
                $stmtItems->execute([$orderId]);
                $stmtRestore = $this->conn->prepare("UPDATE part SET quantity_on_hand = quantity_on_hand + ? WHERE part_id = ?");
                foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $item) {
                    $stmtRestore->execute([intval($item['quantity']), intval($item['part_id'])]);
                }

                $reason = 'Reservation expired: not picked up or paid within ' . RESERVATION_HOLD_DAYS . ' days.';
                $this->conn->prepare("
                    UPDATE part_order
                    SET status = 'Cancelled', cancellation_reason = ?, cancelled_at = NOW(), cancelled_by = NULL
                    WHERE order_id = ?
                ")->execute([$reason, $orderId]);

                $this->conn->prepare("UPDATE invoice SET is_voided = 1 WHERE invoice_id = ?")->execute([intval($row['invoice_id'])]);

                $this->conn->commit();
                $expired++;

                logActivity($this->conn, 'Reservation expired', 'Web order', $orderId, 'PO-' . $orderId, $reason . ' Parts returned to stock; unpaid invoice voided.', null);
            } catch (Exception $e) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                error_log('Reservation expiry PO-' . $orderId . ': ' . $e->getMessage());
            }
        }

        return $expired;
    }

    // Unpaid ready orders that expire within the next $hours hours, soonest first (for the Owner dashboard).
    public function listExpiringSoon($hours = 24) {
        $stmt = $this->conn->prepare("
            SELECT po.order_id, c.first_name, c.last_name, po.total_amount,
                   (i.date_issued + INTERVAL " . intval(RESERVATION_HOLD_DAYS) . " DAY) AS expires_at
            FROM part_order po
            JOIN customer c ON c.customer_id = po.customer_id
            JOIN invoice i ON i.part_order_id = po.order_id AND i.is_voided = 0
            WHERE po.status = 'Ready for Pickup'
              AND COALESCE(i.amount_paid, 0) = 0
              AND (i.date_issued + INTERVAL " . intval(RESERVATION_HOLD_DAYS) . " DAY) <= (NOW() + INTERVAL ? HOUR)
            ORDER BY expires_at ASC
        ");
        $stmt->execute([intval($hours)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isAllowedStatusTransition($current_status, $new_status) {
        $allowedTransitions = [
            'Pending' => ['Approved', 'Cancelled'],
            'Approved' => ['Ready for Pickup', 'Cancelled'],
            'Ready for Pickup' => ['Completed', 'Cancelled']
        ];

        if (!isset($allowedTransitions[$current_status])) {
            return false;
        }

        return in_array($new_status, $allowedTransitions[$current_status]);
    }

    private function getPartOrderInvoice($order_id) {
        $stmtInvoice = $this->conn->prepare("SELECT invoice_id, amount_paid, payment_status FROM invoice WHERE part_order_id = ? LIMIT 1");
        $stmtInvoice->execute([$order_id]);
        return $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }

    private function validateAndDeductStock($order_id) {
        $stmtItems = $this->conn->prepare("
            SELECT poi.part_id, poi.quantity, p.quantity_on_hand, p.part_name
            FROM part_order_item poi
            JOIN part p ON poi.part_id = p.part_id
            WHERE poi.order_id = ?
            FOR UPDATE
        ");
        $stmtItems->execute([$order_id]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        if (count($items) === 0) {
            throw new Exception("Order has no items.");
        }

        foreach ($items as $item) {
            if (intval($item['quantity_on_hand']) < intval($item['quantity'])) {
                throw new Exception(
                    "Insufficient stock for " . $item['part_name'] .
                    ". Available: " . intval($item['quantity_on_hand']) .
                    ", Needed: " . intval($item['quantity'])
                );
            }
        }

        foreach ($items as $item) {
            $quantity = intval($item['quantity']);
            $part_id = intval($item['part_id']);

            $stmtDeduct = $this->conn->prepare("UPDATE part SET quantity_on_hand = quantity_on_hand - ? WHERE part_id = ? AND quantity_on_hand >= ?");
            $stmtDeduct->execute([$quantity, $part_id, $quantity]);

            if ($stmtDeduct->rowCount() === 0) {
                throw new Exception("Unable to deduct stock for " . $item['part_name'] . ".");
            }
        }

        return array_column($items, 'part_id');
    }

    private function restoreStockIfAlreadyReserved($order_id) {
        $invoice = $this->getPartOrderInvoice($order_id);

        if (!$invoice) {
            return;
        }

        if (floatval($invoice['amount_paid']) > 0) {
            throw new Exception("Cannot cancel an order with recorded payment.");
        }

        $stmtItems = $this->conn->prepare("SELECT part_id, quantity FROM part_order_item WHERE order_id = ?");
        $stmtItems->execute([$order_id]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $stmtRestore = $this->conn->prepare("UPDATE part SET quantity_on_hand = quantity_on_hand + ? WHERE part_id = ?");
            $stmtRestore->execute([intval($item['quantity']), intval($item['part_id'])]);
        }
    }
}
?>