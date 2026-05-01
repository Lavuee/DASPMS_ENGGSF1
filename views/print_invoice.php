<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
$db = (new Database())->getConnection();

$invoice_id = intval($_GET['invoice_id'] ?? 0);

$stmt = $db->prepare("
    SELECT 
        i.*,
        c.first_name,
        c.last_name,
        c.contact_number,
        jo.job_order_number,
        po.order_id AS part_order_number
    FROM invoice i
    JOIN customer c ON i.customer_id = c.customer_id
    LEFT JOIN job_order jo ON i.job_order_id = jo.job_order_id
    LEFT JOIN part_order po ON i.part_order_id = po.order_id
    WHERE i.invoice_id = ?
    LIMIT 1
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found.");
}

$stmtPayments = $db->prepare("
    SELECT amount, payment_method, reference_number, payment_date
    FROM payment
    WHERE invoice_id = ?
    ORDER BY payment_date ASC
");
$stmtPayments->execute([$invoice_id]);
$payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

$referenceNo = $invoice['invoice_type'] === 'Service'
    ? $invoice['job_order_number']
    : 'PO-' . $invoice['part_order_number'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
body {
    background: #f4f6f9;
    font-family: Arial, sans-serif;
    color: #111827;
}

.invoice-page {
    max-width: 980px;
    margin: 0 auto;
    padding: 24px 16px 40px;
}

.invoice-action-bar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(244, 246, 249, 0.92);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(17, 24, 39, 0.08);
    margin: 0 -16px 24px;
    padding: 12px 16px;
}

.invoice-action-inner {
    max-width: 850px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.invoice-action-group {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.invoice-action-btn {
    min-height: 38px;
    border-radius: 10px;
    padding: 0.45rem 0.85rem;
    font-weight: 700;
    font-size: 0.86rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.38rem;
    line-height: 1;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.back-history-btn {
    background: rgba(255, 255, 255, 0.65);
    border: 1px solid rgba(17, 24, 39, 0.18);
    color: #111827;
}

.back-history-btn:hover {
    background: #ffffff;
    border-color: #111827;
    color: #111827;
}

.download-invoice-btn {
    background: #f5c518;
    border: 1px solid #f5c518;
    color: #111827;
}

.download-invoice-btn:hover {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
}

.print-invoice-btn {
    background: #ffffff;
    border: 1px solid #111827;
    color: #111827;
}

.print-invoice-btn:hover {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
}

/* =========================
   INVOICE DOCUMENT
========================= */

.invoice-wrapper {
    max-width: 850px;
    margin: 0 auto;
    background: #ffffff;
    padding: 44px 46px;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
}

.invoice-top-accent {
    position: absolute;
    top: 0;
    left: 46px;
    width: 58px;
    height: 92px;
    background: #111827;
    display: flex;
    align-items: center;
    justify-content: center;
}

.invoice-top-accent span {
    color: #f5c518;
    font-weight: 900;
    font-size: 1.35rem;
    letter-spacing: 1px;
}

.invoice-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2rem;
    align-items: start;
    margin-bottom: 2.3rem;
    padding-left: 82px;
}

.shop-title {
    font-size: 1.25rem;
    font-weight: 900;
    margin-bottom: 0.25rem;
    color: #111827;
}

.shop-subtitle,
.shop-address {
    font-size: 0.86rem;
    color: #4b5563;
    line-height: 1.45;
}

.invoice-title {
    font-size: 2.25rem;
    font-weight: 900;
    letter-spacing: 0.25rem;
    text-align: right;
    color: #374151;
    margin-bottom: 1rem;
}

.invoice-meta-table {
    min-width: 250px;
}

.invoice-meta-row {
    display: grid;
    grid-template-columns: 92px 1fr;
    gap: 0.75rem;
    font-size: 0.86rem;
    margin-bottom: 0.4rem;
}

.invoice-meta-label {
    color: #6b7280;
    font-weight: 700;
}

.invoice-meta-value {
    color: #111827;
    font-weight: 700;
    text-align: right;
}

.invoice-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.5rem;
    margin-bottom: 2.2rem;
}

.invoice-info-title {
    font-size: 0.78rem;
    font-weight: 900;
    color: #111827;
    text-transform: uppercase;
    letter-spacing: 0.05rem;
    margin-bottom: 0.6rem;
}

.bill-name {
    font-size: 1.2rem;
    font-weight: 900;
    color: #111827;
    margin-bottom: 0.2rem;
}

.info-line {
    color: #4b5563;
    font-size: 0.88rem;
    line-height: 1.55;
    margin-bottom: 0.25rem;
}

.transaction-info {
    text-align: right;
}

.invoice-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.35rem;
}

.invoice-items-table thead th {
    background: #3f3f46;
    color: #ffffff;
    padding: 0.85rem 0.9rem;
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04rem;
}

.invoice-items-table tbody td {
    padding: 0.95rem 0.9rem;
    border-bottom: 1px solid #e5e7eb;
    color: #111827;
    font-size: 0.9rem;
}

.invoice-items-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.invoice-items-table .item-no {
    width: 54px;
    text-align: center;
    color: #6b7280;
}

.invoice-items-table .item-amount,
.invoice-items-table .item-price,
.invoice-items-table .item-qty {
    text-align: right;
    white-space: nowrap;
}

.invoice-bottom-grid {
    display: grid;
    grid-template-columns: 0.9fr 1.1fr;
    gap: 2.2rem;
    align-items: start;
    margin-top: 1.4rem;
}

.payment-note-box {
    padding-top: 0.4rem;
}

.payment-note-label {
    font-size: 0.78rem;
    color: #6b7280;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04rem;
    margin-bottom: 0.65rem;
}

.payment-note-heading {
    font-size: 1.05rem;
    font-weight: 900;
    color: #111827;
    padding-bottom: 0.7rem;
    border-bottom: 2px solid #111827;
    display: inline-block;
    min-width: 170px;
    margin-bottom: 0.75rem;
}

.payment-note-text {
    color: #6b7280;
    font-size: 0.8rem;
    line-height: 1.6;
    max-width: 280px;
    margin-bottom: 0;
}

.invoice-total-table {
    width: 100%;
    border-collapse: collapse;
}

.invoice-total-table td {
    padding: 0.62rem 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.9rem;
}

.invoice-total-table .label-cell {
    color: #374151;
    text-align: right;
    font-weight: 700;
}

.invoice-total-table .amount-cell {
    color: #111827;
    text-align: right;
    font-weight: 800;
    white-space: nowrap;
}

.invoice-total-table .grand-total-row td {
    background: #3f3f46;
    color: #ffffff;
    border-bottom: none;
    font-size: 1rem;
    font-weight: 900;
    padding: 0.85rem 0.75rem;
}

.payment-section {
    margin-top: 2.2rem;
}

.payment-title {
    font-size: 0.9rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04rem;
    margin-bottom: 0.8rem;
    color: #111827;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-table th {
    color: #374151;
    background: #f3f4f6;
    border-bottom: 1px solid #d1d5db;
    padding: 0.7rem 0.75rem;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.03rem;
}

.payment-table td {
    padding: 0.72rem 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    color: #111827;
    font-size: 0.86rem;
}

.invoice-footer-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 2rem;
    margin-top: 2.4rem;
    align-items: end;
}

