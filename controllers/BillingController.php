<?php
session_start();

require_once '../config/Database.php';
require_once '../models/Billing.php';

if (
    !isset($_SESSION['logged_in']) ||
    ($_SESSION['role'] !== 'Cashier' && $_SESSION['role'] !== 'Owner')
) {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['action'])) {
    header("Location: ../views/billing.php");
    exit;
}

$db = (new Database())->getConnection();
$billing = new Billing($db);

try {
    $action = $_POST['action'];

    if ($action === 'create_invoice') {
        $source = $_POST['source'] ?? '';
        $ref_id = intval($_POST['ref_id'] ?? 0);
        $created_by = intval($_SESSION['user_id']);

        if ($ref_id <= 0) {
            throw new Exception("Invalid billing reference.");
        }

        if ($source === 'job') {
            $result = $billing->createInvoice($ref_id, $created_by);
        } elseif ($source === 'part') {
            $result = $billing->createPartInvoice($ref_id, $created_by);
        } else {
            throw new Exception("Invalid invoice source.");
        }

        if ($result) {
            $_SESSION['success_message'] = "Invoice generated successfully.";
        } else {
            $_SESSION['error_message'] = "Invoice already exists or could not be generated.";
        }
    } elseif ($action === 'pay') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $user_id = intval($_SESSION['user_id']);
        $amount = floatval($_POST['amount'] ?? 0);
        $method = trim($_POST['payment_method'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');

        $allowed_methods = ['Cash', 'GCash', 'Bank Transfer', 'Cheque'];

        if ($invoice_id <= 0) {
            throw new Exception("Invalid invoice.");
        }

        if ($amount <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }

        if (!in_array($method, $allowed_methods)) {
            throw new Exception("Invalid payment method.");
        }

        if (in_array($method, ['GCash', 'Bank Transfer', 'Cheque']) && $reference_number === '') {
            throw new Exception("Reference number is required for {$method} payments.");
        }

        if ($billing->receivePayment($invoice_id, $user_id, $amount, $method, $reference_number)) {
            $_SESSION['success_message'] = "Payment of ₱" . number_format($amount, 2) . " recorded successfully.";
        } else {
            $_SESSION['error_message'] = "Unable to record payment. Please check the invoice balance.";
        }
    } else {
        throw new Exception("Invalid billing action.");
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "System Error: " . $e->getMessage();
}

header("Location: ../views/billing.php");
exit;
?>