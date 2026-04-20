<?php
session_start();
require_once '../config/Database.php';
require_once '../models/POS.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$pos = new POS($db);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'checkout') {
    
    $items = [];
    $total_amount = 0;

    if (isset($_POST['part_ids'])) {
        for ($i = 0; $i < count($_POST['part_ids']); $i++) {
            if (!empty($_POST['part_ids'][$i])) {
                $qty = intval($_POST['part_qtys'][$i]);
                $price = floatval($_POST['part_prices'][$i]);
                $items[] = [
                    'id' => $_POST['part_ids'][$i],
                    'qty' => $qty,
                    'price' => $price
                ];
                $total_amount += ($qty * $price);
            }
        }
    }

    if (empty($items)) {
        $_SESSION['error_message'] = "Cannot process an empty transaction.";
        header("Location: ../views/pos.php");
        exit;
    }

    $data = [
        'customer_id' => !empty($_POST['customer_id']) ? $_POST['customer_id'] : null,
        'processed_by' => $_SESSION['user_id'],
        'total_amount' => $total_amount,
        'payment_method' => $_POST['payment_method'],
        'reference_number' => $_POST['reference_number'] ?? ''
    ];

    if ($pos->checkout($data, $items)) {
        $_SESSION['success_message'] = "Sale processed successfully! Total: ₱" . number_format($total_amount, 2);
    } else {
        $_SESSION['error_message'] = "Transaction failed. Inventory was not deducted.";
    }
    
    header("Location: ../views/pos.php");
    exit;
}
?>