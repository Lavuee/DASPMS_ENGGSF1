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
                po.status,
                po.total_amount,
                po.customer_id,
                c.first_name,
                c.last_name,

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
                i.payment_status,
                i.balance_due

            FROM part_order po
            JOIN customer c ON po.customer_id = c.customer_id
            JOIN part_order_item poi ON po.order_id = poi.order_id
            JOIN part p ON poi.part_id = p.part_id
            LEFT JOIN invoice i ON po.order_id = i.part_order_id

            GROUP BY
                po.order_id,
                po.order_date,
                po.status,
                po.total_amount,
                po.customer_id,
                c.first_name,
                c.last_name,
                i.invoice_id,
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
                po.order_date DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function createOrder($customer_id, $part_id, $quantity) {
        try {
            $customer_id = intval($customer_id);
            $part_id = intval($part_id);
            $quantity = intval($quantity);

            if ($customer_id <= 0 || $part_id <= 0 || $quantity <= 0) {
                throw new Exception("Invalid order details.");
            }

            $this->conn->beginTransaction();

            $stmtPart = $this->conn->prepare("
                SELECT unit_price, quantity_on_hand, is_active
                FROM part
                WHERE part_id = ?
                LIMIT 1
            ");
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

            $stmtOrder = $this->conn->prepare("
                INSERT INTO part_order (
                    customer_id,
                    total_amount,
                    status,
                    order_date
                ) VALUES (
                    ?,
                    ?,
                    'Pending',
                    NOW()
                )
            ");

            $stmtOrder->execute([
                $customer_id,
                $total_amount
            ]);

            $order_id = $this->conn->lastInsertId();

            $stmtItem = $this->conn->prepare("
                INSERT INTO part_order_item (
                    order_id,
                    part_id,
                    quantity,
                    unit_price
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmtItem->execute([
                $order_id,
                $part_id,
                $quantity,
                $unit_price
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

    public function updateStatus($order_id, $new_status, $user_id) {
        try {
            $order_id = intval($order_id);
            $user_id = intval($user_id);
            $new_status = trim($new_status);

            /*
                Completed is intentionally not allowed here.
                Web orders become Completed only after full payment in Billing.
            */
            $allowed_statuses = [
                'Pending',
                'Approved',
                'Ready for Pickup',
                'Cancelled'
            ];

            if ($order_id <= 0 || $user_id <= 0) {
                throw new Exception("Invalid order update request.");
            }

            if (!in_array($new_status, $allowed_statuses)) {
                throw new Exception("Invalid status.");
            }

            $this->conn->beginTransaction();

            $stmtCurrent = $this->conn->prepare("
                SELECT status, customer_id, total_amount
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

            if ($new_status === 'Ready for Pickup') {
                $existingInvoice = $this->getPartOrderInvoice($order_id);

                /*
                    Prevent duplicate stock deduction.
                    If invoice already exists, this order was already processed before,
                    so we should not deduct stock again.
                */
                if (!$existingInvoice) {
                    $this->validateAndDeductStock($order_id);

                    $this->createInvoiceForPartOrder(
                        $order_id,
                        intval($order['customer_id']),
                        floatval($order['total_amount']),
                        $user_id
                    );
                }
            }

            if ($new_status === 'Cancelled') {
                $this->restoreStockIfAlreadyReserved($order_id);
            }

            $stmtUpdate = $this->conn->prepare("
                UPDATE part_order
                SET status = ?
                WHERE order_id = ?
            ");

            $stmtUpdate->execute([
                $new_status,
                $order_id
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

    private function isAllowedStatusTransition($current_status, $new_status) {
        $allowedTransitions = [
            'Pending' => ['Approved', 'Cancelled'],
            'Approved' => ['Ready for Pickup', 'Cancelled'],
            'Ready for Pickup' => ['Cancelled']
        ];

        if (!isset($allowedTransitions[$current_status])) {
            return false;
        }

        return in_array($new_status, $allowedTransitions[$current_status]);
    }

    private function getPartOrderInvoice($order_id) {
        $stmtInvoice = $this->conn->prepare("
            SELECT invoice_id, amount_paid, payment_status
            FROM invoice
            WHERE part_order_id = ?
            LIMIT 1
        ");
        $stmtInvoice->execute([$order_id]);

        return $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }

    private function validateAndDeductStock($order_id) {
        $stmtItems = $this->conn->prepare("
            SELECT 
                poi.part_id,
                poi.quantity,
                p.quantity_on_hand,
                p.part_name
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
            $stmtDeduct = $this->conn->prepare("
                UPDATE part
                SET quantity_on_hand = quantity_on_hand - ?
                WHERE part_id = ?
                  AND quantity_on_hand >= ?
            ");

            $quantity = intval($item['quantity']);
            $part_id = intval($item['part_id']);

            $stmtDeduct->execute([
                $quantity,
                $part_id,
                $quantity
            ]);

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

        $stmtItems = $this->conn->prepare("
            SELECT part_id, quantity
            FROM part_order_item
            WHERE order_id = ?
        ");
        $stmtItems->execute([$order_id]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $stmtRestore = $this->conn->prepare("
                UPDATE part
                SET quantity_on_hand = quantity_on_hand + ?
                WHERE part_id = ?
            ");

            $stmtRestore->execute([
                intval($item['quantity']),
                intval($item['part_id'])
            ]);
        }
    }

    private function createInvoiceForPartOrder($order_id, $customer_id, $total_amount, $created_by) {
        $stmtCheck = $this->conn->prepare("
            SELECT invoice_id
            FROM invoice
            WHERE part_order_id = ?
            LIMIT 1
        ");
        $stmtCheck->execute([$order_id]);

        if ($stmtCheck->rowCount() > 0) {
            return;
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
                0,
                ?,
                'Not Paid'
            )
        ");

        $stmtInvoice->execute([
            $customer_id,
            $order_id,
            $created_by,
            $invoice_number,
            $total_amount,
            $total_amount,
            $total_amount
        ]);
    }
}
?>