.terms-box h6 {
    font-size: 0.86rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04rem;
    margin-bottom: 0.65rem;
}

.terms-box p {
    color: #6b7280;
    font-size: 0.8rem;
    line-height: 1.55;
    margin-bottom: 0;
}

.signature-box {
    text-align: center;
}

.signature-line {
    height: 56px;
    border-bottom: 1px solid #111827;
    margin-bottom: 0.55rem;
}

.signature-name {
    font-size: 0.85rem;
    font-weight: 900;
    color: #111827;
    margin-bottom: 0.15rem;
}

.signature-role {
    font-size: 0.78rem;
    color: #6b7280;
}

.invoice-contact-strip {
    margin: 2.2rem -46px -44px;
    padding: 1rem 46px;
    border-top: 1px solid #e5e7eb;
    background: #fafafa;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    color: #4b5563;
    font-size: 0.76rem;
    line-height: 1.35;
}

.contact-icon {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #111827;
    color: #f5c518;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.empty-payment-note {
    color: #6b7280;
    font-size: 0.86rem;
    margin-bottom: 0;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
}

@media (max-width: 767.98px) {
    .invoice-action-inner {
        flex-direction: column;
        align-items: stretch;
    }

    .invoice-action-group {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .invoice-action-btn {
        width: 100%;
    }

    .invoice-wrapper {
        padding: 30px 24px;
    }

    .invoice-top-accent {
        display: none;
    }

    .invoice-header {
        grid-template-columns: 1fr;
        padding-left: 0;
    }

    .invoice-title,
    .invoice-meta-value,
    .transaction-info {
        text-align: left;
    }

    .invoice-info-grid,
    .invoice-bottom-grid,
    .invoice-footer-grid,
    .invoice-contact-strip {
        grid-template-columns: 1fr;
    }

    .invoice-contact-strip {
        margin: 2rem -24px -30px;
        padding: 1rem 24px;
    }
}

@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    body {
        background: #ffffff !important;
        color: #111827 !important;
    }

    .no-print {
        display: none !important;
    }

    .invoice-page {
        max-width: 100%;
        margin: 0;
        padding: 0;
    }

    .invoice-wrapper {
        box-shadow: none;
        margin: 0;
        max-width: 100%;
        border-radius: 0;
        padding: 28px 30px;
        overflow: visible;
    }

    .invoice-top-accent {
        top: 0;
        left: 30px;
    }

    .invoice-contact-strip {
        margin-left: -30px;
        margin-right: -30px;
        margin-bottom: -28px;
        padding-left: 30px;
        padding-right: 30px;
    }

    .invoice-items-table thead th,
    .invoice-total-table .grand-total-row td,
    .contact-icon,
    .invoice-top-accent {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
</head>

<body>

<div class="invoice-page">

    <!-- ACTION BAR -->
    <div class="invoice-action-bar no-print">
        <div class="invoice-action-inner">
            <a href="invoice_history.php" class="btn invoice-action-btn back-history-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Invoice History
            </a>

            <div class="invoice-action-group">
                <button onclick="downloadInvoicePDF()" class="btn invoice-action-btn download-invoice-btn">
                    <i class="bi bi-download"></i>
                    Download PDF
                </button>

                <button onclick="window.print()" class="btn invoice-action-btn print-invoice-btn">
                    <i class="bi bi-printer"></i>
                    Print Invoice
                </button>
            </div>
        </div>
    </div>

    <!-- INVOICE CONTENT -->
    <div class="invoice-wrapper" id="invoiceArea">

        <div class="invoice-top-accent">
            <span>NV</span>
        </div>

        <div class="invoice-header">
            <div>
                <div class="shop-title">Norily's Vehicle Repair Shop</div>
                <div class="shop-address">Vila Rosario, La Union</div>
                <div class="shop-subtitle">Digital Auto Service and Parts Management System</div>
            </div>

            <div>
                <div class="invoice-title">INVOICE</div>

                <div class="invoice-meta-table">
                    <div class="invoice-meta-row">
                        <div class="invoice-meta-label">Invoice No</div>
                        <div class="invoice-meta-value"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                    </div>

                    <div class="invoice-meta-row">
                        <div class="invoice-meta-label">Invoice Date</div>
                        <div class="invoice-meta-value"><?php echo date('M d, Y'); ?></div>
                    </div>

                    <div class="invoice-meta-row">
                        <div class="invoice-meta-label">Status</div>
                        <div class="invoice-meta-value"><?php echo htmlspecialchars($invoice['payment_status']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="invoice-info-grid">
            <div>
                <div class="invoice-info-title">Invoice To</div>

                <div class="bill-name">
                    <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?>
                </div>

                <div class="info-line">
                    Customer
                </div>

                <div class="info-line mt-3">
                    <strong>Contact:</strong> <?php echo htmlspecialchars($invoice['contact_number'] ?? 'N/A'); ?>
                </div>
            </div>

            <div class="transaction-info">
                <div class="invoice-info-title">Transaction Details</div>

                <div class="info-line">
                    <strong>Type:</strong> <?php echo htmlspecialchars($invoice['invoice_type']); ?>
                </div>

                <div class="info-line">
                    <strong>Reference:</strong> <?php echo htmlspecialchars($referenceNo); ?>
                </div>

                <div class="info-line">
                    <strong>Payment Status:</strong> <?php echo htmlspecialchars($invoice['payment_status']); ?>
                </div>
            </div>
        </div>

        <table class="invoice-items-table">
            <thead>
                <tr>
                    <th class="item-no">#</th>
                    <th>Description</th>
                    <th class="item-price">Price</th>
                    <th class="item-qty">Qty</th>
                    <th class="item-amount">Amount</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td class="item-no">01</td>
                    <td>
                        <?php echo $invoice['invoice_type'] === 'Service'
                            ? 'Repair Service Billing'
                            : 'Auto Parts Purchase'; ?>
                    </td>
                    <td class="item-price">₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                    <td class="item-qty">1</td>
                    <td class="item-amount">₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="invoice-bottom-grid">
            <div class="payment-note-box">
                <div class="payment-note-label">Payment Note</div>
                <div class="payment-note-heading">Shop Payment Policy</div>
                <p class="payment-note-text">
                    For parts orders, payment and claiming are processed at the shop after order approval and pickup confirmation.
                    For repair services, this invoice reflects the recorded billing and payment status in DASPMS.
                </p>
            </div>

            <table class="invoice-total-table">
                <tr>
                    <td class="label-cell">Sub Total</td>
                    <td class="amount-cell">₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>

                <tr>
                    <td class="label-cell">Amount Paid</td>
                    <td class="amount-cell">₱<?php echo number_format($invoice['amount_paid'], 2); ?></td>
                </tr>

                <tr>
                    <td class="label-cell">Balance Due</td>
                    <td class="amount-cell">₱<?php echo number_format($invoice['balance_due'], 2); ?></td>
                </tr>

                <tr class="grand-total-row">
                    <td class="label-cell">TOTAL</td>
                    <td class="amount-cell">₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
            </table>
        </div>

        <div class="payment-section">
            <div class="payment-title">Payment History</div>

            <?php if (count($payments) > 0): ?>
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['reference_number'] ?: 'N/A'); ?></td>
                                <td class="text-end">₱<?php echo number_format($payment['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-payment-note">No payments recorded yet.</p>
            <?php endif; ?>
        </div>

        <div class="invoice-footer-grid">
            <div class="terms-box">
                <h6>Terms & Conditions</h6>
                <p>
                    This digital invoice is generated by DASPMS and may be used as the shop's
                    digital reference for the handwritten official receipt.
                </p>
            </div>

            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-name">Authorized Staff</div>
                <div class="signature-role">Prepared / Verified By</div>
            </div>
        </div>

        <div class="invoice-contact-strip">
            <div class="contact-item">
                <span class="contact-icon">
                    <i class="bi bi-telephone-fill"></i>
                </span>
                <span>
                    <?php echo htmlspecialchars($invoice['contact_number'] ?? 'N/A'); ?><br>
                    Customer Contact
                </span>
            </div>

            <div class="contact-item">
                <span class="contact-icon">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <span>
                    Vila Rosario<br>
                    La Union
                </span>
            </div>

            <div class="contact-item">
                <span class="contact-icon">
                    <i class="bi bi-receipt"></i>
                </span>
                <span>
                    <?php echo htmlspecialchars($invoice['invoice_number']); ?><br>
                    Invoice Reference
                </span>
            </div>
        </div>

    </div>
</div>

<!-- PDF SCRIPT -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
function downloadInvoicePDF() {
    const element = document.getElementById('invoiceArea');

    const opt = {
        margin: 10,
        filename: <?php echo json_encode($invoice['invoice_number'] . '.pdf'); ?>,
        image: {
            type: 'jpeg',
            quality: 1
        },
        html2canvas: {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff'
        },
        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'portrait'
        }
    };

    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>