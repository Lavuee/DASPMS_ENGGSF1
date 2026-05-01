<?php

class POS {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function checkout($data, $items) {
        try {
            $this->conn->beginTransaction();

            if (empty($items)) {
                throw new Exception("Cannot process an empty transaction.");
            }

            $validatedItems = [];
            $totalAmount = 0;

            foreach ($items as $item) {
                $partId = intval($item['id']);
                $qty = intval($item['qty']);

                if ($partId <= 0 || $qty <= 0) {
                    throw new Exception("Invalid item or quantity.");
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
                    throw new Exception("Part '{$part['part_name']}' is inactive and cannot be sold.");
                }

                if (intval($part['quantity_on_hand']) < $qty) {
                    throw new Exception(
                        "Not enough stock for '{$part['part_name']}'. Available: {$part['quantity_on_hand']}, requested: {$qty}."
                    );
                }

                $unitPrice = floatval($part['unit_price']);
                $subtotal = $unitPrice * $qty;

                $validatedItems[] = [
                    'part_id' => $partId,
                    'part_name' => $part['part_name'],
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ];

                $totalAmount += $subtotal;
            }

            if ($totalAmount <= 0) {
                throw new Exception("Invalid transaction total.");
            }

            $queryTx = "
                INSERT INTO pos_transaction (
                    customer_id,
                    processed_by,
                    total_amount,
                    status,
                    payment_method,
                    reference_number,
                    transaction_date
                ) VALUES (
                    :customer_id,
                    :processed_by,
                    :total_amount,
                    'Completed',
                    :payment_method,
                    :reference_number,
                    NOW()
                )
            ";

            $stmtTx = $this->conn->prepare($queryTx);
            $stmtTx->execute([
                ':customer_id' => !empty($data['customer_id']) ? $data['customer_id'] : null,
                ':processed_by' => $data['processed_by'],
                ':total_amount' => $totalAmount,
                ':payment_method' => $data['payment_method'],
                ':reference_number' => htmlspecialchars(strip_tags($data['reference_number']))
            ]);

            $posId = $this->conn->lastInsertId();

            $queryItem = "
                INSERT INTO pos_item (
                    pos_id,
                    part_id,
                    quantity_sold,
                    unit_price_at_sale,
                    subtotal
                ) VALUES (
                    :pos_id,
                    :part_id,
                    :quantity_sold,
                    :unit_price_at_sale,
                    :subtotal
                )
            ";
            $stmtItem = $this->conn->prepare($queryItem);

            $queryDeduct = "
                UPDATE part
                SET quantity_on_hand = quantity_on_hand - :qty
                WHERE part_id = :part_id
                AND quantity_on_hand >= :qty
            ";
            $stmtDeduct = $this->conn->prepare($queryDeduct);

            foreach ($validatedItems as $item) {
                $stmtItem->execute([
                    ':pos_id' => $posId,
                    ':part_id' => $item['part_id'],
                    ':quantity_sold' => $item['qty'],
                    ':unit_price_at_sale' => $item['unit_price'],
                    ':subtotal' => $item['subtotal']
                ]);

                $stmtDeduct->execute([
                    ':qty' => $item['qty'],
                    ':part_id' => $item['part_id']
                ]);

                if ($stmtDeduct->rowCount() === 0) {
                    throw new Exception("Stock update failed for '{$item['part_name']}'.");
                }
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Sale processed successfully.',
                'total_amount' => $totalAmount
            ];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'total_amount' => 0
            ];
        }
    }

    public function readRecent() {
        $query = "
            SELECT 
                pt.*, 
                u.first_name AS staff_first_name,
                u.last_name AS staff_last_name,
                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name
            FROM pos_transaction pt
            LEFT JOIN user u ON pt.processed_by = u.user_id
            LEFT JOIN customer c ON pt.customer_id = c.customer_id
            ORDER BY pt.transaction_date DESC
            LIMIT 50
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }
}
?>