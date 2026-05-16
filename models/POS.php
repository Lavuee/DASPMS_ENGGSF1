<?php
// ADDED: Include the automated restock alert engine
require_once __DIR__ . '/../controllers/RestockAlert.php';

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

            $customerType = trim($data['customer_type'] ?? 'Walk-in');
            $customerId = !empty($data['customer_id']) ? intval($data['customer_id']) : null;

            $walkinCustomerName = trim($data['walkin_customer_name'] ?? '');
            $walkinContactNumber = trim($data['walkin_contact_number'] ?? '');
            $walkinAddress = trim($data['walkin_address'] ?? '');

            if (!in_array($customerType, ['Walk-in', 'Registered'])) {
                throw new Exception("Invalid customer type.");
            }

            if ($customerType === 'Registered') {
                if (!$customerId || $customerId <= 0) {
                    throw new Exception("Please select a registered customer.");
                }

                $stmtCustomer = $this->conn->prepare("
                    SELECT customer_id
                    FROM customer
                    WHERE customer_id = ?
                      AND status = 'Active'
                    LIMIT 1
                ");
                $stmtCustomer->execute([$customerId]);

                if (!$stmtCustomer->fetchColumn()) {
                    throw new Exception("Selected customer does not exist or is inactive.");
                }

                $walkinCustomerName = null;
                $walkinContactNumber = null;
                $walkinAddress = null;
            }

            if ($customerType === 'Walk-in') {
                $customerId = null;

                $walkinCustomerName = $walkinCustomerName !== ''
                    ? htmlspecialchars(strip_tags($walkinCustomerName))
                    : null;

                $walkinContactNumber = $walkinContactNumber !== ''
                    ? htmlspecialchars(strip_tags($walkinContactNumber))
                    : null;

                $walkinAddress = $walkinAddress !== ''
                    ? htmlspecialchars(strip_tags($walkinAddress))
                    : null;
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

                $unitPrice = round(floatval($part['unit_price']), 2);
                $subtotal = round($unitPrice * $qty, 2);

                $validatedItems[] = [
                    'part_id' => $partId,
                    'part_name' => $part['part_name'],
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ];

                $totalAmount = round($totalAmount + $subtotal, 2);
            }

            if ($totalAmount <= 0) {
                throw new Exception("Invalid transaction total.");
            }

            $queryTx = "
                INSERT INTO pos_transaction (
                    customer_id,
                    customer_type,
                    walkin_customer_name,
                    walkin_contact_number,
                    walkin_address,
                    processed_by,
                    total_amount,
                    status,
                    payment_method,
                    reference_number,
                    transaction_date
                ) VALUES (
                    :customer_id,
                    :customer_type,
                    :walkin_customer_name,
                    :walkin_contact_number,
                    :walkin_address,
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
                ':customer_id' => $customerId,
                ':customer_type' => $customerType,
                ':walkin_customer_name' => $walkinCustomerName,
                ':walkin_contact_number' => $walkinContactNumber,
                ':walkin_address' => $walkinAddress,
                ':processed_by' => intval($data['processed_by']),
                ':total_amount' => number_format($totalAmount, 2, '.', ''),
                ':payment_method' => $data['payment_method'],
                ':reference_number' => htmlspecialchars(strip_tags($data['reference_number'] ?? ''))
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
                    ':unit_price_at_sale' => number_format($item['unit_price'], 2, '.', ''),
                    ':subtotal' => number_format($item['subtotal'], 2, '.', '')
                ]);

                $stmtDeduct->execute([
                    ':qty' => $item['qty'],
                    ':part_id' => $item['part_id']
                ]);

                if ($stmtDeduct->rowCount() === 0) {
                    throw new Exception("Stock update failed for '{$item['part_name']}'.");
                }
                
                // ADDED: Trigger automated restock email check
                RestockAlert::checkAndSend($this->conn, $item['part_id']);
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