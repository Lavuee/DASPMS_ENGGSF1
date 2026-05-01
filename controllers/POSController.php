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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'checkout') {

    try {
        $items = [];

        if (isset($_POST['part_ids']) && is_array($_POST['part_ids'])) {
            for ($i = 0; $i < count($_POST['part_ids']); $i++) {
                $part_id = intval($_POST['part_ids'][$i] ?? 0);
                $qty = intval($_POST['part_qtys'][$i] ?? 0);

                if ($part_id > 0 && $qty > 0) {
                    $items[] = [
                        'id' => $part_id,
                        'qty' => $qty
                    ];
                }
            }
        }

        if (empty($items)) {
            throw new Exception("Cannot process an empty transaction.");
        }

        $payment_method = trim($_POST['payment_method'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');

        $allowed_methods = ['Cash', 'GCash', 'Bank Transfer', 'Cheque'];

        if (!in_array($payment_method, $allowed_methods)) {
            throw new Exception("Invalid payment method.");
        }

        if (in_array($payment_method, ['GCash', 'Bank Transfer', 'Cheque']) && $reference_number === '') {
            throw new Exception("Reference number is required for {$payment_method} payments.");
        }

        $data = [
            'customer_id' => !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null,
            'processed_by' => intval($_SESSION['user_id']),
            'payment_method' => $payment_method,
            'reference_number' => $reference_number
        ];

        $result = $pos->checkout($data, $items);

        if ($result['success']) {
            $_SESSION['success_message'] = "Sale processed successfully! Total: ₱" . number_format($result['total_amount'], 2);
        } else {
            $_SESSION['error_message'] = $result['message'];
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "System Error: " . $e->getMessage();
    }

    header("Location: ../views/pos.php");
    exit;
}

header("Location: ../views/pos.php");
exit;
?>