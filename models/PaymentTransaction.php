<?php
/*
    Online payment attempts (payment_transaction table). One row per PayMongo
    checkout session. Stores gateway ids, amount and status only.
*/
class PaymentTransaction {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($partOrderId, $customerId, $purpose, $amount) {
        $stmt = $this->conn->prepare("
            INSERT INTO payment_transaction (part_order_id, customer_id, purpose, amount, status)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([intval($partOrderId), intval($customerId), $purpose, number_format($amount, 2, '.', '')]);

        return intval($this->conn->lastInsertId());
    }

    public function attachCheckoutSession($transactionId, array $session) {
        $stmt = $this->conn->prepare("
            UPDATE payment_transaction
            SET checkout_session_id = ?, payment_intent_id = ?, checkout_url = ?, livemode = ?
            WHERE transaction_id = ?
        ");
        $stmt->execute([
            $session['id'],
            $session['payment_intent_id'],
            $session['checkout_url'],
            $session['livemode'] ? 1 : 0,
            intval($transactionId)
        ]);
    }

    public function find($transactionId) {
        $stmt = $this->conn->prepare("SELECT * FROM payment_transaction WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([intval($transactionId)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findBySessionId($sessionId) {
        $stmt = $this->conn->prepare("SELECT * FROM payment_transaction WHERE checkout_session_id = ? LIMIT 1");
        $stmt->execute([(string)$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByPaymentIntentId($intentId) {
        $stmt = $this->conn->prepare("SELECT * FROM payment_transaction WHERE payment_intent_id = ? LIMIT 1");
        $stmt->execute([(string)$intentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findPendingForOrder($partOrderId) {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM payment_transaction
            WHERE part_order_id = ? AND status = 'Pending' AND checkout_session_id IS NOT NULL
            ORDER BY transaction_id DESC
            LIMIT 1
        ");
        $stmt->execute([intval($partOrderId)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function hasAnyForOrder($partOrderId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM payment_transaction WHERE part_order_id = ?");
        $stmt->execute([intval($partOrderId)]);
        return intval($stmt->fetchColumn()) > 0;
    }

    public function setStatus($transactionId, $status) {
        $stmt = $this->conn->prepare("UPDATE payment_transaction SET status = ? WHERE transaction_id = ? AND status = 'Pending'");
        $stmt->execute([$status, intval($transactionId)]);
    }

    /*
        Records a payment confirmed by the gateway and applies it to the part order.
        Idempotent: returns false if the transaction was already marked paid.
    */
    public function markPaid($transactionId, $gatewayPaymentId) {
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare("SELECT * FROM payment_transaction WHERE transaction_id = ? FOR UPDATE");
            $stmt->execute([intval($transactionId)]);
            $txn = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$txn || $txn['status'] === 'Paid') {
                $this->conn->commit();
                return false;
            }

            $this->conn->prepare("
                UPDATE payment_transaction
                SET status = 'Paid', gateway_payment_id = ?, paid_at = NOW()
                WHERE transaction_id = ?
            ")->execute([$gatewayPaymentId, intval($transactionId)]);

            $stmtOrder = $this->conn->prepare("SELECT status FROM part_order WHERE order_id = ? FOR UPDATE");
            $stmtOrder->execute([intval($txn['part_order_id'])]);
            $orderStatus = $stmtOrder->fetchColumn();

            if ($orderStatus === 'Cancelled') {
                // Paid after the shop cancelled the order: keep it cancelled and flag it for a refund.
                $this->conn->prepare("
                    UPDATE part_order
                    SET payment_notes = CONCAT(COALESCE(payment_notes, ''), ?)
                    WHERE order_id = ?
                ")->execute([
                    " [Online GCash payment {$gatewayPaymentId} of PHP {$txn['amount']} was received after cancellation - refund required.]",
                    intval($txn['part_order_id'])
                ]);
            } else {
                $this->conn->prepare("
                    UPDATE part_order
                    SET gcash_reference = ?,
                        gcash_payment_amount = ?,
                        gcash_verification_status = 'Verified',
                        gcash_verified_at = NOW(),
                        gcash_verified_by = NULL
                    WHERE order_id = ?
                ")->execute([$gatewayPaymentId, $txn['amount'], intval($txn['part_order_id'])]);
            }

            // Any other open checkout for the same order is no longer needed.
            $this->conn->prepare("
                UPDATE payment_transaction
                SET status = 'Cancelled'
                WHERE part_order_id = ? AND status = 'Pending' AND transaction_id <> ?
            ")->execute([intval($txn['part_order_id']), intval($transactionId)]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }
}
?>
