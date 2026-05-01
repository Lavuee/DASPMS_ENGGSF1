<?php
session_start();

require_once '../config/Database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../views/customer_cart.php");
    exit;
}

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['error_message'] = "Your cart is empty.";
    header("Location: ../views/customer_cart.php");
    exit;
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    $stmtCustomer = $db->prepare("
        SELECT customer_id
        FROM customer
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmtCustomer->execute([$_SESSION['user_id']]);
    $customer_id = $stmtCustomer->fetchColumn();

    if (!$customer_id) {
        throw new Exception("Customer profile not found.");
    }

    $cart = $_SESSION['cart'];
    $total_amount = 0;

    foreach ($cart as $part_id => $item) {
        $part_id = intval($part_id);
        $quantity = intval($item['qty'] ?? 0);

        if ($part_id <= 0 || $quantity <= 0) {
            throw new Exception("Invalid cart item.");
        }

        $stmtPart = $db->prepare("
            SELECT part_id, part_name, unit_price, quantity_on_hand, is_active
            FROM part
            WHERE part_id = ?
            LIMIT 1
        ");
        $stmtPart->execute([$part_id]);
        $part = $stmtPart->fetch(PDO::FETCH_ASSOC);

        if (!$part || intval($part['is_active']) !== 1) {
            throw new Exception("One of the selected parts is no longer available.");
        }

        if (intval($part['quantity_on_hand']) < $quantity) {
            throw new Exception(
                "Not enough stock for " . $part['part_name'] .
                ". Available: " . intval($part['quantity_on_hand']) .
                ", Requested: " . $quantity
            );
        }

        $total_amount += floatval($part['unit_price']) * $quantity;
    }

    if ($total_amount <= 0) {
        throw new Exception("Invalid reservation total.");
    }

    $stmtOrder = $db->prepare("
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

    $order_id = $db->lastInsertId();

    $stmtItem = $db->prepare("
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

    foreach ($cart as $part_id => $item) {
        $part_id = intval($part_id);
        $quantity = intval($item['qty']);

        $stmtPartPrice = $db->prepare("
            SELECT unit_price
            FROM part
            WHERE part_id = ?
            LIMIT 1
        ");
        $stmtPartPrice->execute([$part_id]);
        $unit_price = floatval($stmtPartPrice->fetchColumn());

        $stmtItem->execute([
            $order_id,
            $part_id,
            $quantity,
            $unit_price
        ]);
    }

    $db->commit();

    unset($_SESSION['cart']);

    $_SESSION['success_message'] = "Your parts have been reserved. Please wait for shop confirmation.";
    header("Location: ../views/dashboard_customer.php");
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ../views/customer_cart.php");
    exit;
}
?>