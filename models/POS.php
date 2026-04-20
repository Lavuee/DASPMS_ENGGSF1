<?php
class POS {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function checkout($data, $items) {
        try {
            $this->conn->beginTransaction();

            $queryTx = "INSERT INTO pos_transaction 
                        (customer_id, processed_by, total_amount, status, payment_method, reference_number, transaction_date) 
                        VALUES (:customer_id, :processed_by, :total_amount, 'Completed', :payment_method, :reference_number, NOW())";
            
            $stmtTx = $this->conn->prepare($queryTx);
            $stmtTx->execute([
                ':customer_id' => !empty($data['customer_id']) ? $data['customer_id'] : null,
                ':processed_by' => $data['processed_by'],
                ':total_amount' => $data['total_amount'],
                ':payment_method' => $data['payment_method'],
                ':reference_number' => htmlspecialchars(strip_tags($data['reference_number']))
            ]);

            $posId = $this->conn->lastInsertId();

            if (!empty($items)) {
                $queryItem = "INSERT INTO pos_item (pos_id, part_id, quantity_sold, unit_price_at_sale, subtotal) 
                              VALUES (:pos_id, :part_id, :quantity_sold, :unit_price_at_sale, :subtotal)";
                $stmtItem = $this->conn->prepare($queryItem);

                $queryDeduct = "UPDATE part SET quantity_on_hand = quantity_on_hand - :qty WHERE part_id = :part_id";
                $stmtDeduct = $this->conn->prepare($queryDeduct);

                foreach ($items as $item) {
                    $subtotal = $item['qty'] * $item['price'];
                    
                    $stmtItem->execute([
                        ':pos_id' => $posId,
                        ':part_id' => $item['id'],
                        ':quantity_sold' => $item['qty'],
                        ':unit_price_at_sale' => $item['price'],
                        ':subtotal' => $subtotal
                    ]);

                    $stmtDeduct->execute([
                        ':qty' => $item['qty'],
                        ':part_id' => $item['id']
                    ]);
                }
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    public function readRecent() {
        $query = "SELECT pt.*, u.first_name, c.last_name as customer_name 
                  FROM pos_transaction pt
                  LEFT JOIN user u ON pt.processed_by = u.user_id
                  LEFT JOIN customer c ON pt.customer_id = c.customer_id
                  ORDER BY pt.transaction_date DESC LIMIT 50";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?>