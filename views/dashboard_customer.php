<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/ServiceRequest.php';

$db = (new Database())->getConnection();
$serviceRequestModel = new ServiceRequest($db);

$stmtC = $db->prepare("
    SELECT customer_id
    FROM customer
    WHERE user_id = ?
    LIMIT 1
");
$stmtC->execute([$_SESSION['user_id']]);
$customer_id = $stmtC->fetchColumn();

if (!$customer_id) {
    die("Customer profile not found.");
}

$stmtVehicles = $db->prepare("
    SELECT *
    FROM vehicle
    WHERE customer_id = ?
      AND status = 'Active'
    ORDER BY created_at DESC, vehicle_id DESC
");
$stmtVehicles->execute([$customer_id]);
$myVehicles = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);
$vehicleCount = count($myVehicles);

$stmtActiveServices = $serviceRequestModel->readActiveServices();
$activeServices = $stmtActiveServices->fetchAll(PDO::FETCH_ASSOC);

$stmtServiceRequests = $serviceRequestModel->readByCustomer($customer_id);
$myServiceRequests = $stmtServiceRequests->fetchAll(PDO::FETCH_ASSOC);

$stmtJO = $db->prepare("
    SELECT 
        jo.job_order_number,
        v.plate_number,
        v.make,
        v.model,
        jo.status,
        i.payment_status,
        i.balance_due,
        jo.date_created
    FROM job_order jo
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
    WHERE jo.customer_id = ?
    ORDER BY jo.date_created DESC
");
$stmtJO->execute([$customer_id]);
$jobOrders = $stmtJO->fetchAll(PDO::FETCH_ASSOC);

$stmtOrders = $db->prepare("
    SELECT 
        po.order_id,
        po.order_date,
        po.preferred_pickup_date,
        po.preferred_pickup_time,
        po.pickup_notes,
        po.payment_method,
        po.gcash_reference,
        po.gcash_payment_amount,
        po.gcash_verification_status,
        po.gcash_verified_at,
        po.payment_notes,
        po.status,
        po.total_amount,
        i.payment_status,
        i.balance_due,
        GROUP_CONCAT(
            CONCAT(p.part_name, ' x', poi.quantity)
            ORDER BY p.part_name ASC
            SEPARATOR '<br>'
        ) AS items
    FROM part_order po
    JOIN part_order_item poi ON po.order_id = poi.order_id
    JOIN part p ON poi.part_id = p.part_id
    LEFT JOIN invoice i ON po.order_id = i.part_order_id
    WHERE po.customer_id = ?
    GROUP BY 
        po.order_id,
        po.order_date,
        po.preferred_pickup_date,
        po.preferred_pickup_time,
        po.pickup_notes,
        po.payment_method,
        po.gcash_reference,
        po.gcash_payment_amount,
        po.gcash_verification_status,
        po.gcash_verified_at,
        po.payment_notes,
        po.status,
        po.total_amount,
        i.payment_status,
        i.balance_due
    ORDER BY 
        po.order_date DESC,
        po.order_id DESC
");
$stmtOrders->execute([$customer_id]);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

$activeRepairCount = 0;
$pendingPaymentCount = 0;
$pendingBalanceTotal = 0;
$readyPickupCount = 0;
$readyPickupOrders = [];
$pendingServiceRequestCount = 0;

foreach ($jobOrders as $repair) {
    if (($repair['status'] ?? '') !== 'Completed' && ($repair['status'] ?? '') !== 'Cancelled') {
        $activeRepairCount++;
    }

    $paymentStatus = $repair['payment_status'] ?? 'Not Paid';

    if ($paymentStatus !== 'Paid') {
        $pendingPaymentCount++;
        $pendingBalanceTotal += floatval($repair['balance_due'] ?? 0);
    }
}

foreach ($orders as $order) {
    if (($order['status'] ?? '') === 'Ready for Pickup') {
        $readyPickupCount++;
        $readyPickupOrders[] = $order;
    }
}

foreach ($myServiceRequests as $request) {
    if (($request['status'] ?? '') === 'Pending') {
        $pendingServiceRequestCount++;
    }
}

$hour = intval(date('H'));

if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

function isCustomerGcashOrder($paymentMethod) {
    $paymentMethod = trim((string) $paymentMethod);

    return in_array($paymentMethod, [
        'GCash Down Payment',
        'GCash Full Payment',
        'GCash Reservation'
    ]);
}

function getCustomerRepairBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'customer-status status-pending';
        case 'In Progress':
            return 'customer-status status-progress';
        case 'Ready for Pickup':
            return 'customer-status status-ready';
        case 'Completed':
            return 'customer-status status-completed';
        case 'Cancelled':
            return 'customer-status status-cancelled';
        default:
            return 'customer-status status-muted';
    }
}

function getCustomerOrderBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'customer-status status-pending';
        case 'Approved':
            return 'customer-status status-progress';
        case 'Ready for Pickup':
            return 'customer-status status-ready';
        case 'Completed':
            return 'customer-status status-completed';
        case 'Cancelled':
            return 'customer-status status-cancelled';
        default:
            return 'customer-status status-muted';
    }
}

function getCustomerGcashBadge($status) {
    switch ($status) {
        case 'Verified':
            return 'customer-status status-completed';
        case 'Rejected':
            return 'customer-status status-cancelled';
        case 'Pending Verification':
            return 'customer-status status-pending';
        case 'Not Required':
            return 'customer-status status-muted';
        default:
            return 'customer-status status-muted';
    }
}

function getCustomerPaymentBadge($paymentStatus) {
    switch ($paymentStatus) {
        case 'Paid':
            return 'customer-status status-completed';
        case 'Partial':
            return 'customer-status status-ready';
        case 'Not Paid':
            return 'customer-status status-pending';
        case 'No Invoice':
            return 'customer-status status-muted';
        default:
            return 'customer-status status-muted';
    }
}

function getServiceRequestBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'customer-status status-pending';
        case 'Converted':
            return 'customer-status status-completed';
        case 'Rejected':
            return 'customer-status status-cancelled';
        case 'Cancelled':
            return 'customer-status status-muted';
        default:
            return 'customer-status status-muted';
    }
}

function getServiceRequestDisplayLabel($status) {
    switch ($status) {
        case 'Pending':
            return 'Pending';
        case 'Converted':
            return 'Job Order Created';
        case 'Rejected':
            return 'Rejected';
        case 'Cancelled':
            return 'Cancelled';
        default:
            return 'N/A';
    }
}

