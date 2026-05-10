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
    $selectedItemsRaw = $_POST['selected_items'] ?? [];

    if (!is_array($selectedItemsRaw) || empty($selectedItemsRaw)) {
        throw new Exception("Please select at least one item to reserve.");
    }

    $selectedPartIds = array_unique(array_filter(array_map('intval', $selectedItemsRaw)));

    if (empty($selectedPartIds)) {
        throw new Exception("Please select at least one valid item to reserve.");
    }

    $preferredPickupDate = trim($_POST['preferred_pickup_date'] ?? '');
    $preferredPickupTime = trim($_POST['preferred_pickup_time'] ?? '');
    $pickupNotes = trim($_POST['pickup_notes'] ?? '');

    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $gcashReference = trim($_POST['gcash_reference'] ?? '');
    $gcashPaymentAmountInput = trim($_POST['gcash_payment_amount'] ?? '');
    $paymentNotes = trim($_POST['payment_notes'] ?? '');

    $allowedPaymentMethods = [
        'Cash on Pickup',
        'GCash Down Payment',
        'GCash Full Payment'
    ];

    if ($preferredPickupDate === '') {
        throw new Exception("Preferred pickup date is required.");
    }

    if ($preferredPickupTime === '') {
        throw new Exception("Preferred pickup time is required.");
    }

    $pickupDateObj = DateTime::createFromFormat('Y-m-d', $preferredPickupDate);
    $todayObj = new DateTime(date('Y-m-d'));

    if (!$pickupDateObj || $pickupDateObj->format('Y-m-d') !== $preferredPickupDate) {
        throw new Exception("Invalid preferred pickup date.");
    }

    if ($pickupDateObj < $todayObj) {
        throw new Exception("Preferred pickup date cannot be in the past.");
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $preferredPickupTime)) {
        throw new Exception("Invalid preferred pickup time.");
    }

    if (strlen($pickupNotes) > 500) {
        throw new Exception("Pickup notes must not exceed 500 characters.");
    }

    if ($paymentMethod === '' || !in_array($paymentMethod, $allowedPaymentMethods)) {
        throw new Exception("Please select a valid payment method.");
    }

    if ($paymentNotes !== '' && strlen($paymentNotes) > 500) {
        throw new Exception("Payment notes must not exceed 500 characters.");
    }

    if ($paymentMethod !== 'Cash on Pickup') {
        if ($gcashReference === '') {
            throw new Exception("GCash reference number is required for GCash payments.");
        }

        if (strlen($gcashReference) > 100) {
            throw new Exception("GCash reference number must not exceed 100 characters.");
        }
    }

    $preferredPickupTimeForDb = $preferredPickupTime . ':00';

    $db->beginTransaction();

    $stmtCustomer = $db->prepare("SELECT customer_id FROM customer WHERE user_id = ? LIMIT 1");
    $stmtCustomer->execute([$_SESSION['user_id']]);
    $customer_id = $stmtCustomer->fetchColumn();

    if (!$customer_id) {
        throw new Exception("Customer profile not found.");
    }

    $cart = $_SESSION['cart'];
    $selectedCartItems = [];
    $total_amount = 0;

    foreach ($selectedPartIds as $part_id) {
        if (!isset($cart[$part_id])) {
            throw new Exception("One of the selected items is no longer in your cart.");
        }

        $item = $cart[$part_id];
        $quantity = intval($item['qty'] ?? 0);

        if ($part_id <= 0 || $quantity <= 0) {
            throw new Exception("Invalid cart item.");
        }

        $stmtPart = $db->prepare("SELECT part_id, part_name, unit_price, quantity_on_hand, is_active FROM part WHERE part_id = ? LIMIT 1");
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

        $unitPrice = floatval($part['unit_price']);
        $total_amount += $unitPrice * $quantity;

        $selectedCartItems[$part_id] = [
            'part_id' => $part_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice
        ];
    }

    if ($total_amount <= 0) {
        throw new Exception("Invalid reservation total.");
    }

    $gcashPaymentAmount = 0;
    $gcashVerificationStatus = 'Not Required';

    if ($paymentMethod === 'Cash on Pickup') {
        $gcashReference = '';
        $gcashPaymentAmount = 0;
        $gcashVerificationStatus = 'Not Required';
    } elseif ($paymentMethod === 'GCash Down Payment') {
        $gcashPaymentAmount = floatval($gcashPaymentAmountInput);

        if ($gcashPaymentAmount <= 0) {
            throw new Exception("Down payment amount must be greater than zero.");
        }

        if ($gcashPaymentAmount >= $total_amount) {
            throw new Exception("Down payment must be less than the selected total. Choose GCash Full Payment if paying the full amount.");
        }

        $gcashVerificationStatus = 'Pending Verification';
    } elseif ($paymentMethod === 'GCash Full Payment') {
        $gcashPaymentAmount = $total_amount;
        $gcashVerificationStatus = 'Pending Verification';
    }

    $stmtOrder = $db->prepare("
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
        $preferredPickupDate,
        $preferredPickupTimeForDb,
        $pickupNotes !== '' ? $pickupNotes : null,
        $paymentMethod,
        $gcashReference !== '' ? $gcashReference : null,
        $gcashPaymentAmount > 0 ? $gcashPaymentAmount : null,
        $gcashVerificationStatus,
        $paymentNotes !== '' ? $paymentNotes : null
    ]);

    $order_id = $db->lastInsertId();

    $stmtItem = $db->prepare("INSERT INTO part_order_item (order_id, part_id, quantity, unit_price) VALUES (?, ?, ?, ?)");

    foreach ($selectedCartItems as $item) {
        $stmtItem->execute([
            $order_id,
            $item['part_id'],
            $item['quantity'],
            $item['unit_price']
        ]);

        unset($_SESSION['cart'][$item['part_id']]);
    }

    if (empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }

    $db->commit();

    $_SESSION['success_message'] = "Your selected parts have been reserved. Please wait for shop confirmation and GCash verification if applicable.";
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