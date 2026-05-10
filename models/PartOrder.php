<?php

class PartOrder {
    private $conn;

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

    public function createOrder(
        $customer_id,
        $part_id,
        $quantity,
        $pickup_date = null,
        $pickup_time = null,
        $pickup_notes = null,
        $payment_method = 'Cash on Pickup',
        $gcash_reference = null,
        $payment_notes = null,
        $gcash_payment_amount = null
    ) {
        try {
            $customer_id = intval($customer_id);
            $part_id = intval($part_id);
            $quantity = intval($quantity);

            if ($customer_id <= 0 || $part_id <= 0 || $quantity <= 0) {
                throw new Exception("Invalid order details.");
            }

            $gcashVerificationStatus = $this->isGcashPaymentMethod($payment_method)
                ? 'Pending Verification'
                : 'Not Required';

            $this->conn->beginTransaction();

            $stmtPart = $this->conn->prepare("SELECT unit_price, quantity_on_hand, is_active FROM part WHERE part_id = ? LIMIT 1");
            $stmtPart->execute([$part_id]);
            $part = $stmtPart->fetch(PDO::FETCH_ASSOC);

            if (!$part || intval($part['is_active']) !== 1) {
                throw new Exception("Part is unavailable.");
            }

            if (intval($part['quantity_on_hand']) < $quantity) {
                throw new Exception("Insufficient stock.");
            }

            $unit_price = floatval($part['unit_price']);
            $total_amount = $unit_price * $quantity;
            $gcashPaymentAmount = $gcash_payment_amount !== null ? floatval($gcash_payment_amount) : null;

            $stmtOrder = $this->conn->prepare("
                INSERT INTO part_order (
                    customer_id,
                    total_amount,
                    status,
                    order_date,
                    preferred_pickup_date,
                    preferred_pickup_time,
                    pickup_notes,
                    payment_method,
                    gcash_reference,
                    gcash_payment_amount,
                    gcash_verification_status,
                    payment_notes
                ) VALUES (
                    ?,
                    ?,
                    'Pending',
                    NOW(),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmtOrder->execute([
                $customer_id,
                $total_amount,
                $pickup_date,
                $pickup_time,
                $pickup_notes,
                $payment_method,
                $gcash_reference,
                $gcashPaymentAmount,
                $gcashVerificationStatus,
                $payment_notes
            ]);

            $order_id = $this->conn->lastInsertId();

            $stmtItem = $this->conn->prepare("INSERT INTO part_order_item (order_id, part_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $stmtItem->execute([$order_id, $part_id, $quantity, $unit_price]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function updateStatus($order_id, $new_status, $user_id) {
        try {
            $order_id = intval($order_id);
            $user_id = intval($user_id);
            $new_status = trim($new_status);

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
                $this->isGcashPaymentMethod($paymentMethod) &&
                $gcashVerificationStatus !== 'Verified'
            ) {
                throw new Exception("GCash payment must be verified before approving or preparing the order.");
            }

            if ($new_status === 'Cancelled') {
                if ($this->isGcashPaymentMethod($paymentMethod) && $gcashVerificationStatus === 'Verified' && $gcashPaymentAmount > 0) {
                    throw new Exception("Orders with verified GCash payment cannot be cancelled through the normal flow. Process refund/void manually first.");
                }

                $this->restoreStockIfAlreadyReserved($order_id);
            }

            if ($new_status === 'Ready for Pickup') {
                if (!$invoice) {
                    $this->validateAndDeductStock($order_id);

                    $this->createInvoiceForPartOrder(
                        $order_id,
                        intval($order['customer_id']),
                        floatval($order['total_amount']),
                        $user_id
                    );
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

            $stmtUpdate = $this->conn->prepare("UPDATE part_order SET status = ? WHERE order_id = ?");
            $stmtUpdate->execute([$new_status, $order_id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
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

            if (!$this->isGcashPaymentMethod($order['payment_method'] ?? '')) {
                throw new Exception("GCash verification is only available for GCash payments.");
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
            return false;
        }
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

    private function createInvoiceForPartOrder($order_id, $customer_id, $total_amount, $created_by) {
        $stmtCheck = $this->conn->prepare("SELECT invoice_id FROM invoice WHERE part_order_id = ? LIMIT 1");
        $stmtCheck->execute([$order_id]);

        if ($stmtCheck->rowCount() > 0) {
            return;
        }

        $stmtOrder = $this->conn->prepare("
            SELECT payment_method, gcash_reference, gcash_payment_amount, gcash_verification_status
            FROM part_order
            WHERE order_id = ?
            LIMIT 1
        ");
        $stmtOrder->execute([$order_id]);
        $orderPayment = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        $paymentMethod = $orderPayment['payment_method'] ?? 'Cash on Pickup';
        $gcashReference = trim($orderPayment['gcash_reference'] ?? '');
        $gcashAmount = floatval($orderPayment['gcash_payment_amount'] ?? 0);
        $gcashStatus = $orderPayment['gcash_verification_status'] ?? 'Not Required';

        $initialPaid = 0;

        if ($this->isGcashPaymentMethod($paymentMethod) && $gcashStatus === 'Verified') {
            $initialPaid = min($gcashAmount, $total_amount);
        }

        $balanceDue = max($total_amount - $initialPaid, 0);

        if ($initialPaid >= $total_amount) {
            $paymentStatus = 'Paid';
        } elseif ($initialPaid > 0) {
            $paymentStatus = 'Partial';
        } else {
            $paymentStatus = 'Not Paid';
        }

        $invoice_number = 'INV-PART-' . date('Ymd-His') . '-' . $order_id;

        $stmtInvoice = $this->conn->prepare("
            INSERT INTO invoice (
                customer_id,
                job_order_id,
                part_order_id,
                created_by,
                invoice_number,
                invoice_type,
                subtotal_services,
                subtotal_parts,
                total_amount,
                amount_paid,
                balance_due,
                payment_status
            ) VALUES (
                ?,
                NULL,
                ?,
                ?,
                ?,
                'Part',
                0,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmtInvoice->execute([
            $customer_id,
            $order_id,
            $created_by,
            $invoice_number,
            $total_amount,
            $total_amount,
            $initialPaid,
            $balanceDue,
            $paymentStatus
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
                    ?,
                    ?,
                    ?,
                    'GCash',
                    ?,
                    NOW()
                )
            ");

            $stmtPay->execute([
                $invoice_id,
                $created_by,
                $initialPaid,
                htmlspecialchars(strip_tags($gcashReference))
            ]);
        }
    }

    private function isGcashPaymentMethod($paymentMethod) {
        return in_array($paymentMethod, ['GCash Down Payment', 'GCash Full Payment', 'GCash Reservation']);
    }
}
?>