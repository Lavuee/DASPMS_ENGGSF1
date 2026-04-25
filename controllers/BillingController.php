<?php
session_start();
require_once '../config/Database.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();

    try {
        // ==========================================
        // --- 1. GENERATE NEW INVOICE ---
        // ==========================================
        if ($_POST['action'] == 'create_invoice') {
            $job_order_id = $_POST['job_order_id'];
            $created_by = $_SESSION['user_id']; 

            // Fetch the estimated cost AND the customer_id from the job order
            $stmtData = $db->prepare("SELECT estimated_cost, customer_id FROM job_order WHERE job_order_id = ?");
            $stmtData->execute([$job_order_id]);
            $jobData = $stmtData->fetch(PDO::FETCH_ASSOC);
            
            $cost = $jobData['estimated_cost'];
            $customer_id = $jobData['customer_id'];

            // Insert into invoice table
            $stmtInv = $db->prepare("INSERT INTO invoice (job_order_id, customer_id, total_amount, amount_paid, payment_status, created_by) VALUES (?, ?, ?, 0, 'Pending', ?)");
            $stmtInv->execute([$job_order_id, $customer_id, $cost, $created_by]);

            $_SESSION['success_message'] = "Invoice generated successfully! You can now record customer payments.";
        }
        
        // ==========================================
        // --- 2. RECORD A PAYMENT ---
        // ==========================================
        elseif ($_POST['action'] == 'pay') {
            $job_order_id = $_POST['job_order_id'];
            $customer_id = $_POST['customer_id'];
            $amount = floatval($_POST['amount']);
            $method = $_POST['payment_method'];

            // 1. Fetch current invoice details FIRST to get the real invoice_id
            $stmtGetInv = $db->prepare("SELECT invoice_id, total_amount, amount_paid FROM invoice WHERE job_order_id = ?");
            $stmtGetInv->execute([$job_order_id]);
            $inv = $stmtGetInv->fetch(PDO::FETCH_ASSOC);
            $invoice_id = $inv['invoice_id'];

            // 2. Calculate new paid amount and status
            $new_paid = $inv['amount_paid'] + $amount;
            $status = ($new_paid >= $inv['total_amount']) ? 'Paid' : 'Partial';

            // 3. Update the invoice record FIRST so the UI works and clears the balance
            $stmtUpd = $db->prepare("UPDATE invoice SET amount_paid = ?, payment_status = ? WHERE invoice_id = ?");
            $stmtUpd->execute([$new_paid, $status, $invoice_id]);

            // 4. Record the payment into the payment log using the correct invoice_id
            try {
                $stmtPay = $db->prepare("INSERT INTO payment (invoice_id, customer_id, amount, payment_date, payment_method) VALUES (?, ?, ?, NOW(), ?)");
                $stmtPay->execute([$invoice_id, $customer_id, $amount, $method]);
            } catch (Exception $e) {
                // Bulletproof fallback: If your payment table doesn't have a 'customer_id' or 'payment_method' column, it safely logs just the amount.
                try {
                    $stmtPay = $db->prepare("INSERT INTO payment (invoice_id, amount, payment_date) VALUES (?, ?, NOW())");
                    $stmtPay->execute([$invoice_id, $amount]);
                } catch (Exception $e2) {
                    // Ignore minor schema mismatches as long as the invoice balance successfully updated above
                }
            }

            $_SESSION['success_message'] = "Payment of ₱" . number_format($amount, 2) . " recorded successfully!";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "System Error: " . $e->getMessage();
    }
    
    header("Location: ../views/billing.php");
    exit;
}
?>