function getCustomerPaymentInstruction($paymentMethod, $paymentStatus, $balanceDue, $gcashStatus) {
    $paymentMethod = trim((string) $paymentMethod);
    $paymentStatus = trim((string) $paymentStatus);
    $balanceDue = floatval($balanceDue);

    if ($paymentStatus === 'Paid') {
        return [
            'label' => 'Paid',
            'badge' => 'customer-status status-completed'
        ];
    }

    if ($paymentStatus === 'Partial') {
        if ($balanceDue > 0) {
            return [
                'label' => 'Balance at Shop',
                'badge' => 'customer-status status-ready'
            ];
        }

        return [
            'label' => 'Partially Paid',
            'badge' => 'customer-status status-ready'
        ];
    }

    if (isCustomerGcashOrder($paymentMethod)) {
        if ($gcashStatus === 'Verified') {
            if ($paymentMethod === 'GCash Full Payment') {
                return [
                    'label' => 'GCash Verified',
                    'badge' => 'customer-status status-completed'
                ];
            }

            return [
                'label' => 'DP Verified',
                'badge' => 'customer-status status-completed'
            ];
        }

        if ($gcashStatus === 'Rejected') {
            return [
                'label' => 'GCash Rejected',
                'badge' => 'customer-status status-cancelled'
            ];
        }

        return [
            'label' => 'Pending GCash Check',
            'badge' => 'customer-status status-pending'
        ];
    }

    return [
        'label' => 'Pay at Shop',
        'badge' => 'customer-status status-muted'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Portal - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
        background: #f4f5f7;
    }

    .customer-dashboard {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.15rem;
    }

    .customer-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 0.1rem;
    }

    .customer-title h2 {
        font-size: 2rem;
        font-weight: 900;
        color: #111827;
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .customer-title p {
        color: #5f6b7a;
        font-size: 1rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .customer-date-pill {
        height: 44px;
        background: transparent;
        border: 1px solid rgba(17, 24, 39, 0.10);
        color: #5f6b7a;
        border-radius: 999px;
        padding: 0.58rem 0.95rem;
        font-size: 0.9rem;
        font-weight: 800;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        box-shadow: none;
    }

    .customer-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 0;
    }

    .customer-quick-panel {
        padding: 1rem 0;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    }

    .quick-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.85rem;
    }

    .quick-panel-title {
        color: #111827;
        font-size: 0.92rem;
        font-weight: 900;
        margin: 0;
    }

    .quick-panel-subtitle {
        color: #6b7280;
        font-size: 0.84rem;
        font-weight: 500;
        margin: 0;
    }

    .quick-actions-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        align-items: center;
    }

    .quick-action-pill {
        min-height: 40px;
        border-radius: 999px;
        border: 1px solid rgba(17, 24, 39, 0.10);
        background: #fff;
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        padding: 0.55rem 0.9rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        transition: 0.2s ease;
        white-space: nowrap;
        appearance: none;
    }

    .quick-action-pill i {
        color: #111827;
        font-size: 0.95rem;
    }

    .quick-action-pill.primary {
        background: #f5c518;
        border-color: #f5c518;
    }

    .quick-action-pill:hover {
        background: #fffaf0;
        border-color: #f5c518;
        color: #111827;
        transform: translateY(-1px);
    }

    .quick-action-pill.primary:hover {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .quick-action-pill.primary:hover i {
        color: #fff;
    }

    .overview-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.2rem 0 1.15rem 0;
    }

    .overview-item {
        padding: 0.75rem 1.1rem;
        border-right: 1px solid rgba(15, 23, 42, 0.08);
    }

    .overview-item:first-child {
        padding-left: 0;
    }

    .overview-item:last-child {
        border-right: none;
        padding-right: 0;
    }

    .overview-label {
        color: #6b7280;
        font-size: 0.75rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .overview-value {
        color: #111827;
        font-size: 1.55rem;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 0.3rem;
    }

    .overview-helper {
        color: #6b7280;
        font-size: 0.82rem;
        font-weight: 500;
        margin: 0;
    }

    .customer-duo-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.25rem;
        align-items: stretch;
    }

    .customer-grid-main {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
        gap: 1rem;
        align-items: stretch;
    }

    .customer-right-stack {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        height: 100%;
    }

    .customer-section-card {
        background: transparent;
        border: none;
        border-radius: 0;
        box-shadow: none;
        overflow: visible;
        padding-top: 0.25rem;
    }

    .customer-section-card + .customer-section-card {
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        padding-top: 1.15rem;
    }

    .customer-duo-grid .customer-section-card + .customer-section-card {
        border-top: none;
        padding-top: 1.15rem;
    }

    .customer-grid-main .customer-section-card {
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        padding-top: 1.15rem;
    }

    .customer-section-card.customer-panel-card {
        background: #f4f5f7;
        border: 1px solid rgba(17, 24, 39, 0.18);
        border-radius: 18px;
        box-shadow: 0 10px 24px rgba(17, 24, 39, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.75);
        padding: 1.15rem 1.2rem;
        min-height: 310px;
        overflow: hidden;
    }

    .customer-section-card.repair-panel-card {
        min-height: 300px;
    }

    .customer-section-card.pickup-panel-card {
        min-height: 300px;
        display: flex;
        flex-direction: column;
    }

    .customer-section-card.customer-panel-card + .customer-section-card.customer-panel-card {
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        padding-top: 1.15rem;
    }

    .customer-section-card.customer-panel-card .customer-section-header {
        padding-bottom: 0.9rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.13);
    }

    .customer-section-card.customer-panel-card .compact-list {
        margin-top: 0.7rem;
    }

    .customer-section-card.customer-panel-card .compact-row {
        border-radius: 14px;
        padding: 0.95rem 0.55rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.08);
    }

    .customer-section-card.customer-panel-card .compact-row:hover {
        background: rgba(245, 197, 24, 0.075);
        margin-left: 0;
        margin-right: 0;
        padding-left: 0.55rem;
        padding-right: 0.55rem;
    }

    .customer-section-header {
        padding: 0 0 0.95rem 0;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        background: transparent;
    }

    .section-title-wrap h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 900;
        color: #111827;
    }

    .section-title-wrap p {
        margin: 0.25rem 0 0 0;
        font-size: 0.88rem;
        font-weight: 500;
        color: #5f6b7a;
    }

    .compact-list {
        display: flex;
        flex-direction: column;
        gap: 0;
        margin-top: 0.55rem;
    }

    .compact-row {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        gap: 0.85rem;
        align-items: center;
        padding: 0.95rem 0;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    }

    .compact-row:last-child {
        border-bottom: none;
    }

    .compact-icon {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        background: rgba(245, 197, 24, 0.18);
        color: #111827;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
    }

    .compact-main {
        min-width: 0;
    }

    .compact-title {
        color: #111827;
        font-size: 0.96rem;
        font-weight: 900;
        line-height: 1.25;
        margin-bottom: 0.22rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .compact-sub {
        color: #5f6b7a;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.35;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .compact-note {
        color: #6b7280;
        font-size: 0.76rem;
        line-height: 1.4;
        margin-top: 0.2rem;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .compact-side {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
    }

    .compact-action-text {
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .section-add-btn {
        background: transparent;
        border: none;
        color: #111827;
        font-size: 0.84rem;
        font-weight: 900;
        padding: 0;
        border-radius: 0;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        transition: 0.2s ease;
        white-space: nowrap;
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1.5px;
    }

    .section-add-btn:hover {
        color: #111827;
        opacity: 0.78;
    }

    .link-btn-icon {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: rgba(245, 197, 24, 0.22);
        color: #111827;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.82rem;
        flex-shrink: 0;
    }

    .link-btn-text {
        line-height: 1;
    }

    .section-text-link {
        color: #6b7280;
        font-size: 0.86rem;
        font-weight: 900;
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1.4px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: 0.2s ease;
    }

    .section-text-link:hover {
        color: #111827;
    }

    .vehicle-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 86px;
        padding: 0.4rem 0.7rem;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .vehicle-status-active {
        background: #ecfdf5;
        color: #047857;
    }

    .vehicle-status-inactive {
        background: #fff7ed;
        color: #c2410c;
    }

    .vehicle-status-archived {
        background: #f3f4f6;
        color: #4b5563;
    }

    .vehicle-edit-btn {
        border: none;
        background: transparent;
        color: #111827;
        border-radius: 0;
        padding: 0;
        font-size: 0.8rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: 0.2s ease;
        white-space: nowrap;
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1.5px;
    }

    .vehicle-edit-btn:hover {
        color: #111827;
        opacity: 0.78;
    }

    .request-cancel-btn {
        border: none;
        background: transparent;
        color: #b91c1c;
        border-radius: 0;
        padding: 0;
        font-size: 0.8rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: 0.2s ease;
        white-space: nowrap;
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1.5px;
    }

    .request-cancel-btn .link-btn-icon {
        background: #fff1f2;
        color: #b91c1c;
    }

    .request-cancel-btn:hover {
        color: #991b1b;
        opacity: 0.82;
    }

    .repair-list {
        display: flex;
        flex-direction: column;
        margin-top: 0.7rem;
    }

    .repair-row {
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr) auto;
        gap: 0.95rem;
        align-items: center;
        padding: 1rem 0.55rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.08);
        border-radius: 14px;
        background: transparent;
    }

    .repair-row:last-child {
        border-bottom: none;
    }

    .repair-row:hover {
        background: rgba(245, 197, 24, 0.075);
    }

    .repair-icon {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        background: rgba(245, 197, 24, 0.16);
        color: #111827;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .repair-main strong {
        display: block;
        font-size: 1rem;
        color: #111827;
        font-weight: 900;
        line-height: 1.2;
    }

    .repair-main span {
        display: block;
        color: #5f6b7a;
        font-size: 0.84rem;
        margin-top: 0.25rem;
    }

    .repair-status-area {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.4rem;
    }

    .customer-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-pending {
        background: #fff3cd;
        color: #9a5b00;
    }

    .status-progress {
        background: #e7f0ff;
        color: #1d4ed8;
    }

    .status-ready {
        background: #fff7d6;
        color: #9a5b00;
    }

    .status-completed {
        background: #d7fbe5;
        color: #047857;
    }

    .status-cancelled,
    .status-muted {
        background: #eef0f3;
        color: #4b5563;
    }

    .payment-text-paid {
        color: #047857;
        font-weight: 900;
        font-size: 0.82rem;
    }

    .payment-text-unpaid {
        color: #dc2626;
        font-weight: 900;
        font-size: 0.82rem;
    }

    .customer-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .customer-table {
        width: 100%;
        margin-bottom: 0;
        vertical-align: middle;
    }

    .customer-order-table {
        width: 100%;
        min-width: 0;
        table-layout: fixed;
    }

    .compact-order-table th:nth-child(1),
    .compact-order-table td:nth-child(1) {
        width: 10%;
    }

    .compact-order-table th:nth-child(2),
    .compact-order-table td:nth-child(2) {
        width: 15%;
    }

    .compact-order-table th:nth-child(3),
    .compact-order-table td:nth-child(3) {
        width: 15%;
    }

    .compact-order-table th:nth-child(4),
    .compact-order-table td:nth-child(4) {
        width: 32%;
    }

    .compact-order-table th:nth-child(5),
    .compact-order-table td:nth-child(5) {
        width: 12%;
    }

    .compact-order-table th:nth-child(6),
    .compact-order-table td:nth-child(6) {
        width: 16%;
    }

    .compact-order-table td {
        white-space: normal !important;
        vertical-align: middle;
    }

    .customer-table thead th {
        background: transparent;
        color: #5f6b7a;
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 0.85rem 0.85rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.08);
        white-space: nowrap;
    }

    .customer-table tbody td {
        padding: 0.95rem 0.85rem;
        font-size: 0.9rem;
        color: #111827;
        border-bottom: 1px solid rgba(17, 24, 39, 0.06);
        white-space: nowrap;
        background: transparent;
    }

    .customer-table tbody tr:last-child td {
        border-bottom: none;
    }

    .customer-table tbody tr:hover {
        background: rgba(255, 251, 235, 0.55);
    }

    .order-id-main {
        color: #111827;
        font-size: 0.92rem;
        font-weight: 900;
        line-height: 1.25;
    }

    .order-date-sub {
        color: #5f6b7a;
        font-size: 0.78rem;
        font-weight: 700;
        margin-top: 0.25rem;
    }

    .order-item-name {
        color: #111827;
        font-size: 0.9rem;
        font-weight: 900;
        line-height: 1.35;
    }

    .order-detail-main {
        color: #111827;
        font-size: 0.9rem;
        font-weight: 900;
        line-height: 1.35;
    }

    .order-detail-muted {
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.45;
        margin-top: 0.25rem;
        max-width: 240px;
        white-space: normal;
    }

    .order-payment-card {
        display: flex;
        flex-direction: column;
        gap: 0.42rem;
        max-width: 100%;
    }

    .order-payment-method {
        color: #111827;
        font-size: 0.86rem;
        font-weight: 900;
        line-height: 1.3;
    }

    .payment-verification-line {
        color: #5f6b7a;
        font-size: 0.74rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .payment-verification-line strong {
        color: #047857;
        font-weight: 900;
    }

    .order-payment-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.32rem;
    }

    .payment-mini-box {
        border: 1px solid rgba(17, 24, 39, 0.08);
        border-radius: 10px;
        padding: 0.38rem 0.45rem;
        background: rgba(255, 255, 255, 0.30);
        min-width: 0;
    }

    .payment-mini-label {
        color: #6b7280;
        font-size: 0.60rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.22px;
        margin-bottom: 0.12rem;
    }

    .payment-mini-value {
        color: #111827;
        font-size: 0.74rem;
        font-weight: 900;
        line-height: 1.15;
    }

    .payment-mini-value.green {
        color: #047857;
    }

    .payment-detail-line {
        color: #5f6b7a;
        font-size: 0.76rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .payment-detail-line strong {
        color: #111827;
        font-weight: 900;
    }

    .payment-muted-note {
        color: #6b7280;
        font-size: 0.74rem;
        line-height: 1.4;
    }

    .pickup-content {
        flex: 1;
    }

    .pickup-box {
        padding: 2rem 0 1.45rem 0;
        text-align: center;
        min-height: 175px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .pickup-icon {
        width: 50px;
        height: 50px;
        border-radius: 18px;
        background: rgba(245, 197, 24, 0.13);
        color: #f0b400;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.55rem;
        margin-bottom: 0.85rem;
    }

    .pickup-title {
        font-size: 1rem;
        font-weight: 900;
        color: #111827;
        margin-bottom: 0.35rem;
    }

    .pickup-text {
        color: #5f6b7a;
        font-size: 0.93rem;
        margin-bottom: 0;
        line-height: 1.5;
    }

    .pickup-list {
        padding: 1rem 0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .pickup-item {
        border-bottom: 1px solid rgba(17, 24, 39, 0.07);
        padding: 0.85rem 0;
        background: transparent;
    }

    .pickup-item strong {
        color: #111827;
        font-weight: 900;
    }

    .reminder-card {
        margin-top: 0.8rem;
        padding: 1rem 0 0.15rem 0;
        background: transparent;
        border-top: 1px solid rgba(245, 197, 24, 0.65);
        border-bottom: none;
        border-radius: 0;
    }

    .reminder-card h6 {
        font-weight: 900;
        color: #111827;
        margin-bottom: 0.4rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .reminder-card p {
        color: #5f6b7a;
        font-size: 0.88rem;
        margin-bottom: 0;
        line-height: 1.48;
    }

    .empty-state {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #5f6b7a;
    }

    .empty-state i {
        display: block;
        font-size: 2.35rem;
        color: #d1d5db;
        margin-bottom: 0.75rem;
    }

    .customer-section-card.customer-panel-card .empty-state {
        padding: 2.15rem 1rem;
    }

    .customer-modal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(17, 17, 17, 0.18);
    }

    .customer-modal .modal-header {
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 1.15rem 1.35rem;
    }

    .customer-modal .modal-title {
        font-weight: 900;
        color: #111827;
    }

    .customer-modal .modal-body {
        padding: 1.35rem;
    }

    .customer-modal .modal-footer {
        border-top: 1px solid #e5e7eb;
        padding: 1rem 1.35rem;
    }

    .form-label-custom {
        font-size: 0.78rem;
        font-weight: 900;
        color: #4b5563;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .form-control-custom {
        min-height: 44px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 0.65rem 0.85rem;
        color: #111827;
        font-size: 0.92rem;
        box-shadow: none !important;
    }

    .form-control-custom:focus {
        border-color: #f5c518;
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18) !important;
    }

    .modal-save-btn {
        background: #f5c518;
        border: 1px solid #f5c518;
        color: #111827;
        font-weight: 900;
        border-radius: 999px;
        padding: 0.65rem 1.1rem;
    }

    .modal-save-btn:hover {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .modal-cancel-btn {
        border-radius: 999px;
        font-weight: 800;
        padding: 0.65rem 1.1rem;
    }

    @media (max-width: 1200px) {
        .customer-grid-main {
            grid-template-columns: 1fr;
        }

        .customer-right-stack {
            height: auto;
        }

        .customer-order-table {
            min-width: 900px;
        }
    }

    @media (max-width: 991.98px) {
        .customer-header {
            flex-direction: column;
        }

        .overview-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            row-gap: 0.35rem;
        }

        .overview-item:nth-child(2) {
            border-right: none;
            padding-right: 0;
        }

        .overview-item:nth-child(3) {
            padding-left: 0;
        }

        .customer-duo-grid {
            grid-template-columns: 1fr;
        }

        .customer-section-card.customer-panel-card {
            min-height: auto;
        }
    }

    @media (max-width: 767.98px) {
        .customer-order-table {
            min-width: 860px;
        }

        .order-payment-summary {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 575.98px) {
        .customer-title h2 {
            font-size: 1.55rem;
        }

        .customer-date-pill,
        .section-add-btn {
            width: auto;
            justify-content: flex-start;
        }

        .quick-panel-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .quick-actions-row {
            display: grid;
            grid-template-columns: 1fr;
            width: 100%;
        }

        .quick-action-pill {
            width: 100%;
            justify-content: center;
        }

        .overview-strip {
            grid-template-columns: 1fr;
        }

        .overview-item,
        .overview-item:first-child,
        .overview-item:last-child,
        .overview-item:nth-child(3) {
            border-right: none;
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .overview-item:last-child {
            border-bottom: none;
        }

        .compact-row {
            grid-template-columns: 40px minmax(0, 1fr);
        }

        .compact-side {
            grid-column: 2;
            align-items: flex-start;
            flex-direction: row;
            flex-wrap: wrap;
        }

        .repair-row {
            grid-template-columns: 40px minmax(0, 1fr);
        }

        .repair-status-area {
            grid-column: 2;
            align-items: flex-start;
        }
    }
</style>
</head>

<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid py-4 customer-dashboard">

        <div class="customer-header">
            <div class="customer-title">
                <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Customer'); ?>!</h2>
                <p>Welcome back. Here's your repair and order summary.</p>
            </div>

            <div class="customer-date-pill">
                <i class="bi bi-calendar-event me-2"></i>
                <?php echo date('M d, Y'); ?>
            </div>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success customer-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger customer-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="customer-quick-panel">
            <div class="quick-panel-header">
                <div>
                    <p class="quick-panel-title">Quick Actions</p>
                    <p class="quick-panel-subtitle">Choose what you want to do next.</p>
                </div>
            </div>

            <div class="quick-actions-row">
                <a href="customer_services.php" class="quick-action-pill primary">
                    <i class="bi bi-calendar-plus"></i>
                    Request Service
                </a>

                <a href="customer_parts.php" class="quick-action-pill">
                    <i class="bi bi-box-seam"></i>
                    Browse Parts
                </a>

                <a href="customer_services.php" class="quick-action-pill">
                    <i class="bi bi-wrench-adjustable-circle"></i>
                    Services Offered
                </a>

                <a href="customer_cart.php" class="quick-action-pill">
                    <i class="bi bi-cart3"></i>
                    My Cart
                </a>

                <a href="#repairStatusSection" class="quick-action-pill">
                    <i class="bi bi-car-front-fill"></i>
                    My Repairs
                </a>

                <a href="#orderStatusSection" class="quick-action-pill">
                    <i class="bi bi-bag-check"></i>
                    My Orders
                </a>
            </div>
        </div>

        <div class="overview-strip">
            <div class="overview-item">
                <div class="overview-label">Active Repairs</div>
                <div class="overview-value"><?php echo $activeRepairCount; ?></div>
                <p class="overview-helper">Repair jobs still in progress</p>
            </div>

            <div class="overview-item">
                <div class="overview-label">Service Requests</div>
                <div class="overview-value"><?php echo $pendingServiceRequestCount; ?></div>
                <p class="overview-helper">Pending online requests</p>
            </div>

            <div class="overview-item">
                <div class="overview-label">Balance Due</div>
                <div class="overview-value">₱<?php echo number_format($pendingBalanceTotal, 2); ?></div>
                <p class="overview-helper">Total unpaid repair balance</p>
            </div>

            <div class="overview-item">
                <div class="overview-label">Ready for Pickup</div>
                <div class="overview-value"><?php echo $readyPickupCount; ?></div>
                <p class="overview-helper">Parts orders ready at the shop</p>
            </div>
        </div>

        <div class="customer-duo-grid">
            <div class="customer-section-card customer-panel-card" id="myVehiclesSection">
                <div class="customer-section-header">
                    <div class="section-title-wrap">
                        <h5>My Registered Vehicles</h5>
                        <p>Saved vehicle details for faster service requests.</p>
                    </div>

                    <button type="button" class="section-add-btn" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                        <span class="link-btn-icon">
                            <i class="bi bi-plus-circle"></i>
                        </span>
                        <span class="link-btn-text">Add Vehicle</span>
                    </button>
                </div>

                <?php if (empty($myVehicles)): ?>
                    <div class="empty-state">
                        <i class="bi bi-car-front"></i>
                        <h6 class="fw-bold mb-1">No vehicles registered yet</h6>
                        <p class="mb-0">Add your vehicle details so the shop can connect future repairs to your profile.</p>
                    </div>
                <?php else: ?>
                    <div class="compact-list">
                        <?php foreach ($myVehicles as $vehicle): ?>
                            <?php
                                $vehicleStatus = $vehicle['status'] ?? 'Active';
                                $statusClass = 'vehicle-status-active';

                                if ($vehicleStatus === 'Inactive') {
                                    $statusClass = 'vehicle-status-inactive';
                                } elseif ($vehicleStatus === 'Archived') {
                                    $statusClass = 'vehicle-status-archived';
                                }

                                $vehicleName = trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
                                $vehicleNotes = !empty($vehicle['notes']) ? $vehicle['notes'] : 'Registered vehicle';
                            ?>

                            <div class="compact-row">
                                <div class="compact-icon">
                                    <i class="bi bi-car-front-fill"></i>
                                </div>

                                <div class="compact-main">
                                    <div class="compact-title">
                                        <?php echo htmlspecialchars($vehicleName !== '' ? $vehicleName : 'Vehicle'); ?>
                                    </div>
                                    <div class="compact-sub">
                                        Plate: <?php echo htmlspecialchars($vehicle['plate_number'] ?? 'N/A'); ?>
                                        · <?php echo htmlspecialchars($vehicle['year'] ?? 'N/A'); ?>
                                        · <?php echo htmlspecialchars($vehicle['color'] ?? 'N/A'); ?>
                                        · VID-<?php echo intval($vehicle['vehicle_id']); ?>
                                    </div>
                                    <div class="compact-note">
                                        <?php echo htmlspecialchars($vehicleNotes); ?>
                                    </div>
                                </div>

                                <div class="compact-side">
                                    <span class="vehicle-status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($vehicleStatus); ?>
                                    </span>

                                    <button
                                        type="button"
                                        class="vehicle-edit-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editVehicleModal"
                                        data-vehicle-id="<?php echo intval($vehicle['vehicle_id']); ?>"
                                        data-plate-number="<?php echo htmlspecialchars($vehicle['plate_number'] ?? '', ENT_QUOTES); ?>"
                                        data-make="<?php echo htmlspecialchars($vehicle['make'] ?? '', ENT_QUOTES); ?>"
                                        data-model="<?php echo htmlspecialchars($vehicle['model'] ?? '', ENT_QUOTES); ?>"
                                        data-year="<?php echo htmlspecialchars($vehicle['year'] ?? '', ENT_QUOTES); ?>"
                                        data-color="<?php echo htmlspecialchars($vehicle['color'] ?? '', ENT_QUOTES); ?>"
                                        data-notes="<?php echo htmlspecialchars($vehicle['notes'] ?? '', ENT_QUOTES); ?>"
                                    >
                                        <span class="link-btn-icon">
                                            <i class="bi bi-pencil-square"></i>
                                        </span>
                                        <span class="link-btn-text">Edit</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="customer-section-card customer-panel-card" id="serviceRequestsSection">
                <div class="customer-section-header">
                    <div class="section-title-wrap">
                        <h5>My Service Requests</h5>
                        <p>Online appointments and approval status.</p>
                    </div>
                        <a href="customer_service_requests.php" class="section-add-btn">
                            <span class="link-btn-icon">
                                <i class="bi bi-list-check"></i>
                            </span>
                            <span class="link-btn-text">View Requests</span>
                        </a>
                </div>

                <?php if (empty($myServiceRequests)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-check"></i>
                        <h6 class="fw-bold mb-1">No service requests yet</h6>
                        <p class="mb-0">Submit a service request for one of your registered vehicles.</p>
                    </div>
                <?php else: ?>
                    <div class="compact-list">
                        <?php foreach ($myServiceRequests as $request): ?>
                            <?php
                                $requestDate = !empty($request['created_at'])
                                    ? date('M d, Y', strtotime($request['created_at']))
                                    : 'N/A';

                                $preferredDate = !empty($request['preferred_appointment_date'])
                                    ? date('M d, Y', strtotime($request['preferred_appointment_date']))
                                    : 'N/A';

                                $preferredTime = !empty($request['preferred_appointment_time'])
                                    ? date('h:i A', strtotime($request['preferred_appointment_time']))
                                    : '';

                                $requestVehicle = trim(($request['make'] ?? '') . ' ' . ($request['model'] ?? ''));
                            ?>

                            <div class="compact-row">
                                <div class="compact-icon">
                                    <i class="bi bi-wrench-adjustable-circle"></i>
                                </div>

                                <div class="compact-main">
                                    <div class="compact-title">
                                        <?php echo htmlspecialchars($request['service_name'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="compact-sub">
                                        <?php echo htmlspecialchars($request['request_number'] ?? 'N/A'); ?>
                                        · <?php echo htmlspecialchars($requestDate); ?>
                                        · Preferred: <?php echo htmlspecialchars($preferredDate); ?>
                                        <?php if (!empty($preferredTime)): ?>
                                            <?php echo htmlspecialchars(' ' . $preferredTime); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="compact-note">
                                        <?php echo htmlspecialchars($requestVehicle !== '' ? $requestVehicle : 'Vehicle'); ?>
                                        · Plate: <?php echo htmlspecialchars($request['plate_number'] ?? 'N/A'); ?>
                                        · <?php echo htmlspecialchars($request['concern_description'] ?? 'No concern details'); ?>
                                    </div>

                                    <?php if (($request['status'] ?? '') === 'Converted' && !empty($request['job_order_number'])): ?>
                                        <div class="compact-note">
                                            <?php echo htmlspecialchars($request['job_order_number']); ?> created
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="compact-side">
                                    <span class="<?php echo getServiceRequestBadge($request['status'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(getServiceRequestDisplayLabel($request['status'] ?? '')); ?>
                                    </span>

                                    <?php if (($request['status'] ?? '') === 'Pending'): ?>
                                        <form action="../controllers/ServiceRequestController.php" method="POST" class="m-0" onsubmit="return confirm('Cancel this pending service request?');">
                                            <input type="hidden" name="action" value="cancel_customer_request">
                                            <input type="hidden" name="service_request_id" value="<?php echo intval($request['service_request_id']); ?>">
                                            <button type="submit" class="request-cancel-btn">
                                                <span class="link-btn-icon">
                                                    <i class="bi bi-x-circle"></i>
                                                </span>
                                                <span class="link-btn-text">Cancel</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="compact-action-text">No action</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="customer-grid-main">
            <div class="customer-section-card customer-panel-card repair-panel-card" id="repairStatusSection">
                <div class="customer-section-header">
                    <div class="section-title-wrap">
                        <h5>My Vehicle Repairs</h5>
                        <p>Repair records linked to your customer profile.</p>
                    </div>
                </div>

                <?php if (empty($jobOrders)): ?>
                    <div class="empty-state">
                        <i class="bi bi-tools"></i>
                        <h6 class="fw-bold mb-1">No repair records yet</h6>
                        <p class="mb-0">Repair records will appear here once the shop creates a job order for your vehicle.</p>
                    </div>
                <?php else: ?>
                    <div class="repair-list">
                        <?php foreach ($jobOrders as $job): ?>
                            <?php
                                $paymentStatus = $job['payment_status'] ?? 'Not Paid';
                                $isPaid = $paymentStatus === 'Paid';
                            ?>
                            <div class="repair-row">
                                <div class="repair-icon">
                                    <i class="bi bi-car-front-fill"></i>
                                </div>

                                <div class="repair-main">
                                    <strong>
                                        <?php echo htmlspecialchars($job['plate_number'] ?? 'N/A'); ?>
                                        <span class="text-muted">· <?php echo htmlspecialchars(($job['make'] ?? '') . ' ' . ($job['model'] ?? '')); ?></span>
                                    </strong>
                                    <span>
                                        JO: <?php echo htmlspecialchars($job['job_order_number'] ?? 'N/A'); ?>
                                        · Date:
                                        <?php
                                            echo !empty($job['date_created'])
                                                ? date('M d, Y', strtotime($job['date_created']))
                                                : 'N/A';
                                        ?>
                                    </span>
                                </div>

                                <div class="repair-status-area">
                                    <span class="<?php echo getCustomerRepairBadge($job['status'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($job['status'] ?? 'N/A'); ?>
                                    </span>

                                    <span class="<?php echo $isPaid ? 'payment-text-paid' : 'payment-text-unpaid'; ?>">
                                        <?php echo htmlspecialchars($paymentStatus); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="customer-right-stack">
                <div class="customer-section-card customer-panel-card pickup-panel-card">
                    <div class="customer-section-header">
                        <div class="section-title-wrap">
                            <h5>Pickup Reminders</h5>
                            <p>Orders currently ready for shop pickup.</p>
                        </div>

                        <a href="#orderStatusSection" class="section-text-link">
                            View Orders <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <div class="pickup-content">
                        <?php if (empty($readyPickupOrders)): ?>
                            <div class="pickup-box">
                                <div class="pickup-icon">
                                    <i class="bi bi-bag-check"></i>
                                </div>
                                <h6 class="pickup-title">No pickup reminders</h6>
                                <p class="pickup-text">No parts orders are ready for pickup right now.</p>
                            </div>
                        <?php else: ?>
                            <div class="pickup-list">
                                <?php foreach ($readyPickupOrders as $pickup): ?>
                                    <div class="pickup-item">
                                        <strong>Order #<?php echo intval($pickup['order_id']); ?></strong>
                                        <div class="text-muted small mt-1">
                                            <?php echo $pickup['items'] ?? 'No items'; ?>
                                        </div>
                                        <div class="fw-bold mt-1">
                                            ₱<?php echo number_format(floatval($pickup['total_amount'] ?? 0), 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="reminder-card">
                        <h6>
                            <i class="bi bi-info-circle"></i>
                            Customer Reminder
                        </h6>
                        <p>
                            For parts reservations, the shop accepts manual GCash down payment or full payment.
                            GCash payments must be verified by shop staff. If you only paid a down payment,
                            please settle the remaining balance at the shop before claiming your item.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="customer-section-card" id="orderStatusSection">
            <div class="customer-section-header">
                <div class="section-title-wrap">
                    <h5>My Ordered Parts</h5>
                    <p>Parts orders submitted through the customer portal.</p>
                </div>

                <a href="customer_parts.php" class="section-text-link">
                    Browse Parts <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="customer-table-wrap">
                <table class="table customer-table customer-order-table compact-order-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Item Details</th>
                            <th>Pickup Schedule</th>
                            <th>Payment Details</th>
                            <th>Total Cost</th>
                            <th>Order Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="bi bi-receipt"></i>
                                        <h6 class="fw-bold mb-1">No online orders yet</h6>
                                        <p class="mb-0">Browse available parts and submit a pickup order when needed.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                    $paymentMethod = trim($order['payment_method'] ?? 'Cash on Pickup');
                                    $gcashReference = trim($order['gcash_reference'] ?? '');
                                    $gcashAmount = floatval($order['gcash_payment_amount'] ?? 0);

                                    $gcashStatus = trim($order['gcash_verification_status'] ?? '');

                                    if ($gcashStatus === '' && isCustomerGcashOrder($paymentMethod)) {
                                        $gcashStatus = 'Pending Verification';
                                    }

                                    if ($gcashStatus === '' && !isCustomerGcashOrder($paymentMethod)) {
                                        $gcashStatus = 'Not Required';
                                    }

                                    $paymentStatus = trim($order['payment_status'] ?? 'No Invoice');
                                    $balanceDue = floatval($order['balance_due'] ?? 0);
                                    $totalAmount = floatval($order['total_amount'] ?? 0);
                                    $recordedPaid = max($totalAmount - $balanceDue, 0);

                                    if ($paymentStatus === 'No Invoice') {
                                        $recordedPaid = 0;
                                    }

                                    $shopPaidAmount = 0;

                                    if (isCustomerGcashOrder($paymentMethod) && $gcashAmount > 0) {
                                        $shopPaidAmount = max($recordedPaid - $gcashAmount, 0);
                                    }

                                    $pickupDate = $order['preferred_pickup_date'] ?? '';
                                    $pickupTime = $order['preferred_pickup_time'] ?? '';
                                    $pickupNotes = $order['pickup_notes'] ?? '';

                                    $pickupDateDisplay = !empty($pickupDate)
                                        ? date('M d, Y', strtotime($pickupDate))
                                        : 'No pickup schedule';

                                    $pickupTimeDisplay = !empty($pickupTime)
                                        ? date('h:i A', strtotime($pickupTime))
                                        : '';

                                    $orderDateDisplay = !empty($order['order_date'])
                                        ? date('M d, Y', strtotime($order['order_date']))
                                        : 'N/A';
                                ?>

                                <tr>
                                    <td>
                                        <div class="order-id-main">
                                            #<?php echo intval($order['order_id']); ?>
                                        </div>
                                        <div class="order-date-sub">
                                            <?php echo htmlspecialchars($orderDateDisplay); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="order-item-name">
                                            <?php echo $order['items'] ?? 'No items'; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="order-detail-main">
                                            <?php echo htmlspecialchars($pickupDateDisplay); ?>
                                        </div>

                                        <?php if (!empty($pickupTimeDisplay)): ?>
                                            <div class="order-detail-muted">
                                                <?php echo htmlspecialchars($pickupTimeDisplay); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($pickupNotes)): ?>
                                            <div class="order-detail-muted">
                                                <?php echo htmlspecialchars($pickupNotes); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="order-payment-card">
                                            <div class="order-payment-method">
                                                <?php echo htmlspecialchars($paymentMethod); ?>
                                            </div>

                                            <?php if (isCustomerGcashOrder($paymentMethod)): ?>
                                                <div class="payment-verification-line">
                                                    GCash verification:
                                                    <strong><?php echo htmlspecialchars($gcashStatus); ?></strong>
                                                </div>
                                            <?php endif; ?>

                                            <div class="order-payment-summary">
                                                <div class="payment-mini-box">
                                                    <div class="payment-mini-label">Total Paid</div>
                                                    <div class="payment-mini-value green">
                                                        ₱<?php echo number_format($recordedPaid, 2); ?>
                                                    </div>
                                                </div>

                                                <div class="payment-mini-box">
                                                    <div class="payment-mini-label">Balance</div>
                                                    <div class="payment-mini-value">
                                                        ₱<?php echo number_format($balanceDue, 2); ?>
                                                    </div>
                                                </div>

                                                <div class="payment-mini-box">
                                                    <div class="payment-mini-label">Invoice</div>
                                                    <div class="payment-mini-value">
                                                        <?php echo htmlspecialchars($paymentStatus); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (isCustomerGcashOrder($paymentMethod)): ?>
                                                <?php if (!empty($gcashReference)): ?>
                                                    <div class="payment-detail-line">
                                                        <strong>GCash Ref:</strong>
                                                        <?php echo htmlspecialchars($gcashReference); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($gcashAmount > 0): ?>
                                                    <div class="payment-detail-line">
                                                        <strong>GCash Paid:</strong>
                                                        ₱<?php echo number_format($gcashAmount, 2); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($shopPaidAmount > 0): ?>
                                                    <div class="payment-detail-line">
                                                        <strong>Paid at Shop:</strong>
                                                        ₱<?php echo number_format($shopPaidAmount, 2); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($order['gcash_verified_at'])): ?>
                                                    <div class="payment-muted-note">
                                                        Checked: <?php echo date('M d, Y h:i A', strtotime($order['gcash_verified_at'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!empty($order['payment_notes'])): ?>
                                                <div class="payment-muted-note">
                                                    <?php echo htmlspecialchars($order['payment_notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <strong class="text-success">
                                            ₱<?php echo number_format($totalAmount, 2); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <span class="<?php echo getCustomerOrderBadge($order['status'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($order['status'] ?? 'N/A'); ?>
                                        </span>

                                        <div class="text-muted small mt-2">
                                            <?php
                                                switch ($order['status'] ?? '') {
                                                    case 'Pending':
                                                        if (isCustomerGcashOrder($paymentMethod) && $gcashStatus !== 'Verified') {
                                                            echo 'Waiting for GCash verification.';
                                                        } else {
                                                            echo 'Waiting for shop confirmation.';
                                                        }
                                                        break;
                                                    case 'Approved':
                                                        echo 'Order approved and being prepared.';
                                                        break;
                                                    case 'Ready for Pickup':
                                                        if ($paymentStatus === 'Paid') {
                                                            echo 'Paid and ready for shop pickup.';
                                                        } elseif ($balanceDue > 0) {
                                                            echo 'Ready for pickup. Please settle the remaining balance at the shop.';
                                                        } else {
                                                            echo 'Ready for shop pickup.';
                                                        }
                                                        break;
                                                    case 'Completed':
                                                        echo 'Order completed. Thank you!';
                                                        break;
                                                    case 'Cancelled':
                                                        echo 'This order was cancelled.';
                                                        break;
                                                    default:
                                                        echo 'Status unavailable.';
                                                }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- REQUEST SERVICE MODAL -->
<div class="modal fade customer-modal" id="requestServiceModal" tabindex="-1" aria-labelledby="requestServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="../controllers/ServiceRequestController.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="submit_customer_request">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="requestServiceModalLabel">Request Service Appointment</h5>
                    <p class="mb-0 text-muted small">
                        Submit a service request for shop review and estimated cost approval.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <?php if (empty($myVehicles)): ?>
                    <div class="alert alert-warning mb-0" style="border-radius: 14px;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Please add a registered vehicle first before requesting a service appointment.
                    </div>
                <?php elseif (empty($activeServices)): ?>
                    <div class="alert alert-warning mb-0" style="border-radius: 14px;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No active services are available right now. Please contact the shop.
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border mb-3" style="border-radius: 14px;">
                        <i class="bi bi-info-circle me-1"></i>
                        Your request will be reviewed by the shop. The final estimated cost will be provided after approval.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" class="form-select form-control-custom" required>
                                <option value="">Select vehicle</option>
                                <?php foreach ($myVehicles as $vehicle): ?>
                                    <option value="<?php echo intval($vehicle['vehicle_id']); ?>">
                                        <?php echo htmlspecialchars($vehicle['plate_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Service <span class="text-danger">*</span></label>
                            <select name="service_id" class="form-select form-control-custom" required>
                                <option value="">Select service</option>
                                <?php foreach ($activeServices as $service): ?>
                                    <option value="<?php echo intval($service['service_id']); ?>">
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                        <?php if (!empty($service['base_price'])): ?>
                                            - ₱<?php echo number_format(floatval($service['base_price']), 2); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Preferred Date <span class="text-danger">*</span></label>
                            <input
                                type="date"
                                name="preferred_appointment_date"
                                class="form-control form-control-custom"
                                min="<?php echo date('Y-m-d'); ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Preferred Time</label>
                            <input
                                type="time"
                                name="preferred_appointment_time"
                                class="form-control form-control-custom"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label-custom">Concern / Symptoms / Additional Notes <span class="text-danger">*</span></label>
                            <textarea
                                name="concern_description"
                                class="form-control form-control-custom"
                                rows="4"
                                placeholder="Describe the vehicle concern or symptoms. Example: leaking oil, hard starting, unusual noise, weak brakes."
                                required
                            ></textarea>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">Cancel</button>

                <?php if (!empty($myVehicles) && !empty($activeServices)): ?>
                    <button type="submit" class="btn modal-save-btn">
                        <i class="bi bi-send me-1"></i>
                        Submit Request
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ADD VEHICLE MODAL -->
<div class="modal fade customer-modal" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="../controllers/VehicleController.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="add_customer_vehicle">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="addVehicleModalLabel">Add Vehicle</h5>
                    <p class="mb-0 text-muted small">Register your vehicle once so it can be reused for future service records.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-light border mb-3" style="border-radius: 14px;">
                    <i class="bi bi-info-circle me-1"></i>
                    Plate numbers must be unique. If your vehicle is already registered by the shop, contact the staff for assistance.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Plate Number <span class="text-danger">*</span></label>
                        <input type="text" name="plate_number" class="form-control form-control-custom text-uppercase" placeholder="Example: ABC 1234" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Make / Brand <span class="text-danger">*</span></label>
                        <input type="text" name="make" class="form-control form-control-custom" placeholder="Example: Toyota" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Model <span class="text-danger">*</span></label>
                        <input type="text" name="model" class="form-control form-control-custom" placeholder="Example: Vios" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-custom">Year <span class="text-danger">*</span></label>
                        <input type="text" name="year" class="form-control form-control-custom" maxlength="4" pattern="[0-9]{4}" placeholder="2020" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-custom">Color <span class="text-danger">*</span></label>
                        <input type="text" name="color" class="form-control form-control-custom" placeholder="Black" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label-custom">Notes</label>
                        <textarea name="notes" class="form-control form-control-custom" rows="3" placeholder="Optional notes, engine concerns, or vehicle remarks"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn modal-save-btn">
                    <i class="bi bi-save me-1"></i>
                    Save Vehicle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT VEHICLE MODAL -->
<div class="modal fade customer-modal" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="../controllers/VehicleController.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="update_customer_vehicle">
            <input type="hidden" name="vehicle_id" id="edit_vehicle_id">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
                    <p class="mb-0 text-muted small">Update your saved vehicle information.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-light border mb-3" style="border-radius: 14px;">
                    <i class="bi bi-shield-check me-1"></i>
                    You can only edit vehicles linked to your own customer account.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Plate Number <span class="text-danger">*</span></label>
                        <input type="text" name="plate_number" id="edit_plate_number" class="form-control form-control-custom text-uppercase" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Make / Brand <span class="text-danger">*</span></label>
                        <input type="text" name="make" id="edit_make" class="form-control form-control-custom" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Model <span class="text-danger">*</span></label>
                        <input type="text" name="model" id="edit_model" class="form-control form-control-custom" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-custom">Year <span class="text-danger">*</span></label>
                        <input type="text" name="year" id="edit_year" class="form-control form-control-custom" maxlength="4" pattern="[0-9]{4}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-custom">Color <span class="text-danger">*</span></label>
                        <input type="text" name="color" id="edit_color" class="form-control form-control-custom" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label-custom">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control form-control-custom" rows="3" placeholder="Optional notes, engine concerns, or vehicle remarks"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn modal-save-btn">
                    <i class="bi bi-save me-1"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editVehicleModal = document.getElementById('editVehicleModal');
    const plateInputs = document.querySelectorAll('input[name="plate_number"]');

    plateInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    });

    if (editVehicleModal) {
        editVehicleModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            if (!button) {
                return;
            }

            document.getElementById('edit_vehicle_id').value = button.getAttribute('data-vehicle-id') || '';
            document.getElementById('edit_plate_number').value = button.getAttribute('data-plate-number') || '';
            document.getElementById('edit_make').value = button.getAttribute('data-make') || '';
            document.getElementById('edit_model').value = button.getAttribute('data-model') || '';
            document.getElementById('edit_year').value = button.getAttribute('data-year') || '';
            document.getElementById('edit_color').value = button.getAttribute('data-color') || '';
            document.getElementById('edit_notes').value = button.getAttribute('data-notes') || '';
        });
    }
});
</script>

</body>
</html>