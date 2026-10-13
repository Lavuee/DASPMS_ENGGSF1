<?php
/*
    Shop business rules from the SRS / business profile, kept in one place.

      - Minimum service fee: ₱450. Only the Owner may set a lower rate
        (Business Profile 2.4b "Pricing Override Policy", SRS UC18).
      - Down payment: at least 50% of the estimate for services that require one
        (SRS 1.2, UC20).
      - Credit period: unpaid service bills are due 7 days after the invoice
        (SRS 1.3 "Credit Period", Business Profile 2.1A).
*/

const MIN_SERVICE_FEE = 450.00;
const DOWN_PAYMENT_RATE = 0.50;
const SERVICE_CREDIT_DAYS = 7;
// Web orders hold their parts from "Ready for Pickup"; unpaid ones are released after this many days.
const RESERVATION_HOLD_DAYS = 3;

function canOverrideMinimumServiceFee() {
    return ($_SESSION['role'] ?? '') === 'Owner';
}

// Throws when a service/labor amount is below the shop minimum and the user is not the Owner.
function enforceMinimumServiceFee($amount, $label = 'Service fee') {
    if (floatval($amount) < MIN_SERVICE_FEE && !canOverrideMinimumServiceFee()) {
        throw new Exception(
            $label . ' cannot be lower than the ₱' . number_format(MIN_SERVICE_FEE, 2)
            . ' minimum service fee. Only the Owner can approve a lower rate.'
        );
    }
}

// 50% of the estimate, rounded up to the centavo so it never falls below half.
function minimumDownPayment($estimatedCost) {
    return ceil(floatval($estimatedCost) * DOWN_PAYMENT_RATE * 100) / 100;
}

// Due date for a new service invoice (Y-m-d).
function serviceCreditDueDate($issuedDate = null) {
    $base = $issuedDate ? strtotime($issuedDate) : time();

    return date('Y-m-d', strtotime('+' . SERVICE_CREDIT_DAYS . ' days', $base));
}

// True when an unpaid service bill is past its due date.
function isInvoiceOverdue($invoiceType, $paymentStatus, $dueDate) {
    return $invoiceType === 'Service'
        && $paymentStatus !== 'Paid'
        && !empty($dueDate)
        && strtotime($dueDate) < strtotime(date('Y-m-d'));
}

// Number of overdue service bills (for dashboards and alerts).
function countOverdueServiceInvoices(PDO $db) {
    $stmt = $db->query("
        SELECT COUNT(*)
        FROM invoice
        WHERE invoice_type = 'Service'
          AND payment_status <> 'Paid'
          AND is_voided = 0
          AND due_date IS NOT NULL
          AND due_date < CURDATE()
    ");

    return intval($stmt->fetchColumn());
}
