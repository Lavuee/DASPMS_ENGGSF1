<?php
require_once __DIR__ . '/../includes/auth.php';

require_once '../config/Database.php';
require_once __DIR__ . '/../includes/cart_store.php';

requirePermission('shop.purchase', '../views/login.php');

$db = (new Database())->getConnection();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';

// Part Details sends Add to cart in the background (X-Requested-With: fetch) so the customer stays on the page
$wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

function cartItemCount() {
    $count = 0;
    foreach ($_SESSION['cart'] ?? [] as $item) {
        $count += intval($item['qty'] ?? 0);
    }
    return $count;
}

function cartJson(array $data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

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

        cartSaveToDb($db);

        if ($wantsJson) {
            cartJson([
                'ok' => true,
                'message' => 'Added ' . $quantity . ' × ' . html_entity_decode($part['part_name'], ENT_QUOTES, 'UTF-8') . ' to your cart.',
                'item' => $quantity . ' × ' . html_entity_decode($part['part_name'], ENT_QUOTES, 'UTF-8'),
                'cartCount' => cartItemCount(),
                'inCart' => $newQty,
                'canAddMore' => max(intval($part['quantity_on_hand']) - $newQty, 0),
            ]);
        }

        $_SESSION['cp_toast'] = [true, 'Added to cart', $quantity . ' × ' . html_entity_decode($part['part_name'], ENT_QUOTES, 'UTF-8')];
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
            $_SESSION['cp_toast'] = [true, 'Item removed from cart'];
        } else {
            if ($quantity > intval($stock)) {
                throw new Exception("Quantity exceeds available stock.");
            }

            $_SESSION['cart'][$part_id]['qty'] = $quantity;
            $_SESSION['cart'][$part_id]['stock'] = intval($stock);

            $_SESSION['cp_toast'] = [true, 'Cart updated'];
        }

        cartSaveToDb($db);
        header("Location: ../views/customer_cart.php");
        exit;
    }

    if ($action === 'remove') {
        $part_id = intval($_POST['part_id'] ?? 0);

        if (isset($_SESSION['cart'][$part_id])) {
            unset($_SESSION['cart'][$part_id]);
        }

        $_SESSION['cp_toast'] = [true, 'Item removed from cart'];
        cartSaveToDb($db);
        header("Location: ../views/customer_cart.php");
        exit;
    }

    if ($action === 'clear') {
        unset($_SESSION['cart']);

        $_SESSION['cp_toast'] = [true, 'Cart cleared'];
        cartSaveToDb($db);
        header("Location: ../views/customer_cart.php");
        exit;
    }

    throw new Exception("Invalid cart action.");

} catch (Exception $e) {
    if ($wantsJson) {
        cartJson(['ok' => false, 'message' => $e->getMessage(), 'cartCount' => cartItemCount()], 422);
    }

    $_SESSION['cp_toast'] = [false, $e->getMessage()];
    header("Location: ../views/customer_cart.php");
    exit;
}
?>