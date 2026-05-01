<?php
session_start();

require_once '../config/Database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../views/login.php");
    exit;
}

$db = (new Database())->getConnection();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $part_id = intval($_POST['part_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);

        if ($part_id <= 0) {
            throw new Exception("Invalid part selected.");
        }

        if ($quantity <= 0) {
            throw new Exception("Invalid quantity.");
        }

        $stmt = $db->prepare("
            SELECT 
                part_id,
                part_name,
                unit_price,
                quantity_on_hand,
                image,
                is_active
            FROM part
            WHERE part_id = ?
            LIMIT 1
        ");
        $stmt->execute([$part_id]);
        $part = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$part || intval($part['is_active']) !== 1) {
            throw new Exception("Part is unavailable.");
        }

        $currentCartQty = isset($_SESSION['cart'][$part_id])
            ? intval($_SESSION['cart'][$part_id]['qty'])
            : 0;

        $newQty = $currentCartQty + $quantity;

        if ($newQty > intval($part['quantity_on_hand'])) {
            throw new Exception("Quantity exceeds available stock.");
        }

        $_SESSION['cart'][$part_id] = [
            'part_id' => intval($part['part_id']),
            'name' => $part['part_name'],
            'price' => floatval($part['unit_price']),
            'qty' => $newQty,
            'stock' => intval($part['quantity_on_hand']),
            'image' => $part['image']
        ];

        $_SESSION['success_message'] = "Item added to cart.";
        header("Location: ../views/customer_cart.php");
        exit;
    }

    if ($action === 'update') {
        $part_id = intval($_POST['part_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);

        if (!isset($_SESSION['cart'][$part_id])) {
            throw new Exception("Item not found in cart.");
        }

        $stmt = $db->prepare("
            SELECT quantity_on_hand
            FROM part
            WHERE part_id = ?
            AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$part_id]);
        $stock = $stmt->fetchColumn();

        if ($stock === false) {
            throw new Exception("Part is no longer available.");
        }

        if ($quantity <= 0) {
            unset($_SESSION['cart'][$part_id]);
            $_SESSION['success_message'] = "Item removed from cart.";
        } else {
            if ($quantity > intval($stock)) {
                throw new Exception("Quantity exceeds available stock.");
            }

            $_SESSION['cart'][$part_id]['qty'] = $quantity;
            $_SESSION['cart'][$part_id]['stock'] = intval($stock);

            $_SESSION['success_message'] = "Cart updated.";
        }

        header("Location: ../views/customer_cart.php");
        exit;
    }

    if ($action === 'remove') {
        $part_id = intval($_POST['part_id'] ?? 0);

        if (isset($_SESSION['cart'][$part_id])) {
            unset($_SESSION['cart'][$part_id]);
        }

        $_SESSION['success_message'] = "Item removed from cart.";
        header("Location: ../views/customer_cart.php");
        exit;
    }

    if ($action === 'clear') {
        unset($_SESSION['cart']);

        $_SESSION['success_message'] = "Cart cleared.";
        header("Location: ../views/customer_cart.php");
        exit;
    }

    throw new Exception("Invalid cart action.");

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ../views/customer_cart.php");
    exit;
}
?>