<?php
class PartOrder {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Fetch all online orders for the Cashier/Owner
    public function getAllOrders() {
        $query = "SELECT po.order_id, po.order_date, po.status, po.total_amount, 
                         c.first_name, c.last_name, 
                         p.part_name, p.part_id, poi.quantity
                  FROM part_order po
                  JOIN customer c ON po.customer_id = c.customer_id
                  JOIN part_order_item poi ON po.order_id = poi.order_id
                  JOIN part p ON poi.part_id = p.part_id
                  ORDER BY 
                    CASE po.status 
                        WHEN 'Pending' THEN 1 
                        WHEN 'Approved' THEN 2 
                        WHEN 'Ready for Pickup' THEN 3 
                        ELSE 4 
                    END, po.order_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Update order status (and deduct inventory if completed)
    public function updateStatus($order_id, $new_status, $part_id = null, $quantity = null) {
        try {
            $this->conn->beginTransaction();

            // Prevent double-deduction by checking current status first
            $checkStmt = $this->conn->prepare("SELECT status FROM part_order WHERE order_id = ?");
            $checkStmt->execute([$order_id]);
            $current_status = $checkStmt->fetchColumn();

            // Update the status
            $stmt = $this->conn->prepare("UPDATE part_order SET status = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $order_id]);

            // If changing to Completed for the first time, deduct the physical stock
            if ($new_status == 'Completed' && $current_status != 'Completed' && $part_id && $quantity) {
                $deductStmt = $this->conn->prepare("UPDATE part SET quantity_on_hand = quantity_on_hand - ? WHERE part_id = ?");
                $deductStmt->execute([$quantity, $part_id]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>